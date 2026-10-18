<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan\Actions;

use Wtroiano\MigrationPlan\Data\MigrationGroup;
use Wtroiano\MigrationPlan\Exceptions\InvalidMigrationPlan;

final readonly class OrderMigrationGroups
{
    /**
     * @param  list<MigrationGroup>  $groups
     * @return list<MigrationGroup>
     */
    public function handle(array $groups): array
    {
        $indexed = [];
        foreach ($groups as $group) {
            $indexed[$group->owner] = $group;
        }

        ksort($indexed, SORT_STRING);
        $ordered = [];
        $visited = [];
        $visiting = [];
        $visit = function (string $owner) use (&$visit, &$ordered, &$visited, &$visiting, $indexed): void {
            if (isset($visited[$owner])) {
                return;
            }

            if (! isset($indexed[$owner])) {
                $dependent = (string) array_key_last($visiting);
                throw new InvalidMigrationPlan("Missing migration prerequisite [{$owner}] required by [{$dependent}].", ['owner' => $dependent, 'prerequisite' => $owner]);
            }

            if (isset($visiting[$owner])) {
                $cycle = implode(' -> ', [...array_keys($visiting), $owner]);
                throw new InvalidMigrationPlan('Migration dependency cycle: '.$cycle, ['cycle' => $cycle]);
            }

            $visiting[$owner] = true;
            $requires = $indexed[$owner]->requires;
            sort($requires, SORT_STRING);
            foreach ($requires as $requirement) {
                $visit($requirement);
            }

            unset($visiting[$owner]);
            $visited[$owner] = true;
            $ordered[] = $indexed[$owner];
        };

        foreach ($indexed as $group) {
            $visit($group->owner);
        }

        return $ordered;
    }
}
