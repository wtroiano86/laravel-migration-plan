# Migration plan API

Use `Wtroiano\MigrationPlan\Services\MigrationPlan` to register ownership and inspect execution order. The service is a singleton in Laravel and can also be constructed with `new MigrationPlan`.

## Registration

```php
$plan->register(
    owner: 'example/invoices',
    paths: [$invoiceMigrations],
    requires: ['example/customers'],
    excludes: [$legacyMigration],
);
```

Owners are unique, case-sensitive strings. Paths, prerequisites, and exclusions are lists of strings. Prerequisites identify owners, not individual migration filenames. Empty groups are allowed and can express dependencies without owning files.

Register all owners before invoking a command or entering `withinOperation()`. Forward references are allowed: the dependency graph is validated when ordered or resolved. Registering the same owner twice fails; registering during an active operation also fails.

In a Laravel service provider, use `callAfterResolving(MigrationPlan::class, $callback)` to attach declarations to the singleton, including when another provider resolved it earlier. See [Getting started](GETTING_STARTED.md#register-the-application-directory) for a complete provider and [Recipes](RECIPES.md) for multiple owners.

Paths may identify directories or individual files. Directories discover immediate `*_*.php` children, without recursion. Paths and exclusions are canonicalized with `realpath()` and must exist. Exclusions apply only to the declaring group, allowing another owner to claim the file. A migration name is its filename without the extension and must be unique across the complete plan, including within one owner.

## Inspection

| Method | Result |
| --- | --- |
| `registeredOwners()` | Owner names in registration order |
| `owners()` | Owner names in dependency order; validates prerequisites and cycles |
| `paths()` | Unique canonical declared paths, preserving registration order |
| `files()` | Ordered `array<string, array{path: string, owner: string}>`, keyed by migration name |
| `resolve()` | A `ResolvedMigrationPlan` containing typed groups, paths, and files |
| `assertOwnedPaths($paths)` | Rejects canonical paths not explicitly registered |
| `assertSafeRollback($selected, $ran)` | Validates file availability and retained dependencies against recorded history |

Dependencies precede dependents. Graph traversal visits owner names and prerequisite names alphabetically, making ordering independent of registration order. Files within each owner sort by migration name. Filename timestamps do not override group dependencies.

`assertSafeRollback()` accepts migration names, with `$ran` in recorded execution order. It checks structural safety only; resolving migration instances and checking `down()` belong to the migrator's rollback pipeline.

## Typed data

All classes in `Data` are `final readonly`:

| Type | Properties |
| --- | --- |
| `MigrationGroup` | `owner`, `paths`, `requires`, `excludes` |
| `MigrationFile` | `name`, `path`, `owner` |
| `ResolvedMigrationPlan` | Dependency-ordered `groups`, registration-ordered `paths`, name-keyed `files`; exposes `owners()` and transitive `dependencies()` |
| `MigrationPlanDefinition` | Pipeline input containing `groups` and declared `paths` |
| `RollbackPlan` | Resolved `plan`, `selected` migration names, and recorded `ran` names |

Obtain validated snapshots through `MigrationPlan::resolve()`. Data constructors describe values; they do not perform filesystem or graph validation.

```php
$resolved = $plan->resolve();

foreach ($resolved->files as $migration) {
    echo "{$migration->owner}: {$migration->name}\n";
}
```

Outside an operation, each resolution reads current declarations and files. Inside `withinOperation()`, nested resolutions reuse one snapshot:

```php
$files = $plan->withinOperation(fn (): array => $plan->files());
```

The callback result is returned unchanged. Snapshot state is released in `finally`, including on failure. Commands and `OrderedMigrator::run()`, `rollback()`, and `reset()` manage this scope automatically.

## Console inspection

`migration-plan:check` validates the graph, file discovery, ownership of all effective paths, and absence of schema dumps. On success it prints owner and migration counts. `migration-plan:show` applies the same validation and displays owner requirements and migration execution order. Both are available through the package provider even when ordered migration execution is disabled.

Neither command opens a database connection nor loads migration PHP files. They do not check installed history, `shouldRun()`, PHP syntax, or migration method signatures. A valid inspection result does not replace execution-time validation.

`migration-plan:show --json` writes one JSON document on success:

```json
{
  "version": 1,
  "groups": [
    {"owner": "application", "requires": [], "paths": ["/app/database/migrations"], "excludes": []}
  ],
  "paths": ["/app/database/migrations"],
  "migrations": [
    {"name": "2026_01_01_000001_create_records", "owner": "application", "path": "/app/database/migrations/2026_01_01_000001_create_records.php"}
  ]
}
```

`groups` and `migrations` are ordered lists, including empty owners. `groups[].paths`, `requires`, and `excludes` retain their declarations. Top-level `paths` are unique canonical paths in registration order; migration paths are canonical. Empty collections are JSON arrays. `version` identifies the output format, independently of the package version.

Successful inspection exits with code 0. Validation failures use Laravel's normal exception output and a nonzero exit code, including with `--json`; JSON consumers must check the exit code before parsing output.

## Errors

All domain errors extend `RuntimeException`, preserving existing catch blocks. Each exposes `context(): array<string, string>` for logging or diagnostics. Context keys depend on the failure.

| Exception | Meaning | Context examples |
| --- | --- | --- |
| `InvalidMigrationPlan` | Invalid registration, dependency graph, path, duplicate name, or unsupported command input | `owner`, `prerequisite`, `cycle`, `path`, `migration`, `previous_owner`, `option` |
| `UnsafeRollback` | Missing installed file, unknown selection, or a retained dependent/later migration | `migration`, `owner`, `retained`, `dependent` |
| `IrreversibleMigration` | A selected migration lacks a public `down()` callable without arguments, or declares `down(): never` | `migration`, `owner`, `path` |
| `UnappliedPrerequisite` | A pending dependent migration has an unapplied migration in a required owner | `migration`, `owner`, `prerequisite`, `prerequisite_owner` |

Catch the exception type to distinguish failures. Human-readable messages provide recovery guidance; use `context()` instead of parsing them. Exceptions thrown by migration code or Laravel remain unchanged.
