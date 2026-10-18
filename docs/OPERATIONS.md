# Operating dependency-aware migrations

Activate the package and register every migration directory before using the normal Laravel commands. Include `database_path('migrations')` even if it is empty. Ownership preflight must pass before any command changes the database.

## Commands and options

| Command | Behavior |
| --- | --- |
| `migration-plan:check` | Validates declarations and effective path ownership without database access |
| `migration-plan:show [--json]` | Validates and displays owners, dependencies, and complete execution order |
| `migrate` | Runs pending migrations in dependency and filename order |
| `migrate:status` | Displays Laravel's migration status after plan validation |
| `migrate:rollback` | Selects a batch or step count, validates the complete selection, then rolls it back |
| `migrate:reset` | Validates and rolls back all recorded migrations |
| `migrate:refresh` | Resets or rolls back the requested steps, then runs pending migrations |
| `migrate:fresh` | Validates the plan and ownership before Laravel wipes and rebuilds the database |

Any supplied `--path`, even one naming the complete plan, is rejected. `--schema-path` and `--graceful` are also rejected wherever Laravel exposes them. Schema files matching `database/schema/*-schema.sql` are rejected.

Other Laravel options retain their command-specific meanings, including `--database`, `--force`, `--step`, `--batch`, seeding options, and `--pretend`. `--step` means separate migration batches on `migrate`, and a rollback count on commands that accept a rollback count. `--force` and `--pretend` do not bypass plan validation. Laravel's pretend mode can execute PHP migration methods while collecting SQL; it is not a sandbox for arbitrary side effects.

## Skipped prerequisites

Before attempting a pending migration, the migrator checks that every discovered migration in its required owners, including transitive prerequisites through empty owners, is recorded as applied. A prerequisite skipped by Laravel's `shouldRun()` or `Migrator::withoutMigrations()` remains unapplied and causes `UnappliedPrerequisite` before dependent code runs. Already completed migrations remain recorded, so enabling the prerequisite and retrying continues safely.

This check occurs before a dependent's own `shouldRun()` method. Skipping both sides of a declared dependency does not satisfy it. If features must be independently optional, give their migrations separate owners and declare only the required relationships. Migrations without dependent owners retain Laravel's skip behavior. Filename order inside one owner does not imply a prerequisite relationship between every file.

`--pretend` retains Laravel's simulation semantics: it calls migration methods while collecting SQL, without logging successful applications, and does not evaluate `shouldRun()`. Runtime prerequisite-completion checks are therefore omitted during simulation; graph, ownership, and rollback preflight still apply. Use `migration-plan:check` for inspection that does not load migration files.

## Rollback guarantees

Before any selected `down()` runs, the package checks that:

1. All recorded migration names still have available files in the plan.
2. Every selected migration has run.
3. No retained migration belongs to an owner that transitively requires a selected owner's schema.
4. No later recorded migration in the same owner remains.
5. Every selected migration defines a public `down()` with no required arguments and does not declare a `never` return type. Optional arguments are allowed.

Rollback order reverses owner dependency order and recorded execution order within each owner. Prerequisites are owner-wide, not version-specific; retaining a dependent owner can therefore prevent rolling back even a recent prerequisite migration.

These checks cannot prove that a `down()` body restores data or will succeed. A normal `down(): void` can still throw, perform irreversible work, or fail halfway through on a database without transactional DDL. `fresh` intentionally wipes tables and does not need reversible migrations. Use forward fixes for destructive production changes.

## Recovery

| Failure | Next step |
| --- | --- |
| Unassigned application or package path | Register its existing directory and prerequisites before retrying |
| Missing declared path or exclusion | Restore the expected file/path or correct the registration |
| Missing prerequisite or cycle | Correct the owner dependency declarations |
| Duplicate migration name | Give each migration one owner; use exclusions to transfer ownership explicitly |
| Installed migration has no available owner | Restore the original package and migration files before rollback |
| Retained dependent or later migration | Roll back the dependents/later executions first, or apply a forward fix |
| Unapplied prerequisite | Enable/apply the prerequisite or correct the owner relationship, then retry |
| Irreversible migration | Apply a forward fix |
| Schema dump detected | Restore the complete migration history and remove the dump from the migration workflow |

After a failed migration, Laravel's history still identifies completed migrations, which a retry skips. Inspect partially applied schema according to the database engine's transaction behavior before retrying. Never rewrite shared production migrations to make the history appear consistent.

The package does not coordinate concurrent deployments or package removal. Use the host's deployment coordination, including Laravel's migration isolation option where appropriate, and keep installed migration files available.
