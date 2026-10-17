# Dependency-aware Laravel migrations

`wtroiano/laravel-migration-plan` orders migration groups by their declared prerequisites and validates ownership and rollback safety. Requires PHP 8.5 and Laravel 13.34+. It has no Helm dependency and owns no application tables.

Register `Wtroiano\MigrationPlan\MigrationPlanServiceProvider` explicitly in your Laravel application. Installation alone does not override Laravel's migrator. Register all package paths before a migration command resolves the plan:

```php
use Wtroiano\MigrationPlan\MigrationPlan;

$this->app->afterResolving(MigrationPlan::class, function (MigrationPlan $plan): void {
    $plan->register('example/customers', [__DIR__.'/../database/migrations']);
    $plan->register('example/invoices', [$invoiceMigrations], ['example/customers']);
});
```

Every migration directory, including the application's directory, needs an owner. `register()` also accepts an `excludes` list of migration files. Missing prerequisites, cycles, duplicate names, and undeclared paths fail before migrations run. Dependencies run before dependents regardless of timestamps. Within a group, filename ordering applies. Rollback reverses dependency and recorded execution ordering, refusing retained dependents, unavailable files, or irreversible selected migrations.

Use the normal migrate, status, rollback, reset, refresh, and fresh commands. Partial `--path`, `--schema-path`, `--graceful`, and schema dumps are rejected because they bypass the complete plan. A failed migration still follows the database engine's transactional limitations. Keep production migration files immutable; this library does not make destructive rollback safe or coordinate package removal.

`MigrationPlan`, `OrderedMigrator`, and `OrderedMigrationRepository` are extensible for host compatibility adapters. Their core behavior lives in this library. A host that supplies its own provider must bind the library's `MigrationPlan` type to the same plan used by its migrator and commands.

Install with `composer require wtroiano/laravel-migration-plan:^0.1`. Run `composer install`, `composer test`, `composer test:types`, and `composer test:lint` in this checkout. Tests use isolated in-memory SQLite databases and temporary migration fixtures; no Helm workbench or PostgreSQL instance is needed.
