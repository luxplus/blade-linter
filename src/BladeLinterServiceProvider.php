<?php

namespace Luxplus\BladeLinter;

use Illuminate\Contracts\Foundation\Application;
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
            ->give(fn(Application $app): array => $app->make('config')->boolean('blade-linter.sibling_blank_lines.include_directives_as_elements', true)
                ? SiblingBlankLines::DEFAULT_ELEMENT_DIRECTIVES
                : []);
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
