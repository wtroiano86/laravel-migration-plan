<?php

declare(strict_types=1);

use Wtroiano\MigrationPlan\Actions\OrderMigrationGroups;
use Wtroiano\MigrationPlan\Data\MigrationGroup;
use Wtroiano\MigrationPlan\Exceptions\InvalidMigrationPlan;

it('orders shared prerequisites once and breaks ties alphabetically regardless of registration order', function (bool $reverse): void {
    $groups = [
        new MigrationGroup('z', [], ['c', 'b']),
        new MigrationGroup('c', [], ['a']),
        new MigrationGroup('b', [], ['a']),
        new MigrationGroup('a', []),
    ];

    $ordered = (new OrderMigrationGroups)->handle($reverse ? array_reverse($groups) : $groups);

    expect(array_map(fn (MigrationGroup $group): string => $group->owner, $ordered))->toBe(['a', 'b', 'c', 'z']);
})->with([false, true]);

it('reports the dependent and missing prerequisite without accessing the filesystem', function (): void {
    $groups = [new MigrationGroup('invoices', ['/does/not/exist'], ['customers'])];

    try {
        (new OrderMigrationGroups)->handle($groups);
        $this->fail('A missing prerequisite must reject the graph.');
    } catch (InvalidMigrationPlan $invalidMigrationPlan) {
        expect($invalidMigrationPlan->context())->toBe(['owner' => 'invoices', 'prerequisite' => 'customers']);
    }
});

it('reports the complete cycle including self dependencies', function (array $groups, string $cycle): void {
    expect(fn (): array => (new OrderMigrationGroups)->handle($groups))
        ->toThrow(InvalidMigrationPlan::class, 'Migration dependency cycle: '.$cycle);
})->with([
    'self' => [[new MigrationGroup('a', [], ['a'])], 'a -> a'],
    'transitive' => [[new MigrationGroup('a', [], ['b']), new MigrationGroup('b', [], ['c']), new MigrationGroup('c', [], ['a'])], 'a -> b -> c -> a'],
]);
