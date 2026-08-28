<?php

namespace Luxplus\BladeLinter;

use Exception;
use Luxplus\BladeLinter\Rules\LintRule;
use Luxplus\BladeLinter\Rules\SiblingBlankLines;
use Stillat\BladeParser\Document\Document;

final class Linter
{
    /** @var list<LintRule> */
    private readonly array $rules;

    /**
     * @param null|list<LintRule> $rules
     */
    public function __construct(?array $rules = null)
    {
        $this->rules = $rules ?? [new SiblingBlankLines()];
    }

    public function lint(string $template): LintResult
    {
        [$lines] = $this->splitLines($template);

        try {
            /** @throws Exception */
            $document = Document::fromText($template);
            $document->resolveStructures();

            if ($document->getErrors()->isNotEmpty()) {
                return new LintResult([], 'template has ' . $document->getErrors()->count() . ' parse error(s)');
            }

            $root = (new TreeBuilder())->build($document, $template, $lines);
            $violations = [];

            foreach ($this->rules as $rule) {
                foreach ($rule->check($root, $lines) as $violation) {
                    $violations[] = $violation;
                }
            }

            usort($violations, fn(Violation $a, Violation $b): int => $a->line <=> $b->line);
        } catch (Exception $exception) {
            return new LintResult([], 'skipped: ' . $exception->getMessage());
        }

        return new LintResult($violations);
    }

    public function fix(string $template): string
    {
        for ($pass = 0; $pass < 5; $pass++) {
            $result = $this->lint($template);

            if ($result->violations === []) {
                return $template;
            }

            $template = $this->applyFixes($template, $result->violations);
        }

        return $template;
    }

    /**
     * @param list<Violation> $violations
     */
    private function applyFixes(string $template, array $violations): string
    {
        [$lines, $hasTrailingNewline] = $this->splitLines($template);

        usort($violations, fn(Violation $a, Violation $b): int => $b->gapAfterLine <=> $a->gapAfterLine);

        foreach ($violations as $violation) {
            $gapLength = min($violation->gapBeforeLine - 1, count($lines)) - $violation->gapAfterLine;
            array_splice(
                $lines,
                $violation->gapAfterLine,
                max($gapLength, 0),
                array_fill(0, $violation->requiredBlankLines, ''),
            );
        }

        return implode("\n", $lines) . ($hasTrailingNewline ? "\n" : '');
    }

    /**
     * @return array{0: list<string>, 1: bool}
     */
    private function splitLines(string $template): array
    {
        $hasTrailingNewline = str_ends_with($template, "\n");
        $body = $hasTrailingNewline ? mb_substr($template, 0, -1) : $template;

        return [explode("\n", $body), $hasTrailingNewline];
    }
}
