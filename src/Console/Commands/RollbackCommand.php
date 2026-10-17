<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan\Console\Commands;

use Illuminate\Database\Console\Migrations\RollbackCommand as LaravelCommand;
use Wtroiano\MigrationPlan\Concerns\ValidatesMigrationPlan;

final class RollbackCommand extends LaravelCommand
{
    use ValidatesMigrationPlan;
}
