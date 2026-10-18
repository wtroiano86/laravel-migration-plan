<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan\Data;

final readonly class ResolvedMigrationPlan
{
    /**
     * @param  list<MigrationGroup>  $groups
     * @param  list<string>  $paths
     * @param  array<string, MigrationFile>  $files
     */
    public function __construct(public array $groups, public array $paths, public array $files) {}

    /** @return list<string> */
    public function owners(): array
    {
        return array_map(fn (MigrationGroup $group): string => $group->owner, $this->groups);
    }

    /** @return array<string, array<string, true>> */
    public function dependencies(): array
    {
        $dependencies = [];
        foreach ($this->groups as $group) {
            $dependencies[$group->owner] = [];
            foreach ($group->requires as $required) {
                $dependencies[$group->owner][$required] = true;
                $dependencies[$group->owner] += $dependencies[$required];
            }
        }

        return $dependencies;
    }
}
