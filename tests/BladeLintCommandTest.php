<?php

namespace Luxplus\BladeLinter\Tests;

use Illuminate\Testing\PendingCommand;

final class BladeLintCommandTest extends TestCase
{
    private string $viewPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->viewPath = sys_get_temp_dir() . '/blade-linter-test-' . uniqid() . '.blade.php';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->viewPath)) {
            unlink($this->viewPath);
        }

        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function runBladeLint(array $parameters): PendingCommand
    {
        $command = $this->artisan('blade:lint', $parameters);
        self::assertInstanceOf(PendingCommand::class, $command);

        return $command;
    }

    public function testCommandReportsViolationsAndFails(): void
    {
        file_put_contents($this->viewPath, "<div>\n    <span>{{ \$a }}</span>\n\n    <span>{{ \$b }}</span>\n</div>\n");

        $this->runBladeLint(['paths' => [$this->viewPath]])
            ->expectsOutputToContain('Unexpected blank line between single-line siblings')
            ->assertExitCode(1);
    }

    public function testCommandFixesViolations(): void
    {
        file_put_contents($this->viewPath, "<div>\n    <span>{{ \$a }}</span>\n\n    <span>{{ \$b }}</span>\n</div>\n");

        $this->runBladeLint(['paths' => [$this->viewPath], '--fix' => true])->assertExitCode(0);

        self::assertSame(
            "<div>\n    <span>{{ \$a }}</span>\n    <span>{{ \$b }}</span>\n</div>\n",
            file_get_contents($this->viewPath),
        );

        $this->runBladeLint(['paths' => [$this->viewPath]])->assertExitCode(0);
    }

    public function testCleanFileSucceeds(): void
    {
        file_put_contents($this->viewPath, "<div>\n    <span>{{ \$a }}</span>\n    <span>{{ \$b }}</span>\n</div>\n");

        $this->runBladeLint(['paths' => [$this->viewPath]])
            ->expectsOutputToContain('No violations found')
            ->assertExitCode(0);
    }

    public function testElementDirectivesComeFromConfig(): void
    {
        config(['blade-linter.sibling_blank_lines.element_directives' => []]);
        file_put_contents($this->viewPath, "<div>\n    <span>{{ \$a }}</span>\n    @include('partials.a')\n</div>\n");

        $this->runBladeLint(['paths' => [$this->viewPath]])
            ->expectsOutputToContain('Missing blank line around directive `@include`')
            ->assertExitCode(1);
    }

    public function testPreformattedClassesComeFromConfig(): void
    {
        config(['blade-linter.element_content_on_own_lines.preformatted_classes' => ['english-text']]);
        file_put_contents($this->viewPath, "<div class=\"english-text\"><p>{{ \$a }}</p></div>\n<div class=\"other\"><p>{{ \$b }}</p></div>\n");

        $this->runBladeLint(['paths' => [$this->viewPath]])
            ->expectsOutputToContain(':2 Content of `<div>` must be on its own lines')
            ->doesntExpectOutputToContain(':1 ')
            ->assertExitCode(1);
    }

    public function testMaxLineLengthComesFromConfig(): void
    {
        config(['blade-linter.element_content_on_own_lines.max_line_length' => 20]);
        file_put_contents($this->viewPath, "<p>{{ \$something }}</p>\n");

        $this->runBladeLint(['paths' => [$this->viewPath]])
            ->expectsOutputToContain('Content of `<p>` must be on its own lines (line exceeds 20 characters)')
            ->assertExitCode(1);
    }
}
