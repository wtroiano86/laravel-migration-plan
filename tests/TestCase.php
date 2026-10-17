<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Wtroiano\MigrationPlan\PackageServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders(mixed $app): array
    {
        return [PackageServiceProvider::class];
    }

    protected function defineEnvironment(mixed $app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'foreign_key_constraints' => true,
        ]);
    }
}
