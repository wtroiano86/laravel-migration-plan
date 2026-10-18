<?php

declare(strict_types=1);

use Wtroiano\MigrationPlan\Console\Commands\CheckCommand;
use Wtroiano\MigrationPlan\Console\Commands\FreshCommand;
use Wtroiano\MigrationPlan\Console\Commands\MigrateCommand;
use Wtroiano\MigrationPlan\Console\Commands\RefreshCommand;
use Wtroiano\MigrationPlan\Console\Commands\ResetCommand;
use Wtroiano\MigrationPlan\Console\Commands\RollbackCommand;
use Wtroiano\MigrationPlan\Console\Commands\ShowCommand;
use Wtroiano\MigrationPlan\Console\Commands\StatusCommand;
use Wtroiano\MigrationPlan\Migrations\OrderedMigrator;
use Wtroiano\MigrationPlan\Repositories\OrderedMigrationRepository;
use Wtroiano\MigrationPlan\Services\MigrationPlan;

$extensible = [MigrationPlan::class, OrderedMigrator::class, OrderedMigrationRepository::class];
$commandHooks = [
    CheckCommand::class,
    ShowCommand::class,
    FreshCommand::class,
    MigrateCommand::class,
    RefreshCommand::class,
    ResetCommand::class,
    RollbackCommand::class,
    StatusCommand::class,
];

arch()->preset()->php();
arch()->preset()->strict()->ignoring([...$extensible, ...$commandHooks]);
arch()->preset()->laravel();
arch()->preset()->security();

arch('only compatibility extension points may be nonfinal')
    ->expect('Wtroiano\MigrationPlan')
    ->classes()->toBeFinal()->ignoring($extensible);

arch('compatibility extension points remain extensible')
    ->expect($extensible)
    ->not->toBeFinal();

arch('extension points and command hooks retain strict types and equality')
    ->expect([...$extensible, ...$commandHooks])
    ->toUseStrictTypes()->toUseStrictEquality()->not->toBeAbstract();

arch('only Laravel migration hooks may declare protected methods')
    ->expect($extensible)
    ->not->toHaveProtectedMethods()->ignoring(OrderedMigrator::class);

arch('migration data is immutable and independent of the framework')
    ->expect('Wtroiano\MigrationPlan\Data')
    ->toBeReadonly()
    ->not->toUse('Illuminate');

arch('domain actions do not depend on command or database execution')
    ->expect('Wtroiano\MigrationPlan\Actions')
    ->not->toUse([
        'Wtroiano\MigrationPlan\Console',
        'Wtroiano\MigrationPlan\Providers',
        'Wtroiano\MigrationPlan\Migrations',
        'Wtroiano\MigrationPlan\Repositories',
        'Illuminate\Database',
    ]);
