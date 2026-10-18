# Laravel Boost and AI agents

The package ships guidance in Laravel Boost's standard third-party resource locations:

| Resource | Purpose |
| --- | --- |
| [`resources/boost/guidelines/core.blade.php`](../resources/boost/guidelines/core.blade.php) | Short package conventions included in the agent's generated guidelines |
| [`resources/boost/skills/laravel-migration-plan/SKILL.md`](../resources/boost/skills/laravel-migration-plan/SKILL.md) | Task-specific instructions for ownership, inspection, and migration troubleshooting |

This follows Laravel's documented [package guideline](https://laravel.com/docs/13.x/boost#third-party-package-ai-guidelines) and [package skill](https://laravel.com/docs/13.x/boost#third-party-package-skills) formats. The integration is verified against Boost 2.10. Boost stays a development dependency of the consuming application; it is not a runtime dependency of this package.

## Install in a consuming application

Run these commands from the application's root, after installing `wtroiano/laravel-migration-plan`:

```bash
composer require laravel/boost --dev
php artisan boost:install
```

Select your coding agents, enable guidelines and skills, and select `wtroiano/laravel-migration-plan` when Boost asks which third-party packages to include. MCP configuration is an additional Boost feature; these package resources do not define their own MCP server.

For an application that already uses Boost, add this package's resources with:

```bash
php artisan boost:install --guidelines --skills
```

Select the package and retain any other package guidance the application already uses. Boost records these choices in the application's `boost.json`.

Boost 2.10 discovers third-party resources from **direct dependencies**. If this library is present only through another package, declare a compatible direct requirement in the consuming application's `composer.json` before selecting its guidance. A package being present somewhere in `vendor/` does not by itself ensure its resources are installed.

## Verify discovery and refresh guidance

```bash
php artisan boost:list-skills
php artisan boost:update
```

The available skills list should include `laravel-migration-plan`. Also check the selected agent's generated guideline file for the `wtroiano/laravel-migration-plan` section and its skills directory for the installed skill. The available-skills list confirms discovery; the generated files confirm installation for that agent.

Refresh resources after updating this package. An unattended `boost:update --no-interaction` refreshes existing selections but does not opt into a newly discovered third-party package. Use the interactive installation command when initially selecting this package or enabling skills.

If guidance is missing, check:

1. `composer show wtroiano/laravel-migration-plan --path` resolves an installed package containing the `resources/boost` directory.
2. The application directly requires the package and selected it in Boost.
3. Guidelines and skills are enabled for the intended agent.
4. Application exclusions in `config/boost.php` or local skill overrides are not hiding these resources.
5. The agent has reloaded its instructions after Boost regenerated the files.

## What agents learn

The guideline establishes the shared `MigrationPlan` API, explicit activation, ownership requirements, and command restrictions. The `laravel-migration-plan` skill adds registration examples, inspection steps, structured error handling, skip/rollback semantics, and links to the installed consumer documentation.

Example requests:

- “Use laravel-migration-plan to make the invoicing package depend on the customer schema.”
- “Inspect the migration plan and explain why this rollback is blocked.”
- “Register this package's published migration without changing its deployed filename.”

The skill follows the consumer's existing provider conventions and task scope. Installing guidance does not activate ordered migrations or authorize running destructive commands.

## Agents without Boost

The same instructions can be read directly from the installed package. Start with the package's `README.md`, then open `resources/boost/skills/laravel-migration-plan/SKILL.md` for the task workflow. For the default Composer vendor directory, these files live under `vendor/wtroiano/laravel-migration-plan/`; use `composer show ... --path` if the application uses another layout.

Keep application-specific owner names, deployment commands, and policies in the application's own agent instructions. The package documentation describes reusable behavior rather than a particular host's conventions.
