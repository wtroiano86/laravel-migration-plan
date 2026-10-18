# Dependency-aware Laravel migrations

`wtroiano/laravel-migration-plan` is a Laravel Composer package that orders migration groups by their declared prerequisites and validates ownership and rollback safety. Requires PHP 8.5 and Laravel 13.34+. It owns no application tables.

Start with [Getting started](docs/GETTING_STARTED.md) for a complete application setup, [Recipes](docs/RECIPES.md) for modular applications, or [Laravel Boost](docs/LARAVEL_BOOST.md) to give consumer AI agents package-specific guidance.

## Why use this package?

Use this package when migrations live across multiple Laravel packages or application modules and one module's schema depends on another's. Explicit ownership and prerequisites make those relationships easier to maintain as packages evolve independently.

For example, an invoicing package may create a foreign key to a customers table. Its migration could have an earlier timestamp than the migration that creates that table. Declaring that invoices require customers makes the customer migrations run first while preserving existing filenames and migration history.

- **Make dependencies visible.** Register each module's migration paths and prerequisites in one declaration. Developers can see which package owns a migration and what must run before it.
- **Catch configuration errors before database changes.** Missing prerequisites, cycles, duplicate migration names, and unowned paths fail during preflight.
- **Inspect the execution plan before deployment.** Validate registrations and review ordered migrations without a database connection. JSON output supports tooling and deployment checks.
- **Check dependencies during rollback.** Reject selections that would leave applied dependents behind, and detect missing files or unsupported `down()` methods before rollback begins.
- **Keep familiar Laravel workflows.** Continue writing ordinary migrations and using Laravel's migration commands. Consumer documentation and Laravel Boost guidance help developers and AI agents maintain the same conventions.

## Installation and activation

```bash
composer require wtroiano/laravel-migration-plan:^0.1
php artisan vendor:publish --tag=migration-plan-config
```

Laravel automatically discovers `Wtroiano\MigrationPlan\Providers\PackageServiceProvider`, exposes a singleton `MigrationPlan`, and merges the package configuration. Set `enabled` to `true` in the published `config/migration-plan.php` to use the ordered migrator and commands. Installation leaves this option disabled so it preserves the host's migration setup. Applications that disable discovery can register `PackageServiceProvider` in `bootstrap/providers.php`.

Explicitly registering `Wtroiano\MigrationPlan\Providers\MigrationPlanServiceProvider` remains supported as an alternative activation mechanism. Hosts that supply their own composition provider do not need to enable the configuration option.

## Registering migration ownership

Register all package paths in an application service provider's `register()` method before a migration command executes:

```php
use Wtroiano\MigrationPlan\Services\MigrationPlan;

$this->callAfterResolving(MigrationPlan::class, function (MigrationPlan $plan): void {
    $plan->register('example/customers', [base_path('packages/example/customers/database/migrations')]);
    $plan->register('example/invoices', [base_path('packages/example/invoices/database/migrations')], ['example/customers']);
    $plan->register('application', [database_path('migrations')], ['example/invoices']);
});
```

Adapt these example paths to the application's existing directories. Laravel's `callAfterResolving()` helper also handles a plan already resolved by another provider. Register each owner once, and avoid inspecting the complete plan until all providers have declared their owners.

Every migration directory, including the application's directory, needs an owner. Create and register the application's directory even when it is empty. `register()` also accepts an `excludes` list of migration files. Missing prerequisites, cycles, duplicate names, and undeclared paths fail during preflight, before commands create the migration repository, roll back migrations, or wipe tables. Dependencies run before dependents regardless of timestamps. Within a group, filename ordering applies. Rollback reverses dependency and recorded execution ordering, refusing retained dependents, unavailable files, or irreversible selected migrations.

Use the normal migrate, status, rollback, reset, refresh, and fresh commands. Explicit `--path`, `--schema-path`, `--graceful`, and schema dumps are rejected because they bypass the complete plan. A failed migration still follows the database engine's transactional limitations. Keep production migration files immutable; this library does not make destructive rollback safe or coordinate package removal.

Inspect the plan before deployment without opening a database connection or loading migration PHP files:

```bash
php artisan migration-plan:check
php artisan migration-plan:show
php artisan migration-plan:show --json
```

