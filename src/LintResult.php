<?php

namespace Luxplus\BladeLinter;

final class LintResult
{
    /**
     * @param list<Violation> $violations
     */
    public function __construct(
        public readonly array $violations = [],
        public readonly ?string $skippedReason = null,
    ) {}
}
