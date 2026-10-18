<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Wtroiano\MigrationPlan\Exceptions\InvalidMigrationPlan;
use Wtroiano\MigrationPlan\Services\MigrationPlan;

beforeEach(function (): void {
    $this->directory = sys_get_temp_dir().'/migration-plan-inspection-'.bin2hex(random_bytes(8));
    mkdir($this->directory.'/database/migrations', recursive: true);
    mkdir($this->directory.'/package');
    $this->app->useDatabasePath($this->directory.'/database');
    Config::set('database.connections.testing.database', $this->directory.'/unavailable.sqlite');
});

afterEach(function (): void {
    (new Filesystem)->deleteDirectory($this->directory);
});

it('checks the plan without a database connection or executing migration files', function (): void {
    file_put_contents($this->directory.'/package/2030_01_01_000001_customers.php', '<?php throw new RuntimeException("Migration file executed");');
    resolve(MigrationPlan::class)->register('application', [$this->directory.'/database/migrations'], ['customers']);
    resolve(MigrationPlan::class)->register('customers', [$this->directory.'/package']);

    $this->artisan('migration-plan:check')
        ->expectsOutput('Migration plan is valid. Owners: 2. Migrations: 1.')
        ->assertSuccessful();
});

it('reports an owned application directory with no migrations as valid', function (): void {
    resolve(MigrationPlan::class)->register('application', [$this->directory.'/database/migrations']);

    $this->artisan('migration-plan:check')
        ->expectsOutput('Migration plan is valid. Owners: 1. Migrations: 0.')
        ->assertSuccessful();
});

it('exports a stable ordered JSON plan with owners dependencies paths and exclusions', function (): void {
    $application = $this->directory.'/database/migrations';
    $package = $this->directory.'/package';
    file_put_contents($package.'/2030_01_01_000001_customers.php', '<?php throw new RuntimeException("Migration file executed");');
    file_put_contents($application.'/2020_01_01_000001_invoices.php', '<?php throw new RuntimeException("Migration file executed");');
    file_put_contents($package.'/2030_01_01_000002_excluded.php', '<?php');
    resolve(MigrationPlan::class)->register('application', [$application], ['customers']);
    resolve(MigrationPlan::class)->register('customers', [$package], excludes: [$package.'/2030_01_01_000002_excluded.php']);
    $this->withoutMockingConsoleOutput();

    expect(Artisan::call('migration-plan:show', ['--json' => true]))->toBe(0);

    expect(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR))->toBe([
        'version' => 1,
        'groups' => [
            ['owner' => 'customers', 'requires' => [], 'paths' => [$package], 'excludes' => [$package.'/2030_01_01_000002_excluded.php']],
            ['owner' => 'application', 'requires' => ['customers'], 'paths' => [$application], 'excludes' => []],
        ],
        'paths' => [$application, $package],
        'migrations' => [
            ['name' => '2030_01_01_000001_customers', 'owner' => 'customers', 'path' => $package.'/2030_01_01_000001_customers.php'],
            ['name' => '2020_01_01_000001_invoices', 'owner' => 'application', 'path' => $application.'/2020_01_01_000001_invoices.php'],
        ],
    ]);
});

it('shows human readable execution order and empty owners', function (): void {
    $application = $this->directory.'/database/migrations';
    $package = $this->directory.'/package';
    file_put_contents($package.'/2030_01_01_000001_customers.php', '<?php');
    resolve(MigrationPlan::class)->register('application', [$application], ['customers']);
    resolve(MigrationPlan::class)->register('customers', [$package]);

    $this->artisan('migration-plan:show')
        ->expectsTable(['Owner', 'Requires', 'Declared paths'], [
            ['customers', '', $package], ['application', 'customers', $application],
        ])
        ->expectsTable(['Order', 'Owner', 'Migration', 'Path'], [
            [1, 'customers', '2030_01_01_000001_customers', $package.'/2030_01_01_000001_customers.php'],
        ])
        ->assertSuccessful();
});

it('returns a failing exit code for invalid plans including JSON inspection', function (string $command, array $options): void {
    resolve(MigrationPlan::class)->register('application', [$this->directory.'/database/migrations'], ['missing']);
    $output = new BufferedOutput;

    // The runtime kernel renders failures; Testbench's kernel rethrows them.
    $status = resolve(Kernel::class)->handle(new ArrayInput(['command' => $command, ...$options]), $output);

    expect($status)->toBe(1);
    expect($output->fetch())->toContain('Missing migration prerequisite [missing]');
})->with([
    'check' => ['migration-plan:check', []],
    'JSON' => ['migration-plan:show', ['--json' => true]],
]);

it('rejects an unowned application directory during inspection', function (string $command): void {
    resolve(MigrationPlan::class)->register('customers', [$this->directory.'/package']);

    expect(fn () => $this->artisan($command)->run())->toThrow(InvalidMigrationPlan::class, 'Unassigned migration path');
})->with(['migration-plan:check', 'migration-plan:show']);

it('preserves console formatting characters in JSON and exports empty lists', function (): void {
    resolve(MigrationPlan::class)->register('application/<info>tenant</info>', [$this->directory.'/database/migrations']);
    $this->withoutMockingConsoleOutput();

    expect(Artisan::call('migration-plan:show', ['--json' => true]))->toBe(0);

    expect(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR))->toBe([
        'version' => 1,
        'groups' => [[
            'owner' => 'application/<info>tenant</info>', 'requires' => [],
            'paths' => [$this->directory.'/database/migrations'], 'excludes' => [],
        ]],
        'paths' => [$this->directory.'/database/migrations'],
        'migrations' => [],
    ]);
});
