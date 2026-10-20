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
        'include_directives_as_elements' => true,
    ],
];
