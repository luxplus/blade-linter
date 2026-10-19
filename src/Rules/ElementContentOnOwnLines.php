<?php

namespace Luxplus\BladeLinter\Rules;

use Luxplus\BladeLinter\ItemKind;
use Luxplus\BladeLinter\LineIndex;
use Luxplus\BladeLinter\LintItem;
use Luxplus\BladeLinter\Violation;
use Override;

final class ElementContentOnOwnLines implements LintRule
{
    public const array DEFAULT_BLOCK_ELEMENTS = [
        'address', 'article', 'aside', 'blockquote', 'body', 'caption', 'dd', 'details', 'dialog', 'div',
        'dl', 'dt', 'fieldset', 'figcaption', 'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'head', 'header', 'html', 'li', 'main', 'nav', 'ol', 'p', 'section', 'summary', 'table', 'tbody',
        'td', 'tfoot', 'th', 'thead', 'tr', 'ul',
    ];

    public const array DEFAULT_INLINE_ELEMENTS = ['a', 'b', 'button', 'code', 'em', 'i', 'label', 'small', 'span', 'strong'];

    public const array DEFAULT_PREFORMATTED_CLASSES = ['whitespace-pre', 'whitespace-pre-line', 'whitespace-pre-wrap', 'whitespace-break-spaces'];

    public const int DEFAULT_MAX_LINE_LENGTH = 120;

    private const string INDENT = '    ';

    private const string TRAILING_COMMENT_PATTERN = '/^(?:\{\{--.*--\}\}|<!--.*-->)$/s';

    private const string PREFORMATTED_STYLE_PATTERN = '/white-space\s*:\s*(?:pre|break-spaces)/i';

    private const string CLASS_ATTRIBUTE_PATTERN = '/\sclass\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i';

    private string $template = '';

    private LineIndex $lineIndex;

    /** @var list<LintItem> */
    private array $scopes = [];

    /** @var array<int, string> break offset => message */
    private array $breaks = [];

    /** @var array<int, string> */
    private array $indents = [];

    /**
     * An element's content moves onto lines of its own when it holds structure (a block
     * element, a block directive or component, or a single wrapped element), or when the
     * line holding it would be longer than `$maxLineLength`.
     *
     * @param list<string> $blockElements       always on their own lines; their content moves when it holds structure or is too long
     * @param list<string> $inlineElements      content moves the same way, but only when the element is the sole content
     *                                          of an expanded parent, where the added whitespace is invisible
     * @param list<string> $preformattedClasses classes whose CSS preserves whitespace; such elements keep their content
     */
    public function __construct(
        private readonly array $blockElements = self::DEFAULT_BLOCK_ELEMENTS,
        private readonly array $inlineElements = self::DEFAULT_INLINE_ELEMENTS,
        private readonly array $preformattedClasses = self::DEFAULT_PREFORMATTED_CLASSES,
        private readonly int $maxLineLength = self::DEFAULT_MAX_LINE_LENGTH,
    ) {}

    #[Override]
    public function check(LintItem $root, array $lines): array
    {
        $this->template = implode("\n", $lines);
        $this->lineIndex = new LineIndex($this->template);
        $this->scopes = [];
        $this->breaks = [];
        $this->indents = [];

        $this->walk($root, false);
        ksort($this->breaks);
        $this->indents = [];

        $breaksByLine = [];

        foreach (array_keys($this->breaks) as $offset) {
            $breaksByLine[$this->lineIndex->lineAt($offset)][] = $offset;
        }

        $violations = [];

        foreach ($breaksByLine as $line => $offsets) {
            $violations[] = new Violation(
                $line,
                $this->breaks[$offsets[0]] ?? '',
                $line - 1,
                $line + 1,
                0,
                $this->splitLine($line, $offsets),
            );
        }

        return $violations;
    }

    private function walk(LintItem $item, bool $itemExpands): void
    {
        foreach ($item->regions as $region) {
            foreach ($region->children as $child) {
                if ($child->kind !== ItemKind::Scope || !$child->hasOffsets()) {
                    continue;
                }

                $this->scopes[] = $child;
                $childExpands = false;

                if (!$this->hasBalancedOpeningTag($child)) {
                    $this->walk($child, false);

                    continue;
                }

                if (in_array($child->tagName, $this->blockElements, true)) {
                    $this->requireOwnLine($child);

                    if ($this->preservesWhitespace($child)) {
                        continue;
                    }

                    $childExpands = $this->expandContent($child);
                } elseif ($this->preservesWhitespace($child)) {
                    continue;
                } elseif (in_array($child->tagName, $this->inlineElements, true) && $itemExpands && $this->isSoleContent($item, $child)) {
                    $childExpands = $this->expandContent($child);
                }

                $this->walk($child, $childExpands);
            }
        }
    }

