<?php

namespace Luxplus\BladeLinter\Rules;

use Luxplus\BladeLinter\ItemKind;
use Luxplus\BladeLinter\LintItem;
use Luxplus\BladeLinter\Region;
use Luxplus\BladeLinter\Violation;
use Override;

final class SiblingBlankLines implements LintRule
{
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
        $previousComment = false;

        $entries = $region->children;
        $entries[] = null;

        foreach ($entries as $entry) {
            $currentStart = $entry->startLine ?? $region->closeBoundaryLine;
            $currentEnd = $entry->endLine ?? $region->closeBoundaryLine;
            $currentMultiline = $entry?->isMultiline();
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
                $required = $previousMultiline === null || $currentMultiline === null
                    ? 0
                    : (($previousMultiline || $currentMultiline) ? 1 : 0);

                if ($blanks !== $required) {
                    $violations[] = new Violation(
                        $blanks < $required ? $currentStart : $previousEnd + 1,
                        $this->message($previousMultiline, $currentMultiline, $blanks, $required),
                        $previousEnd,
                        $currentStart,
                        $required,
                    );
                }
            }

            $previousEnd = $currentEnd;
            $previousMultiline = $currentMultiline;
            $previousComment = $currentComment;
        }
    }

    private function message(?bool $previousMultiline, ?bool $currentMultiline, int $blanks, int $required): string
    {
        if ($previousMultiline === null || $currentMultiline === null) {
            return 'Unexpected blank line at block edge';
        }

        if ($required === 1 && $blanks === 0) {
            return 'Missing blank line between siblings (one spans multiple lines)';
        }

        if ($required === 1) {
            return 'Expected exactly one blank line between siblings';
        }

        return 'Unexpected blank line between single-line siblings';
    }
}
