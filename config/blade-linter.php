<?php

use Luxplus\BladeLinter\Rules\SiblingBlankLines;

return [
    'rules' => [
        SiblingBlankLines::class,
    ],
    'paths' => [
        'resources/views',
    ],
    'base'                => 'main',
    'sibling_blank_lines' => [
        'element_directives' => SiblingBlankLines::DEFAULT_ELEMENT_DIRECTIVES,
    ],
];
