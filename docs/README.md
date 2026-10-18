# Package documentation

`wtroiano/laravel-migration-plan` gives ordinary Laravel migrations explicit owners and prerequisites. It resolves a deterministic plan, runs prerequisites first, and validates rollback selections before executing them. The consumer chooses the ownership model and activation point.

## For consuming applications

| Task | Guide |
| --- | --- |
| Install and activate the package | [Getting started](GETTING_STARTED.md) |
| Register package dependencies or published migrations | [Consumer recipes](RECIPES.md) |
| Adopt existing migration history | [Adoption recipe](RECIPES.md#adopt-an-existing-application) |
| Inspect declarations or parse JSON output | [API reference](API.md#console-inspection) |
| Run migrations, diagnose skips, or understand rollback failures | [Operations](OPERATIONS.md) |
| Use custom host providers and adapters | [Compatibility](COMPATIBILITY.md) |
| Teach AI agents the package conventions | [Laravel Boost](LARAVEL_BOOST.md) |

Start with the application registration example. Inspection commands can validate declarations before ordered execution is enabled. They do not change schema or query migration history.

## For package maintainers

| Task | Guide |
| --- | --- |
| Understand data classes, actions, and pipelines | [Architecture](ARCHITECTURE.md) |
| Preserve public types and error contracts | [API reference](API.md) |
| Run package tests and quality checks | [Contributing](../CONTRIBUTING.md) |
| Review framework upgrades | [Compatibility](COMPATIBILITY.md#framework-upgrades) |
| Prepare a tag and publish a release | [Releasing](RELEASING.md) |
| Review release changes | [Changelog](../CHANGELOG.md) |
| Report a vulnerability privately | [Security policy](../SECURITY.md) |

The application owns its migrations and registrations. The library owns plan compilation, validation, and Laravel integration. Keep application-specific policies in the consumer and update these documents when package behavior changes.
