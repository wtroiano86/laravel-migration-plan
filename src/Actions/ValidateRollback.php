<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan\Actions;

use Wtroiano\MigrationPlan\Data\RollbackPlan;
use Wtroiano\MigrationPlan\Exceptions\UnsafeRollback;

final readonly class ValidateRollback
{
    public function handle(RollbackPlan $rollback): void
    {
        $files = $rollback->plan->files;
        foreach ($rollback->ran as $name) {
            throw_unless(isset($files[$name]), UnsafeRollback::class, "Cannot safely roll back: installed migration [{$name}] has no available owner. Restore its package first.", ['migration' => $name]);
        }

        $execution = array_flip($rollback->ran);
        foreach ($rollback->selected as $name) {
            throw_unless(isset($execution[$name]), UnsafeRollback::class, "Cannot roll back migration [{$name}] because it has not run.", ['migration' => $name]);
        }

        $dependencies = $rollback->plan->dependencies();

        $remaining = array_diff($rollback->ran, $rollback->selected);
        foreach ($rollback->selected as $name) {
            $owner = $files[$name]->owner;
            foreach ($remaining as $retained) {
                $dependent = $files[$retained]->owner;
                throw_if($dependent !== $owner && isset($dependencies[$dependent][$owner]), UnsafeRollback::class, "Cannot roll back [{$name}] from [{$owner}] while dependent migration [{$retained}] from [{$dependent}] remains.", [
                    'migration' => $name, 'owner' => $owner, 'retained' => $retained, 'dependent' => $dependent,
                ]);

                throw_if($dependent === $owner && $execution[$retained] > $execution[$name], UnsafeRollback::class, "Cannot roll back [{$name}] while a later migration [{$retained}] in [{$owner}] remains.", [
                    'migration' => $name, 'owner' => $owner, 'retained' => $retained,
                ]);
            }
        }
    }
}
