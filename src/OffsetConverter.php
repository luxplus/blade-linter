<?php

namespace Luxplus\BladeLinter;

final class OffsetConverter
{
    /** @var null|list<int> */
    private ?array $map = null;

    public function __construct(string $text)
    {
        if (mb_strlen($text, '8bit') === mb_strlen($text, 'UTF-8')) {
            return;
        }

        $map = [0];
        $byte = 0;

        foreach (mb_str_split($text, 1, 'UTF-8') as $char) {
            $byte += mb_strlen($char, '8bit');
            $map[] = $byte;
        }

        $this->map = $map;
    }

    public function byteOffset(int $charOffset): int
    {
        if ($this->map === null) {
            return $charOffset;
        }

        return $this->map[min(max($charOffset, 0), count($this->map) - 1)] ?? $charOffset;
    }

    public function byteEndOffset(int $inclusiveCharOffset): int
    {
        return $this->byteOffset($inclusiveCharOffset + 1) - 1;
    }
}
