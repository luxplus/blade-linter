<?php

namespace Luxplus\BladeLinter;

final class LintItem
{
    /**
     * @param list<Region> $regions
     */
    public function __construct(
        public readonly ItemKind $kind,
        public readonly int $startLine,
        public readonly int $endLine,
        public readonly int $openEndLine,
        public array $regions = [],
        public readonly bool $opaque = false,
    ) {}

    public function isMultiline(): bool
    {
        return $this->endLine > $this->openEndLine;
    }
}
