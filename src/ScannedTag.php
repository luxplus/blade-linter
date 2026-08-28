<?php

namespace Luxplus\BladeLinter;

final class ScannedTag
{
    public function __construct(
        public readonly string $tagName,
        public readonly bool $isClosingTag,
        public readonly bool $isSelfClosing,
        public readonly bool $isComment,
        public readonly int $startOffset,
        public readonly int $endOffset,
        public readonly int $startLine,
        public readonly int $endLine,
    ) {}
}
