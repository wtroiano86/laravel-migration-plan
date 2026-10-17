<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;
use ReflectionMethod;
use RuntimeException;

class OrderedMigrator extends Migrator
{
    public function __construct(MigrationRepositoryInterface $repository, ConnectionResolverInterface $resolver, Filesystem $files, Dispatcher $dispatcher, private readonly MigrationPlan $plan)
    {
        parent::__construct($repository, $resolver, $files, $dispatcher);
    }

    /** @param array<string>|string $paths
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

    /** @param array<object{id: int, migration: string, batch: int}> $migrations
     * @param  array<string>|string  $paths
     * @param  array<string, mixed>  $options
     * @return array<string>
     */
    protected function rollbackMigrations(array $migrations, mixed $paths, array $options): array
    {
        $ran = array_values($this->repository->getRan());
        $this->plan->assertSafeRollback(array_values(array_map(fn (object $migration): string => $migration->migration, $migrations)), $ran);
        $owners = array_flip($this->plan->owners());
        $files = $this->plan->files();
        foreach ($migrations as $migration) {
            $instance = $this->resolvePath($files[$migration->migration]['path']);
            if (! method_exists($instance, 'down') || (string) new ReflectionMethod($instance, 'down')->getReturnType() === 'never') {
                throw new RuntimeException("Migration [{$migration->migration}] is irreversible. Apply a forward fix instead of rolling back this batch.");
            }
        }

        $execution = array_flip($ran);
        usort($migrations, fn (object $left, object $right): int => ($owners[$files[$right->migration]['owner']] <=> $owners[$files[$left->migration]['owner']])
            ?: ($execution[$right->migration] <=> $execution[$left->migration]));

        return parent::rollbackMigrations($migrations, $paths, $options);
    }
}
