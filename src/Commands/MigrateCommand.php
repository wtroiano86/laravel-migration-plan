<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan\Commands;

use Illuminate\Database\Console\Migrations\MigrateCommand as LaravelCommand;
use Wtroiano\MigrationPlan\ValidatesMigrationPlan;

final class MigrateCommand extends LaravelCommand
{
    use ValidatesMigrationPlan;
}
