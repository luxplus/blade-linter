<?php

namespace Luxplus\BladeLinter;

use Illuminate\Support\ServiceProvider;
use Luxplus\BladeLinter\Console\BladeLintCommand;
use Luxplus\BladeLinter\Rules\ElementContentOnOwnLines;
use Luxplus\BladeLinter\Rules\SiblingBlankLines;
use Override;

final class BladeLinterServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/blade-linter.php', 'blade-linter');
        $this->app->when(SiblingBlankLines::class)
            ->needs('$elementDirectives')
            ->giveConfig('blade-linter.sibling_blank_lines.element_directives', SiblingBlankLines::DEFAULT_ELEMENT_DIRECTIVES);
        $this->app->when(ElementContentOnOwnLines::class)
            ->needs('$blockElements')
            ->giveConfig('blade-linter.element_content_on_own_lines.block_elements', ElementContentOnOwnLines::DEFAULT_BLOCK_ELEMENTS);
        $this->app->when(ElementContentOnOwnLines::class)
            ->needs('$inlineElements')
            ->giveConfig('blade-linter.element_content_on_own_lines.inline_elements', ElementContentOnOwnLines::DEFAULT_INLINE_ELEMENTS);
        $this->app->when(ElementContentOnOwnLines::class)
            ->needs('$preformattedClasses')
            ->giveConfig('blade-linter.element_content_on_own_lines.preformatted_classes', ElementContentOnOwnLines::DEFAULT_PREFORMATTED_CLASSES);
        $this->app->when(ElementContentOnOwnLines::class)
            ->needs('$maxLineLength')
            ->giveConfig('blade-linter.element_content_on_own_lines.max_line_length', ElementContentOnOwnLines::DEFAULT_MAX_LINE_LENGTH);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([BladeLintCommand::class]);
            $this->publishes([
                __DIR__ . '/../config/blade-linter.php' => config_path('blade-linter.php'),
            ], 'blade-linter-config');
        }
    }
}
