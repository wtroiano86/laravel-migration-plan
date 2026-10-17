<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Wtroiano\MigrationPlan\MigrationPlan;
use Wtroiano\MigrationPlan\OrderedMigrator;
use Wtroiano\MigrationPlan\PackageServiceProvider;

it('exposes one plan while preserving the Laravel migrator until activation', function (): void {
    expect(app()->bound(MigrationPlan::class))->toBeTrue();
    expect(app(MigrationPlan::class))->toBe(app(MigrationPlan::class));
    expect(app('migrator'))->toBeInstanceOf(Migrator::class)->not->toBeInstanceOf(OrderedMigrator::class);
});

it('publishes the activation config to the host application', function (): void {
    $directory = sys_get_temp_dir().'/migration-plan-config-'.bin2hex(random_bytes(8));
    app()->useConfigPath($directory);
    app()->register(PackageServiceProvider::class, force: true);

    try {
        $this->artisan('vendor:publish', ['--tag' => 'migration-plan-config'])->assertSuccessful();
        expect($directory.'/migration-plan.php')->toBeFile();
        expect(require $directory.'/migration-plan.php')->toBe(['enabled' => false]);
    } finally {
        (new Filesystem)->deleteDirectory($directory);
    }
});

it('uses configured activation to run and roll back owned migrations through Artisan', function (): void {
    $directory = sys_get_temp_dir().'/migration-plan-artisan-'.bin2hex(random_bytes(8));
    mkdir($directory);
    $applicationMigrations = app()->databasePath('migrations');
    $createdApplicationDirectory = ! is_dir($applicationMigrations);
    if ($createdApplicationDirectory) {
        mkdir($applicationMigrations, recursive: true);
    }

    file_put_contents($directory.'/2026_01_01_000001_create_records.php', <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_records', fn (Blueprint $table) => $table->id());
    }

    public function down(): void
    {
        Schema::drop('package_records');
    }
};
PHP);

    Config::set('migration-plan.enabled', true);
    app()->register(PackageServiceProvider::class, force: true);
    app(MigrationPlan::class)->register('example/package', [$directory]);
    app(MigrationPlan::class)->register('example/application', [$applicationMigrations], ['example/package']);

    try {
        expect(app('migrator'))->toBeInstanceOf(OrderedMigrator::class);
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        expect(Schema::hasTable('package_records'))->toBeTrue();
        $this->assertDatabaseHas('migrations', ['migration' => '2026_01_01_000001_create_records']);
        $this->artisan('migrate:rollback', ['--force' => true])->assertSuccessful();
        expect(Schema::hasTable('package_records'))->toBeFalse();
        $this->assertDatabaseCount('migrations', 0);
    } finally {
        (new Filesystem)->deleteDirectory($directory);
        if ($createdApplicationDirectory) {
            rmdir($applicationMigrations);
        }
    }
});
