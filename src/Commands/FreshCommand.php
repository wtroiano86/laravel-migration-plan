<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan\Commands;

use Illuminate\Database\Console\Migrations\FreshCommand as LaravelCommand;
use Wtroiano\MigrationPlan\ValidatesMigrationPlan;

final class FreshCommand extends LaravelCommand
{
    use ValidatesMigrationPlan;
}
