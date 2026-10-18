<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan\Actions;

use Wtroiano\MigrationPlan\Data\MigrationFile;
use Wtroiano\MigrationPlan\Data\MigrationGroup;
use Wtroiano\MigrationPlan\Exceptions\InvalidMigrationPlan;
use Wtroiano\MigrationPlan\Services\MigrationPaths;

final readonly class DiscoverMigrationFiles
{
    public function __construct(private MigrationPaths $paths = new MigrationPaths) {}

    /**
     * @param  list<MigrationGroup>  $groups
     * @return array<string, MigrationFile>
     */
    public function handle(array $groups): array
    {
        $files = [];
        foreach ($groups as $group) {
            $excluded = array_map($this->paths->canonical(...), $group->excludes);
            $selected = [];
            foreach ($group->paths as $path) {
                $canonical = $this->paths->canonical($path);
                foreach (is_dir($canonical) ? (glob($canonical.'/*_*.php') ?: []) : [$canonical] as $file) {
                    $file = $this->paths->canonical($file);
                    if (in_array($file, $excluded, true)) {
                        continue;
                    }

                    $name = pathinfo($file, PATHINFO_FILENAME);
                    if (isset($selected[$name]) || isset($files[$name])) {
                        $previous = $files[$name]->owner ?? $group->owner;
                        throw new InvalidMigrationPlan("Duplicate migration [{$name}] owned by [{$previous}] and [{$group->owner}].", [
                            'migration' => $name, 'owner' => $group->owner, 'previous_owner' => $previous, 'path' => $file,
                        ]);
                    }

                    $selected[$name] = new MigrationFile($name, $file, $group->owner);
                }
            }

            ksort($selected, SORT_STRING);
            $files += $selected;
        }

        return $files;
    }
}
