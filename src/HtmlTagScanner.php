<?php

namespace Luxplus\BladeLinter;

use Stillat\BladeParser\Document\Document;
use Stillat\BladeParser\Nodes\AbstractNode;
use Stillat\BladeParser\Nodes\LiteralNode;

final class HtmlTagScanner
{
    private const string TAG_PATTERN = '/<(\/?)([a-zA-Z][a-zA-Z0-9._:-]*+)((?>"[^"]*+"|\'[^\']*+\'|[^"\'>]++)*+)>/';

    private const string COMMENT_PATTERN = '/<!--.*?-->|<![^>]*+>/s';

    /**
     * @return list<ScannedTag>
     */
    public function scan(Document $document, string $template, OffsetConverter $offsets, LineIndex $lines): array
    {
        $masked = $this->maskNonLiteralRanges($document, $template, $offsets);
        $tags = [];

        if (preg_match_all(self::COMMENT_PATTERN, $masked, $commentMatches, PREG_OFFSET_CAPTURE) > 0) {
            foreach ($commentMatches[0] as $match) {
                [$text, $offset] = $match;
                $end = $offset + mb_strlen($text, '8bit') - 1;
                $tags[] = new ScannedTag(
                    '',
                    false,
                    false,
                    true,
                    $offset,
                    $end,
                    $lines->lineAt($offset),
                    $lines->lineAt($end),
                );
                $masked = substr_replace($masked, $this->blankOut($text), $offset, mb_strlen($text, '8bit'));
            }
        }

        if (preg_match_all(self::TAG_PATTERN, $masked, $tagMatches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) > 0) {
            foreach ($tagMatches as $match) {
                [$text, $offset] = $match[0];
                $end = $offset + mb_strlen($text, '8bit') - 1;
                $tags[] = new ScannedTag(
                    mb_strtolower($match[2][0]),
                    $match[1][0] === '/',
                    str_ends_with(mb_rtrim($match[3][0]), '/'),
                    false,
                    $offset,
                    $end,
                    $lines->lineAt($offset),
                    $lines->lineAt($end),
                );
            }
        }

        usort($tags, fn(ScannedTag $a, ScannedTag $b): int => $a->startOffset <=> $b->startOffset);

        return $tags;
    }

    private function maskNonLiteralRanges(Document $document, string $template, OffsetConverter $offsets): string
    {
        $length = mb_strlen($template, '8bit');
        $masked = $template;

        foreach ($document->getNodes() as $node) {
            if (!$node instanceof AbstractNode || $node instanceof LiteralNode || $node->position === null) {
                continue;
            }

            $start = max($offsets->byteOffset($node->position->startOffset), 0);
            $rangeLength = min($offsets->byteEndOffset($node->position->endOffset), $length - 1) - $start + 1;

            if ($start >= $length || $rangeLength <= 0) {
                continue;
            }

            $masked = substr_replace($masked, $this->blankOut(mb_substr($template, $start, $rangeLength, '8bit')), $start, $rangeLength);
        }

        return $masked;
    }

    private function blankOut(string $text): string
    {
        return preg_replace('/[^\n]/', ' ', $text) ?? str_repeat(' ', mb_strlen($text, '8bit'));
    }
}
