# luxplus/blade-linter

Lints Laravel Blade templates for line-break and blank-line conventions, with auto-fix.

## Rules

### `ElementContentOnOwnLines`

Elements holding structure put their content on lines of its own, one level deeper than their tags. Structure is a block element, a block directive (`@if`, `@foreach`, ...), a component with content, or a single element wrapping all of the content:

```blade
<tr><th>Name</th><th>Email</th></tr>      {{-- before --}}

<tr>                                      {{-- after --}}
    <th>Name</th>
    <th>Email</th>
</tr>
```

```blade
<li><a href="#">Home</a></li>             {{-- before --}}

<li>                                      {{-- after --}}
    <a href="#">Home</a>
</li>
```

Text, echoes, void tags (`<input>`, `<img>`) and inline elements mixed with text are not structure. That content stays on the tag's line until the line gets longer than `max_line_length` (120 by default), and then it moves too. So `<p>{{ $something }}</p>` stays as it is, and a `<p>` holding a long expression gets three lines. Content that already spans several lines while sharing a line with a tag (`<div>Hello` ... `world</div>`) is always moved off the tag lines.

Block elements (`div`, `p`, `li`, `td`, `tr`, `h1`, ...) are also placed on their own lines, so `<tr><td>a</td><td>b</td></tr>` gets one cell per line. Whitespace around and inside block elements doesn't render, so the page looks the same.

Inline elements (`a`, `span`, `strong`, `button`, `label`, ...) are different: whitespace inside them shows up as a space, so `(<span>3</span>)` would turn into `( 3 )`. Their content only moves when they are the sole content of an expanded parent, as in `<li><a href="#">...</a></li>`, where the added whitespace lands at the start and end of a line and is dropped. In `<li>Read <a href="#">the terms</a>.</li>`, the `<a>` always stays on one line.

