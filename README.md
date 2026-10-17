# Dependency-aware Laravel migrations

`wtroiano/laravel-migration-plan` is a Laravel Composer package that orders migration groups by their declared prerequisites and validates ownership and rollback safety. Requires PHP 8.5 and Laravel 13.34+. It has no Helm dependency and owns no application tables.

## Installation and activation

```bash
composer require wtroiano/laravel-migration-plan:^0.1
php artisan vendor:publish --tag=migration-plan-config
```

Laravel automatically discovers `Wtroiano\MigrationPlan\Providers\PackageServiceProvider`, exposes a singleton `MigrationPlan`, and merges the package configuration. Set `enabled` to `true` in the published `config/migration-plan.php` to use the ordered migrator and commands. Installation leaves this option disabled so it preserves the host's migration setup. Applications that disable discovery can register `PackageServiceProvider` in `bootstrap/providers.php`.

Explicitly registering `Wtroiano\MigrationPlan\Providers\MigrationPlanServiceProvider` remains supported as an alternative activation mechanism. Helm supplies its own composition provider and does not need to enable the configuration option.

## Registering migration ownership

Register all package paths in an application service provider before a migration command resolves the plan:

```php
use Wtroiano\MigrationPlan\Services\MigrationPlan;

$this->app->afterResolving(MigrationPlan::class, function (MigrationPlan $plan): void {
    $plan->register('example/customers', [base_path('packages/example/customers/database/migrations')]);
    $plan->register('example/invoices', [base_path('packages/example/invoices/database/migrations')], ['example/customers']);
    $plan->register('application', [database_path('migrations')], ['example/invoices']);
});
```

Every migration directory, including the application's directory, needs an owner. `register()` also accepts an `excludes` list of migration files. Missing prerequisites, cycles, duplicate names, and undeclared paths fail before migrations run. Dependencies run before dependents regardless of timestamps. Within a group, filename ordering applies. Rollback reverses dependency and recorded execution ordering, refusing retained dependents, unavailable files, or irreversible selected migrations.

Use the normal migrate, status, rollback, reset, refresh, and fresh commands. Partial `--path`, `--schema-path`, `--graceful`, and schema dumps are rejected because they bypass the complete plan. A failed migration still follows the database engine's transactional limitations. Keep production migration files immutable; this library does not make destructive rollback safe or coordinate package removal.

`Services\MigrationPlan`, `Migrations\OrderedMigrator`, and `Repositories\OrderedMigrationRepository` are extensible for host compatibility adapters. Their core behavior lives in this library. A host that supplies its own provider must bind the library's `Services\MigrationPlan` type to the same plan used by its migrator and commands.

## Package development

```text
config/migration-plan.php   Publishable activation configuration
src/Providers/             Discovery and ordered-migration activation providers
src/Services/              Migration dependency plan
src/Migrations/            Ordered Laravel migrator
src/Repositories/          Ordered migration history repository
src/Concerns/              Migration command validation trait
src/Console/Commands/      Laravel migration command integrations
tests/Feature/Providers/   Testbench provider, publishing, and Artisan tests
tests/Unit/Services/       Isolated migration ordering and rollback tests
tests/Support/             Temporary migration and SQLite test harness
tests/TestCase.php         Orchestra Testbench application
tests/Pest.php             Pest test bindings
```

Use a Composer path repository for local development. Run these commands from this checkout:

```bash
composer install
composer test
composer test:types
composer test:type-coverage
composer test:lint
```

Composer prepares Testbench's package discovery after autoload generation. Feature tests boot Laravel through Testbench 11. Algorithm tests use isolated in-memory SQLite databases and temporary migration fixtures. CI runs these same checks. No Helm workbench or PostgreSQL instance is needed; Testbench and the full framework are development dependencies only.
