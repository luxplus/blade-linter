<?php

namespace Luxplus\BladeLinter;

use Illuminate\Support\ServiceProvider;
use Luxplus\BladeLinter\Console\BladeLintCommand;
use Override;

final class BladeLinterServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/blade-linter.php', 'blade-linter');
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
