<?php

use Luxplus\BladeLinter\Rules\SiblingBlankLines;

return [
    'rules' => [
        SiblingBlankLines::class,
    ],
    'paths' => [
        'resources/views',
    ],
    'base' => 'main',
];
