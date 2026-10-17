<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan\Tests;

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Wtroiano\MigrationPlan\MigrationPlan;
use Wtroiano\MigrationPlan\OrderedMigrationRepository;
use Wtroiano\MigrationPlan\OrderedMigrator;

abstract class MigrationPlanTestCase extends TestCase
{
    protected string $directory;

    protected MigrationPlan $plan;

    protected OrderedMigrationRepository $repository;

    protected OrderedMigrator $migrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/helm-migrations-'.bin2hex(random_bytes(8));
        mkdir($this->directory);
        mkdir($this->directory.'/foundation');
        mkdir($this->directory.'/app');
        $container = new Container;
        $capsule = new Manager($container);
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]);

        $events = new Dispatcher($container);
        $container->instance('db', $capsule->getDatabaseManager());
        $container->bind('db.schema', fn () => $capsule->getConnection()->getSchemaBuilder());
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($container);
        $this->plan = new MigrationPlan;
        $this->plan->register('example/invoices', [$this->directory.'/app'], ['example/customers']);
        $this->plan->register('example/customers', [$this->directory.'/foundation']);

        $this->repository = new OrderedMigrationRepository($capsule->getDatabaseManager(), 'migrations');
        $this->repository->createRepository();

        $this->migrator = new OrderedMigrator($this->repository, $capsule->getDatabaseManager(), new Filesystem, $events, $this->plan);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->directory);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        parent::tearDown();
    }

    protected function migration(string $group, string $name, string $up, ?string $down = null): void
    {
        $rollback = $down === null ? '' : "public function down(): void { {$down} }";
        file_put_contents($this->directory.'/'.$group.'/'.$name.'.php', "<?php\nuse Illuminate\\Database\\Migrations\\Migration;\nuse Illuminate\\Database\\Schema\\Blueprint;\nuse Illuminate\\Support\\Facades\\Schema;\nreturn new class extends Migration { public function up(): void { {$up} } {$rollback} };\n");
    }
}

uses(MigrationPlanTestCase::class);

it('it runs dependencies before older dependent migrations and rolls back in reverse', function (): void {
    $this->migration('foundation', '2030_01_01_000001_customers', "Schema::create('customers', fn (Blueprint \$table) => \$table->id());", "if (Schema::hasTable('invoices')) { throw new RuntimeException('Dependent schema remains'); } Schema::drop('customers');");
    $this->migration('app', '2020_01_01_000001_invoices', "if (! Schema::hasTable('customers')) { throw new RuntimeException('Missing prerequisite'); } Schema::create('invoices', fn (Blueprint \$table) => \$table->id());", "Schema::drop('invoices');");

    $this->migrator->run();

    $this->assertSame(['2030_01_01_000001_customers', '2020_01_01_000001_invoices'], $this->repository->getRan());
    $this->migrator->rollback();
    $this->assertSame([], $this->repository->getRan());
    $this->assertFalse(Schema::hasTable('customers'));
    $this->assertFalse(Schema::hasTable('invoices'));
});

it('upgrades finish new prerequisites before dependent changes', function (): void {
    $this->migration('foundation', '2020_01_01_000001_customers', "Schema::create('customers', fn (Blueprint \$table) => \$table->id());");
    $this->migrator->run();
    $this->migration('foundation', '2040_01_01_000001_customer_codes', "Schema::table('customers', fn (Blueprint \$table) => \$table->string('code')->nullable());");
    $this->migration('app', '2025_01_01_000001_invoices', "if (! Schema::hasColumn('customers', 'code')) { throw new RuntimeException('Missing dependency schema'); } Schema::create('invoices', fn (Blueprint \$table) => \$table->id());");

    $this->migrator->run();

    $this->assertSame(['2020_01_01_000001_customers', '2040_01_01_000001_customer_codes', '2025_01_01_000001_invoices'], $this->repository->getRan());
});

