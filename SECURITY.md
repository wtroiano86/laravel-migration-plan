# Security policy

## Report a vulnerability

Email [opensource.troiano86@pm.me](mailto:opensource.troiano86@pm.me) to report suspected vulnerabilities in `wtroiano/laravel-migration-plan`. Please keep vulnerability details out of public GitHub issues, pull requests, and discussions while a report is being investigated.

Include the affected package, PHP, and Laravel versions; a description of the impact; and a minimal reproduction or proof of concept. Describe any relevant migration ownership, dependency, or rollback configuration. Remove credentials, personal information, and production data from examples and logs.

The maintainer will investigate and coordinate any fix and public disclosure with the reporter. Response and remediation times depend on the issue and maintainer availability; there is no guaranteed response deadline.

## Supported releases

The latest patch release in the `0.1.x` line receives security fixes. Older patches and unpublished development branches are not maintained as separate security release lines. Supported release lines will be updated here when that policy changes.

Use the latest supported patch and the PHP/Laravel versions declared in `composer.json`. For non-sensitive bugs and usage questions, use the [issue tracker](https://github.com/wtroiano86/laravel-migration-plan/issues).

## Scope and limitations

This package validates migration ownership, dependency order, and rollback eligibility. It executes application and package migration PHP with the application's privileges; it does not sandbox untrusted migrations. Preflight validation cannot guarantee data restoration, atomic DDL, or safe concurrent deployments. See [Operations](docs/OPERATIONS.md) for execution and rollback limits.
