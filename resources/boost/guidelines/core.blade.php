# Laravel Migration Plan

`wtroiano/laravel-migration-plan` adds migration ownership, dependency ordering, and rollback validation. Use the shared `Wtroiano\MigrationPlan\Services\MigrationPlan`; there is no package facade or migration base class. Ordinary Laravel migrations remain valid. Use the `laravel-migration-plan` skill when integrating or troubleshooting this package.

## Register and activate

The auto-discovered `PackageServiceProvider` exposes the plan and inspection commands. Ordered execution is disabled until `config/migration-plan.php` contains `'enabled' => true`, or the host explicitly installs a compatible migration provider. Check the existing host integration before changing activation.

Register each owner once in an application or package service provider, before migration execution. Inside the provider's `register()` method:

<code-snippet name="Declare application migration ownership" lang="php">
$this->callAfterResolving(\Wtroiano\MigrationPlan\Services\MigrationPlan::class, function (\Wtroiano\MigrationPlan\Services\MigrationPlan $plan): void {
    $plan->register('application', [database_path('migrations')]);
});
</code-snippet>

`register(string $owner, array $paths, array $requires = [], array $excludes = [])` accepts lists of strings. `requires` identifies other owners. Every directory loaded through `loadMigrationsFrom()` and the application directory must be declared, even when empty. Paths and exclusions must exist; directories discover only immediate `*_*.php` children. Use exact file exclusions to assign a file to another owner without duplicate discovery.

## Inspect before executing

- `php artisan migration-plan:check` validates declarations without database access or loading migration PHP files.
- `php artisan migration-plan:show --json` reports owners, dependencies, and full execution order; it does not report applied history.
- Normal Laravel migration commands use the plan after activation. `--path`, `--schema-path`, `--graceful`, and schema dumps are rejected; do not use them to bypass a validation failure.

Prerequisites run before dependents regardless of timestamps. Within an owner, files sort by name. All migrations in required owners must be applied before a dependent is attempted; `shouldRun()` and `withoutMigrations()` skips do not satisfy prerequisites. `--pretend` executes migration PHP to collect SQL and does not simulate prerequisite completion.

Rollback preflights the entire selection: files must remain available, retained dependents/later executions must be respected, and `down()` must be public, callable without required arguments, and not return `never`. These checks cannot guarantee data restoration or atomic DDL. Preserve deployed filenames and history; fixing ownership does not require `migrate:fresh`.

Read `docs/GETTING_STARTED.md`, `docs/RECIPES.md`, and `docs/OPERATIONS.md` under this package's installed root for consumer workflows; `docs/API.md` documents typed results and errors. Change the consuming application's registrations rather than editing installed vendor code or creating a second plan instance.
