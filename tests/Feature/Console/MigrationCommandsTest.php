<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Wtroiano\MigrationPlan\Exceptions\InvalidMigrationPlan;
use Wtroiano\MigrationPlan\Providers\MigrationPlanServiceProvider;
use Wtroiano\MigrationPlan\Services\MigrationPlan;

beforeEach(function (): void {
    $this->directory = sys_get_temp_dir().'/migration-plan-commands-'.bin2hex(random_bytes(8));
    mkdir($this->directory.'/database/migrations', recursive: true);
    mkdir($this->directory.'/package');
    $this->app->useDatabasePath($this->directory.'/database');
    $this->app->register(MigrationPlanServiceProvider::class);
});

afterEach(function (): void {
    (new Filesystem)->deleteDirectory($this->directory);
});

it('preserves existing tables when the application migration path has no owner', function (string $command): void {
    resolve(MigrationPlan::class)->register('example/package', [$this->directory.'/package']);
    resolve('migration.repository')->createRepository();
    Schema::create('existing_records', fn (Blueprint $table) => $table->id());

    expect(fn () => $this->artisan($command, ['--force' => true])->run())
        ->toThrow(InvalidMigrationPlan::class, 'Unassigned migration path');

    expect(Schema::hasTable('existing_records'))->toBeTrue();
    expect(Schema::hasTable('migrations'))->toBeTrue();
})->with(['migrate:fresh', 'migrate:refresh', 'migrate:reset']);

it('rejects unsupported options before creating a repository', function (string $command, array $options, string $option): void {
    resolve(MigrationPlan::class)->register('application', [$this->directory.'/database/migrations']);

    expect(fn () => $this->artisan($command, $options)->run())
        ->toThrow(InvalidMigrationPlan::class, "does not support [--{$option}]");

    expect(resolve('migration.repository')->repositoryExists())->toBeFalse();
})->with([
    'migration path' => ['migrate', ['--path' => ['database/migrations']], 'path'],
    'migration schema path' => ['migrate', ['--schema-path' => '/schema.sql'], 'schema-path'],
    'graceful failure' => ['migrate', ['--graceful' => true], 'graceful'],
    'fresh path' => ['migrate:fresh', ['--path' => ['database/migrations']], 'path'],
    'fresh schema path' => ['migrate:fresh', ['--schema-path' => '/schema.sql'], 'schema-path'],
    'refresh path' => ['migrate:refresh', ['--path' => ['database/migrations']], 'path'],
    'reset path' => ['migrate:reset', ['--path' => ['database/migrations']], 'path'],
]);

it('preserves tables when schema dumps would bypass ownership', function (string $command): void {
    resolve(MigrationPlan::class)->register('application', [$this->directory.'/database/migrations']);
    resolve('migration.repository')->createRepository();
    Schema::create('existing_records', fn (Blueprint $table) => $table->id());
    mkdir($this->directory.'/database/schema');
    file_put_contents($this->directory.'/database/schema/testing-schema.sql', '-- schema fixture');

    expect(fn () => $this->artisan($command)->run())
        ->toThrow(InvalidMigrationPlan::class, 'Schema dumps bypass package migration ownership');

    expect(Schema::hasTable('existing_records'))->toBeTrue();
})->with(['migrate', 'migrate:fresh', 'migrate:refresh', 'migrate:reset', 'migrate:rollback', 'migrate:status', 'migration-plan:check', 'migration-plan:show']);

it('rejects invalid dependencies before destructive commands change tables', function (string $command): void {
    resolve(MigrationPlan::class)->register('application', [$this->directory.'/database/migrations'], ['missing']);
    resolve('migration.repository')->createRepository();
    Schema::create('existing_records', fn (Blueprint $table) => $table->id());

    expect(fn () => $this->artisan($command, ['--force' => true])->run())
        ->toThrow(InvalidMigrationPlan::class, 'Missing migration prerequisite [missing]');

    expect(Schema::hasTable('existing_records'))->toBeTrue();
})->with(['migrate:fresh', 'migrate:refresh', 'migrate:reset']);

it('runs the normal command lifecycle with an owned application directory', function (string $command, bool $retainsRecords): void {
    $name = '2026_01_01_000001_create_package_records';
    copy(__DIR__.'/../../Fixtures/migrations/'.$name.'.php', $this->directory.'/package/'.$name.'.php');
    resolve(MigrationPlan::class)->register('example/package', [$this->directory.'/package']);
    resolve(MigrationPlan::class)->register('application', [$this->directory.'/database/migrations'], ['example/package']);
    $this->artisan('migrate')->assertSuccessful();

    $this->artisan($command)->assertSuccessful();

    expect(Schema::hasTable('package_records'))->toBe($retainsRecords);
    $this->assertDatabaseCount('migrations', $retainsRecords ? 1 : 0);
})->with([
    'fresh' => ['migrate:fresh', true],
    'refresh' => ['migrate:refresh', true],
    'reset' => ['migrate:reset', false],
    'status' => ['migrate:status', true],
]);

