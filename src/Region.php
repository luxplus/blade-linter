<?php

namespace Luxplus\BladeLinter;

final class Region
{
    /**
     * @param list<LintItem> $children
     */
    public function __construct(
        public readonly int $openBoundaryLine,
        public int $closeBoundaryLine = 0,
        public array $children = [],
    ) {}
}
