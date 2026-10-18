# Contributing to the migration plan package

Use PHP 8.5 with SQLite enabled. Install dependencies from the package directory; Composer prepares Testbench's discovery automatically.

Use the [issue tracker](https://github.com/wtroiano86/laravel-migration-plan/issues) for non-sensitive bugs and proposals. For vulnerabilities, follow [SECURITY.md](SECURITY.md) and report privately to `opensource.troiano86@pm.me`.

```bash
composer install
composer test
```

Coverage requires PCOV or Xdebug in coverage mode. With Xdebug:

```bash
XDEBUG_MODE=coverage composer test
```

## Test suites

| Suite | Purpose |
| --- | --- |
| `Architecture` | Finality, immutable data, extension points, and dependency boundaries |
| `Unit` | Graph and rollback policy without a database; resolution with temporary files |
| `Integration` | Real migration execution, history, failures, and rollback using SQLite, PostgreSQL, or MySQL |
| `Feature` | Testbench providers, publishing, and Artisan command behavior |

Keep tests focused on observable behavior. Preserve assertions that invalid plans leave schema and history unchanged. Cover transitive dependencies, ordering independent of timestamps, operation cleanup on failure, and new files discovered by later runs. A 100% line coverage result does not replace meaningful branch and failure tests.

```bash
composer test:unit -- tests/Unit/Actions/ValidateRollbackTest.php
composer test:unit -- tests/Integration/Migrations/OrderedMigratorTest.php
composer test:unit -- tests/Feature/Console/MigrationCommandsTest.php
```

`test:unit` is the existing name for the Pest runner and can run any suite or file. Migration fixtures belong in `tests/Fixtures/migrations`; copy them to a temporary directory before use.

## Database integration tests

Without additional configuration, integration tests use in-memory SQLite. To use a server, create a dedicated database named `migration_plan_test`, enable the corresponding PDO extension, and supply its URL:

```bash
MIGRATION_PLAN_TEST_DATABASE_URL='postgres://migration_plan:testing@127.0.0.1:5432/migration_plan_test' \
  composer test:unit -- --testsuite=Integration

MIGRATION_PLAN_TEST_DATABASE_URL='mysql://migration_plan:testing@127.0.0.1:3306/migration_plan_test' \
  composer test:unit -- --testsuite=Integration
```

These are example credentials for disposable test servers. The harness drops all tables before and after each test, and rejects server databases with any other name. Do not run multiple suites concurrently against the same database. Feature tests continue to use their own in-memory SQLite connections. CI provisions separate PostgreSQL 16 and MySQL 8.4 service containers and runs this integration suite on each.

Container-based PHP wrappers must pass `MIGRATION_PLAN_TEST_DATABASE_URL` into the PHP container; setting it only on the host shell may be insufficient.

## Quality checks

`composer lint` applies Rector and Pint. `composer test` checks formatting, Rector, PHPStan at maximum level, 100% type coverage, architecture rules, and exactly 100% line coverage. Individual commands are `test:lint`, `test:types`, `test:type-coverage`, and `test:coverage`.

CI uses clean installations with both `composer update --prefer-lowest --prefer-stable` and `composer update --prefer-stable`. Use a separate checkout for dependency experiments so another developer's installed dependencies remain intact.

CI audits latest dependencies for known vulnerabilities, reports abandoned development dependencies, and rejects abandoned runtime dependencies. The distribution job also checks that consumer documentation and Boost resources are included in the Composer archive. See [Releasing](docs/RELEASING.md) for audit commands and the current tooling maintenance note.

## Design changes

Preserve the public registration and array APIs when changing internal data structures. Keep required validation ahead of database writes. Use immutable data for values and focused actions for domain algorithms; keep orchestration in the pipelines and framework execution in the migrator.

Update the API, architecture, operations, or compatibility documentation alongside behavior changes. New command restrictions need a test proving rejection before side effects. Framework integration changes need command-level tests, even when an isolated algorithm test already passes.

## Consumer documentation and Boost resources

Keep [Getting started](docs/GETTING_STARTED.md) and [Recipes](docs/RECIPES.md) focused on consuming applications. Contributor commands belong here, not in instructions for installed vendor packages.

The package ships `resources/boost/guidelines/core.blade.php` and `resources/boost/skills/laravel-migration-plan/SKILL.md`. Keep the guideline short; put task workflows in the skill and full explanations in `docs/`. The skill is copied into a consumer's agent directory, so documentation references must identify the installed package root rather than assume paths relative to the copied skill.

For changes to these resources, check Blade rendering, skill frontmatter/discovery, documentation links, PHP examples, and inclusion in the Composer archive. Verify discovery with a compatible Boost installation in a consumer that directly requires this package. Do not regenerate a contributor's agent instructions merely to validate package resources. Boost is optional; no Boost provider or dependency is required in the migration runtime.

Build a local distribution from the package root to inspect its contents:

```bash
mkdir -p tmp
composer archive --format=zip --file=laravel-migration-plan --dir=tmp
```

The archive must include `docs/` and `resources/boost/`. Export exclusions keep dependencies, test fixtures, caches, and temporary files out of the distribution, including archives built from a working checkout with development dependencies installed.

## Releases

Follow [Releasing](docs/RELEASING.md) for the release checklist, version policy, tag workflow, and Packagist setup. Add user-visible changes to [CHANGELOG.md](CHANGELOG.md) under Unreleased. Composer versions come from Git tags; keep the package manifest free of a hard-coded version.
