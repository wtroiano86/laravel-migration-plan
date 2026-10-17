<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan\Commands;

use Illuminate\Database\Console\Migrations\RefreshCommand as LaravelCommand;
use Wtroiano\MigrationPlan\ValidatesMigrationPlan;

final class RefreshCommand extends LaravelCommand
{
    use ValidatesMigrationPlan;
}
