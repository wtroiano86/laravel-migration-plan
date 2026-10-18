<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan\Concerns;

use Illuminate\Database\Migrations\Migrator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wtroiano\MigrationPlan\Exceptions\InvalidMigrationPlan;
use Wtroiano\MigrationPlan\Services\MigrationPlan;

trait ValidatesMigrationPlan
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach (['path', 'schema-path', 'graceful'] as $option) {
            throw_if($input->hasOption($option) && $input->getOption($option), InvalidMigrationPlan::class, "The platform migration plan does not support [--{$option}]. Run the complete migration plan without bypass or failure-suppression options.", ['option' => $option]);
        }

        $schemaPath = $this->laravel->databasePath('schema/*-schema.sql');
        throw_if((glob($schemaPath) ?: []) !== [], InvalidMigrationPlan::class, 'Schema dumps bypass package migration ownership. Remove the schema dump and run the platform migration plan.', ['path' => $schemaPath]);

        $plan = $this->laravel->make(MigrationPlan::class);

        return $plan->withinOperation(function () use ($plan, $input, $output): int {
            $plan->assertOwnedPaths([
                ...array_values($this->laravel->make(Migrator::class)->paths()),
                $this->laravel->databasePath('migrations'),
            ]);

            return parent::execute($input, $output);
        });
    }
}
