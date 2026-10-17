<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan\Concerns;

use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wtroiano\MigrationPlan\Services\MigrationPlan;

trait ValidatesMigrationPlan
{
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        foreach (['path', 'schema-path', 'graceful'] as $option) {
            throw_if($input->hasOption($option) && $input->getOption($option), RuntimeException::class, "The platform migration plan does not support [--{$option}]. Run the complete migration plan without bypass or failure-suppression options.");
        }

        throw_if((glob(database_path('schema/*-schema.sql')) ?: []) !== [], RuntimeException::class, 'Schema dumps bypass package migration ownership. Remove the schema dump and run the platform migration plan.');

        $this->laravel->make(MigrationPlan::class)->files();
        $this->laravel->make(\Illuminate\Database\Migrations\Migrator::class)->paths();
    }
}
