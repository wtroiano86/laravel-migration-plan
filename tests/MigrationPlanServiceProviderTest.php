<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Symfony\Component\Console\Tester\CommandTester;
use Wtroiano\MigrationPlan\Commands\MigrateCommand;
use Wtroiano\MigrationPlan\MigrationPlan;
use Wtroiano\MigrationPlan\MigrationPlanServiceProvider;
use Wtroiano\MigrationPlan\OrderedMigrator;

beforeEach(function (): void {
    $this->directory = sys_get_temp_dir().'/migration-plan-provider-'.bin2hex(random_bytes(8));
    mkdir($this->directory.'/database/migrations', recursive: true);
    $this->application = new Application($this->directory);
    $this->application->instance('config', new Repository([
        'database' => ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:']], 'migrations' => 'migrations'],
    ]));
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($this->application);
    $this->application->register(DatabaseServiceProvider::class);
    $this->application->register(FilesystemServiceProvider::class);
});

afterEach(function (): void {
    (new Filesystem)->deleteDirectory($this->directory);
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication(null);
    $this->application->flush();
});

it('uses host registrations and the ordered migrator after explicit provider registration', function (): void {
    $this->application->afterResolving(MigrationPlan::class, function (MigrationPlan $plan): void {
        $plan->register('example/host', [$this->directory.'/database/migrations']);
    });
    $this->application->register(MigrationPlanServiceProvider::class);
    $plan = $this->application->make(MigrationPlan::class);

    expect($plan->owners())->toBe(['example/host']);
    expect($this->application->make(Migrator::class))->toBeInstanceOf(OrderedMigrator::class)
        ->toBe($this->application->make('migrator'));
    expect($this->application->make('migrator')->paths())->toBe([$this->directory.'/database/migrations']);
});

it('preserves a host-supplied plan when the provider registers', function (): void {
    $plan = new MigrationPlan;
    $plan->register('example/host', [$this->directory.'/database/migrations']);
    $this->application->instance(MigrationPlan::class, $plan);
    $this->application->register(MigrationPlanServiceProvider::class);

    expect($this->application->make(MigrationPlan::class))->toBe($plan);
});

it('rejects partial migration CLI requests before creating the migration repository', function (): void {
    $this->application->register(MigrationPlanServiceProvider::class);
    $this->application->make(MigrationPlan::class)->register('example/host', [$this->directory.'/database/migrations']);
    $command = $this->application->make(Illuminate\Database\Console\Migrations\MigrateCommand::class);
    $command->setLaravel($this->application);

    expect($command)->toBeInstanceOf(MigrateCommand::class);
    expect(fn () => (new CommandTester($command))->execute(['--path' => 'database/migrations']))->toThrow(RuntimeException::class, 'does not support [--path]');
    expect($this->application->make('migration.repository')->repositoryExists())->toBeFalse();
});
