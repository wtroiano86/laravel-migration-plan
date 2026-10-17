<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan\Commands;

use Illuminate\Database\Console\Migrations\RollbackCommand as LaravelCommand;
use Wtroiano\MigrationPlan\ValidatesMigrationPlan;

final class RollbackCommand extends LaravelCommand
{
    use ValidatesMigrationPlan;
}