Inspection commands are available before activation. They validate declarations and ownership; they do not inspect database history or prove that migration code will succeed. See the [JSON format](docs/API.md#console-inspection) and [operating guide](docs/OPERATIONS.md).

During execution, every migration in a required owner must have run before a dependent migration is attempted. A prerequisite skipped by `shouldRun()` or `Migrator::withoutMigrations()` blocks its dependents until it is applied. Rollback preflight also rejects nonpublic `down()` methods and methods with required arguments before rolling back any selected migration.

`Services\MigrationPlan`, `Migrations\OrderedMigrator`, and `Repositories\OrderedMigrationRepository` are extensible for host compatibility adapters. Their core behavior lives in this library. A host that supplies its own provider must bind the library's `Services\MigrationPlan` type to the same plan used by its migrator and commands.

## Laravel Boost and AI agents

The package includes a Boost guideline and a `laravel-migration-plan` skill. In a consuming application with Boost installed, run:

```bash
php artisan boost:install --guidelines --skills
```

Select `wtroiano/laravel-migration-plan` in Boost's third-party package prompt and choose the agents that should receive it. Boost 2.10 discovers these resources for direct dependencies. After package updates, run `php artisan boost:update` to refresh selected guidance. See [Laravel Boost integration](docs/LARAVEL_BOOST.md) for first-time installation, verification, and troubleshooting. Boost is optional and does not change migration activation.

## Documentation

- [Documentation index](docs/README.md): choose the guide for your task.
- [Getting started](docs/GETTING_STARTED.md): installation, ownership registration, inspection, and activation.
- [Consumer recipes](docs/RECIPES.md): package dependencies, published files, existing applications, and consumer tests.
- [API reference](docs/API.md): registration, typed data, ordering, and structured errors.
- [Architecture](docs/ARCHITECTURE.md): compilation and rollback pipelines, operation lifetime, and responsibilities.
- [Operations](docs/OPERATIONS.md): commands, recovery, and rollback limits.
- [Compatibility](docs/COMPATIBILITY.md): host adapters, provider bindings, and supported versions.
- [Laravel Boost](docs/LARAVEL_BOOST.md): install and maintain AI guidance in consuming applications.
- [Contributing](CONTRIBUTING.md): test suites and quality checks.
- [Releasing](docs/RELEASING.md): versioning, release checks, GitHub drafts, and Packagist publication.
- [Changelog](CHANGELOG.md): release notes and compatibility changes.

## Support and security

Report non-sensitive bugs and usage questions in the [GitHub issue tracker](https://github.com/wtroiano86/laravel-migration-plan/issues). Send suspected vulnerabilities privately to [opensource.troiano86@pm.me](mailto:opensource.troiano86@pm.me); see the [security policy](SECURITY.md) for reporting details and supported releases.

The package is available under the [MIT license](LICENSE).

## Package development

```text
config/migration-plan.php   Publishable activation configuration
src/Providers/             Discovery and ordered-migration activation providers
src/Data/                  Immutable definitions, files, and resolved plans
src/Actions/               Dependency ordering, discovery, and migration policies
src/Pipelines/             Plan compilation and rollback preparation
src/Exceptions/            Domain failures with structured context
src/Services/              Public plan registry and canonical path handling
src/Migrations/            Ordered Laravel migrator
src/Repositories/          Ordered migration history repository
src/Concerns/              Migration command validation trait
src/Console/Commands/      Inspection and Laravel migration command integrations
resources/boost/           Consumer AI guidelines and the migration plan skill
tests/Feature/Providers/   Testbench provider, publishing, and Artisan tests
tests/Feature/Console/     Artisan preflight and command lifecycle tests
tests/Unit/                Graph, policy, and plan resolution tests without a database
tests/Integration/         Migration execution with SQLite, PostgreSQL, and MySQL
tests/Support/             Temporary migrations and database test harness
tests/TestCase.php         Orchestra Testbench application
tests/Pest.php             Pest test bindings
```

Use a Composer path repository for local development. Run these commands from this checkout:

```bash
composer install
composer test

# Run a focused test without the complete quality gate:
composer test:unit -- --filter="test description"
```

Composer prepares Testbench's package discovery after autoload generation. Feature tests boot Laravel through Testbench 11. Unit tests exercise graph and migration policy without a database; discovery tests use temporary files. Integration tests use in-memory SQLite by default and run against PostgreSQL 16 and MySQL 8.4 in CI. Package CI also runs the complete quality gate with minimum and latest supported dependencies. Local checks need no database server or host application; Testbench and the full framework are development dependencies only. See [Contributing](CONTRIBUTING.md#database-integration-tests) to run server integration tests locally.

`composer test` enforces Pint and Rector, PHPStan at `max` with Larastan, Carbon, and bleeding-edge rules, 100% type coverage, architecture rules, and exactly 100% line coverage across all `src` files. Coverage requires PCOV or Xdebug in coverage mode; CI installs PCOV. `composer lint` applies the PHP formatting and refactoring rules. Individual checks remain available as `test:lint`, `test:types`, `test:type-coverage`, and `test:coverage`.

The architecture suite permits only `MigrationPlan`, `OrderedMigrator`, and `OrderedMigrationRepository` to remain nonfinal for host adapters. Other classes must be final, and only Laravel migration hooks may declare protected methods. Pint leaves finality to these targeted architecture checks; strict typing, equality, and the other formatting rules remain enforced.
