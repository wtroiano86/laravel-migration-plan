<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan\Services;

use Closure;
use Wtroiano\MigrationPlan\Actions\OrderMigrationGroups;
use Wtroiano\MigrationPlan\Actions\ValidateRollback;
use Wtroiano\MigrationPlan\Data\MigrationFile;
use Wtroiano\MigrationPlan\Data\MigrationGroup;
use Wtroiano\MigrationPlan\Data\ResolvedMigrationPlan;
use Wtroiano\MigrationPlan\Data\RollbackPlan;
use Wtroiano\MigrationPlan\Exceptions\InvalidMigrationPlan;
use Wtroiano\MigrationPlan\Pipelines\CompileMigrationPlan;

class MigrationPlan
{
    /** @var array<string, MigrationGroup> */
    private array $groups = [];

    private ?ResolvedMigrationPlan $activePlan = null;

    public function __construct(
        private readonly CompileMigrationPlan $compiler = new CompileMigrationPlan,
        private readonly MigrationPaths $migrationPaths = new MigrationPaths,
    ) {}

    /**
     * @param  list<string>  $paths
     * @param  list<string>  $requires
     * @param  list<string>  $excludes
     */
    public function register(string $owner, array $paths, array $requires = [], array $excludes = []): void
    {
        throw_if($this->activePlan instanceof ResolvedMigrationPlan, InvalidMigrationPlan::class, 'Cannot register migration owners during an active migration operation.', ['owner' => $owner]);

        throw_if(isset($this->groups[$owner]), InvalidMigrationPlan::class, "Duplicate migration owner [{$owner}].", ['owner' => $owner]);

        $this->groups[$owner] = new MigrationGroup($owner, $paths, $requires, $excludes);
    }

    /** @return list<string> */
    public function registeredOwners(): array
    {
        return array_values(array_map(fn (MigrationGroup $group): string => $group->owner, $this->groups));
    }

    /** @return list<string> */
    public function owners(): array
    {
        return $this->activePlan?->owners() ?? array_map(
            fn (MigrationGroup $group): string => $group->owner,
            (new OrderMigrationGroups)->handle(array_values($this->groups)),
        );
    }

    /** @return array<string, array{path: string, owner: string}> */
    public function files(): array
    {
        return array_map(fn (MigrationFile $file): array => ['path' => $file->path, 'owner' => $file->owner], $this->resolve()->files);
    }

    /** @return list<string> */
    public function paths(): array
    {
        return $this->activePlan->paths ?? $this->migrationPaths->forGroups(array_values($this->groups));
    }

    public function resolve(): ResolvedMigrationPlan
    {
        return $this->activePlan ?? $this->compiler->handle(array_values($this->groups));
    }

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $operation
     * @return TResult
     */
    public function withinOperation(Closure $operation): mixed
    {
        if ($this->activePlan instanceof ResolvedMigrationPlan) {
            return $operation();
        }

        $this->activePlan = $this->resolve();
        try {
            return $operation();
        } finally {
            $this->activePlan = null;
        }
    }

    /** @param list<string> $paths */
    public function assertOwnedPaths(array $paths): void
    {
        $this->migrationPaths->assertOwned($paths, $this->paths());
    }

    /**
     * @param  list<string>  $selected
     * @param  list<string>  $ran
     */
    public function assertSafeRollback(array $selected, array $ran): void
    {
        (new ValidateRollback)->handle(new RollbackPlan($this->resolve(), $selected, $ran));
    }
}
