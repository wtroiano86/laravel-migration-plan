# Consumer recipes

These examples belong in the consuming application's ownership registration. Paths and owner names are examples: adapt them to existing files and provider conventions. Register each owner once; do not append multiple alternative examples to the same plan.

## Order package migrations before application migrations

Inside an application service provider's `register()` method:

```php
$this->callAfterResolving(\Wtroiano\MigrationPlan\Services\MigrationPlan::class, function (\Wtroiano\MigrationPlan\Services\MigrationPlan $plan): void {
    $plan->register(
        owner: 'example/customers',
        paths: [base_path('packages/example/customers/database/migrations')],
    );

    $plan->register(
        owner: 'example/invoices',
        paths: [base_path('packages/example/invoices/database/migrations')],
        requires: ['example/customers'],
    );

    $plan->register(
        owner: 'application',
        paths: [database_path('migrations')],
        requires: ['example/invoices'],
    );
});
```

The order is customers, invoices, application. An invoice migration dated 2020 still runs after a customer migration dated 2030. Requirements are owner names, not migration filenames or Composer constraints. Prerequisites are transitive, so the application also depends on customers.

Ordering is deterministic: owner and prerequisite names are visited alphabetically, independently of registration order, and each owner's files sort by name. Do not rely on registration order to express a dependency.

## Register a reusable package's migrations

A package that directly depends on this library can declare its own owner in its provider. For a provider located at `src/InvoiceServiceProvider.php` and migrations at `database/migrations` in that package:

```php
public function register(): void
{
    $this->callAfterResolving(\Wtroiano\MigrationPlan\Services\MigrationPlan::class, function (\Wtroiano\MigrationPlan\Services\MigrationPlan $plan): void {
        $plan->register(
            'example/invoices',
            [__DIR__.'/../database/migrations'],
            ['example/customers'],
        );
    });
}
```

The consumer still registers its application directory and decides when to activate the ordered migrator. If the package also calls `loadMigrationsFrom()`, declare that same canonical directory in the plan. The dependency owner must be supplied by another package or the consumer before execution.

For a third-party package that does not support this library, register its actual migration path from the application's provider. Check the installed package's provider for the path it loads; do not assume every package stores migrations in the same location. A package that already declares its own owner must not be registered again by the application.

## Assign a published migration to a separate owner

An existing migration in the application directory can belong to a package owner without moving or renaming the file. Exclude that exact file from the application owner and register it under its own owner:

```php
$customers = database_path('migrations/2026_01_01_000001_create_customers.php');

$plan->register('example/customers', [$customers]);
$plan->register(
    owner: 'application',
    paths: [database_path('migrations')],
    requires: ['example/customers'],
    excludes: [$customers],
);
```

Exclusions are existing file paths, not wildcard patterns. They affect only their declaring owner. Registering the application directory remains necessary because Laravel passes that directory to migration commands.

If a vendor package also loads its original migration directory, that directory still needs an owner. Use exclusions deliberately to select the intended copies. Keeping both original and published copies can cause duplicate names or duplicate schema operations even when the filenames differ. Preserve the filenames already recorded in deployed migration history.

## Express dependencies through an empty owner

An owner may group prerequisites without owning files:

```php
$plan->register('example/customers', [$customerMigrations]);
$plan->register('example/catalog', [$catalogMigrations]);
$plan->register('example/commerce', [], ['example/customers', 'example/catalog']);
$plan->register('application', [database_path('migrations')], ['example/commerce']);
```

The variables above identify existing migration directories. Empty owners participate in cycle detection, execution dependencies, and rollback protection. They do not require placeholder migration files.

## Inspect a plan from application code

Resolve the shared service after providers have registered all owners:

```php
use Wtroiano\MigrationPlan\Services\MigrationPlan;

$plan = app(MigrationPlan::class);
$resolved = $plan->resolve();

foreach ($resolved->files as $migration) {
    echo $migration->owner.': '.$migration->name.PHP_EOL;
}
```

Constructor-inject `MigrationPlan` when a service uses it repeatedly. The public `files()` method still returns the original array shape; `resolve()` returns readonly data objects. Neither inspection API executes migration files. Avoid constructing a second plan inside the application: the commands use the container's shared instance.

## Adopt an existing application

1. Install with ordered execution disabled, then identify the application directory, package-loaded directories, published copies, and any custom migration paths.
2. Declare owners and prerequisites using the existing files. Keep deployed migration names unchanged.
3. Run `migration-plan:check` and inspect `migration-plan:show --json`. These checks validate declarations, not the database's applied history.
4. Compare the plan with the intended database's migration history. Restore missing installed packages/files before attempting rollback. The package does not automatically reconcile history, import a baseline, or assign owners to deleted files.
5. Exercise migration and rollback behavior on a disposable database or restored development copy, then enable the package through the application's deployment process.

If the application uses schema dumps or pruned migrations, plan an explicit transition before activation. Do not delete the migration table, rewrite history, or use `migrate:fresh` to make validation pass on a database whose contents must be preserved.

Owner-wide dependencies also affect upgrades: a newly added customer migration must run before a pending invoice migration. Keeping any invoice migration applied can prevent rolling back a customer migration. Choose owner boundaries that match the application's schema relationships; see [skip behavior and rollback limits](OPERATIONS.md).

## Test a consuming application

Use the application's existing test runner and disposable database configuration. Valuable integration cases include:

- A dependent migration with an older timestamp still runs after its prerequisite.
- Registration covers package-loaded paths and the application directory, including an empty one.
- A skipped prerequisite stops a dependent before its schema is changed.
- A rejected rollback leaves both the schema and migration history intact.
- Inspection succeeds without opening a database connection.

The package's Composer quality scripts are for this repository. Consumers should run their own tests against the installed package and keep application customizations outside `vendor/`.
