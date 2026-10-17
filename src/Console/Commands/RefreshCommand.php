<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan\Console\Commands;

use Illuminate\Database\Console\Migrations\RefreshCommand as LaravelCommand;
use Wtroiano\MigrationPlan\Concerns\ValidatesMigrationPlan;

final class RefreshCommand extends LaravelCommand
{
    use ValidatesMigrationPlan;
}
