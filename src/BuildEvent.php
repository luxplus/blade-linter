<?php

namespace Luxplus\BladeLinter;

final class BuildEvent
{
    public function __construct(
        public readonly int $offset,
        public readonly int $sequence,
        public readonly BuildEventType $type,
        public readonly ?LintItem $leaf = null,
        public readonly int $scopeId = 0,
        public readonly string $tagName = '',
        public readonly int $startLine = 0,
        public readonly int $endLine = 0,
    ) {}
}
