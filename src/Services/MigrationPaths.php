<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan\Services;

use Wtroiano\MigrationPlan\Data\MigrationGroup;
use Wtroiano\MigrationPlan\Exceptions\InvalidMigrationPlan;

final readonly class MigrationPaths
{
    public function canonical(string $path): string
    {
        $real = realpath($path);
        throw_if($real === false, InvalidMigrationPlan::class, "Missing declared migration path [{$path}].", ['path' => $path]);

        return $real;
    }

    /**
     * @param  list<MigrationGroup>  $groups
     * @return list<string>
     */
    public function forGroups(array $groups): array
    {
        $paths = [];
        foreach ($groups as $group) {
            foreach ($group->paths as $path) {
                $paths[] = $this->canonical($path);
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @param  list<string>  $paths
     * @param  list<string>  $owned
     */
    public function assertOwned(array $paths, array $owned): void
    {
        foreach ($paths as $path) {
            throw_unless(in_array($this->canonical($path), $owned, true), InvalidMigrationPlan::class, "Unassigned migration path [{$path}]. Register its package and schema prerequisites in the platform migration plan.", ['path' => $path]);
        }
    }
}
