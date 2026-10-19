<?php

namespace Luxplus\BladeLinter;

use Illuminate\Support\ServiceProvider;
use Luxplus\BladeLinter\Console\BladeLintCommand;
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
