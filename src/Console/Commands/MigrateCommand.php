<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan\Console\Commands;

use Illuminate\Database\Console\Migrations\MigrateCommand as LaravelCommand;
use Wtroiano\MigrationPlan\Concerns\ValidatesMigrationPlan;

final class MigrateCommand extends LaravelCommand
{
    use ValidatesMigrationPlan;
}
