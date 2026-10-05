# Contributing

Thank you for your interest in improving Eddy. This guide covers working on the scaffold template itself.

When someone runs `php init.php` to create a project from this template, this file is replaced by the generated project's own `CONTRIBUTING.md` (produced from `CONTRIBUTING.dist.md`). Keep scaffold-specific notes here and consumer-facing notes in `CONTRIBUTING.dist.md`.

## What lives where

The repository is a working Drupal extension - the demo extension in the project root - plus the tooling that turns it into a reusable template:

- `init.php` - the interactive script that renames, rewrites and prunes the template files for a new project.
- `scripts/eddy-tooling` - installs the `drevops/eddy-tooling` package, whose commands build, provision and deploy the extension, into `vendor/`. Generated projects get the package from Packagist; this repository installs `.eddy/tooling` as a symlink, so an edit there applies to the next command without a release.
- `.eddy/` - everything used to develop and test the scaffold itself, including the source of the tooling package. It is removed from generated projects.

## Building and testing the demo extension

The scaffold builds and tests itself exactly like a generated project, so the commands documented in [`README.md`](README.md) apply here too: `make build` or `ahoy build` to assemble the site, `make lint` or `ahoy lint` to check coding standards, and `make test` or `ahoy test` to run the extension tests.

## The `.eddy` directory

- `.eddy/assets/` - source files and the generator for the animated demos embedded in `README.md`.
- `.eddy/tests/` - the PHPUnit suite that validates the scaffold: the `init.php` flow, the tooling commands and their installer, and the resulting project structure. Snapshot fixtures live under `.eddy/tests/fixtures/init/`.
- `.eddy/tooling/` - the source of the [`drevops/eddy-tooling`](https://github.com/drevops/eddy-tooling) package, published to its own read-only repository (see below).
- `.eddy/skills/` - the `update-consumer-eddy` skill that generated projects fetch to update themselves.

## Running the scaffold self-tests

Run these from the repository root. Install the dependencies once:

```bash
composer --working-dir=.eddy/tests install
```

| Action           | Command                                                        |
|------------------|----------------------------------------------------------------|
| All tests        | `composer --working-dir=.eddy/tests test`                      |
| A single group   | `composer --working-dir=.eddy/tests test -- --group=p0`        |
| A single class   | `composer --working-dir=.eddy/tests test -- --filter=InitTest` |
| Coding standards | `composer --working-dir=.eddy/tests lint`                      |

Tests are tagged `p0` to `p5` so CI can run them as parallel jobs. `p0` is the in-process unit suite, `p1` is the `init.php` snapshot test, and `p2` to `p5` exercise the full build pipeline and need a Drupal-friendly PHP setup. `p3` and `p4` also need a WebDriver backend: a Selenium container or a local Chrome driven by chromedriver.

## Regenerating snapshot fixtures

Files under `.eddy/tests/fixtures/init/` are generated - never edit them by hand. After any change that affects `init.php` output, regenerate them:

1. Commit your source changes first, as their own commit.
2. From `.eddy/tests`, run the update command:

```bash
cd .eddy/tests
composer update-snapshots
```

It commits the regenerated baseline on its own, then amends it with each dataset fixture. Review the result with `git show --stat` and confirm `composer test -- --filter=InitTest` passes before pushing.

## Publishing the tooling package

Changes to the commands go into `.eddy/tooling/`, where the scaffold's own tests and builds use them straight away. On every push to `1.x`, `.github/workflows/scaffold-publish-tooling.yml` mirrors the directory to the `1.x` branch of [`drevops/eddy-tooling`](https://github.com/drevops/eddy-tooling), which Packagist serves. The workflow pushes with the `EDDY_TOOLING_DEPLOY_KEY` secret, a deploy key with write access to that repository.

Releases are tagged on the mirror by hand:

1. Tag the mirror commit that corresponds to the `1.x` commit you're releasing, for example `1.0.1`. Generated projects require `~1.0.0`, so they pick up a patch release on their next fresh install.
2. For a new minor version, also raise the constraint in `composer.dev.json` (for example to `~1.1.0`). Generated projects receive it with their next scaffold update.

## Continuous integration

`.github/workflows/scaffold-test.yml` runs the suite across the `p0` to `p5` groups. See [`.eddy/CLAUDE.md`](.eddy/CLAUDE.md) for the full maintenance reference, including how to regenerate the animated demo assets.