    private function requireOwnLine(LintItem $element): void
    {
        $message = "Element `<{$element->tagName}>` must be on its own line";
        $lineStart = $this->lineIndex->lineStartOffset($this->lineIndex->lineAt($element->startOffset));

        if (mb_trim($this->slice($lineStart, $element->startOffset)) !== '') {
            $this->addBreak($element->startOffset, $message);
        }

        $next = $this->firstNonWhitespace($element->endOffset + 1, $this->lineEnd($element->endOffset));

        if ($next !== null && preg_match(self::TRAILING_COMMENT_PATTERN, mb_trim($this->slice($next, $this->lineEnd($next)))) !== 1) {
            $this->addBreak($next, $message);
        }
    }

    /**
     * @return bool whether the content goes on lines of its own, so its children may follow
     */
    private function expandContent(LintItem $element): bool
    {
        $first = $this->firstNonWhitespace($element->openEndOffset + 1, $element->closeStartOffset);
        $last = $this->lastNonWhitespace($element->openEndOffset + 1, $element->closeStartOffset);

        if ($first === null || $last === null) {
            return false;
        }

        $sharesOpeningLine = $this->lineIndex->lineAt($first) === $this->lineIndex->lineAt($element->openEndOffset);
        $sharesClosingLine = $this->lineIndex->lineAt($last) === $this->lineIndex->lineAt($element->closeStartOffset);
        $onOneLine = $this->lineIndex->lineAt($element->openEndOffset) === $this->lineIndex->lineAt($element->closeStartOffset);

        if ($this->holdsStructure($element, $first, $last)) {
            $message = "Content of `<{$element->tagName}>` must be on its own lines";
        } elseif (!$onOneLine) {
            $message = "Content of `<{$element->tagName}>` must not share a line with its tags";
        } elseif ($this->pieceLength($element->openEndOffset) > $this->maxLineLength) {
            $message = "Content of `<{$element->tagName}>` must be on its own lines (line exceeds {$this->maxLineLength} characters)";
        } else {
            return false;
        }

        if ($sharesOpeningLine) {
            $this->addBreak($first, $message);
        }

        if ($sharesClosingLine) {
            $this->addBreak($element->closeStartOffset, $message);
        }

        return true;
    }

