<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan\Pipelines;

use Closure;
use Illuminate\Pipeline\Pipeline;
use Wtroiano\MigrationPlan\Actions\DiscoverMigrationFiles;
use Wtroiano\MigrationPlan\Actions\OrderMigrationGroups;
use Wtroiano\MigrationPlan\Data\MigrationGroup;
use Wtroiano\MigrationPlan\Data\MigrationPlanDefinition;
use Wtroiano\MigrationPlan\Data\ResolvedMigrationPlan;
use Wtroiano\MigrationPlan\Services\MigrationPaths;

final readonly class CompileMigrationPlan
{
    public function __construct(
        private OrderMigrationGroups $order = new OrderMigrationGroups,
        private DiscoverMigrationFiles $discover = new DiscoverMigrationFiles,
        private MigrationPaths $paths = new MigrationPaths,
    ) {}

    /** @param list<MigrationGroup> $groups */
    public function handle(array $groups): ResolvedMigrationPlan
    {
        $paths = [];
        foreach ($groups as $group) {
            array_push($paths, ...$group->paths);
        }

        /** @var ResolvedMigrationPlan $resolved */
        $resolved = (new Pipeline)->send(new MigrationPlanDefinition($groups, $paths))
            ->through([$this->orderGroups(...), $this->discoverFiles(...)])
            ->thenReturn();

        return $resolved;
    }

    /** @param Closure(MigrationPlanDefinition): ResolvedMigrationPlan $next */
    private function orderGroups(MigrationPlanDefinition $definition, Closure $next): ResolvedMigrationPlan
    {
        return $next(new MigrationPlanDefinition($this->order->handle($definition->groups), $definition->paths));
    }

    /** @param Closure(ResolvedMigrationPlan): ResolvedMigrationPlan $next */
    private function discoverFiles(MigrationPlanDefinition $definition, Closure $next): ResolvedMigrationPlan
    {
        return $next(new ResolvedMigrationPlan(
            $definition->groups,
            array_values(array_unique(array_map($this->paths->canonical(...), $definition->paths))),
            $this->discover->handle($definition->groups),
        ));
    }
}
