<?php

namespace Luxplus\BladeLinter;

final class BuildFrame
{
    /** @var list<Region> */
    public array $regions = [];

    public Region $currentRegion;

    /** @var list<int> */
    public array $boundaryOffsets = [];

    public function __construct(
        public readonly string $type,
        public readonly string $tagName,
        public readonly int $openStartLine,
        public readonly int $openEndLine,
        public readonly int $scopeId = 0,
        public readonly ?string $directive = null,
        public readonly int $openStartOffset = -1,
        public readonly int $openEndOffset = -1,
    ) {
        $this->currentRegion = new Region($openEndLine);
    }

    public function closeRegion(int $closeBoundaryLine): void
    {
        $this->currentRegion->closeBoundaryLine = $closeBoundaryLine;
        $this->regions[] = $this->currentRegion;
    }

    public function startRegion(int $openBoundaryLine): void
    {
        $this->currentRegion = new Region($openBoundaryLine);
    }

    /**
     * @return list<LintItem>
     */
    public function allChildren(): array
    {
        $children = [];

        foreach ($this->regions as $region) {
            foreach ($region->children as $child) {
                $children[] = $child;
            }
        }

        foreach ($this->currentRegion->children as $child) {
            $children[] = $child;
        }

        return $children;
    }
}
