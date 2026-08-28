<?php

namespace Luxplus\BladeLinter\Tests;

use Luxplus\BladeLinter\BladeLinterServiceProvider;
use Orchestra\Testbench\TestCase as TestbenchTestCase;
use Override;

abstract class TestCase extends TestbenchTestCase
{
    #[Override]
    protected function getPackageProviders($app): array
    {
        return [BladeLinterServiceProvider::class];
    }
}
