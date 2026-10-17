<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Tester\CommandTester;
use Wtroiano\MigrationPlan\Commands\MigrateCommand;
use Wtroiano\MigrationPlan\MigrationPlan;
use Wtroiano\MigrationPlan\MigrationPlanServiceProvider;
use Wtroiano\MigrationPlan\OrderedMigrator;

beforeEach(function (): void {
    $this->directory = sys_get_temp_dir().'/migration-plan-provider-'.bin2hex(random_bytes(8));
    mkdir($this->directory.'/database/migrations', recursive: true);

});

afterEach(function (): void {
    (new Filesystem)->deleteDirectory($this->directory);
});

it('uses host registrations and the ordered migrator after explicit provider registration', function (): void {
    $this->app->afterResolving(MigrationPlan::class, function (MigrationPlan $plan): void {
        $plan->register('example/host', [$this->directory.'/database/migrations']);
    });
    $this->app->register(MigrationPlanServiceProvider::class);
    $plan = $this->app->make(MigrationPlan::class);

    expect($plan->owners())->toBe(['example/host']);
    expect($this->app->make(Migrator::class))->toBeInstanceOf(OrderedMigrator::class)
        ->toBe($this->app->make('migrator'));
    expect($this->app->make('migrator')->paths())->toBe([$this->directory.'/database/migrations']);
});

it('preserves a host-supplied plan when the provider registers', function (): void {
    $plan = new MigrationPlan;
    $plan->register('example/host', [$this->directory.'/database/migrations']);
    $this->app->instance(MigrationPlan::class, $plan);
    $this->app->register(MigrationPlanServiceProvider::class);

    expect($this->app->make(MigrationPlan::class))->toBe($plan);
});

it('rejects partial migration CLI requests before creating the migration repository', function (): void {
    $this->app->register(MigrationPlanServiceProvider::class);
    $this->app->make(MigrationPlan::class)->register('example/host', [$this->directory.'/database/migrations']);
    $command = $this->app->make(Illuminate\Database\Console\Migrations\MigrateCommand::class);
    $command->setLaravel($this->app);

    expect($command)->toBeInstanceOf(MigrateCommand::class);
    expect(fn () => (new CommandTester($command))->execute(['--path' => 'database/migrations']))->toThrow(RuntimeException::class, 'does not support [--path]');
    expect($this->app->make('migration.repository')->repositoryExists())->toBeFalse();
});
