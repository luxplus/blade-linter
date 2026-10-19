<?php

namespace Luxplus\BladeLinter;

final class LintItem
{
    /**
     * Byte offsets are only set on closed scopes (HTML elements, directive blocks and
     * components); everything else is described by lines alone.
     *
     * @param list<Region> $regions
     * @param list<int>    $boundaryOffsets start offsets of `@else`-style branch boundaries
     */
    public function __construct(
        public readonly ItemKind $kind,
        public readonly int $startLine,
        public readonly int $endLine,
        public readonly int $openEndLine,
        public array $regions = [],
        public readonly bool $opaque = false,
        public readonly ?string $directive = null,
        public readonly ?string $tagName = null,
        public readonly int $startOffset = -1,
        public readonly int $openEndOffset = -1,
        public readonly int $closeStartOffset = -1,
        public readonly int $endOffset = -1,
        public readonly array $boundaryOffsets = [],
    ) {}

    public function isMultiline(): bool
    {
        return $this->endLine > $this->startLine;
    }

    public function hasOffsets(): bool
    {
        return $this->startOffset >= 0;
    }

    public function isDirective(): bool
    {
        return $this->directive !== null;
    }
}
