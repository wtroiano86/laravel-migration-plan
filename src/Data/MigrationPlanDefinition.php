<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan\Data;

final readonly class MigrationPlanDefinition
{
    /**
     * @param  list<MigrationGroup>  $groups
     * @param  list<string>  $paths
     */
    public function __construct(public array $groups, public array $paths) {}
}
