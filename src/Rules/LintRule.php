<?php

namespace Luxplus\BladeLinter\Rules;

use Luxplus\BladeLinter\LintItem;
use Luxplus\BladeLinter\Violation;

interface LintRule
{
    /**
     * @param list<string> $lines
     *
     * @return list<Violation>
     */
    public function check(LintItem $root, array $lines): array;
}
