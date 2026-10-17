<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan\Tests;

use Illuminate\Contracts\Config\Repository;
use Orchestra\Testbench\TestCase as Orchestra;
use Wtroiano\MigrationPlan\Providers\PackageServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders(mixed $app): array
    {
        return [PackageServiceProvider::class];
    }

    protected function defineEnvironment(mixed $app): void
    {
        $app->make(Repository::class)->set('database.default', 'testing');
        $app->make(Repository::class)->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'foreign_key_constraints' => true,
        ]);
    }
}