it('pretends to migrate the complete dependency plan without schema or history writes', function (): void {
    $name = '2030_01_01_000001_skippable_customers';
    copy(__DIR__.'/../../Fixtures/migrations/'.$name.'.php', $this->directory.'/package/'.$name.'.php');
    $dependent = '2026_01_01_000001_create_package_records';
    copy(__DIR__.'/../../Fixtures/migrations/'.$dependent.'.php', $this->directory.'/database/migrations/'.$dependent.'.php');
    resolve(MigrationPlan::class)->register('customers', [$this->directory.'/package']);
    resolve(MigrationPlan::class)->register('application', [$this->directory.'/database/migrations'], ['customers']);

    $this->artisan('migrate', ['--pretend' => true])
        ->expectsOutputToContain('create table "customers"')
        ->expectsOutputToContain('create table "package_records"')
        ->assertSuccessful();

    expect(Schema::hasTable('customers'))->toBeFalse();
    expect(Schema::hasTable('package_records'))->toBeFalse();
    $this->assertDatabaseCount('migrations', 0);
});

it('pretends to roll back without changing schema or recorded history', function (): void {
    $name = '2026_01_01_000001_create_package_records';
    copy(__DIR__.'/../../Fixtures/migrations/'.$name.'.php', $this->directory.'/package/'.$name.'.php');
    resolve(MigrationPlan::class)->register('example/package', [$this->directory.'/package']);
    resolve(MigrationPlan::class)->register('application', [$this->directory.'/database/migrations'], ['example/package']);
    $this->artisan('migrate')->assertSuccessful();

    $this->artisan('migrate:rollback', ['--pretend' => true])
        ->expectsOutputToContain('drop table "package_records"')
        ->assertSuccessful();

    expect(Schema::hasTable('package_records'))->toBeTrue();
    $this->assertDatabaseHas('migrations', ['migration' => $name]);
});

it('keeps alternate database commands and history isolated from the default connection', function (string $command, bool $retainsRecords): void {
    Config::set('database.connections.secondary', ['driver' => 'sqlite', 'database' => ':memory:']);
    $name = '2026_01_01_000001_create_package_records';
    copy(__DIR__.'/../../Fixtures/migrations/'.$name.'.php', $this->directory.'/package/'.$name.'.php');
    resolve(MigrationPlan::class)->register('example/package', [$this->directory.'/package']);
    resolve(MigrationPlan::class)->register('application', [$this->directory.'/database/migrations'], ['example/package']);
    $this->artisan('migrate', ['--database' => 'secondary'])->assertSuccessful();

    $this->artisan($command, ['--database' => 'secondary'])->assertSuccessful();

    expect(Schema::connection('secondary')->hasTable('package_records'))->toBe($retainsRecords);
    $this->assertDatabaseCount('migrations', $retainsRecords ? 1 : 0, 'secondary');
    expect(DB::getDefaultConnection())->toBe('testing');
    expect(Schema::hasTable('package_records'))->toBeFalse();
    expect(Schema::hasTable('migrations'))->toBeFalse();
})->with([
    'rollback' => ['migrate:rollback', false],
    'reset' => ['migrate:reset', false],
    'refresh' => ['migrate:refresh', true],
    'fresh' => ['migrate:fresh', true],
    'status' => ['migrate:status', true],
]);

it('honors per-migration connections while recording history on the command connection', function (): void {
    Config::set('database.connections.secondary', ['driver' => 'sqlite', 'database' => ':memory:']);
    $name = '2026_01_01_000001_create_secondary_records';
    copy(__DIR__.'/../../Fixtures/migrations/'.$name.'.php', $this->directory.'/package/'.$name.'.php');
    resolve(MigrationPlan::class)->register('example/package', [$this->directory.'/package']);
    resolve(MigrationPlan::class)->register('application', [$this->directory.'/database/migrations'], ['example/package']);

    $this->artisan('migrate')->assertSuccessful();

    expect(Schema::connection('secondary')->hasTable('secondary_records'))->toBeTrue();
    expect(Schema::hasTable('secondary_records'))->toBeFalse();
    expect(Schema::connection('secondary')->hasTable('migrations'))->toBeFalse();
    $this->assertDatabaseHas('migrations', ['migration' => $name]);

    $this->artisan('migrate:rollback')->assertSuccessful();

    expect(Schema::connection('secondary')->hasTable('secondary_records'))->toBeFalse();
    expect(DB::getDefaultConnection())->toBe('testing');
    $this->assertDatabaseCount('migrations', 0);
});

it('assigns separate batches in dependency order with migrate step enabled', function (): void {
    $first = '2030_01_01_000001_skippable_customers';
    copy(__DIR__.'/../../Fixtures/migrations/'.$first.'.php', $this->directory.'/package/'.$first.'.php');
    touch($this->directory.'/package/enable_customers');
    $last = '2026_01_01_000001_create_package_records';
    copy(__DIR__.'/../../Fixtures/migrations/'.$last.'.php', $this->directory.'/database/migrations/'.$last.'.php');
    resolve(MigrationPlan::class)->register('customers', [$this->directory.'/package']);
    resolve(MigrationPlan::class)->register('application', [$this->directory.'/database/migrations'], ['customers']);

    $this->artisan('migrate', ['--step' => true])->assertSuccessful();

    $this->assertDatabaseHas('migrations', ['migration' => $first, 'batch' => 1]);
    $this->assertDatabaseHas('migrations', ['migration' => $last, 'batch' => 2]);
    $this->artisan('migrate:rollback')->assertSuccessful();
    expect(Schema::hasTable('customers'))->toBeTrue();
    expect(Schema::hasTable('package_records'))->toBeFalse();
});
