<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan\Providers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Wtroiano\MigrationPlan\Console\Commands\CheckCommand;
use Wtroiano\MigrationPlan\Console\Commands\ShowCommand;
use Wtroiano\MigrationPlan\Services\MigrationPlan;

final class PackageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/migration-plan.php', 'migration-plan');
        $this->app->singletonIf(MigrationPlan::class);

        if (Config::get('migration-plan.enabled') === true) {
            $this->app->register(MigrationPlanServiceProvider::class);
        }
    }

    public function boot(): void
    {
        $this->commands([CheckCommand::class, ShowCommand::class]);

        $this->publishes([
            __DIR__.'/../../config/migration-plan.php' => $this->app->configPath('migration-plan.php'),
        ], 'migration-plan-config');
    }
}
