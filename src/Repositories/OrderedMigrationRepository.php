<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan\Repositories;

use Illuminate\Database\Migrations\DatabaseMigrationRepository;

class OrderedMigrationRepository extends DatabaseMigrationRepository
{
    public function getRan(): array
    {
        /** @var list<string> $migrations */
        $migrations = $this->table()->orderBy('id')->pluck('migration')->all();

        return $migrations;
    }

    public function getMigrations(mixed $steps): array
    {
        /** @var list<object{id: int, migration: string, batch: int}> $migrations */
        $migrations = $this->table()->where('batch', '>=', 1)->orderByDesc('id')->limit($steps)->get()->all();

        return $migrations;
    }

    public function getMigrationsByBatch(mixed $batch): array
    {
        /** @var list<object{id: int, migration: string, batch: int}> $migrations */
        $migrations = $this->table()->where('batch', $batch)->orderByDesc('id')->get()->all();

        return $migrations;
    }

    public function getLast(): array
    {
        return $this->getMigrationsByBatch($this->getLastBatchNumber());
    }
}
