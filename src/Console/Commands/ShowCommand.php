<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Wtroiano\MigrationPlan\Concerns\ValidatesMigrationPlan;
use Wtroiano\MigrationPlan\Data\MigrationFile;
use Wtroiano\MigrationPlan\Data\MigrationGroup;
use Wtroiano\MigrationPlan\Services\MigrationPlan;

#[Description('Show migration owners, dependencies, and execution order')]
#[Signature('migration-plan:show {--json : Output the ordered plan as JSON}')]
final class ShowCommand extends Command
{
    use ValidatesMigrationPlan;

    public function handle(MigrationPlan $plan): int
    {
        $resolved = $plan->resolve();
        $groups = array_map(fn (MigrationGroup $group): array => [
            'owner' => $group->owner, 'requires' => $group->requires, 'paths' => $group->paths, 'excludes' => $group->excludes,
        ], $resolved->groups);
        $migrations = array_values(array_map(fn (MigrationFile $file): array => [
            'name' => $file->name, 'owner' => $file->owner, 'path' => $file->path,
        ], $resolved->files));

        if ($this->option('json')) {
            $this->output->writeln(json_encode([
                'version' => 1, 'groups' => $groups, 'paths' => $resolved->paths, 'migrations' => $migrations,
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), OutputInterface::OUTPUT_RAW);

            return self::SUCCESS;
        }

        $this->table(['Owner', 'Requires', 'Declared paths'], array_map(fn (array $group): array => [
            $group['owner'], implode(', ', $group['requires']), implode("\n", $group['paths']),
        ], $groups));

        $rows = [];
        foreach ($migrations as $index => $migration) {
            $rows[] = [$index + 1, $migration['owner'], $migration['name'], $migration['path']];
        }

        $this->table(['Order', 'Owner', 'Migration', 'Path'], $rows);

        return self::SUCCESS;
    }
}
