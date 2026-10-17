<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan\Providers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Database\MigrationServiceProvider as LaravelMigrationServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use RuntimeException;
use Wtroiano\MigrationPlan\Migrations\OrderedMigrator;
use Wtroiano\MigrationPlan\Repositories\OrderedMigrationRepository;
use Wtroiano\MigrationPlan\Services\MigrationPlan;

final class MigrationPlanServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(LaravelMigrationServiceProvider::class);
        $this->app->singletonIf(MigrationPlan::class);
        $this->app->singleton('migration.repository', function (Application $app): OrderedMigrationRepository {
            $configuration = Config::get('database.migrations', 'migrations');
            $table = is_array($configuration) ? ($configuration['table'] ?? null) : $configuration;
            throw_unless(is_string($table), RuntimeException::class, 'The migration repository table must be a string.');

            return new OrderedMigrationRepository($app->make(DatabaseManager::class), $table);
        });
        $this->app->alias('migration.repository', OrderedMigrationRepository::class);
        $this->app->singleton('migrator', fn (Application $app): OrderedMigrator => new OrderedMigrator(
            $app->make(OrderedMigrationRepository::class), $app->make(DatabaseManager::class), $app->make(Filesystem::class),
            $app->make(Dispatcher::class), $app->make(MigrationPlan::class),
        ));
        $this->app->alias('migrator', OrderedMigrator::class);
        $this->app->bind(Migrator::class, fn (Application $app): OrderedMigrator => $app->make(OrderedMigrator::class));
        foreach (['Migrate', 'Fresh', 'Refresh', 'Rollback', 'Reset', 'Status'] as $command) {
            $this->app->bind(
                'Illuminate\\Database\\Console\\Migrations\\'.$command.'Command',
                'Wtroiano\\MigrationPlan\\Console\\Commands\\'.$command.'Command',
            );
        }
    }
}
