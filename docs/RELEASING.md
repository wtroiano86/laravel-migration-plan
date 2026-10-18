# Releasing the package

The source repository is [wtroiano86/laravel-migration-plan](https://github.com/wtroiano86/laravel-migration-plan). The Composer package remains `wtroiano/laravel-migration-plan`; the GitHub account name does not change the Composer vendor name.

Examples below use the initial release, `v0.1.0`; substitute the version being released for later releases. Keep upcoming changes under **Unreleased** until the release commit is prepared. Editing release files does not publish a tag, GitHub release, or Packagist package.

## Version policy

Use annotated tags named `vMAJOR.MINOR.PATCH`. Composer and Packagist infer versions from Git tags, so do not add a `version` field to `composer.json`. The release workflow accepts numeric release tags; prerelease tags require an intentional workflow update.

While the package is below `1.0`, keep fixes compatible within a minor line and use a new minor version for breaking changes. Describe changes to registration signatures, typed results, JSON output, error context, provider bindings, and migration execution or rollback semantics in the changelog. Update the supported lines in [SECURITY.md](../SECURITY.md) when maintenance policy changes.

See Composer's [version metadata guidance](https://getcomposer.org/doc/04-schema.md#version) and [library versioning guidance](https://getcomposer.org/doc/02-libraries.md#library-versioning).

## One-time repository setup

1. Push the reviewed package history and release-preparation files to the repository. Preserve the existing local branch name unless the project deliberately adopts a different default branch.
2. Enable GitHub Actions. Require the quality, database integration, and distribution checks on the default branch. Configure tag rules so only maintainers can create release tags and published tags cannot be rewritten or deleted.
3. Ensure repository policy allows the release job's `contents: write` permission. The workflow uses GitHub's built-in token to create a draft; no personal access token or Packagist secret is required in Actions.
4. Submit `https://github.com/wtroiano86/laravel-migration-plan` through [Packagist's submission page](https://packagist.org/packages/submit) using an account authorized to publish the `wtroiano` package. Configure Packagist's GitHub integration for automatic updates and verify that it recognizes the package name.
5. Verify the repository's security policy and issue link point reporters to `opensource.troiano86@pm.me`.

Follow [Packagist's publishing and update instructions](https://packagist.org/about). Package registration and repository settings are account-level actions; committing this repository's files does not configure them.

## Prepare the release commit

Work from the reviewed default branch and resolve outstanding changes before tagging:

```bash
git status --short
composer validate --strict
composer install
composer audit --locked --abandoned=report
composer audit --locked --no-dev
composer test
```

Coverage requires PCOV or Xdebug; see [Contributing](../CONTRIBUTING.md). CI also resolves minimum and latest supported dependencies and runs PostgreSQL/MySQL integration tests. Run relevant host adapter tests when the public bindings or framework hooks change.

The first audit blocks known vulnerabilities across all dependencies and reports abandoned tooling; the second also blocks abandoned runtime dependencies. At initial release preparation, Rector's development dependency `driftingly/rector-laravel` requires the abandoned `symplify/rule-doc-generator-contracts`. It is not a runtime dependency. Review reported tooling maintenance issues during releases and update or replace the upstream tool when a supported option is available.

Review the consumer docs and both Laravel Boost resources against the release behavior. Confirm the MIT license, repository links, and security address are correct.

Move the release notes from **Unreleased** to a dated heading such as `## [0.1.0] - YYYY-MM-DD`, replacing the date with the actual release date. Leave an empty Unreleased section for future work and add the release link. For the first release, use:

```markdown
[Unreleased]: https://github.com/wtroiano86/laravel-migration-plan/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/wtroiano86/laravel-migration-plan/releases/tag/v0.1.0
```

The release workflow rejects missing, undated, or empty notes for the tagged version. Commit the reviewed changes, push the branch, and wait for all checks to pass.

## Inspect the distribution

```bash
mkdir -p tmp
composer archive --format=zip --file=laravel-migration-plan --dir=tmp
unzip -l tmp/laravel-migration-plan.zip
```

The archive must include the source, publishable configuration, license, security policy, changelog, consumer docs, Boost guideline, and Boost skill. It must exclude vendor dependencies, tests, workflows, caches, and temporary files. The CI distribution job checks required resources and export exclusions on every branch and pull request.

## Tag and review the release

After the release commit passes CI, confirm the working tree is clean and the remote points to this repository. For the initial release:

```bash
git remote -v
git status --short
git tag -a v0.1.0 -m 'Release v0.1.0'
git push origin v0.1.0
```

Pushing a tag makes that version public and can make it installable through Packagist immediately. A draft GitHub release does not delay Composer availability. Complete compatibility review before pushing the tag.

The **Prepare release** workflow runs the same quality, dependency, database, and distribution checks against the tag. On success, it creates a **draft** GitHub release using that version's changelog section, with a Composer ZIP archive and `SHA256SUMS` attached. It does not publish the GitHub release automatically.

Review the draft's notes, tag commit, assets, and workflow results, then publish it from GitHub when ready. If the workflow fails before creating the draft, fix the cause and rerun it as appropriate. If a release already exists, review that release; the workflow does not overwrite existing releases. Never move a tag already available to consumers; ship a new patch for code corrections.

## Verify publication

Confirm Packagist indexed the expected tag and requirements. In a clean Laravel application using supported PHP/Laravel versions, install the release:

```bash
composer require wtroiano/laravel-migration-plan:^0.1
composer show wtroiano/laravel-migration-plan
php artisan vendor:publish --tag=migration-plan-config
```

Follow [Getting started](GETTING_STARTED.md) to register ownership, inspect the plan, and activate execution on a disposable database. Verify the inspection commands and normal migration lifecycle. If the application uses Boost, follow [Laravel Boost](LARAVEL_BOOST.md) and confirm both package resources install for the selected agent.

For a local path repository before tagging, Composer may infer a development branch instead of `0.1.0`. Declare the intended development constraint and, when needed, a path repository `options.versions` override in the consuming application's local Composer configuration. Do not restore a hard-coded package version to resolve a local development constraint.
