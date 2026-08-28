<?php

namespace Luxplus\BladeLinter;

use Stillat\BladeParser\Document\Document;
use Stillat\BladeParser\Nodes\AbstractNode;
use Stillat\BladeParser\Nodes\BaseNode;
use Stillat\BladeParser\Nodes\CommentNode;
use Stillat\BladeParser\Nodes\Components\ComponentNode;
use Stillat\BladeParser\Nodes\DirectiveNode;
use Stillat\BladeParser\Nodes\EchoNode;
use Stillat\BladeParser\Nodes\LiteralNode;
use Stillat\BladeParser\Nodes\PhpBlockNode;
use Stillat\BladeParser\Nodes\PhpTagNode;
use Stillat\BladeParser\Nodes\Position;
use Stillat\BladeParser\Nodes\VerbatimNode;

final class TreeBuilder
{
    private const array VOID_TAGS = [
        'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
        'link', 'meta', 'param', 'source', 'track', 'wbr',
    ];

    private const array OPAQUE_TAGS = ['script', 'style', 'pre', 'textarea', 'svg'];

    private const array BRANCH_DIRECTIVES = ['empty'];

    private const array OPAQUE_DIRECTIVES = ['switch'];

    private int $sequence = 0;

    private int $nextScopeId = 1;

    /** @var list<BuildEvent> */
    private array $events = [];

    /** @var list<array{start: int, end: int}> */
    private array $opaqueRanges = [];

    /** @var list<array{start: int, end: int}> */
    private array $tagRanges = [];

    private OffsetConverter $offsets;

    /**
     * @param list<string> $lines
     */
    public function build(Document $document, string $template, array $lines): LintItem
    {
        $this->sequence = 0;
        $this->nextScopeId = 1;
        $this->events = [];
        $this->opaqueRanges = [];
        $this->offsets = new OffsetConverter($template);

        $tags = (new HtmlTagScanner())->scan($document, $template, $this->offsets);
        $this->tagRanges = array_map(
            fn(ScannedTag $tag): array => ['start' => $tag->startOffset, 'end' => $tag->endOffset],
            $tags,
        );

        $this->collectOpaqueRanges($tags);
        $this->collectTagEvents($tags);
        $this->collectNodeEvents($document);

        usort($this->events, fn(BuildEvent $a, BuildEvent $b): int => [$a->offset, $a->sequence] <=> [$b->offset, $b->sequence]);

        $root = $this->assemble(count($lines));
        $this->attachTextRuns($root, $lines);

        return $root;
    }

    private function position(BaseNode $node): Position
    {
        if ($node->position === null) {
            throw new BladeLintException('node without position information');
        }

        return $node->position;
    }

    private function insideOpaqueRange(int $offset): bool
    {
        foreach ($this->opaqueRanges as $range) {
            if ($offset >= $range['start'] && $offset <= $range['end']) {
                return true;
            }
        }

        return false;
    }

    private function insideTagRange(int $offset): bool
    {
        $low = 0;
        $high = count($this->tagRanges) - 1;

        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);
            $range = $this->tagRanges[$mid] ?? null;

            if ($range === null) {
                return false;
            }

