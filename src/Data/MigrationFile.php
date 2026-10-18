<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan\Data;

final readonly class MigrationFile
{
    public function __construct(public string $name, public string $path, public string $owner) {}
}
