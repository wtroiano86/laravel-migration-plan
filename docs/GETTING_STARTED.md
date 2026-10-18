# Getting started

This guide configures a consuming Laravel application. Run its commands from the application root. Package contributors should use [Contributing](../CONTRIBUTING.md).

## Requirements

- PHP 8.5 or later within PHP 8.
- Laravel 13.34 or later within Laravel 13; Symfony Console 8.
- Existing migration files and directories that the application can read.

The package uses Laravel's migration repository and database connections. It creates no additional application tables. Laravel Boost is optional development tooling; it is not needed to execute migrations.

## Install without changing migration execution

```bash
composer require wtroiano/laravel-migration-plan:^0.1
php artisan vendor:publish --tag=migration-plan-config
```

Laravel discovers `Wtroiano\MigrationPlan\Providers\PackageServiceProvider`, binds the shared plan, and registers the inspection commands. The published `config/migration-plan.php` starts with `enabled` set to `false`, preserving the application's existing migrator while you declare ownership.

If package discovery is disabled, add `Wtroiano\MigrationPlan\Providers\PackageServiceProvider::class` to the existing array in `bootstrap/providers.php`.

## Register the application directory

Ensure `database/migrations` exists, even if it contains no migrations. In an existing application service provider, register each owner once. For an application with no package migration directories, the complete provider can look like this; merge the registration into your existing provider if it already contains other logic:

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Wtroiano\MigrationPlan\Services\MigrationPlan;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(MigrationPlan::class, function (MigrationPlan $plan): void {
            $plan->register('application', [database_path('migrations')]);
        });
    }
}
```

`callAfterResolving()` is Laravel's service-provider helper: it registers the callback and also handles a plan that another provider has already resolved. Finish all ownership registration before a migration command or operation begins. Do not resolve the execution order inside registration callbacks while other providers are still declaring owners.

For modular applications, add the other owners and their prerequisites as shown in [Recipes](RECIPES.md#order-package-migrations-before-application-migrations). Every directory loaded with Laravel's `loadMigrationsFrom()` also needs a declaration. Registering a parent directory does not implicitly own its children.

## Inspect the declarations

```bash
php artisan migration-plan:check
php artisan migration-plan:show
php artisan migration-plan:show --json
```

`check` reports counts after validation. `show` lists owners and migration order; `--json` emits the [versioned output format](API.md#console-inspection). These commands work while execution is disabled. They neither connect to the database nor load migration PHP files, so they do not validate migration bodies, reversibility, or applied history.

Correct missing paths, undeclared owners, dependency cycles, and duplicate migration names before activation. A successful check exits with code 0; validation failures exit nonzero. Directories discover immediate `*_*.php` files only, without recursion.

## Enable ordered execution

Set the published configuration to:

```php
<?php

return [
    'enabled' => true,
];
```

This is a boolean configuration value. The package does not define a `MIGRATION_PLAN_ENABLED` environment variable. If your application uses cached configuration, rebuild that cache through its normal deployment process after changing the file.

The provider reads the setting during application registration. Changing it inside a migration, command handler, or service-provider `boot()` method is too late for that process. The next Artisan process reads the updated configuration.

On the intended development or deployment database, use Laravel's normal commands:

```bash
php artisan migrate:status
php artisan migrate
```

On a new database, `migrate:status` reports that the migration table is missing; `migrate` creates it. Dependencies execute before their dependents regardless of filename timestamps. Files within an owner run in filename order. An unapplied prerequisite blocks its dependent migrations, including when Laravel deliberately skipped the prerequisite.

For rollback behavior and command restrictions, read [Operations](OPERATIONS.md). `--path`, `--schema-path`, `--graceful`, and schema dumps are unsupported when using the plan. `--database`, `--step`, and `--pretend` retain their documented command-specific behavior.

## Existing applications and host integrations

Adopting the package does not require wiping the database, renaming deployed migrations, or replacing migration history. Start by inventorying all existing sources and registering them; see [adopting an existing application](RECIPES.md#adopt-an-existing-application).

An integration may already activate ordered execution by registering `MigrationPlanServiceProvider` or supplying a compatible host provider. Keep that composition rather than creating a second registry. See [Compatibility](COMPATIBILITY.md#providers-and-host-adapters).

To teach a consuming application's AI agents these conventions, follow [Laravel Boost integration](LARAVEL_BOOST.md).
