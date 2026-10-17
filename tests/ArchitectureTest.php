<?php

declare(strict_types=1);

use Wtroiano\MigrationPlan\Console\Commands\FreshCommand;
use Wtroiano\MigrationPlan\Console\Commands\MigrateCommand;
use Wtroiano\MigrationPlan\Console\Commands\RefreshCommand;
use Wtroiano\MigrationPlan\Console\Commands\ResetCommand;
use Wtroiano\MigrationPlan\Console\Commands\RollbackCommand;
use Wtroiano\MigrationPlan\Console\Commands\StatusCommand;
use Wtroiano\MigrationPlan\Migrations\OrderedMigrator;
use Wtroiano\MigrationPlan\Repositories\OrderedMigrationRepository;
use Wtroiano\MigrationPlan\Services\MigrationPlan;

$extensible = [MigrationPlan::class, OrderedMigrator::class, OrderedMigrationRepository::class];
$commandHooks = [
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

arch('extension points and command hooks retain strict types and equality')
    ->expect([...$extensible, ...$commandHooks])
    ->toUseStrictTypes()->toUseStrictEquality()->not->toBeAbstract();

arch('only Laravel migration hooks may declare protected methods')
    ->expect($extensible)
    ->not->toHaveProtectedMethods()->ignoring(OrderedMigrator::class);
