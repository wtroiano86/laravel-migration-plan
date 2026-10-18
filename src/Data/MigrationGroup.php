<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan\Data;

final readonly class MigrationGroup
{
    /**
     * @param  list<string>  $paths
     * @param  list<string>  $requires
     * @param  list<string>  $excludes
     */
    public function __construct(
        public string $owner,
        public array $paths,
        public array $requires = [],
        public array $excludes = [],
    ) {}
}
