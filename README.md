<p align="center">
  <a href="" rel="noopener">
  <img width="320" src="logo.svg" alt="Eddy logo"></a>
</p>

<h1 align="center">Eddy</h1>
<h3 align="center">Template for contributed Drupal modules and themes</h3>

<div align="center">

[![GitHub Issues](https://img.shields.io/github/issues/drevops/eddy.svg)](https://github.com/drevops/eddy/issues)
[![GitHub Pull Requests](https://img.shields.io/github/issues-pr/drevops/eddy.svg)](https://github.com/drevops/eddy/pulls)
[![Build, test and deploy](https://github.com/drevops/eddy/actions/workflows/test.yml/badge.svg)](https://github.com/drevops/eddy/actions/workflows/test.yml)
[![codecov](https://codecov.io/gh/drevops/eddy/graph/badge.svg)](https://codecov.io/gh/drevops/eddy)
![GitHub release (latest by date)](https://img.shields.io/github/v/release/drevops/eddy)
![LICENSE](https://img.shields.io/github/license/drevops/eddy)
![Renovate](https://img.shields.io/badge/renovate-enabled-green?logo=renovatebot)

![PHP 8.3](https://img.shields.io/badge/PHP-8.3-777BB4.svg)
![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777BB4.svg)
![PHP 8.5](https://img.shields.io/badge/PHP-8.5-777BB4.svg)
![Drupal 10](https://img.shields.io/badge/Drupal-10-009CDE.svg)
![Drupal 11](https://img.shields.io/badge/Drupal-11-006AA9.svg)
![Drupal 12](https://img.shields.io/badge/Drupal-12-004B7A.svg)
</div>

---

**Eddy** is a template for maintaining a contributed Drupal module or theme on GitHub. Start your extension from it and you get CI on GitHub Actions, a local Drupal site to develop and test against, coding standards checks, and mirroring of the code to [Drupal.org](https://drupal.org).

Eddy isn't for building websites. To stand up a Drupal site, use [Vortex](https://github.com/drevops/vortex) instead. The two are separate templates for separate jobs: Vortex builds consumer websites, Eddy maintains contributed modules and themes, and a repository uses one or the other, never both.

## Index

- [Features](#features)
- [Setup overview](#setup-overview)
- [Codebase setup](#codebase-setup)
- [Building website](#building-website)
- [Coding standards](#coding-standards)
- [Testing](#testing)
- [Branch protection](#branch-protection)
- [Deployment](#deployment)
- [Updating your extension](#updating-your-extension)
- [Renovate](#renovate)
- [Projects using Eddy](#projects-using-eddy)
- [Contributing](#contributing)

## Features

- Turnkey CI configuration:
  - PHP version matrix: `8.3`, `8.4`, `8.5`.
  - Drupal version matrix: `stable` on Drupal 10, 11 and 12, plus `legacy` and `canary` tiers on Drupal 11 and 12.
  - CI provider: [GitHub Actions](.github/workflows/test.yml)
  - Code coverage with https://github.com/krakjoe/pcov pushed to [codecov.io](https://codecov.io).
  - Compatible with Drupal.org GitLab CI ([DrupalCI](#drupalorg-ci-drupalci)).
- Develop locally using PHP running on your host with the same [tooling](.eddy/tooling) commands as CI, installed from the `drevops/eddy-tooling` Composer package:
  - Uses [drupal/recommended-project](https://www.drupal.org/docs/develop/using-composer/starting-a-site-using-drupal-composer-project-templates) to create Drupal site structure.
  - Additional development dependencies provided in [`composer.dev.json`](composer.dev.json). These are merged during the codebase assembly.
  - The extension can be installed as a module or a theme: modify `type` property set in the `info.yml` file.
  - Additional dependencies can be added for integration testing between extensions: add dependencies into `suggest` section of `composer.json` and they will be included into the assembled codebase.
  - Patches can be applied to the dependencies: declare a patch in the `patches` section of `composer.dev.json` or `composer.json` and the build applies it with [composer-patches](https://github.com/cweagans/composer-patches). Local patches are sourced from the `patches` directory.
  - Command wrappers using `make` and [Ahoy](https://github.com/ahoy-cli/ahoy) for common tasks.
- Coding standards checking:
  - PHP code standards checking against `Drupal` and `DrupalPractice` standards.
  - PHP code static analysis with PHPStan (including [PHPStan Drupal](https://github.com/mglaman/phpstan-drupal)).
  - PHP deprecated code analysis and auto-fixing with [Drupal Rector](https://github.com/palantirnet/drupal-rector).
  - Twig code analysis with [Twig CS Fixer](https://github.com/VincentLanglet/Twig-CS-Fixer).
  - JavaScript code analysis with [ESLint](https://eslint.org/).
  - CSS code analysis with [Stylelint](https://stylelint.io/).
  - Spell checking with [CSpell](https://cspell.org/).
  - Code formatting with [Prettier](https://prettier.io/).
- PHPUnit tests: Unit, Kernel, Functional and FunctionalJavascript (in a local Chrome or a Selenium container).
- JavaScript unit tests with [Jest](https://jestjs.io/).
- [Renovate](#renovate) configuration to keep dependencies up-to-date with grouped PRs.
- [README.md](README.dist.md) and [CONTRIBUTING.md](CONTRIBUTING.dist.md) templates for your extension.
- Deployment:
  - Mirroring of the repo to Drupal.org (or any other git repo) after CI passes.
  - Deploy to a destination branch different from the source branch.
  - Tags mirroring.
- This template is tested in the same way as a project using it. See an example of the deployment destination repository for [GitHub Actions](https://github.com/AlexSkrypnyk/drupal_extension_scaffold_destination_github).

## Setup overview

1. Download this template's code with **Code** -> **Download ZIP** in the GitHub UI.
2. Expand into a new directory.
3. Run the initial [codebase setup](#codebase-setup) script: `php init.php`.
4. If you already have existing extension code, copy it into the directory created in step 2.
5. [Build website](#building-website) with `make build` or `ahoy build` to check that everything is set up correctly.
6. [Check coding standards](#coding-standards) with `make lint` or `ahoy lint`.
7. [Run tests](#testing) with `make test` or `ahoy test`.
8. Create your extension's repository on GitHub.
9. Commit and push to your new GitHub repo.
10. [Configure branch protection in GitHub](#branch-protection).
11. [Configure deployment](#deployment) to Drupal.org.

See the sections below for more details.

## Codebase setup

The initial codebase setup script `php init.php` will ask you for some information and update the codebase to reflect your extension's name and other details. It asks for the extension name, machine name and type, the Drupal versions to target, the command wrapper, the tools to keep, and whether to keep the example lifecycle scripts. Each answer can be pre-filled with an `INIT_*` environment variable for an unattended run - `php init.php --help` lists them.

![Init process](.eddy/assets/init.svg)

## Building website

`make build` or `ahoy build` assembles the codebase, starts the PHP server and provisions the Drupal website with your extension enabled. These operations are run by the commands of the [`drevops/eddy-tooling`](#the-tooling-package) package. CI uses the same commands to build and test your extension.

The resulting codebase is then placed in the `build` directory. Your extension files are symlinked into the Drupal site structure.

The `build` command is a wrapper for more granular commands:
```bash
make assemble     # Assemble the codebase
make start        # Start the PHP server
make provision    # Provision the Drupal website

ahoy assemble     # Assemble the codebase
ahoy start        # Start the PHP server
ahoy provision    # Provision the Drupal website
```

The `provision` command is useful for re-installing the Drupal website without re-assembling the codebase.

Your extension documents the same commands for its own contributors in [CONTRIBUTING.md](CONTRIBUTING.dist.md).

![Build process](.eddy/assets/build.svg)

### The tooling package

The commands that assemble, start, provision and deploy your extension ship as the [`drevops/eddy-tooling`](.eddy/tooling) Composer package rather than as files copied into your project, so fixes reach you through a version constraint instead of a manual merge. The package can't come from the site build it assembles, so `scripts/eddy-tooling` installs it into `vendor/`, with each command in `vendor/bin/eddy-*`. `make` and `ahoy` run the installer before their commands and CI runs it as a step of its own; without a wrapper, run `scripts/eddy-tooling` once yourself.

The version constraint lives in `composer.dev.json`, next to the other development dependencies:

```json
"drevops/eddy-tooling": "~1.0.0"
```

The `~` accepts patch releases and holds the minor version, so CI picks up fixes on its own and a new minor version arrives with the next scaffold update. The installer runs Composer only when the constraint, the patches declared for the package or the contents of a local patch file change, or when a command is missing, so running it before every command costs next to nothing. A local checkout keeps the patch release it installed until `vendor/` is removed, which `make reset` and `ahoy reset` do.

To add a project-specific step, use a [custom lifecycle script](#custom-lifecycle-scripts). To change what a command itself does, declare a patch for the package in `composer.dev.json` as described in the [package README](.eddy/tooling/README.md#patching-the-package).

### Drupal versions

The Drupal version used for the codebase assembly is determined by the `DRUPAL_VERSION` variable and defaults to `11`, the newest stable Drupal 11 release. The default is the `extra.eddy.drupal-version` value in `composer.dev.json`, which `init.php` sets to the highest Drupal major you select.

You can specify a different version by setting the `DRUPAL_VERSION` environment variable before running the `make build` or `ahoy build` command:

```bash
# Newest stable Drupal 11 release.
DRUPAL_VERSION=11 make build

# Newest Drupal 11.1.x patch release.
DRUPAL_VERSION=11.1.0 make build

# Newest Drupal 11 beta, release candidate or stable release.
DRUPAL_VERSION=11@beta make build

# Newest stable Drupal 12 release, or newest pre-release if none.
DRUPAL_VERSION=12 make build
```

#### How versions are resolved

The build resolves versions in 2 steps, so nothing needs editing as Drupal and its tools publish new releases:

1. **Drupal core is pinned to one exact release.** The build picks the newest release matching `DRUPAL_VERSION`, prints it and pins core to it. A version with no stable release yet resolves to its newest pre-release.
2. **Every other dependency gets its most stable release that fits.** The build sets `minimum-stability: dev` with `prefer-stable: true`, so `composer.dev.json` and your `composer.json` only say which versions are acceptable. A dependency falls back to a pre-release or a development branch only when no stable release supports the chosen core, and the build output lists every dependency installed from a development branch.

For example, as of October 2026:

| `DRUPAL_VERSION` | Drupal core | Drush |
|------------------|-------------|-------|
| `11` | `11.4.8` | `13.8.0` |
| `11@beta` | `11.4.8`, then the 11.5 pre-releases once they exist | `13.8.0` |
| `12` | `12.0.0-beta1`, then `12.0.0` once it ships | `14.x-dev`, then `14.0.0` once it's tagged |

### CI Drupal version matrix

The CI configuration ([GitHub Actions](.github/workflows/test.yml)) tests the extension against deliberate *role corners* rather than a full cross-product of every PHP and Drupal version. 10 jobs cover the lowest and highest supported PHP on each Drupal major, plus the next minor pre-release and the oldest tested minor of Drupal 11 and 12:

| Job | PHP | Drupal | Role |
|-----|-----|--------|------|
| `test-php-min-d10-stable` | `8.3` | `10` | Drupal 10 on the lowest supported PHP |
| `test-php-max-d10-stable` | `8.4` | `10` | Drupal 10 on the highest supported PHP |
| `test-php-min-d11-stable` | `8.3` | `11` | Drupal 11 on the lowest supported PHP |
| `test-php-max-d11-stable` | `8.5` | `11` | Drupal 11 on the highest supported PHP |
| `test-php-min-d11-legacy` | `8.3` | `11.1.0` | Oldest tested Drupal 11 minor (pinned) |
| `test-php-max-d11-canary` | `8.5` | `11@beta` | Next Drupal 11 minor pre-release |
| `test-php-min-d12-stable` | `8.5` | `12` | Drupal 12 on the lowest supported PHP |
| `test-php-max-d12-stable` | `8.5` | `12` | Drupal 12 on the highest supported PHP |
| `test-php-min-d12-legacy` | `8.5` | `12.0.0` | Oldest tested Drupal 12 minor (pinned) |
| `test-php-max-d12-canary` | `8.5` | `12@beta` | Next Drupal 12 minor pre-release |

Each job name encodes the PHP bound (`min`/`max`), the Drupal major (`d10`/`d11`/`d12`) and the release tier (`stable`/`legacy`/`canary`). The exact PHP version for each job is shown in its "Setup PHP" step.

The two axes behave differently:

- **Drupal versions float, except for the pinned `legacy` minors.** `stable` (`10`, `11`, `12`) resolves to the newest stable release, and `canary` (`11@beta`, `12@beta`) to the newest release at beta stability or above - the next minor's beta or release candidate when there is one, otherwise the current stable release. A major or minor with no stable release yet resolves to its newest pre-release instead. The `legacy` minors (`11.1.0` and `12.0.0`, each resolving to the newest patch of its minor) are pinned on purpose so the jobs genuinely exercise an older minor.
- **PHP versions are pinned bounds.** The GitHub Actions setup action can't float a PHP version - it takes an explicit version - so the `min` and `max` PHP values are fixed. They change rarely: `max` when a newer PHP is added, `min` only when support for an old PHP is dropped.

Because `stable` and `canary` float, the matrix follows Drupal core on its own - a new stable minor or pre-release is picked up on the next CI run with no manual changes. The pinned `legacy` minors and the PHP versions are set-and-forget: they keep exercising the same floor indefinitely, so there is nothing you have to maintain by hand. When you want to move that floor forward as core and PHP advance, re-pull from the scaffold (see [Updating your extension](#updating-your-extension)) - this template tracks the versions Drupal core provides, so updating from it refreshes the `legacy` pins and the PHP versions for you.

#### Drupal 12

Drupal 12 has no stable release yet, but its jobs use the same kind of values as the Drupal 11 ones: `12` for `stable`, `12.0.0` for `legacy` and `12@beta` for `canary`. Until 12.0.0 ships, all of them build the newest Drupal 12 pre-release. After that, each moves on its own: all of them to 12.0.0 when it ships, then `canary` to the 12.1 pre-releases while `legacy` stays on the 12.0.x patches. Nothing in the CI configuration needs editing along the way.

Drupal 12 requires PHP 8.5, which is already the matrix ceiling, so every Drupal 12 job runs PHP 8.5 for now. The `min` and `max` jobs split apart as soon as a newer PHP joins the matrix.

Some of the Drupal 12 toolchain is still catching up - Drush, for one, supports Drupal 12 only on its `14.x` development branch. Drupal 12 builds install that branch for now and move to Drush 14.0.0 on their own once it's tagged - see [How versions are resolved](#how-versions-are-resolved).

Drupal 12 core packages also leave out every `tests` directory, and with it the test base classes and the PHPUnit bootstrap your tests need. So Drupal 12 builds install `drupal/core` from source (a git checkout) rather than from its package archive. The checkout is bigger than the archive, so Drupal 12 builds take a little longer to assemble.

Drupal 12 is opt-in when you run `init.php`: only Drupal 11 starts checked.

### Distributing tools across CI runners

A test job declares which tools it runs in a single variable block at the top of the job, and each tool step reads only its own flag. Moving a tool onto a different runner is one edit in one place rather than a repeated runner-index condition spread across every step that belongs to that tool - the kind of edit where missing one step is silent, because the step then either runs on every runner (duplicated work) or on none (the tool stops running and nothing reports it).

Two anchors are always declared, whichever tools are enabled:

| Variable | Meaning |
|----------|---------|
| `CI_RUNNER_INDEX` | Zero-based index of the current runner. |
| `CI_RUNNER_TOTAL` | How many runners the job has. |

Every tool adds one flag of its own, named `CI_IS_<TOOL>_RUNNER` and declared inside that tool's block so that removing the tool removes its flag too. Use one flag per tool rather than a single shared "primary runner" flag: a shared flag reads wrong as soon as it gates tools that have nothing to do with each other, and it cannot put two tools on two different runners.

**GitHub Actions.** The matrix in [`.github/workflows/test.yml`](.github/workflows/test.yml) is a Drupal and PHP matrix, so each leg is a runner covering a different version pair and every tool has to run on all of them. The flags are therefore `true`:

```yaml
env:
  CI_RUNNER_INDEX: ${{ strategy.job-index }}
  CI_RUNNER_TOTAL: ${{ strategy.job-total }}
  CI_IS_PHPUNIT_RUNNER: true
```

```yaml
- name: Run tests
  if: ${{ env.CI_IS_PHPUNIT_RUNNER == 'true' }}
```

To shard a tool across extra runners, add a matrix dimension for the shard and compare that tool's flag against it. A matrix change that renames or duplicates an existing leg also renames its status check, so review the required checks under [branch protection](#branch-protection) first.

One trap is worth knowing before moving a tool. Where a test runner derives a shard or profile name from the runner index, excluding runner 0 from that tool orphans the first shard, and if that shard is the catch-all then everything untagged silently stops being tested. Give such a tool the *last* runner rather than the first when it needs one to itself.

### Patching dependencies

The build installs [`cweagans/composer-patches`](https://github.com/cweagans/composer-patches) 2.x and applies the patches declared in the `patches` section of `composer.dev.json` or `composer.json`. Each entry maps a description to a local path or a URL:

```json
"extra": {
    "patches": {
        "drupal/core": {
            "Describe what the patch fixes": "patches/core-fix.patch"
        }
    }
}
```

Both composer-patches 1.x and 2.x read this compact format, and Drupal.org GitLab CI installs 1.x, so a patch declared in `composer.json` applies there as well.

A patch that no longer applies fails the build. When a dependency update conflicts with your patch, you get a failed CI run instead of a build that quietly runs without it.

Where you declare a patch decides where it reaches:

- `composer.dev.json` - the build only, locally and in CI. Use it for patches that only your tests need. Keep their files in the `patches` directory, which the build copies into `build/`, and reference them by path as above.
- `composer.json` - the build and Drupal.org GitLab CI. This file also ships with your extension, so composer-patches 2.x on a site that installs your extension applies these patches too. Reference them by a public URL, since a local `patches/` path doesn't exist on that site.

Patches declared for `drevops/eddy-tooling` itself are the exception: the build leaves them out, and `scripts/eddy-tooling` applies them when it installs [the tooling package](#the-tooling-package) into `vendor/`.

### Providing `GITHUB_TOKEN`

To overcome GitHub API rate limits, you may provide a `GITHUB_TOKEN` environment variable with a personal access token.

### Debugging command output

The output of the underlying commands (Composer, npm, Drush) is suppressed by default and shown only when a command fails. Set `DEBUG=1` to stream the full output of every command:

```bash
DEBUG=1 make build   # stream all command output
DEBUG=1 ahoy build   # same, with ahoy
```

### Optional dependencies

If your extension requires additional dependencies for integration testing between extensions, add the dependency into the `suggest` section of `composer.json`. The dependency is included in the assembled codebase and enabled in the Drupal website.

### Frontend dependencies

If your extension requires frontend dependencies for testing, add them to the `package.json` file. The `package-lock.json` file is expected to be committed to the repository.

The `assemble` command installs (`npm ci`) and builds (`npm run build`) the frontend dependencies within the `build` directory. You can add and commit a `.skip_npm_build` file to skip all Node.js processing, which will also disable JS/CSS linting (ESLint, Stylelint, Prettier) and the Jest tests.

### Provisioning the website

The `provision` command installs the Drupal website from the `standard` profile with your extension (and any `suggest`'ed extensions) enabled. The profile can be changed by setting the `DRUPAL_PROFILE` environment variable.

The website will be available at http://localhost:8000 by default. The hostname can be changed by setting the `WEBSERVER_HOST` environment variable.

#### Webserver port

The `WEBSERVER_PORT` is resolved with the following precedence:

1. **`WEBSERVER_PORT` exported in the shell** - used as-is. Useful for one-off runs: `WEBSERVER_PORT=9000 make build`.
2. **`WEBSERVER_PORT` line in the project-root `.env` file** - used as-is. The `start` script does not modify `.env` when this entry is already present, so the same port is reused across `start`, `stop`, `provision`, `drush` and `login` commands.
3. **Neither is set** - the `start` script discovers the first free port in the range `8000-8099` and writes it to `.env` as `WEBSERVER_PORT=NNNN`. Subsequent commands read this value from `.env`.

The `.env` file is loaded automatically by `make` (via `-include .env` and `export`) and `ahoy` (via the native `env:` field), so all wrapper commands see the resolved port without further configuration. The file is gitignored.

To force re-discovery, delete `.env` (or just the `WEBSERVER_PORT` line in it) and re-run `make start` / `ahoy start`.

An SQLite database is created in `/tmp/site_[EXTENSION_NAME].sqlite` file. You can browse the contents of the created SQLite database using [DB Browser for SQLite](https://sqlitebrowser.org/).

A one-time login link will be printed to the console.

### Custom lifecycle scripts

The `assemble`, `provision`, `start`, and `stop` commands each look for project-local shell scripts in the `scripts/` directory and run them during their respective phase:

- `scripts/assemble-*.sh` runs at the tail of `make assemble` / `ahoy assemble`, after dependencies are installed and the extension is symlinked into `build/`.
- `scripts/provision-*.sh` runs at the tail of `make provision` / `ahoy provision`, after the site is installed, the extension is enabled, and caches are pre-warmed.
- `scripts/start-*.sh` runs at the tail of `make start` / `ahoy start`, after the PHP webserver is up and serving.
- `scripts/stop-*.sh` runs during `make stop` / `ahoy stop`, before the webserver is stopped, while it is still reachable.

Matching files are executed in lexicographic order. The current working directory is the project root, and each script inherits the parent process environment. A non-zero exit from any script aborts the parent run.

The directory is `export-ignore`d via `.gitattributes`, so anything under `scripts/` is excluded from distribution archives published to Drupal.org. It also holds `scripts/eddy-tooling`, the [tooling installer](#the-tooling-package), which is not a hook: its name matches none of the phase prefixes.

Example scripts ship with the scaffold (`scripts/assemble-example.sh`, `scripts/provision-example.sh`, `scripts/start-example.sh`, `scripts/stop-example.sh`). Each one prints a marker line so you can see its phase fire. `init.php` asks whether to keep them and removes them unless you say yes, so answer yes if you want them as a starting point for your own hooks.

### Public HTTPS tunnel (Cloudflare)

Remote and cloud development environments (Codespaces, DevPod, a remote Docker host, an SSH dev box) cannot reach the `localhost`-bound PHP dev server directly. The [tooling](#the-tooling-package) can expose it through a [Cloudflare quick tunnel](https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/do-more-with-tunnels/trycloudflare/) - a public `*.trycloudflare.com` HTTPS URL with no account, DNS, or config:

```bash
export CLOUDFLARE_TUNNEL=1
make build
```

> [!WARNING]
> A quick tunnel publishes your local site to a public URL with no authentication in front of it - anyone with the URL can reach it while the tunnel is up. A local Drupal install typically ships with a known admin account and no firewall, so treat the exposed site as fully public: use disposable test data only, never real or sensitive content, and stop the tunnel with `make stop` when you are done.

With `CLOUDFLARE_TUNNEL` set, in the shell or in `.env`, and the [`cloudflared`](https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/downloads/) binary on `PATH`, `start` starts (or reuses a healthy) tunnel and writes its URL to `.env` as `TUNNEL_URL`. The `start`, `provision`, and `info` output, and `make`/`ahoy drush` and `login`, then use that URL. `provision` configures Drupal's reverse-proxy and trusted-host settings so the tunnel serves correctly, and `stop` tears the tunnel down and removes its URL from `.env`. Without the variable, behaviour is unchanged; with it set but `cloudflared` absent, `start` skips the tunnel with a note.

Any tool that writes a `TUNNEL_URL` to `.env` (ngrok, tailscale funnel, etc.) is picked up the same way, for example from a [custom lifecycle script](#custom-lifecycle-scripts), and `stop` leaves a URL it didn't create alone.

#### Scannable QR codes

Render any URL as a terminal QR code with `vendor/bin/eddy-qrcode <url>`. Scan it to open the URL on a phone or another device, which is most useful for a one-time login link while the site is exposed through a public tunnel. The command requires [`qrencode`](https://fukuchi.org/works/qrencode/) on `PATH` and exits with an install hint when it is missing.

A QR code below the `make login` / `ahoy login` link is opt-in. Set `LOGIN_QRCODE=1` in `.env` (or in your shell environment) and both commands render the one-time login link as a QR code under the printed URL. The wrappers test only that the variable is non-empty, so unset it to turn the QR code off rather than setting it to `0`. Left unset (the default) the login output is unchanged; set with `qrencode` missing, `login` prints the link and then fails with the install hint.

### Step-debugging with XDebug

PHP step-debugging is supported via [XDebug](https://xdebug.org/docs/install). Install the XDebug PHP extension on your host (`php -v` should mention `with Xdebug`), then toggle it on the development server:

```bash
make debug      # restart with XDebug enabled
ahoy debug      # same, with ahoy

make start      # restart without XDebug
ahoy start      # same, with ahoy
```

`debug` is also available as `debug-on`, `xdebug` and `xdebug-on`, and `start` as `debug-off` and `xdebug-off`.

The `debug` command probes the running PHP server's command line for `xdebug.mode=debug` and skips the restart if XDebug is already enabled. Code coverage stays on [pcov](https://github.com/krakjoe/pcov) because the XDebug settings apply only to the development server, not to the test commands.

To start and stop debug sessions from the browser, install the Xdebug Helper extension: [Chrome](https://chromewebstore.google.com/detail/xdebug-helper-by-jetbrain/aoelhdemabeimdhedkidlnbkfhnhgnhm) / [Firefox](https://addons.mozilla.org/en-US/firefox/addon/xdebug-helper-by-jetbrains/).

## Coding standards

The `make lint` or `ahoy lint` command checks the codebase using multiple tools:
- Spell checking with CSpell.
- PHP code standards checking against `Drupal` and `DrupalPractice` standards.
- PHP code static analysis with PHPStan.
- PHP deprecated code analysis and auto-fixing with Drupal Rector.
- Twig code analysis with Twig CS Fixer.
- JavaScript code analysis with ESLint.
- CSS code analysis with Stylelint.

The configuration files for these tools are located in the root of the codebase.

![Lint process](.eddy/assets/lint.svg)

### Fixing coding standards issues

To fix coding standards issues automatically, run the `make lint-fix` or `ahoy lint-fix`. This runs the same tools as `lint` command but with the `--fix` option (for the tools that support it).

If automatic fixes are not accurate, you can adjust the configuration files to either suppress the issue or adjust the fix.

## Testing

The `make test` or `ahoy test` command runs the PHPUnit tests for your extension.

The tests are located in the `tests/src` directory. The `phpunit.xml` file configures PHPUnit to run the tests. It uses Drupal core's bootstrap file `web/core/tests/bootstrap.php` to bootstrap the Drupal environment before running the tests.

The `test` command is a wrapper for multiple test commands:
```bash
make test-unit                    # Run Unit tests
make test-kernel                  # Run Kernel tests
make test-functional              # Run Functional tests
make test-functional-javascript   # Run FunctionalJavascript tests
make test-javascript              # Run JavaScript unit tests (Jest)

ahoy test-unit                    # Run Unit tests
ahoy test-kernel                  # Run Kernel tests
ahoy test-functional              # Run Functional tests
ahoy test-functional-javascript   # Run FunctionalJavascript tests
ahoy test-javascript              # Run JavaScript unit tests (Jest)
```

### Running FunctionalJavascript tests

FunctionalJavascript tests need a real browser driven via WebDriver. By default they use the Google Chrome already installed on your machine - a matching `chromedriver` is downloaded automatically on first run, so no Docker is required:

```bash
ahoy start
ahoy provision
ahoy test-functional-javascript
ahoy browser-stop
```

To run the browser in a Docker Selenium container instead, set `WEBDRIVER_BACKEND=selenium`. The container cannot reach the host's `localhost`, so start the webserver on all interfaces:

```bash
WEBSERVER_HOST=0.0.0.0 ahoy start
ahoy provision
WEBDRIVER_BACKEND=selenium ahoy test-functional-javascript
ahoy browser-stop
```

The browser reaches the webserver at `localhost` with the default backend, and at `host.docker.internal` (macOS) or `172.17.0.1` (other systems) from the Selenium container. Set `WEBDRIVER_HOST` to use a different address.

Either backend gets its own WebDriver port: a free one is claimed starting at 4444 and stored in `.env`, so several projects can run FunctionalJavascript tests at the same time. Set `WEBDRIVER_PORT` to pin a specific one. Tests reach the claimed port because the base class applies it to the WebDriver endpoint, so extend `YourExtensionFunctionalJavascriptTestBase` rather than `WebDriverTestBase` directly - a test that bypasses it keeps the default endpoint from `phpunit.xml` unless it exports its own `MINK_DRIVER_ARGS_WEBDRIVER`.

![Test process](.eddy/assets/test.svg)

### Running specific tests

You can run specific tests by passing a path to the test file or PHPUnit CLI option (`--filter`, `--group`, etc.) to the `make test` or `ahoy test` command. PHPUnit runs inside `build`, so a test path starts at your extension's symlink in the assembled site (`web/themes/custom/` for a theme):

```bash
make test-unit web/modules/custom/your_extension/tests/src/Unit/MyUnitTest.php
make test-unit -- --group=wip

ahoy test-unit web/modules/custom/your_extension/tests/src/Unit/MyUnitTest.php
ahoy test-unit -- --group=wip
```

You may also run tests using the `phpunit` command directly:

```bash
cd build
php -d pcov.directory=.. vendor/bin/phpunit \
  web/modules/custom/your_extension/tests/src/Unit/MyUnitTest.php
php -d pcov.directory=.. vendor/bin/phpunit --group=wip
```

### Deprecated code testing

The tests are configured to check for deprecated code usage and fail if any is found. You can fix the deprecated code or ignore the deprecations by adding a `.deprecation-ignore.txt` file to the root of the codebase and setting the `SYMFONY_DEPRECATIONS_HELPER` environment variable in the `phpunit.xml` to `ignoreFile=../.deprecation-ignore.txt`. PHPUnit runs from the `build` directory, so the path is relative to it. See https://www.drupal.org/node/3285162 for more details.

Note that the CI test jobs that run PHP 8.4 or newer set the `SYMFONY_DEPRECATIONS_HELPER` environment variable to `disabled` to ignore deprecation errors, because not every tested Drupal version fully supports the newer PHP versions yet. You may want to adjust this CI configuration for your project depending on your deprecated code policy.

## Branch protection

You should configure [branch protection rules](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-protected-branches/managing-a-branch-protection-rule) in GitHub to ensure that the code tests pass before merging.

Add the jobs for every Drupal major you selected in `init.php` as required status checks for your default branch:

| Drupal | Required jobs |
|--------|---------------|
| 10 | `lint-d10`, `test-php-min-d10-stable`, `test-php-max-d10-stable` |
| 11 | `lint-d11`, `test-php-min-d11-stable`, `test-php-max-d11-stable`, `test-php-min-d11-legacy`, `test-php-max-d11-canary` |
| 12 | `lint-d12`, `test-php-min-d12-stable`, `test-php-max-d12-stable`, `test-php-min-d12-legacy`, `test-php-max-d12-canary` |

## Deployment

The CI mirrors the code to your extension's Drupal.org repository (or any other git remote) once the tests pass. It deploys pushes to the `1.x` branch and release tags, and pull requests never deploy. To deploy another branch, such as `2.x`, add it to the `push` branches in `.github/workflows/test.yml`.

A branch push deploys the commit that CI tested to the same branch of the destination repository. If the branch has moved on by the time the tests finish, or an older run is re-run, the deployment is skipped, so an older commit never replaces a newer one. A release tag is deployed as that tag alone and leaves the destination branches untouched.

See this example of the deployment destination repository: [GitHub Actions](https://github.com/AlexSkrypnyk/drupal_extension_scaffold_destination_github)

CI will use the SSH key to push the code to the destination repository. The public part of the SSH key should be added to your [Drupal.org account](https://git.drupalcode.org/-/user_settings/ssh_keys). The private part of the SSH key should be added to the CI provider.

It is a good practice to use a dedicated SSH key for every project.

### Setting up SSH key for deployment

1. Generate a new SSH key without the pass phrase:

```bash
ssh-keygen -m PEM -t rsa -b 4096 -C "your_email+project_name@example.com"
```

2. Add **public** key to your [Drupal.org account](https://git.drupalcode.org/-/user_settings/ssh_keys)
3. Add **private** key to your CI:
  - GitHub Actions:
    - Go to your project -> **Settings** -> **Secrets and variables** -> **Actions**
    - Add a new secret with the `DEPLOY_SSH_KEY` name and the private key as the value.

4. In CI, use UI to add the following variables as secrets:

- `DEPLOY_REMOTE` - your extension's Drupal.org repository (for example, `git@git.drupal.org:project/myextension.git`). Until it is set, the deployment job skips deployment and reports a notice.
- `DEPLOY_USER_NAME` - the name of the user who commits to the remote repository (i.e., your name on Drupal.org).
- `DEPLOY_USER_EMAIL` - the email address of the user who commits to the remote repository (i.e., your email on Drupal.org).
- `DEPLOY_PROCEED` - set to `1` once CI is working, and you are ready to deploy. Without this variable, the deployment job will run but will not push the code. This is useful for testing the deployment job.

5. Optionally, set `DEPLOY_BRANCH` to the branch to push to in the destination repository. It is not a secret: add it as a repository variable in GitHub Actions (**Settings** -> **Secrets and variables** -> **Actions** -> **Variables**). Without it, the code is pushed to the branch that triggered the build. It has no effect on release tags, which are always pushed as tags.

### Drupal.org CI (DrupalCI)

Once your extension is mirrored to Drupal.org, its GitLab CI ("DrupalCI") runs automatically. The scaffold's configuration is compatible with DrupalCI out of the box - the PHPStan error suppressions resolve correctly even though DrupalCI runs the analysis from within the module directory.

PHPUnit needs one override: disable code coverage on DrupalCI. DrupalCI symlinks your project back into the built site's `web/modules/custom/<name>/` directory, so PHPUnit's coverage scan follows that recursive symlink into `web/core/node_modules` and exhausts the available file descriptors. Coverage is already collected by GitHub Actions, so turning it off on DrupalCI is safe.

Add the [standard DrupalCI includes](https://git.drupalcode.org/project/gitlab_templates) to a `.gitlab-ci.yml` in your project root and set the override:

```yaml
variables:
  _PHPUNIT_EXTRA: '--no-coverage'
```

## Updating your extension

When this template is updated, you can merge the changes into your extension codebase.

If you use Claude Code, the bundled [`update-consumer-eddy`](.eddy/skills/update-consumer-eddy/SKILL.md) skill automates this process: in your initialised project, ask Claude to "update scaffold" and it will fetch the skill, download the latest scaffold, re-run `init.php` with your original answers, restore project-specific files from git, and reconcile differences.

For a manual update, follow these steps:

1. Download the latest version of this template's code with **Code** -> **Download ZIP** in the GitHub UI.
2. Expand into a new directory.
3. Run the initial [codebase setup](#codebase-setup) script: `php init.php` and repeat the answers you provided during the initial setup.
4. Create a new branch in your extension's repository.
5. Copy all files into your extension's directory and override the existing files.
6. Resolve any conflicts between the new files and your extension's files. Refer to the release notes for any breaking changes and accept/reject them as needed.
7. [Build website](#building-website) with `make build` or `ahoy build` to check that everything is set up correctly.
8. [Check coding standards](#coding-standards) with `make lint` or `ahoy lint`.
9. [Run tests](#testing) with `make test` or `ahoy test`.
10. Commit and push to your new GitHub repo.
11. Check that all the CI jobs are finishing successfully.
12. Merge the new branch into your main branch.
13. Check that the deployment job is working correctly.

## Renovate

This template includes a [Renovate](https://docs.renovatebot.com/) configuration ([`renovate.json`](renovate.json)) to automatically keep dependencies up-to-date.

### What is updated

| Source                         | Dependencies                                  | Examples                                                                     |
|--------------------------------|-----------------------------------------------|------------------------------------------------------------------------------|
| [`package.json`](package.json) | npm packages                                  | `eslint`, `stylelint`, `prettier`                                            |
| `.github/workflows/*.yml`      | GitHub Actions and the Docker images they use | `actions/checkout`, `codecov/codecov-action`, `selenium/standalone-chromium` |

### What is NOT updated

- **Composer packages** - Renovate's Composer manager is not enabled, so neither `composer.json` nor `composer.dev.json` is updated. `composer.json` contains the extension's production dependencies (`require` and `require-dev`), which should be updated manually to ensure compatibility with Drupal.org packaging. Every build resolves the `composer.dev.json` ranges to the newest releases they allow (see [How versions are resolved](#how-versions-are-resolved)).
- **Major npm versions** - major npm updates are disabled and should be applied manually to avoid breaking changes.

### How it works

- **Grouped PRs**: all minor and patch updates share 1 pull request on the `deps/all` branch, and major GitHub Actions updates share another on `deps/major-all`.
- **Automerge**: PRs are automatically merged when all CI checks pass.
- **Digest pinning**: GitHub Actions and Docker images are pinned to SHA digests for reproducibility and security.
- **Range strategy**: version ranges in `package.json` are bumped to the latest version (e.g., `^1.2` becomes `^1.3`).
- **Dependency Dashboard**: an issue is created in the repository to track pending updates, approval requests, and detected problems.

See the [Renovate documentation](https://docs.renovatebot.com/configuration-options/) for all available options.

## Projects using Eddy

- [Testmode](https://github.com/AlexSkrypnyk/testmode) - Drupal module to alter existing site content and other configurations when running tests.
- [Generated Content](https://github.com/AlexSkrypnyk/generated_content) - Drupal module to programmatically generate content.
- [Integration Report](https://github.com/AlexSkrypnyk/integration_report) - Drupal module to report on availability status of 3rd party endpoints.
- [Drupal Helpers](https://github.com/alexSkrypnyk/drupal_helpers) - Helper utilities for Drupal.
- [Deploy_Steps](https://github.com/alexSkrypnyk/deploy_steps) - Runs repeatable run-on-every-deploy logic as discoverable plugins.

---

## Contributing

Contributions are welcome. See [CONTRIBUTING.md](CONTRIBUTING.md) for how to build and test the scaffold, run its self-tests, and regenerate the snapshot fixtures.
