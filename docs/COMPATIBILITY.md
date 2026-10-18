# Host and framework compatibility

The package requires PHP 8.5+, Illuminate 13.34+ within Laravel 13, and Symfony Console 8. Composer declares `illuminate/pipeline` directly because compilation and rollback preparation use it. The full Laravel framework and Testbench are development dependencies.

CI resolves both the lowest and highest versions allowed by the package's Composer constraints. Both jobs run the complete quality gate, including actual Artisan command tests. Separate integration jobs run against PostgreSQL 16 and MySQL 8.4 with latest supported dependencies. SQLite remains the default for local testing. Coverage exercises ordering, history, foreign keys, retry, and rollback preflight; it does not promise atomic migrations across database engines.

Feature coverage verifies `--pretend`, `--database`, per-migration connections, and `--step`. Skipped prerequisites now explicitly prevent dependent execution; see [skip semantics](OPERATIONS.md#skipped-prerequisites). Rollback also rejects nonpublic `down()` methods and required arguments before any selected rollback runs.

## Existing public entry points

`MigrationPlan::register()`, `registeredOwners()`, `owners()`, `paths()`, `files()`, `assertOwnedPaths()`, and `assertSafeRollback()` retain their signatures and result shapes. `new MigrationPlan` remains supported. The ordered migrator's constructor arguments and repository bindings remain compatible with the existing host adapters.

`resolve()` and `withinOperation()` add typed inspection and explicit operation scope. Existing `RuntimeException` catches continue to catch package domain errors.

Registration must finish before execution. Registering owners inside migration methods or command execution now fails explicitly because an operation uses one resolved snapshot. Standalone inspection outside an operation remains fresh on each call.

## Providers and host adapters

Automatic discovery registers `PackageServiceProvider`, merges configuration, publishes it under `migration-plan-config`, and exposes the plan singleton. Ordered migration behavior is disabled until `migration-plan.enabled` is exactly `true`.

The package provider always registers `migration-plan:check` and `migration-plan:show`. They can inspect registrations before activation, without establishing database connections.

Explicit registration of `MigrationPlanServiceProvider` remains supported. It preserves an existing plan binding and installs the ordered repository, migrator, and six command replacements.

These classes remain nonfinal for host namespace adapters:

- `Services\MigrationPlan`
- `Migrations\OrderedMigrator`
- `Repositories\OrderedMigrationRepository`

Hosts that compose their own provider must bind the library plan type to the same instance used by their migrator and commands. For example, after registering a host plan singleton:

```php
$this->app->alias(
    HostMigrationPlan::class,
    \Wtroiano\MigrationPlan\Services\MigrationPlan::class,
);
```

Register owners through the shared public plan. A custom command can reuse `Concerns\ValidatesMigrationPlan`; it wraps Laravel's protected `execute()` hook. If overriding that hook, delegate to the trait's implementation to retain validation and operation scoping.

Private pipeline stages and action implementation details are internal. Host subclasses should not depend on them. A resolved plan must satisfy the graph and ownership invariants before rollback preparation consumes it.

## Framework upgrades

When changing supported Laravel versions, verify command replacement bindings, effective application paths, nested `fresh`/`refresh` behavior, the migrator's rollback hooks, and migration repository ordering. Run the host adapter tests as well as package tests. Review upstream method signatures before widening Composer constraints.