it('failure stops dependents and retry skips completed migrations', function (): void {
    $sentinel = $this->directory.'/fail';
    touch($sentinel);
    $this->migration('foundation', '2020_01_01_000001_customers', "Schema::create('customers', fn (Blueprint \$table) => \$table->id());");
    $this->migration('foundation', '2020_01_01_000002_fail', 'if (file_exists('.var_export($sentinel, true).")) { throw new RuntimeException('Fixture failure'); }");
    $this->migration('app', '2010_01_01_000001_invoices', "Schema::create('invoices', fn (Blueprint \$table) => \$table->id());");

    try {
        $this->migrator->run();
        $this->fail('The failing prerequisite should stop migration.');
    } catch (RuntimeException $runtimeException) {
        $this->assertSame('Fixture failure', $runtimeException->getMessage());
    }

    $this->assertSame(['2020_01_01_000001_customers'], $this->repository->getRan());
    $this->assertFalse(Schema::hasTable('invoices'));
    unlink($sentinel);
    $this->migrator->run();
    $this->assertCount(3, $this->repository->getRan());
    $this->assertTrue(Schema::hasTable('invoices'));
});

it('it refuses to roll back a prerequisite batch while dependent schema remains', function (): void {
    $this->migration('foundation', '2030_01_01_000001_customers', "Schema::create('customers', fn (Blueprint \$table) => \$table->id());", "Schema::drop('customers');");
    $this->migrator->run();
    $this->migration('app', '2020_01_01_000001_invoices', "Schema::create('invoices', fn (Blueprint \$table) => \$table->id());", "Schema::drop('invoices');");
    $this->migrator->run();

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('while dependent migration');
    $this->migrator->rollback([], ['batch' => 1]);
});

it('step rollback uses execution order instead of filename order', function (): void {
    $this->migration('foundation', '2030_01_01_000001_customers', "Schema::create('customers', fn (Blueprint \$table) => \$table->id());", "Schema::drop('customers');");
    $this->migration('app', '2020_01_01_000001_invoices', "Schema::create('invoices', fn (Blueprint \$table) => \$table->id());", "Schema::drop('invoices');");
    $this->migrator->run();

    $this->migrator->rollback([], ['step' => 1]);

    $this->assertTrue(Schema::hasTable('customers'));
    $this->assertFalse(Schema::hasTable('invoices'));
});

it('duplicate names are rejected before schema writes', function (): void {
    foreach (['foundation', 'app'] as $group) {
        $this->migration($group, '2030_01_01_000001_duplicate', "Schema::create('unexpected', fn (Blueprint \$table) => \$table->id());");
    }

    try {
        $this->migrator->run();
        $this->fail('Duplicate migration names must fail.');
    } catch (RuntimeException $runtimeException) {
        $this->assertStringContainsString('Duplicate migration', $runtimeException->getMessage());
    }

    $this->assertFalse(Schema::hasTable('unexpected'));
    $this->assertSame([], $this->repository->getRan());
});

it('missing prerequisites and cycles are rejected', function (): void {
    $this->plan->register('example/orphan', [], ['example/missing']);
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Missing migration prerequisite [example/missing]');
    $this->plan->files();
});

it('cycles are rejected', function (): void {
    $plan = new MigrationPlan;
    $plan->register('example/first', [], ['example/second']);
    $plan->register('example/second', [], ['example/first']);
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Migration dependency cycle');
    $plan->files();
});

it('unassigned paths are rejected', function (): void {
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Unassigned migration path');
    $this->migrator->getMigrationFiles([$this->directory]);
});

it('refuses an irreversible batch before rolling back any of its schema', function (): void {
    $this->migration('foundation', '2030_01_01_000001_customers', "Schema::create('customers', fn (Blueprint \$table) => \$table->id());");
    $this->migration('app', '2020_01_01_000001_invoices', "Schema::create('invoices', fn (Blueprint \$table) => \$table->id());", "Schema::drop('invoices');");
    $this->migrator->run();

    try {
        $this->migrator->rollback();
        $this->fail('Irreversible migration should reject the whole rollback before writes.');
    } catch (RuntimeException $runtimeException) {
        expect($runtimeException->getMessage())->toContain('is irreversible');
    }

    expect(Schema::hasTable('invoices'))->toBeTrue()
        ->and($this->repository->getRan())->toHaveCount(2);
});
