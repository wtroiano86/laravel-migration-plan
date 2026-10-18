<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan\Migrations;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;
use Wtroiano\MigrationPlan\Actions\ValidatePrerequisites;
use Wtroiano\MigrationPlan\Data\RollbackPlan;
use Wtroiano\MigrationPlan\Pipelines\PrepareRollback;
use Wtroiano\MigrationPlan\Services\MigrationPlan;

class OrderedMigrator extends Migrator
{
    public function __construct(MigrationRepositoryInterface $repository, ConnectionResolverInterface $resolver, Filesystem $files, Dispatcher $dispatcher, private readonly MigrationPlan $plan)
    {
        parent::__construct($repository, $resolver, $files, $dispatcher);
    }

    /**
     * @param  array<string>|string  $paths
     * @param  array<string, mixed>  $options
     * @return array<string>
     */
    public function run(mixed $paths = [], array $options = []): array
    {
        return $this->plan->withinOperation(fn (): array => parent::run($paths, $options));
    }

    /**
     * @param  array<string>|string  $paths
     * @param  array<string, mixed>  $options
     * @return array<string>
     */
    public function rollback(mixed $paths = [], array $options = []): array
    {
        return $this->plan->withinOperation(fn (): array => parent::rollback($paths, $options));
    }

    /**
     * @param  array<string>|string  $paths
     * @param  bool  $pretend
     * @return array<string>
     */
    public function reset(mixed $paths = [], mixed $pretend = false): array
    {
        return $this->plan->withinOperation(function () use ($paths, $pretend): array {
            /** @var array<string> $files */
            $files = parent::reset($paths, $pretend);

            return $files;
        });
    }

    /**
     * @param  array<string>|string  $paths
     * @return array<string, string>
     */
    public function getMigrationFiles(mixed $paths): array
    {
        $this->plan->assertOwnedPaths(array_values((array) $paths));

        return array_map(fn (array $file): string => $file['path'], $this->plan->files());
    }

    public function paths(): array
    {
        $this->plan->assertOwnedPaths(array_values(parent::paths()));

        return $this->plan->paths();
    }

    /**
     * @param  string  $file
     * @param  int  $batch
     * @param  bool  $pretend
     */
    protected function runUp(mixed $file, mixed $batch, mixed $pretend): void
    {
        if (! $pretend) {
            (new ValidatePrerequisites)->handle($this->plan->resolve(), $this->getMigrationName($file), array_values($this->repository->getRan()));
        }

        parent::runUp($file, $batch, $pretend);
    }

    /**
     * @param  array<object{migration: string}>  $migrations
     * @param  array<string>|string  $paths
     * @param  array<string, mixed>  $options
     * @return array<string>
     */
    protected function rollbackMigrations(array $migrations, mixed $paths, array $options): array
    {
        $rollback = (new PrepareRollback)->handle(new RollbackPlan(
            $this->plan->resolve(),
            array_values(array_map(fn (object $migration): string => $migration->migration, $migrations)),
            array_values($this->repository->getRan()),
        ), $this->resolvePath(...));

        $records = [];
        foreach ($migrations as $migration) {
            $records[$migration->migration] = $migration;
        }

        return parent::rollbackMigrations(
            array_map(fn (string $name): object => $records[$name], $rollback->selected), $paths, $options,
        );
    }
}
