<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan\Console\Commands;

use Illuminate\Database\Console\Migrations\ResetCommand as LaravelCommand;
use Wtroiano\MigrationPlan\Concerns\ValidatesMigrationPlan;

final class ResetCommand extends LaravelCommand
{
    use ValidatesMigrationPlan;
}
