<?php

namespace Luxplus\BladeLinter\Tests;

use Illuminate\Support\Facades\Process;

final class PublishableConfigTest extends TestCase
{
    public function testConfigLoadsWithoutThePackageInstalled(): void
    {
        $result = Process::run([PHP_BINARY, '-r', 'var_export(require $argv[1]);', '--', __DIR__ . '/../config/blade-linter.php']);

        self::assertTrue($result->successful(), $result->errorOutput() . $result->output());
    }
}
