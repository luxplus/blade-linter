<?php

namespace Luxplus\BladeLinter;

final class LineIndex
{
    /** @var list<int> */
    private readonly array $lineStarts;

    public function __construct(string $text)
    {
        $starts = [0];
        $offset = mb_strpos($text, "\n", 0, '8bit');

        while ($offset !== false) {
            $starts[] = $offset + 1;
            $offset = mb_strpos($text, "\n", $offset + 1, '8bit');
        }

        $this->lineStarts = $starts;
    }

    public function lineAt(int $byteOffset): int
    {
        $low = 0;
        $high = count($this->lineStarts) - 1;

        while ($low < $high) {
            $mid = intdiv($low + $high + 1, 2);

            if (($this->lineStarts[$mid] ?? 0) <= $byteOffset) {
                $low = $mid;
            } else {
                $high = $mid - 1;
            }
        }

        return $low + 1;
    }
}
