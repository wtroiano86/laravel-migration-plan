---
name: laravel-migration-plan
description: Integrate and troubleshoot wtroiano/laravel-migration-plan in consuming Laravel applications, including ownership, dependency order, inspection, skipped prerequisites, and rollback failures. Use when this package is installed or explicitly requested.
---

# Laravel Migration Plan

Work in the consuming application and preserve its existing provider composition. This package orders ordinary Laravel migrations; consumers do not extend its internal actions or pipelines.

## Locate the integration

Use `composer show wtroiano/laravel-migration-plan` to check the installed version; add `--path` to locate its root. Documentation paths below are relative to that package root, usually `vendor/wtroiano/laravel-migration-plan`, not to this skill's installed directory:

- `docs/GETTING_STARTED.md`: installation, activation, and a complete application provider.
- `docs/RECIPES.md`: package ownership, published files, and adoption of existing history.
- `docs/OPERATIONS.md`: command options, skipped prerequisites, and rollback recovery.
- `docs/API.md`: registration, readonly results, JSON format, and error context.
- `docs/COMPATIBILITY.md`: custom host providers and adapter bindings.

Read the sections needed for the task. Inspect the application's configuration and provider registrations before adding new ones. Auto-discovery exposes the singleton plan and inspection commands, but ordered execution defaults to disabled. A host may already activate it through its own provider.

## Declare ownership

Use `Wtroiano\MigrationPlan\Services\MigrationPlan::register($owner, $paths, $requires = [], $excludes = [])`. Inside a service provider's `register()` method:

```php
$this->callAfterResolving(\Wtroiano\MigrationPlan\Services\MigrationPlan::class, function (\Wtroiano\MigrationPlan\Services\MigrationPlan $plan): void {
    $plan->register('example/customers', [base_path('packages/example/customers/database/migrations')]);
    $plan->register('application', [database_path('migrations')], ['example/customers']);
});
```

Adapt the example path to the consumer's actual installation. `callAfterResolving()` also handles a plan that another provider has already resolved. Register each owner once and finish registration before operations begin. Do not inspect the complete plan inside a registration callback while other owners are still being registered.

Requirements name owners, not files. Every effective migration directory must have a declaration, including the application directory when empty and package paths registered through `loadMigrationsFrom()`. A parent path does not automatically own subdirectories. Paths and excluded files must exist; discovery is nonrecursive. Empty owners may group prerequisites. Duplicate migration names are rejected, even within one owner.

To give a published file its own owner, exclude the exact file from its original owner and register it under the new owner. Preserve deployed filenames and migration history. Use `docs/RECIPES.md` for overlapping vendor and published paths.

## Inspect and validate the change

Run the read-only commands:

```bash
php artisan migration-plan:check
php artisan migration-plan:show --json
```

They validate declarations without querying the database or loading migration PHP files. JSON uses format `version: 1` with ordered `groups`, canonical `paths`, and ordered `migrations`. Check the exit code before parsing: failures use normal Laravel exception output. This is an execution plan, not database status or proof that migration bodies will succeed.

For activation, publish `migration-plan-config` and set the boolean `enabled` to `true` in `config/migration-plan.php`, then follow the host's configuration-cache process. Do not introduce an undocumented environment flag or a second `MigrationPlan` instance. Use normal Laravel migration commands within the user's requested database scope; a documentation or inspection request does not require running migrations.

## Diagnose failures

- `InvalidMigrationPlan`: correct registration, missing paths, duplicate names, cycles, or unsupported options.
- `UnappliedPrerequisite`: apply/enable the skipped prerequisite or correct the owner relationship. The guard runs before a dependent's own `shouldRun()`. Skipping both sides does not satisfy the dependency.
- `UnsafeRollback`: restore unavailable installed files or address retained dependents/later executions. Owner-wide dependencies can block rollback of even a newly added prerequisite migration.
- `IrreversibleMigration`: selected `down()` is absent, nonpublic, requires arguments, or declares `never`. Prefer a forward fix when reversal cannot be supported.

These exceptions extend `RuntimeException` and expose structured `context()`. Read context rather than parsing messages. Do not bypass failures with `--path`, `--schema-path`, `--graceful`, a schema dump, deleted history, or an unsolicited database wipe.

`--pretend` preserves Laravel's SQL simulation: migration PHP runs, `shouldRun()` is not evaluated, and successful applications are not recorded. Runtime prerequisite-completion checks are omitted during simulation; structural and rollback checks remain. Neither pretend mode nor rollback preflight guarantees restoration or transaction safety.

Run the consuming application's focused tests for changed ownership and dependencies. On disposable test databases, assert execution order, schema/history preservation when validation fails, and retry behavior. Package contributor scripts and tests belong to the library checkout, not the consumer's installed vendor directory.
