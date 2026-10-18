<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan\Data;

final readonly class RollbackPlan
{
    /**
     * @param  list<string>  $selected
     * @param  list<string>  $ran
     */
    public function __construct(
        public ResolvedMigrationPlan $plan,
        public array $selected,
        public array $ran,
    ) {}
}
