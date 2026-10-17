<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan;

use RuntimeException;

class MigrationPlan
{
    /** @var array<string, array{paths: list<string>, requires: list<string>, excludes: list<string>}> */
    private array $groups = [];

    /**
     * @param  list<string>  $paths
     * @param  list<string>  $requires
     * @param  list<string>  $excludes
     */
    public function register(string $owner, array $paths, array $requires = [], array $excludes = []): void
    {
        throw_if(isset($this->groups[$owner]), RuntimeException::class, "Duplicate migration owner [{$owner}].");

        $this->groups[$owner] = ['paths' => $paths, 'requires' => $requires, 'excludes' => $excludes];
    }

    /** @return list<string> */
    public function registeredOwners(): array
    {
        return array_keys($this->groups);
    }

    /** @return list<string> */
    public function owners(): array
    {
        $ordered = [];
        $visiting = [];
        $owners = array_keys($this->groups);
        sort($owners, SORT_STRING);
        $visit = function (string $owner) use (&$visit, &$ordered, &$visiting): void {
            if (in_array($owner, $ordered, true)) {
                return;
            }

            if (! isset($this->groups[$owner])) {
                $dependent = array_key_last($visiting) ?? 'migration plan';
                throw new RuntimeException("Missing migration prerequisite [{$owner}] required by [{$dependent}].");
            }

            if (isset($visiting[$owner])) {
                throw new RuntimeException('Migration dependency cycle: '.implode(' -> ', [...array_keys($visiting), $owner]));
            }

            $visiting[$owner] = true;
            $requires = $this->groups[$owner]['requires'];
            sort($requires, SORT_STRING);
            foreach ($requires as $requirement) {
                $visit($requirement);
            }

            unset($visiting[$owner]);
            $ordered[] = $owner;
        };

        foreach ($owners as $owner) {
            $visit($owner);
        }

        return $ordered;
    }

    /** @return array<string, array{path: string, owner: string}> */
    public function files(): array
    {
        $files = [];
        foreach ($this->owners() as $owner) {
            $group = $this->groups[$owner];
            $excluded = array_map($this->canonical(...), $group['excludes']);
            $selected = [];
            foreach ($group['paths'] as $path) {
                $canonical = $this->canonical($path);
                foreach (is_dir($canonical) ? (glob($canonical.'/*_*.php') ?: []) : [$canonical] as $file) {
                    $file = $this->canonical($file);
                    if (in_array($file, $excluded, true)) {
                        continue;
                    }

                    $name = pathinfo($file, PATHINFO_FILENAME);
                    if (isset($selected[$name]) || isset($files[$name])) {
                        $previous = $files[$name]['owner'] ?? $owner;
                        throw new RuntimeException("Duplicate migration [{$name}] owned by [{$previous}] and [{$owner}].");
                    }

                    $selected[$name] = ['path' => $file, 'owner' => $owner];
                }
            }

            ksort($selected, SORT_STRING);
            $files += $selected;
        }

        return $files;
    }

    /** @return list<string> */
    public function paths(): array
    {
        $paths = [];
        foreach ($this->groups as $group) {
            foreach ($group['paths'] as $path) {
                $paths[] = $this->canonical($path);
            }
        }

        return array_values(array_unique($paths));
    }

    /** @param list<string> $paths */
    public function assertOwnedPaths(array $paths): void
    {
        $owned = $this->paths();
        foreach ($paths as $path) {
            throw_unless(in_array($this->canonical($path), $owned, true), RuntimeException::class, "Unassigned migration path [{$path}]. Register its package and schema prerequisites in the platform migration plan.");
        }
    }

    /**
     * @param  list<string>  $selected
     * @param  list<string>  $ran
     */
    public function assertSafeRollback(array $selected, array $ran): void
    {
        $files = $this->files();
        foreach ($ran as $name) {
            throw_unless(isset($files[$name]), RuntimeException::class, "Cannot safely roll back: installed migration [{$name}] has no available owner. Restore its package first.");
        }

        $remaining = array_diff($ran, $selected);
        foreach ($selected as $name) {
            $owner = $files[$name]['owner'];
            foreach ($remaining as $retained) {
                $dependent = $files[$retained]['owner'];
                throw_if($dependent !== $owner && $this->requires($dependent, $owner), RuntimeException::class, "Cannot roll back [{$name}] from [{$owner}] while dependent migration [{$retained}] from [{$dependent}] remains.");

                throw_if($dependent === $owner && array_search($retained, $ran, true) > array_search($name, $ran, true), RuntimeException::class, "Cannot roll back [{$name}] while a later migration [{$retained}] in [{$owner}] remains.");
            }
        }
    }

    private function requires(string $owner, string $prerequisite): bool
    {
        foreach ($this->groups[$owner]['requires'] as $required) {
            if ($required === $prerequisite || $this->requires($required, $prerequisite)) {
                return true;
            }
        }

        return false;
    }

    private function canonical(string $path): string
    {
        $real = realpath($path);
        throw_if($real === false, RuntimeException::class, "Missing declared migration path [{$path}].");

        return $real;
    }
}