    /**
     * Structure is a block element, a block directive or component, or a single element
     * wrapping all of the content. Text, echoes, void tags and inline elements next to
     * text are not.
     */
    private function holdsStructure(LintItem $element, int $first, int $last): bool
    {
        foreach ($element->regions as $region) {
            foreach ($region->children as $child) {
                if ($child->kind !== ItemKind::Scope || !$child->hasOffsets()) {
                    continue;
                }

                if ($child->tagName === null || in_array($child->tagName, $this->blockElements, true)) {
                    return true;
                }

                if ($child->startOffset === $first && $child->endOffset === $last) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The length the line holding `$offset` will have once the breaks found so far apply.
     */
    private function pieceLength(int $offset): int
    {
        $lineStart = $this->lineIndex->lineStartOffset($this->lineIndex->lineAt($offset));
        $start = $lineStart;
        $end = $this->lineEnd($lineStart);

        foreach (array_keys($this->breaks) as $break) {
            if ($break > $start && $break <= $offset) {
                $start = $break;
            } elseif ($break > $offset && $break < $end) {
                $end = $break;
            }
        }

        $piece = $start === $lineStart
            ? mb_rtrim($this->slice($start, $end))
            : $this->indentAt($start) . mb_trim($this->slice($start, $end));

        return mb_strlen($piece, 'UTF-8');
    }

    private function isSoleContent(LintItem $parent, LintItem $child): bool
    {
        return $parent->tagName !== null
            && $this->firstNonWhitespace($parent->openEndOffset + 1, $parent->closeStartOffset) === $child->startOffset
            && $this->lastNonWhitespace($parent->openEndOffset + 1, $parent->closeStartOffset) === $child->endOffset;
    }

    /**
     * Guards against an opening tag cut short by a `>` inside something the parser doesn't
     * know, such as `=>` in the arguments of an unrecognised directive.
     */
    private function hasBalancedOpeningTag(LintItem $element): bool
    {
        if ($element->tagName === null) {
            return true;
        }

        $tag = preg_replace('/"[^"]*"|\'[^\']*\'/', '', $this->slice($element->startOffset, $element->openEndOffset + 1)) ?? '';

        return mb_substr_count($tag, '(') === mb_substr_count($tag, ')')
            && mb_substr_count($tag, '[') === mb_substr_count($tag, ']')
            && mb_substr_count($tag, '{') === mb_substr_count($tag, '}');
    }

    /**
     * `white-space` is inherited, so nothing inside such an element is touched either.
     */
    private function preservesWhitespace(LintItem $element): bool
    {
        $tag = $this->slice($element->startOffset, $element->openEndOffset + 1);

        if (preg_match(self::PREFORMATTED_STYLE_PATTERN, $tag) === 1) {
            return true;
        }

        if (preg_match(self::CLASS_ATTRIBUTE_PATTERN, $tag, $match) !== 1) {
            return false;
        }

        $classes = preg_split('/\s+/', $match[1] . ' ' . ($match[2] ?? '')) ?: [];

        return array_intersect($classes, $this->preformattedClasses) !== [];
    }

    private function addBreak(int $offset, string $message): void
    {
        $this->breaks[$offset] ??= $message;
    }

    /**
     * @param non-empty-list<int> $offsets
     *
     * @return list<string>
     */
    private function splitLine(int $line, array $offsets): array
    {
        $lineStart = $this->lineIndex->lineStartOffset($line);
        $lineEnd = $this->lineEnd($lineStart);
        $pieces = [mb_rtrim($this->slice($lineStart, $offsets[0]))];

        foreach ($offsets as $index => $offset) {
            $piece = mb_trim($this->slice($offset, $offsets[$index + 1] ?? $lineEnd));

            if ($piece !== '') {
                $pieces[] = $this->indentAt($offset) . $piece;
            }
        }

        return array_values(array_filter($pieces, fn(string $piece): bool => mb_trim($piece) !== ''));
    }

    /**
     * The indentation of a line starting at `$offset` once this pass's breaks are applied:
     * one level deeper than the innermost enclosing scope, or level with it for that
     * scope's own closing tag and `@else`-style boundaries.
     */
    private function indentAt(int $offset): string
    {
        if (isset($this->indents[$offset])) {
            return $this->indents[$offset];
        }

        $container = null;

        foreach ($this->scopes as $scope) {
            if ($scope->startOffset < $offset && $offset <= $scope->endOffset && ($container === null || $scope->startOffset > $container->startOffset)) {
                $container = $scope;
            }
        }

        if ($container === null) {
            $indent = '';
        } elseif ($offset === $container->closeStartOffset || in_array($offset, $container->boundaryOffsets, true)) {
            $indent = $this->indentOf($container);
        } else {
            $indent = $this->indentOf($container) . self::INDENT;
        }

        return $this->indents[$offset] = $indent;
    }

    private function indentOf(LintItem $scope): string
    {
        $lineStart = $this->lineIndex->lineStartOffset($this->lineIndex->lineAt($scope->startOffset));
        $prefix = $this->slice($lineStart, $scope->startOffset);

        if (mb_trim($prefix) === '') {
            return $prefix;
        }

        $pieceStart = null;

        foreach (array_keys($this->breaks) as $offset) {
            if ($offset > $lineStart && $offset <= $scope->startOffset) {
                $pieceStart = $offset;
            }
        }

        if ($pieceStart !== null) {
            return $this->indentAt($pieceStart);
        }

        return mb_substr($prefix, 0, mb_strlen($prefix, '8bit') - mb_strlen(mb_ltrim($prefix), '8bit'), '8bit');
    }

    private function firstNonWhitespace(int $from, int $to): ?int
    {
        for ($offset = $from; $offset < $to; $offset++) {
            if (!ctype_space($this->template[$offset] ?? ' ')) {
                return $offset;
            }
        }

        return null;
    }

    private function lastNonWhitespace(int $from, int $to): ?int
    {
        for ($offset = $to - 1; $offset >= $from; $offset--) {
            if (!ctype_space($this->template[$offset] ?? ' ')) {
                return $offset;
            }
        }

        return null;
    }

    /**
     * The offset just past the last character on the line containing `$offset`.
     */
    private function lineEnd(int $offset): int
    {
        $newline = mb_strpos($this->template, "\n", $offset, '8bit');

        return $newline === false ? mb_strlen($this->template, '8bit') : $newline;
    }

    private function slice(int $from, int $to): string
    {
        return mb_substr($this->template, $from, max($to - $from, 0), '8bit');
    }
}
