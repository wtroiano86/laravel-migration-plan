<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Wtroiano\MigrationPlan\Concerns\ValidatesMigrationPlan;
use Wtroiano\MigrationPlan\Services\MigrationPlan;

#[Description('Validate migration ownership and dependencies without running migrations')]
#[Signature('migration-plan:check')]
final class CheckCommand extends Command
{
    use ValidatesMigrationPlan;

    public function handle(MigrationPlan $plan): int
    {
        $resolved = $plan->resolve();
        $this->info('Migration plan is valid. Owners: '.count($resolved->groups).'. Migrations: '.count($resolved->files).'.');

        return self::SUCCESS;
    }
}
