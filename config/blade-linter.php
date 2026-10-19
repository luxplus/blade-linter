<?php

use Luxplus\BladeLinter\Rules\ElementContentOnOwnLines;
use Luxplus\BladeLinter\Rules\SiblingBlankLines;

return [
    'rules' => [
        ElementContentOnOwnLines::class,
        SiblingBlankLines::class,
    ],
    'paths' => [
        'resources/views',
    ],
    'base'                => 'main',
    'sibling_blank_lines' => [
        'element_directives' => SiblingBlankLines::DEFAULT_ELEMENT_DIRECTIVES,
    ],
    'element_content_on_own_lines' => [
        'block_elements'       => ElementContentOnOwnLines::DEFAULT_BLOCK_ELEMENTS,
        'inline_elements'      => ElementContentOnOwnLines::DEFAULT_INLINE_ELEMENTS,
        'preformatted_classes' => ElementContentOnOwnLines::DEFAULT_PREFORMATTED_CLASSES,
        'max_line_length'      => ElementContentOnOwnLines::DEFAULT_MAX_LINE_LENGTH,
    ],
];
