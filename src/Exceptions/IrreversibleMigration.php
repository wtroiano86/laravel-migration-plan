<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan\Exceptions;

use RuntimeException;
use Wtroiano\MigrationPlan\Data\MigrationFile;

final class IrreversibleMigration extends RuntimeException
{
    public function __construct(private readonly MigrationFile $migration)
    {
        parent::__construct("Migration [{$migration->name}] is irreversible. Apply a forward fix instead of rolling back this batch.");
    }

    /** @return array<string, string> */
    public function context(): array
    {
        return ['migration' => $this->migration->name, 'owner' => $this->migration->owner, 'path' => $this->migration->path];
    }
}
