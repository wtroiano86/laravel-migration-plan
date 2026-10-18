# Changelog

Release versions come from Git tags. Dates below record publication dates; unreleased work has no publication date.

## [Unreleased]

## [0.1.0] - 2026-10-18

### Added

- Migration owners with explicit prerequisites, deterministic execution order, and file exclusions.
- Validation of missing owners, paths, prerequisites, dependency cycles, and duplicate migration names before database writes.
- Ordered Laravel migration, status, rollback, reset, refresh, and fresh commands with explicit activation.
- Rollback preflight for unavailable files, retained dependents, execution order, and irreversible migrations.
- Runtime guards that prevent skipped prerequisites from allowing dependent migrations to run.
- Readonly data classes, focused actions, compilation and rollback pipelines, and operation-scoped plan resolution.
- Structured domain errors and database-independent `migration-plan:check` / `migration-plan:show` commands, including versioned JSON output.
- Laravel package discovery, publishable configuration, and compatibility with custom host adapters.
- Consumer and contributor documentation, Laravel Boost guidelines, and a consumer AI skill.
- Quality checks for formatting, static analysis, type coverage, and line coverage; minimum/latest dependency testing; SQLite, PostgreSQL, and MySQL integration coverage.

### Requirements

- PHP 8.5 or later within PHP 8, Laravel 13.34 or later within Laravel 13, and Symfony Console 8.
- Ordered migrations are disabled by default. Register every effective migration path before activation.

[Unreleased]: https://github.com/wtroiano86/laravel-migration-plan/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/wtroiano86/laravel-migration-plan/releases/tag/v0.1.0
