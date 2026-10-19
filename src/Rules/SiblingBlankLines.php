<?php

namespace Luxplus\BladeLinter\Rules;

use Luxplus\BladeLinter\ItemKind;
use Luxplus\BladeLinter\LintItem;
use Luxplus\BladeLinter\Region;
use Luxplus\BladeLinter\Violation;
use Override;

final class SiblingBlankLines implements LintRule
{
    public const array DEFAULT_ELEMENT_DIRECTIVES = ['include', 'includeIf', 'includeWhen', 'includeUnless', 'includeFirst', 'includeIsolated'];

    /**
     * @param list<string> $elementDirectives directives spaced like ordinary elements rather than directives
     */
    public function __construct(
        private readonly array $elementDirectives = self::DEFAULT_ELEMENT_DIRECTIVES,
    ) {}

    #[Override]
    public function check(LintItem $root, array $lines): array
    {
        $violations = [];
        $this->walk($root, $lines, $violations);
        usort($violations, fn(Violation $a, Violation $b): int => $a->line <=> $b->line);

        return $violations;
    }

    /**
     * @param list<string>    $lines
     * @param list<Violation> $violations
     */
    private function walk(LintItem $item, array $lines, array &$violations): void
    {
        foreach ($item->regions as $region) {
            $this->checkRegion($region, $lines, $violations);

            foreach ($region->children as $child) {
                if ($child->kind === ItemKind::Scope && !$child->opaque) {
                    $this->walk($child, $lines, $violations);
                }
            }
        }
    }

    /**
     * @param list<string>    $lines
     * @param list<Violation> $violations
     */
    private function checkRegion(Region $region, array $lines, array &$violations): void
    {
        $previousEnd = $region->openBoundaryLine;
        $previousMultiline = null;
        $previousDirective = null;
        $previousComment = false;

        $entries = $region->children;
        $entries[] = null;

        foreach ($entries as $entry) {
            $currentStart = $entry->startLine ?? $region->closeBoundaryLine;
            $currentEnd = $entry->endLine ?? $region->closeBoundaryLine;
            $currentMultiline = $entry?->isMultiline();
            $currentDirective = in_array($entry?->directive, $this->elementDirectives, true) ? null : $entry?->directive;
            $currentComment = $entry !== null && $entry->kind === ItemKind::Comment;

            if ($currentStart <= $previousEnd) {
                $previousEnd = max($previousEnd, $currentEnd);
                $previousMultiline = ($previousMultiline ?? false) || ($currentMultiline ?? false);
                $previousComment = $previousComment || $currentComment;

                continue;
            }

            $blanks = 0;

            for ($line = $previousEnd + 1; $line < $currentStart; $line++) {
                if ($line >= 1 && $line <= count($lines) && mb_trim($lines[$line - 1] ?? '') === '') {
                    $blanks++;
                }
            }

            $gapIsClean = $blanks === $currentStart - $previousEnd - 1;

            if (!$previousComment && !$currentComment && $gapIsClean) {
                $required = $this->requiredBlankLines($previousMultiline, $previousDirective, $currentMultiline, $currentDirective);

                if ($blanks !== $required) {
                    $violations[] = new Violation(
                        $blanks < $required ? $currentStart : $previousEnd + 1,
                        $this->message($previousMultiline, $previousDirective, $currentMultiline, $currentDirective, $blanks),
                        $previousEnd,
                        $currentStart,
                        $required,
                    );
                }
            }

            $previousEnd = $currentEnd;
            $previousMultiline = $currentMultiline;
            $previousDirective = $currentDirective;
            $previousComment = $currentComment;
        }
    }

    /**
     * A null multiline flag marks the scope edge. Multi-line siblings always get a blank
     * line; otherwise directives do, except between single-line directives of the same name.
     */
    private function requiredBlankLines(?bool $previousMultiline, ?string $previousDirective, ?bool $currentMultiline, ?string $currentDirective): int
    {
        if ($previousMultiline === null || $currentMultiline === null) {
            return 0;
        }

        if ($previousMultiline || $currentMultiline) {
            return 1;
        }

        if ($previousDirective === null && $currentDirective === null) {
            return 0;
        }

        return $previousDirective === $currentDirective ? 0 : 1;
    }

    private function message(?bool $previousMultiline, ?string $previousDirective, ?bool $currentMultiline, ?string $currentDirective, int $blanks): string
    {
        if ($previousMultiline === null || $currentMultiline === null) {
            return 'Unexpected blank line at block edge';
        }

        if ($previousMultiline || $currentMultiline) {
            return $blanks === 0
                ? 'Missing blank line between siblings (one spans multiple lines)'
                : 'Expected exactly one blank line between siblings';
        }

        if ($previousDirective !== null && $previousDirective === $currentDirective) {
            return "Unexpected blank line inside a group of `@{$previousDirective}` directives";
        }

        if ($previousDirective === null && $currentDirective === null) {
            return 'Unexpected blank line between single-line siblings';
        }

        $subject = $previousDirective !== null && $currentDirective !== null
            ? "between directives `@{$previousDirective}` and `@{$currentDirective}`"
            : 'around directive `@' . ($previousDirective ?? $currentDirective) . '`';

        return $blanks === 0 ? "Missing blank line {$subject}" : "Expected exactly one blank line {$subject}";
    }
}
