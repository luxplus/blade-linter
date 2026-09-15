# luxplus/blade-linter

Lints Laravel Blade templates for blank-line conventions between sibling elements, with auto-fix.

The core rule (`SiblingBlankLines`): within any scope (an HTML element, an `@if` branch, a loop body, the file root), two adjacent sibling elements must be separated by exactly one blank line when at least one of them spans multiple lines, and by none when both are single-line. Scope edges stay unpadded, `@else`/`@elseif`/`@empty` boundaries get no surrounding blanks, and attribute wrapping does not make an element multi-line.

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

Then require it as a dev dependency. There are no tagged releases yet, so pin the branch explicitly:

```bash
composer require --dev luxplus/blade-linter:dev-main
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

Check mode exits `1` when violations are found; `--fix` output is idempotent and blade-formatter-compatible.

## Configuration

```bash
php artisan vendor:publish --tag=blade-linter-config
```

```php
// config/blade-linter.php
return [
    'rules' => [
        Luxplus\BladeLinter\Rules\SiblingBlankLines::class,
        App\BladeLint\MyProjectRule::class,
    ],
    'paths' => ['resources/views'],
    'base' => 'main',
];
```

## Custom rules

A rule is any class implementing `Luxplus\BladeLinter\Rules\LintRule`. It receives the parsed template tree (`LintItem` scopes/regions with line spans) plus the raw lines, and returns `Violation`s that carry their own fix (a blank-line gap rewrite). Register it in the `rules` array of the consuming project's config — no package release required.

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