            if ($offset < $range['start']) {
                $high = $mid - 1;
            } elseif ($offset > $range['end']) {
                $low = $mid + 1;
            } else {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<ScannedTag> $tags
     */
    private function collectOpaqueRanges(array $tags): void
    {
        $count = count($tags);

        for ($i = 0; $i < $count; $i++) {
            $open = $tags[$i] ?? null;

            if ($open === null || $open->isComment || $open->isClosingTag || $open->isSelfClosing || !in_array($open->tagName, self::OPAQUE_TAGS, true)) {
                continue;
            }

            if ($this->insideOpaqueRange($open->startOffset)) {
                continue;
            }

            $depth = 0;

            for ($j = $i + 1; $j < $count; $j++) {
                $candidate = $tags[$j] ?? null;

                if ($candidate === null) {
                    break;
                }

                if ($candidate->tagName !== $open->tagName) {
                    continue;
                }

                if (!$candidate->isClosingTag) {
                    if (!$candidate->isSelfClosing) {
                        $depth++;
                    }

                    continue;
                }

                if ($depth > 0) {
                    $depth--;

                    continue;
                }

                $this->opaqueRanges[] = ['start' => $open->startOffset, 'end' => $candidate->endOffset];
                $this->pushLeafEvent($open->startOffset, new LintItem(
                    ItemKind::Leaf,
                    $open->startLine,
                    $candidate->endLine,
                    $open->endLine,
                    opaque: true,
                ));

                break;
            }
        }
    }

    /**
     * @param list<ScannedTag> $tags
     */
    private function collectTagEvents(array $tags): void
    {
        foreach ($tags as $tag) {
            if ($this->insideOpaqueRange($tag->startOffset)) {
                continue;
            }

            if ($tag->isComment) {
                $this->pushLeafEvent($tag->startOffset, new LintItem(ItemKind::Comment, $tag->startLine, $tag->endLine, $tag->endLine));

                continue;
            }

            if ($tag->isClosingTag) {
                $this->events[] = new BuildEvent(
                    $tag->startOffset,
                    $this->sequence++,
                    BuildEventType::TagClose,
                    tagName: $tag->tagName,
                    startLine: $tag->startLine,
                    endLine: $tag->endLine,
                );

                continue;
            }

            if ($tag->isSelfClosing || in_array($tag->tagName, self::VOID_TAGS, true)) {
                $this->pushLeafEvent($tag->startOffset, new LintItem(ItemKind::Leaf, $tag->startLine, $tag->endLine, $tag->endLine));

                continue;
            }

            $this->events[] = new BuildEvent(
                $tag->startOffset,
                $this->sequence++,
                BuildEventType::TagOpen,
                tagName: $tag->tagName,
                startLine: $tag->startLine,
                endLine: $tag->endLine,
            );
        }
    }

    private function collectNodeEvents(Document $document): void
    {
        foreach ($document->getNodes() as $node) {
            if (!$node instanceof AbstractNode || $node instanceof LiteralNode) {
                continue;
            }

            $position = $this->position($node);
            $offset = $this->offsets->byteOffset($position->startOffset);

            if ($this->insideOpaqueRange($offset) || $this->insideTagRange($offset)) {
                continue;
            }

            $startLine = $this->line($position->startLine);
            $endLine = $this->line($position->endLine);

            if ($node instanceof CommentNode) {
                $this->pushLeafEvent($offset, new LintItem(ItemKind::Comment, $startLine, $endLine, $startLine));

                continue;
            }

            if ($node instanceof EchoNode) {
                $this->pushLeafEvent($offset, new LintItem(ItemKind::Leaf, $startLine, $endLine, $startLine));

                continue;
            }

            if ($node instanceof PhpBlockNode || $node instanceof VerbatimNode || $node instanceof PhpTagNode) {
                $this->pushLeafEvent($offset, new LintItem(ItemKind::Leaf, $startLine, $endLine, $startLine, opaque: true));

                continue;
            }

            if ($node instanceof ComponentNode) {
                $this->collectComponentEvents($node, $offset, $startLine, $endLine);

                continue;
            }

            if ($node instanceof DirectiveNode) {
                $this->collectDirectiveEvents($node, $offset, $startLine, $endLine);
            }
        }
    }

    private function collectComponentEvents(ComponentNode $node, int $offset, int $startLine, int $endLine): void
    {
        if ($node->isClosingTag && !$node->isSelfClosing) {
            return;
        }

        $close = $node->isClosedBy;

        if ($node->isSelfClosing || $close === null) {
            $this->pushLeafEvent($offset, new LintItem(ItemKind::Leaf, $startLine, $endLine, $endLine));

            return;
        }

        $closePosition = $this->position($close);
        $scopeId = $this->nextScopeId++;

        $this->events[] = new BuildEvent(
            $offset,
            $this->sequence++,
            BuildEventType::ScopeOpen,
            scopeId: $scopeId,
            startLine: $startLine,
            endLine: $endLine,
        );
        $this->events[] = new BuildEvent(
            $this->offsets->byteOffset($closePosition->startOffset),
            $this->sequence++,
            BuildEventType::ScopeClose,
            scopeId: $scopeId,
            startLine: $this->line($closePosition->startLine),
            endLine: $this->line($closePosition->endLine),
        );
    }

    private function collectDirectiveEvents(DirectiveNode $node, int $offset, int $startLine, int $endLine): void
    {
        if ($node->isClosingDirective) {
            return;
        }

        $name = mb_strtolower($node->content);

        if (in_array($name, self::BRANCH_DIRECTIVES, true) && $node->isClosedBy === null) {
            $this->events[] = new BuildEvent(
                $offset,
                $this->sequence++,
                BuildEventType::ScopeBoundary,
                startLine: $startLine,
                endLine: $endLine,
            );

            return;
        }

        if ($node->isClosedBy === null) {
            $this->pushLeafEvent($offset, new LintItem(ItemKind::Leaf, $startLine, $endLine, $endLine));

            return;
        }

        $chain = [$node];
        $current = $node;

        while ($current->isClosedBy !== null && count($chain) < 500) {
            $chain[] = $current->isClosedBy;
            $current = $current->isClosedBy;
        }

        $terminator = end($chain);
        $terminatorPosition = $this->position($terminator);
        $chainInsideOpaque = false;

        foreach ($chain as $member) {
            if ($this->insideOpaqueRange($this->offsets->byteOffset($this->position($member)->startOffset))) {
                $chainInsideOpaque = true;

                break;
            }
        }

        if (in_array($name, self::OPAQUE_DIRECTIVES, true) || $chainInsideOpaque) {
            $this->pushLeafEvent($offset, new LintItem(
                ItemKind::Leaf,
                $startLine,
                $this->line($terminatorPosition->endLine),
                $endLine,
                opaque: true,
            ));

            return;
        }

        $scopeId = $this->nextScopeId++;

        $this->events[] = new BuildEvent(
            $offset,
            $this->sequence++,
            BuildEventType::ScopeOpen,
            scopeId: $scopeId,
            startLine: $startLine,
            endLine: $endLine,
        );

        foreach (array_slice($chain, 1, count($chain) - 2) as $member) {
            $memberPosition = $this->position($member);
            $this->events[] = new BuildEvent(
                $this->offsets->byteOffset($memberPosition->startOffset),
                $this->sequence++,
                BuildEventType::ScopeBoundary,
                scopeId: $scopeId,
                startLine: $this->line($memberPosition->startLine),
                endLine: $this->line($memberPosition->endLine),
            );
        }

        $this->events[] = new BuildEvent(
            $this->offsets->byteOffset($terminatorPosition->startOffset),
            $this->sequence++,
            BuildEventType::ScopeClose,
            scopeId: $scopeId,
            startLine: $this->line($terminatorPosition->startLine),
            endLine: $this->line($terminatorPosition->endLine),
        );
    }

    private function pushLeafEvent(int $offset, LintItem $item): void
    {
        $this->events[] = new BuildEvent($offset, $this->sequence++, BuildEventType::Leaf, leaf: $item);
    }

    private function line(?int $line): int
    {
        if ($line === null) {
            throw new BladeLintException('node without line information');
        }

        return $line;
    }

    private function assemble(int $lineCount): LintItem
    {
        $rootFrame = new BuildFrame('root', '', 0, 0);

        /** @var list<BuildFrame> $stack */
        $stack = [$rootFrame];

        foreach ($this->events as $event) {
            switch ($event->type) {
                case BuildEventType::Leaf:
                    if ($event->leaf !== null) {
                        $this->topFrame($stack)->currentRegion->children[] = $event->leaf;
                    }

                    break;
                case BuildEventType::TagOpen:
                    $stack[] = new BuildFrame('html', $event->tagName, $event->startLine, $event->endLine);

                    break;
                case BuildEventType::TagClose:
                    $this->closeTag($stack, $event);

                    break;
                case BuildEventType::ScopeOpen:
                    $stack[] = new BuildFrame('scope', '', $event->startLine, $event->endLine, $event->scopeId);

                    break;
                case BuildEventType::ScopeBoundary:
                    $this->applyBoundary($stack, $event);

                    break;
                case BuildEventType::ScopeClose:
                    $this->closeScope($stack, $event);

                    break;
            }
        }

        while (count($stack) > 1) {
            $this->forceCloseTop($stack);
        }

        $rootFrame->closeRegion($lineCount + 1);

        return new LintItem(ItemKind::Scope, 0, $lineCount + 1, 0, $rootFrame->regions);
    }

    /**
     * @param list<BuildFrame> $stack
     */
    private function topFrame(array $stack): BuildFrame
    {
        $top = end($stack);

        if ($top === false) {
            throw new BladeLintException('unbalanced build stack');
        }

        return $top;
    }

    /**
     * @param list<BuildFrame> $stack
     */
    private function forceCloseTop(array &$stack): void
    {
        $frame = array_pop($stack);

        if ($frame === null || $stack === []) {
            return;
        }

        $parent = $this->topFrame($stack);
        $parent->currentRegion->children[] = new LintItem(ItemKind::Leaf, $frame->openStartLine, $frame->openEndLine, $frame->openEndLine);

        foreach ($frame->allChildren() as $child) {
            $parent->currentRegion->children[] = $child;
        }
    }

    /**
     * @param list<BuildFrame> $stack
     */
    private function closeTag(array &$stack, BuildEvent $event): void
    {
        $matchIndex = null;

        for ($i = count($stack) - 1; $i >= 0; $i--) {
            $candidate = $stack[$i] ?? null;

            if ($candidate === null || $candidate->type !== 'html') {
                break;
            }

            if ($candidate->tagName === $event->tagName) {
                $matchIndex = $i;

                break;
            }
        }

        if ($matchIndex === null) {
            $this->topFrame($stack)->currentRegion->children[] = new LintItem(ItemKind::Leaf, $event->startLine, $event->endLine, $event->endLine);

            return;
        }

        while (count($stack) - 1 > $matchIndex) {
            $this->forceCloseTop($stack);
        }

        $frame = array_pop($stack);

        if ($frame === null || $stack === []) {
            return;
        }

        $frame->closeRegion($event->startLine);
        $this->topFrame($stack)->currentRegion->children[] = new LintItem(
            ItemKind::Scope,
            $frame->openStartLine,
            $event->endLine,
            $frame->openEndLine,
            $frame->regions,
        );
    }

    /**
     * @param list<BuildFrame> $stack
     */
    private function applyBoundary(array &$stack, BuildEvent $event): void
    {
        $targetIndex = null;

        for ($i = count($stack) - 1; $i >= 0; $i--) {
            $candidate = $stack[$i] ?? null;

            if ($candidate === null) {
                break;
            }

            if ($candidate->type === 'scope' && ($event->scopeId === 0 || $candidate->scopeId === $event->scopeId)) {
                $targetIndex = $i;

                break;
            }
        }

        if ($targetIndex === null) {
            $this->topFrame($stack)->currentRegion->children[] = new LintItem(ItemKind::Leaf, $event->startLine, $event->endLine, $event->endLine);

            return;
        }

        while (count($stack) - 1 > $targetIndex) {
            $this->forceCloseTop($stack);
        }

        $frame = $stack[$targetIndex] ?? null;

        if ($frame === null) {
            return;
        }

        $frame->closeRegion($event->startLine);
        $frame->startRegion($event->endLine);
    }

    /**
     * @param list<BuildFrame> $stack
     */
    private function closeScope(array &$stack, BuildEvent $event): void
    {
        $targetIndex = null;

        for ($i = count($stack) - 1; $i >= 0; $i--) {
            $candidate = $stack[$i] ?? null;

            if ($candidate === null) {
                break;
            }

            if ($candidate->type === 'scope' && $candidate->scopeId === $event->scopeId) {
                $targetIndex = $i;

                break;
            }
        }

        if ($targetIndex === null) {
            return;
        }

        while (count($stack) - 1 > $targetIndex) {
            $this->forceCloseTop($stack);
        }

        $frame = array_pop($stack);

        if ($frame === null || $stack === []) {
            return;
        }

        $frame->closeRegion($event->startLine);
        $this->topFrame($stack)->currentRegion->children[] = new LintItem(
            ItemKind::Scope,
            $frame->openStartLine,
            $event->endLine,
            $frame->openEndLine,
            $frame->regions,
        );
    }

    /**
     * @param list<string> $lines
     */
    private function attachTextRuns(LintItem $item, array $lines): void
    {
        foreach ($item->regions as $region) {
            $from = $region->openBoundaryLine + 1;
            $to = min($region->closeBoundaryLine - 1, count($lines));

            if ($to >= $from) {
                $covered = [];

                foreach ($region->children as $child) {
                    for ($line = $child->startLine; $line <= $child->endLine; $line++) {
                        $covered[$line] = true;
                    }
                }

                $runStart = null;

                for ($line = max($from, 1); $line <= $to + 1; $line++) {
                    $isText = $line <= $to && !isset($covered[$line]) && mb_trim($lines[$line - 1] ?? '') !== '';

                    if ($isText && $runStart === null) {
                        $runStart = $line;
                    }

                    if (!$isText && $runStart !== null) {
                        $region->children[] = new LintItem(ItemKind::TextRun, $runStart, $line - 1, $runStart);
                        $runStart = null;
                    }
                }

                usort($region->children, fn(LintItem $a, LintItem $b): int => $a->startLine <=> $b->startLine);
            }

            foreach ($region->children as $child) {
                if ($child->kind === ItemKind::Scope && !$child->opaque) {
                    $this->attachTextRuns($child, $lines);
                }
            }
        }
    }
}
