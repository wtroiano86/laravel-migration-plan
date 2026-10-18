<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Wtroiano\MigrationPlan\Data\MigrationFile;
use Wtroiano\MigrationPlan\Data\ResolvedMigrationPlan;
use Wtroiano\MigrationPlan\Exceptions\InvalidMigrationPlan;
use Wtroiano\MigrationPlan\Exceptions\UnsafeRollback;
use Wtroiano\MigrationPlan\Services\MigrationPlan;

beforeEach(function (): void {
    $this->directory = sys_get_temp_dir().'/migration-plan-resolution-'.bin2hex(random_bytes(8));
    mkdir($this->directory);
});

afterEach(function (): void {
    (new Filesystem)->deleteDirectory($this->directory);
});

it('preserves the public array API while exposing typed resolved files', function (): void {
    $file = $this->directory.'/2026_01_01_000001_records.php';
    touch($file);
    $plan = new MigrationPlan;
    $plan->register('example/records', [$this->directory]);

    expect($plan->files())->toBe(['2026_01_01_000001_records' => ['path' => $file, 'owner' => 'example/records']]);
    expect($plan->resolve()->files['2026_01_01_000001_records'])->toEqual(new MigrationFile('2026_01_01_000001_records', $file, 'example/records'));
    expect($plan->paths())->toBe([$this->directory]);
});

it('shares one snapshot with nested operations and discovers added files on the next operation', function (): void {
    $first = $this->directory.'/2026_01_01_000001_first.php';
    touch($first);
    $plan = new MigrationPlan;
    $plan->register('example/records', [$this->directory]);

    $snapshot = $plan->withinOperation(function () use ($plan): ResolvedMigrationPlan {
        $snapshot = $plan->resolve();
        touch($this->directory.'/2026_01_01_000002_second.php');
        expect($plan->withinOperation(fn (): ResolvedMigrationPlan => $plan->resolve()))->toBe($snapshot);
        expect(array_keys($plan->files()))->toBe(['2026_01_01_000001_first']);
        expect($plan->owners())->toBe(['example/records']);

        return $snapshot;
    });

    expect($plan->resolve())->not->toBe($snapshot);
    expect(array_keys($plan->files()))->toBe(['2026_01_01_000001_first', '2026_01_01_000002_second']);
});

it('releases the snapshot after failure and permits subsequent registrations', function (): void {
    $plan = new MigrationPlan;
    $plan->register('initial', [$this->directory]);

    expect(fn () => $plan->withinOperation(fn () => $plan->register('later', [])))
        ->toThrow(InvalidMigrationPlan::class, 'during an active migration operation');

    $plan->register('later', [], ['initial']);
    expect($plan->owners())->toBe(['initial', 'later']);
});

it('retains path registration order and removes canonical duplicates', function (): void {
    mkdir($this->directory.'/a');
    mkdir($this->directory.'/z');
    $plan = new MigrationPlan;
    $plan->register('z', [$this->directory.'/z', $this->directory.'/z/.']);
    $plan->register('a', [$this->directory.'/a']);

    expect($plan->resolve()->paths)->toBe([$this->directory.'/z', $this->directory.'/a']);
    expect($plan->resolve()->owners())->toBe(['a', 'z']);
});

it('rejects duplicate owners with structured context', function (): void {
    $plan = new MigrationPlan;
    $plan->register('example/records', []);

    try {
        $plan->register('example/records', []);
        $this->fail('Duplicate owners must be rejected.');
    } catch (InvalidMigrationPlan $invalidMigrationPlan) {
        expect($invalidMigrationPlan)->toBeInstanceOf(RuntimeException::class);
        expect($invalidMigrationPlan->context())->toBe(['owner' => 'example/records']);
    }
});

it('rejects missing declared paths and exclusions', function (bool $exclude): void {
    $plan = new MigrationPlan;
    $missing = $this->directory.'/missing';
    $plan->register('example/records', $exclude ? [$this->directory] : [$missing], excludes: $exclude ? [$missing] : []);

    expect(fn (): ResolvedMigrationPlan => $plan->resolve())->toThrow(InvalidMigrationPlan::class, "Missing declared migration path [{$missing}]");
})->with([false, true]);

it('rejects duplicate migration declarations within one owner', function (): void {
    $file = $this->directory.'/2026_01_01_000001_records.php';
    touch($file);
    $plan = new MigrationPlan;
    $plan->register('example/records', [$this->directory, $file]);

    expect(fn (): array => $plan->files())->toThrow(InvalidMigrationPlan::class, 'Duplicate migration');
});

it('retains the public rollback safety entry point', function (): void {
    $plan = new MigrationPlan;

    expect(fn () => $plan->assertSafeRollback([], ['missing']))
        ->toThrow(UnsafeRollback::class, 'installed migration [missing]');
});
