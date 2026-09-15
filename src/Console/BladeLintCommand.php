<?php

namespace Luxplus\BladeLinter\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Luxplus\BladeLinter\Linter;
use Luxplus\BladeLinter\Rules\LintRule;
use Symfony\Component\Finder\Finder;

final class BladeLintCommand extends Command
{
    protected $signature = 'blade:lint
        {paths?* : Files or directories to lint}
        {--fix : Apply fixes instead of reporting}
        {--changed : Lint only blade files changed relative to the base branch}
        {--base= : Base branch used by --changed (defaults to blade-linter.base config)}';

    protected $description = 'Lint blade templates for blank line conventions';

    public function handle(): int
    {
        $files = $this->resolveFiles();

        if ($files === null) {
            return self::FAILURE;
        }

        if ($files === []) {
            $this->info('No blade files to lint.');

            return self::SUCCESS;
        }

        $linter = new Linter($this->configuredRules());
        $violationCount = 0;
        $fixedFiles = 0;
        $skipped = [];

        foreach ($files as $file) {
            $contents = file_get_contents($file);

            if ($contents === false) {
                $skipped[] = "{$file} (unreadable)";

                continue;
            }

            $result = $linter->lint($contents);

            if ($result->skippedReason !== null) {
                $skipped[] = "{$file} ({$result->skippedReason})";

                continue;
            }

            if ($result->violations === []) {
                continue;
            }

            if ($this->option('fix') === true) {
                file_put_contents($file, $linter->fix($contents));
                $fixedFiles++;
                $this->line("Fixed {$file} (" . count($result->violations) . ' violation(s))');

                continue;
            }

            foreach ($result->violations as $violation) {
                $violationCount++;
                $this->line("{$file}:{$violation->line} {$violation->message}");
            }
        }

        foreach ($skipped as $entry) {
            $this->warn("Skipped {$entry}");
        }

        if ($this->option('fix') === true) {
            $this->info($fixedFiles === 0 ? 'Nothing to fix.' : "Fixed {$fixedFiles} file(s).");

            return self::SUCCESS;
        }

        if ($violationCount > 0) {
            $this->error("{$violationCount} violation(s) found. Run with --fix to resolve.");

            return self::FAILURE;
        }

        $this->info('No violations found in ' . count($files) . ' file(s).');

        return self::SUCCESS;
    }

    /**
     * @return null|list<string>
     */
    private function resolveFiles(): ?array
    {
        if ($this->option('changed') === true) {
            return $this->changedFiles();
        }

        /** @var list<string> $paths */
        $paths = (array)$this->argument('paths');

        if ($paths === []) {
            /** @var list<string> $patterns */
            $patterns = config('blade-linter.paths', ['resources/views']);

            foreach ($patterns as $pattern) {
                $viewDirectories = glob($pattern, GLOB_ONLYDIR);
                $paths = [...$paths, ...($viewDirectories === false ? [] : $viewDirectories)];
            }
        }

        $files = [];

        foreach ($paths as $path) {
            if (is_file($path)) {
                $files[] = $path;

                continue;
            }

            if (!is_dir($path)) {
                $this->error("Path not found: {$path}");

                return null;
            }

            $finder = Finder::create()->files()->in($path)->name('*.blade.php')->sortByName();

            foreach ($finder as $foundFile) {
                $files[] = $foundFile->getPathname();
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * @return list<LintRule>
     */
    private function configuredRules(): array
    {
        /** @var list<class-string> $classes */
        $classes = config('blade-linter.rules', []);
        $rules = [];

        foreach ($classes as $class) {
            $rule = $this->laravel->make($class);

            if ($rule instanceof LintRule) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    /**
     * @return null|list<string>
     */
    private function changedFiles(): ?array
    {
        $base = $this->option('base') ?? config('blade-linter.base', 'main');

        if (!is_string($base) || $base === '') {
            $this->error('The --base option requires a branch name.');

            return null;
        }

        $baseDiff = Process::run(['git', 'diff', '--relative', '--name-only', '--diff-filter=ACMR', "{$base}...HEAD", '--', '*.blade.php']);

        if (!$baseDiff->successful()) {
            $baseDiff = Process::run(['git', 'diff', '--relative', '--name-only', '--diff-filter=ACMR', "origin/{$base}...HEAD", '--', '*.blade.php']);
        }

        if (!$baseDiff->successful()) {
            $this->warn("Could not diff against {$base} or origin/{$base}; skipping blade lint.");

            return [];
        }

        $commands = [
            ['git', 'diff', '--relative', '--name-only', '--diff-filter=ACMR', '--', '*.blade.php'],
            ['git', 'diff', '--relative', '--name-only', '--diff-filter=ACMR', '--cached', '--', '*.blade.php'],
            ['git', 'ls-files', '--others', '--exclude-standard', '--', '*.blade.php'],
        ];

        $files = [];

        foreach (explode("\n", mb_trim($baseDiff->output())) as $file) {
            if ($file !== '' && is_file($file)) {
                $files[] = $file;
            }
        }

        foreach ($commands as $command) {
            $result = Process::run($command);

            if (!$result->successful()) {
                $this->error('git failed: ' . mb_trim($result->errorOutput()));

                return null;
            }

            foreach (explode("\n", mb_trim($result->output())) as $file) {
                if ($file !== '' && is_file($file)) {
                    $files[] = $file;
                }
            }
        }

        sort($files);

        return array_values(array_unique($files));
    }
}
