<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan\Console\Commands;

use Illuminate\Database\Console\Migrations\StatusCommand as LaravelCommand;
use Wtroiano\MigrationPlan\Concerns\ValidatesMigrationPlan;

final class StatusCommand extends LaravelCommand
{
    use ValidatesMigrationPlan;
}
