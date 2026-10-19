<?php

namespace Luxplus\BladeLinter;

final class Violation
{
    /**
     * The fix replaces the lines strictly between `gapAfterLine` and `gapBeforeLine` with
     * `replacement`, or with `requiredBlankLines` blank lines when no replacement is given.
     *
     * @param null|list<string> $replacement
     */
    public function __construct(
        public readonly int $line,
        public readonly string $message,
        public readonly int $gapAfterLine,
        public readonly int $gapBeforeLine,
        public readonly int $requiredBlankLines,
        public readonly ?array $replacement = null,
    ) {}
}
