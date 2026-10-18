<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan\Exceptions;

use RuntimeException;
use Wtroiano\MigrationPlan\Data\MigrationFile;

final class UnappliedPrerequisite extends RuntimeException
{
    public function __construct(private readonly MigrationFile $migration, private readonly MigrationFile $prerequisite)
    {
        parent::__construct("Cannot run [{$migration->name}] from [{$migration->owner}] while prerequisite migration [{$prerequisite->name}] from [{$prerequisite->owner}] has not run.");
    }

    /** @return array<string, string> */
    public function context(): array
    {
        return [
            'migration' => $this->migration->name,
            'owner' => $this->migration->owner,
            'prerequisite' => $this->prerequisite->name,
            'prerequisite_owner' => $this->prerequisite->owner,
        ];
    }
}
