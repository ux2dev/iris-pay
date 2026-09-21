<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Ux2Dev\Iris\Laravel\IrisServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [IrisServiceProvider::class];
    }
}
