<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Wtroiano\MigrationPlan\MigrationPlan;
use Wtroiano\MigrationPlan\Tests\Support\MigrationPlanTestCase;

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
