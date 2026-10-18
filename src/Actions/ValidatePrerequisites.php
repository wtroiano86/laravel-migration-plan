<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan\Actions;

use Wtroiano\MigrationPlan\Data\ResolvedMigrationPlan;
use Wtroiano\MigrationPlan\Exceptions\UnappliedPrerequisite;

final readonly class ValidatePrerequisites
{
    /** @param list<string> $ran */
    public function handle(ResolvedMigrationPlan $plan, string $name, array $ran): void
    {
        $migration = $plan->files[$name];
        $required = $plan->dependencies()[$migration->owner];
        $applied = array_fill_keys($ran, true);

        foreach ($plan->files as $file) {
            throw_if(isset($required[$file->owner]) && ! isset($applied[$file->name]), UnappliedPrerequisite::class, $migration, $file);
        }
    }
}
