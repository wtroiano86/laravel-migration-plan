<?php

declare(strict_types=1);

use Wtroiano\MigrationPlan\Actions\ValidatePrerequisites;
use Wtroiano\MigrationPlan\Data\MigrationFile;
use Wtroiano\MigrationPlan\Data\MigrationGroup;
use Wtroiano\MigrationPlan\Data\ResolvedMigrationPlan;
use Wtroiano\MigrationPlan\Exceptions\UnappliedPrerequisite;

it('rejects unapplied transitive prerequisites through empty intermediate owners', function (): void {
    $plan = new ResolvedMigrationPlan([
        new MigrationGroup('customers', []),
        new MigrationGroup('billing', [], ['customers']),
        new MigrationGroup('invoices', [], ['billing']),
    ], [], [
        'customers' => new MigrationFile('customers', '/customers.php', 'customers'),
        'invoices' => new MigrationFile('invoices', '/invoices.php', 'invoices'),
    ]);

    expect(fn () => (new ValidatePrerequisites)->handle($plan, 'invoices', []))
        ->toThrow(UnappliedPrerequisite::class, 'prerequisite migration [customers]');
});

it('ignores unapplied unrelated migrations when all prerequisites have run', function (): void {
    $plan = new ResolvedMigrationPlan([
        new MigrationGroup('customers', []),
        new MigrationGroup('invoices', [], ['customers']),
        new MigrationGroup('unrelated', []),
    ], [], [
        'customers' => new MigrationFile('customers', '/customers.php', 'customers'),
        'invoices' => new MigrationFile('invoices', '/invoices.php', 'invoices'),
        'unrelated' => new MigrationFile('unrelated', '/unrelated.php', 'unrelated'),
    ]);

    expect(fn () => (new ValidatePrerequisites)->handle($plan, 'invoices', ['customers']))
        ->not->toThrow(UnappliedPrerequisite::class);
});