Elements without content (`<td></td>`) stay on one line, and a comment after a closing tag (`</div> {{-- /.row --}}`) stays where it is. Content whose whitespace is significant is never touched: `<pre>`, `<textarea>`, `<script>`, `<style>` and `<svg>`, elements with an inline `white-space: pre...` style, and elements with one of the configured preformatted classes (by default Tailwind's `whitespace-pre*`). Add your own classes whose CSS sets `white-space: pre-wrap` or similar; the linter can't see stylesheets. Custom tags (such as XML feed elements) are not in either list, so they are left alone.

The element lists, the preformatted classes and the line length are configurable (see below).

### `SiblingBlankLines`

Within any scope (an HTML element, an `@if` branch, a loop body, the file root), two adjacent sibling elements must be separated by exactly one blank line when at least one of them spans multiple lines, and by none when both are single-line. A sibling is multi-line whenever its source spans more than one line, so a tag with wrapped attributes counts as multi-line, whichever tool wrapped it.

Blade directives get a blank line around them too: a sibling whose line starts with a directive (`@csrf`, `@props(...)`, `@vite(...)`, ...) needs exactly one blank line between it and its neighbours, even when both are single-line. The exception is a run of single-line directives with the same name (`@vite`, `@vite`, `@vite`), which forms a group with no blank lines inside and a blank line on either side. Names must match exactly, so `@vite` followed by `@viteReactRefresh` still needs a blank line, and a directive that wraps onto several lines breaks out of its group.

The `@include` family (`@include`, `@includeIf`, `@includeWhen`, `@includeUnless`, `@includeFirst`, `@includeIsolated`) renders markup in place, so it is spaced like an ordinary element instead: no blank line next to another single-line element or include, but still one next to a multi-line sibling or any other directive. The list is configurable (see below). Block directives (`@php` ... `@endphp`, `@if` ... `@endif`) are a single multi-line sibling. Escaped `@@`/`@{{`, attributes such as Alpine's `@click`, and directives the parser doesn't know (custom ones registered by the app) are not treated as directives.

Scope edges stay unpadded, `@else`/`@elseif`/`@empty` boundaries get no surrounding blanks, pairs touching a Blade/HTML comment (or the doctype) are left alone, and the contents of `@switch`, `<script>`, `<style>`, `<pre>`, `<textarea>` and `<svg>` are not checked.

## Installation

The package is not on Packagist yet, so add the repository to your project's `composer.json` first:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/luxplus/blade-linter",
        "no-api": true
    }
],
"config": {
    "preferred-install": {
        "luxplus/blade-linter": "source",
        "*": "dist"
    }
}
```

Then require it as a dev dependency:

```bash
composer require --dev luxplus/blade-linter
```

`no-api` makes Composer clone the repository with git instead of going through the GitHub API, so installs never prompt for a token and are not subject to anonymous API rate limits. Because that disables zip downloads for this package, it must be installed from source, which the `preferred-install` entry above ensures even when you pass `--prefer-dist`.

The `blade:lint` artisan command is registered through package auto-discovery.

## Usage

```bash
php artisan blade:lint                          # lint all configured view paths
php artisan blade:lint resources/views/shop     # lint specific paths (files or directories)
php artisan blade:lint --fix                    # apply fixes instead of reporting
php artisan blade:lint --changed                # ratchet mode: only blade files changed vs the base branch
php artisan blade:lint --changed --base=main    # override the base branch
```

Check mode exits `1` when violations are found; `--fix` output is idempotent. If you also use blade-formatter, run `blade:lint --fix`, then blade-formatter, then `blade:lint --fix` once more: moving content onto its own lines shortens lines that blade-formatter had wrapped, and it joins them back up, which can change blank-line spacing. After that round the two tools agree.

## Configuration

```bash
php artisan vendor:publish --tag=blade-linter-config
```

```php
// config/blade-linter.php
return [
    'rules' => [
        Luxplus\BladeLinter\Rules\ElementContentOnOwnLines::class,
        Luxplus\BladeLinter\Rules\SiblingBlankLines::class,
        App\BladeLint\MyProjectRule::class,
    ],
    'paths' => ['resources/views'],
    'base' => 'main',
    'sibling_blank_lines' => [
        // directives spaced like ordinary elements; replaces the default @include family
        'element_directives' => [
            ...Luxplus\BladeLinter\Rules\SiblingBlankLines::DEFAULT_ELEMENT_DIRECTIVES,
            'stack',
        ],
    ],
    'element_content_on_own_lines' => [
        // always on their own lines; content moves when it holds structure or the line is too long
        'block_elements' => Luxplus\BladeLinter\Rules\ElementContentOnOwnLines::DEFAULT_BLOCK_ELEMENTS,
        // content moves the same way, but only when the element is the sole content of an expanded parent
        'inline_elements' => Luxplus\BladeLinter\Rules\ElementContentOnOwnLines::DEFAULT_INLINE_ELEMENTS,
        // classes whose CSS preserves whitespace; these elements and their contents are left alone
        'preformatted_classes' => [
            ...Luxplus\BladeLinter\Rules\ElementContentOnOwnLines::DEFAULT_PREFORMATTED_CLASSES,
            'note-body',
        ],
        // text-only content moves onto its own lines once the line would exceed this
        'max_line_length' => 120,
    ],
];
```

## Custom rules

A rule is any class implementing `Luxplus\BladeLinter\Rules\LintRule`. It receives the parsed template tree (`LintItem` scopes/regions with line spans) plus the raw lines, and returns `Violation`s that carry their own fix: the lines strictly between `gapAfterLine` and `gapBeforeLine` are replaced by `replacement`, or by `requiredBlankLines` blank lines when no replacement is given. Register it in the `rules` array of the consuming project's config — no package release required.

## Development

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyze
vendor/bin/php-cs-fixer fix
```

Note: templates are parsed via `stillat/blade-parser` for Blade constructs, with an internal byte-offset HTML tag scanner (the parser's own fragment API is avoided deliberately: it hangs on some real-world templates, and its positions are character-based while all internal arithmetic here is byte-based — see `OffsetConverter`).

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).
