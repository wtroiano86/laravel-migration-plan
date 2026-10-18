<?php

declare(strict_types=1);

use Wtroiano\MigrationPlan\Actions\ValidateRollback;
use Wtroiano\MigrationPlan\Data\MigrationFile;
use Wtroiano\MigrationPlan\Data\MigrationGroup;
use Wtroiano\MigrationPlan\Data\ResolvedMigrationPlan;
use Wtroiano\MigrationPlan\Data\RollbackPlan;
use Wtroiano\MigrationPlan\Exceptions\UnsafeRollback;

it('rejects transitive retained dependents even when an intermediate group has no migrations', function (): void {
    $plan = new ResolvedMigrationPlan([
        new MigrationGroup('customers', []),
        new MigrationGroup('billing', [], ['customers']),
        new MigrationGroup('invoices', [], ['billing']),
    ], [], [
        'customers' => new MigrationFile('customers', '/customers.php', 'customers'),
        'invoices' => new MigrationFile('invoices', '/invoices.php', 'invoices'),
    ]);

    try {
        (new ValidateRollback)->handle(new RollbackPlan($plan, ['customers'], ['customers', 'invoices']));
        $this->fail('A retained transitive dependent must prevent rollback.');
    } catch (UnsafeRollback $unsafeRollback) {
        expect($unsafeRollback->context())->toBe([
            'migration' => 'customers', 'owner' => 'customers', 'retained' => 'invoices', 'dependent' => 'invoices',
        ]);
    }
});

it('uses recorded execution order to protect later migrations in the same owner', function (): void {
    $plan = new ResolvedMigrationPlan([new MigrationGroup('application', [])], [], [
        '2030_first' => new MigrationFile('2030_first', '/first.php', 'application'),
        '2020_later' => new MigrationFile('2020_later', '/later.php', 'application'),
    ]);

    expect(fn () => (new ValidateRollback)->handle(new RollbackPlan($plan, ['2030_first'], ['2030_first', '2020_later'])))
        ->toThrow(UnsafeRollback::class, 'while a later migration [2020_later]');
});

it('allows a latest migration to roll back while unrelated owners and earlier migrations remain', function (): void {
    $plan = new ResolvedMigrationPlan([
        new MigrationGroup('application', []), new MigrationGroup('unrelated', []),
    ], [], [
        'first' => new MigrationFile('first', '/first.php', 'application'),
        'later' => new MigrationFile('later', '/later.php', 'application'),
        'unrelated' => new MigrationFile('unrelated', '/unrelated.php', 'unrelated'),
    ]);

    expect(fn () => (new ValidateRollback)->handle(new RollbackPlan($plan, ['later'], ['first', 'later', 'unrelated'])))
        ->not->toThrow(UnsafeRollback::class);
});

it('rejects unavailable history and selections that have never run', function (array $selected, array $ran, string $message): void {
    $plan = new ResolvedMigrationPlan([], [], []);

    expect(fn () => (new ValidateRollback)->handle(new RollbackPlan($plan, $selected, $ran)))
        ->toThrow(UnsafeRollback::class, $message);
})->with([
    'unavailable history' => [[], ['removed'], 'installed migration [removed] has no available owner'],
    'unknown selection' => [['unknown'], [], 'migration [unknown] because it has not run'],
]);
