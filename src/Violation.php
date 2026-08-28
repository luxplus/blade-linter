<?php

namespace Luxplus\BladeLinter;

final class Violation
{
    public function __construct(
        public readonly int $line,
        public readonly string $message,
        public readonly int $gapAfterLine,
        public readonly int $gapBeforeLine,
        public readonly int $requiredBlankLines,
    ) {}
}
