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

Eddy isn't for building websites: use [Vortex](https://github.com/drevops/vortex) for that. The 2 templates do separate jobs, and a repository uses one or the other, never both.

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

- ⚙️ **CI on [GitHub Actions](.github/workflows/test.yml)** - PHP `8.3`, `8.4` and `8.5`, and Drupal `stable` on 10, 11 and 12, plus `legacy` and `canary` tiers on 11 and 12. Code coverage comes from [pcov](https://github.com/krakjoe/pcov) and goes to [codecov.io](https://codecov.io), and the setup works with [Drupal.org GitLab CI](#drupalorg-ci-drupalci) too.
- 💻 **Local development** - a site built from [drupal/recommended-project](https://www.drupal.org/docs/develop/using-composer/starting-a-site-using-drupal-composer-project-templates) on the PHP of your host, by the same [tooling](#the-tooling-package) commands that CI runs, through `make` or [Ahoy](https://github.com/ahoy-cli/ahoy). It builds a module or a theme, adds the development dependencies from [`composer.dev.json`](composer.dev.json) and the extensions you `suggest`, and applies your [patches](#patching-dependencies).
- 🧹 **Coding standards** - PHPCS with the `Drupal`, `DrupalPractice`, `DrevOps` and `PHPCompatibility` standards, PHPStan with [PHPStan Drupal](https://github.com/mglaman/phpstan-drupal), [Drupal Rector](https://github.com/palantirnet/drupal-rector), [Twig CS Fixer](https://github.com/VincentLanglet/Twig-CS-Fixer), [ESLint](https://eslint.org/), [Stylelint](https://stylelint.io/), [CSpell](https://cspell.org/) and [Prettier](https://prettier.io/).
- 🧪 **Tests** - PHPUnit Unit, Kernel, Functional and FunctionalJavascript tests, in a local Chrome or a Selenium container, and JavaScript tests with [Jest](https://jestjs.io/).
- 🚚 **Deployment** - mirroring of the code and its tags to Drupal.org, or any other git remote, once CI passes, optionally to a different destination branch.
- 🤖 **[Renovate](#renovate)** - grouped pull requests that keep dependencies up to date.
- 📝 **Docs for your extension** - [README.md](README.dist.md) and [CONTRIBUTING.md](CONTRIBUTING.dist.md) templates.

Eddy tests itself the way it tests a project created from it, and deploys to [drevops/eddy_destination](https://github.com/drevops/eddy_destination) as a working example.

## Setup overview

1. Download this template's code with **Code** -> **Download ZIP** in the GitHub UI.
2. Expand it into a new directory.
3. Run `php install.php` there and answer its questions (see [Codebase setup](#codebase-setup)).
4. If you already have extension code, copy it into the directory.
5. [Build the website](#building-website) with `make build` or `ahoy build` to check that everything works.
6. [Check coding standards](#coding-standards) with `make lint` or `ahoy lint`.
7. [Run the tests](#testing) with `make test` or `ahoy test`.
8. Create your extension's repository on GitHub, then commit and push.
9. [Configure branch protection](#branch-protection).
10. [Configure deployment](#deployment) to Drupal.org.

## Codebase setup

`php install.php` asks for your extension's name, machine name and type, the Drupal versions to target, the command wrapper, the tools to keep, and whether to keep the example lifecycle scripts. It then renames and trims the template to match, and deletes itself unless you answer no to "Remove this script".

![Installation process](.eddy/assets/install.svg)

## Building website

`make build` or `ahoy build` assembles the codebase in the `build` directory, starts the PHP server and installs the Drupal website with your extension enabled. Your extension's files are symlinked into the site, and CI builds it the same way. `build` runs these commands in turn:

```bash
make assemble     # Assemble the codebase
make start        # Start the PHP server
make provision    # Provision the Drupal website

ahoy assemble     # Assemble the codebase
ahoy start        # Start the PHP server
ahoy provision    # Provision the Drupal website
```

A few more commands help along the way:

- `provision` reinstalls the website without reassembling the codebase.
- `info` (alias `describe`) prints a summary of the environment, such as the PHP, Drupal and Drush versions, the site URL and the database path.
- `reset` (aliases `delete` and `destroy`) stops the project's webserver and browser, and removes the `build`, `.logs` and `vendor` directories.
- `make help` lists the `make` commands, since a bare `make` runs `build` instead. A bare `ahoy` lists the `ahoy` ones.

Your extension documents these commands for its own contributors in [CONTRIBUTING.md](CONTRIBUTING.dist.md).

![Build process](.eddy/assets/build.svg)

### The tooling package

The commands that assemble, start, provision and deploy your extension come from the [`drevops/eddy-tooling`](.eddy/tooling) Composer package, so fixes reach you through a version constraint rather than a manual merge. `scripts/eddy-tooling` installs the package into `vendor/`, with each command in `vendor/bin/eddy-*`. `make` and `ahoy` run the installer before the commands that need it, and CI runs it as a step of its own. Without a wrapper, run it yourself before them.

The version constraint lives in `composer.dev.json`:

```json
"drevops/eddy-tooling": "~1.0.0"
```

The `~` accepts patch releases and holds the minor version, so CI picks up fixes on its own and a new minor version arrives with the next scaffold update. The installer runs Composer only when the constraint or the package's patches change, including an edit to a local patch file, or when a command is missing, so it costs next to nothing. A local checkout keeps its patch release until `vendor/` is removed, which `make reset` and `ahoy reset` do.

Never modify `scripts/eddy-tooling` itself, by hand or with AI: your project uses it as-is from Eddy, and a scaffold update replaces it with the release's copy. To add a step of your own, use a [custom lifecycle script](#custom-lifecycle-scripts). To change what a command does, declare a patch for the package as its [README](.eddy/tooling/README.md#patching-the-package) describes.

### Drupal versions

`DRUPAL_VERSION` sets the Drupal version to build, and defaults to `11`, the newest stable Drupal 11 release. The default comes from `extra.eddy.drupal-version` in `composer.dev.json`, which `install.php` sets to the highest Drupal major you select that has a stable release, or to the lowest one you select when none has.

Set it before `make build` or `ahoy build` to build another version:

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

The build pins Drupal core to the newest release that matches `DRUPAL_VERSION`, or to the newest pre-release when that version has no stable release yet. Every other dependency gets its most stable release that fits, because the build uses `minimum-stability: dev` with `prefer-stable: true`. A dependency falls back to a pre-release or a development branch only when no stable release supports the chosen core, and the build output lists every dependency it installed from a development branch. So nothing needs editing as Drupal and its tools publish new releases.

Before it touches the `build` directory, the build checks the PHP that runs it against the PHP requirement of the chosen core release, and stops with a message when the release needs a newer PHP. It then sets `config.platform.php` in `build/composer.json` to that PHP, so a `platform` setting in your global Composer configuration doesn't apply. To resolve for another PHP version on purpose, set `config.platform.php` in `composer.dev.json`, and the build checks that version against the requirement too.

For example, as of October 2026:

| `DRUPAL_VERSION` | Drupal core | Drush |
|------------------|-------------|-------|
| `11` | `11.4.9` | `13.8.0` |
| `11@beta` | `11.4.9`, then the 11.5 pre-releases once they exist | `13.8.0` |
| `12` | `12.0.0-beta1`, then `12.0.0` once it ships | `14.x-dev`, then `14.0.0` once it's tagged |

### CI Drupal version matrix

CI ([GitHub Actions](.github/workflows/test.yml)) tests a few deliberate corners rather than every PHP and Drupal pair. 10 jobs cover the lowest and highest supported PHP on each Drupal major, plus the next minor pre-release and the oldest tested minor of Drupal 11 and 12:

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

A job's name gives its PHP bound (`min`/`max`), its Drupal major (`d10`/`d11`/`d12`) and its release tier (`stable`/`legacy`/`canary`), and its "Setup PHP" step shows the exact PHP version.

- **Drupal versions float, apart from the `legacy` pins.** `stable` builds the newest stable release, and `canary` the newest release at beta stability or above: the next minor's beta or release candidate when there is one, otherwise the current stable release. New releases reach CI with no changes. The `legacy` minors, `11.1.0` and `12.0.0`, each build the newest patch of an older minor on purpose.
- **PHP versions are pinned bounds.** Each job sets an explicit PHP version, so `max` changes when a newer PHP is added, and `min` only when support for an old PHP is dropped. Updating your extension from the scaffold (see [Updating your extension](#updating-your-extension)) refreshes them, along with the `legacy` pins.

#### Drupal 12

Drupal 12 has no stable release yet, so its jobs build the newest Drupal 12 pre-release for now. They use the same kinds of values as the Drupal 11 jobs (`12`, `12.0.0` and `12@beta`) and move on their own: all of them to 12.0.0 when it ships, then `canary` to the 12.1 pre-releases while `legacy` stays on the 12.0.x patches.

Drupal 12 requires PHP 8.5, so every Drupal 12 job runs it, and so does a local build: run `DRUPAL_VERSION=12 make build` or `DRUPAL_VERSION=12 ahoy build` on PHP 8.5. On an older PHP, the build stops before it changes anything and says which PHP the release needs.

Drupal 12 builds install Drush from its `14.x` development branch until Drush 14.0.0 is tagged. They install `drupal/core` from source too, because the core packages leave out the test base classes and the PHPUnit bootstrap, so they take a little longer to assemble.

Drupal 12 is opt-in in `install.php`, where only Drupal 11 starts checked. Selecting it next to Drupal 11 adds its CI jobs and badge, and leaves the local build on Drupal 11 (see [Drupal versions](#drupal-versions)).

### Distributing tools across CI runners

Each test job lists at its top which tools run on which runner. `CI_RUNNER_INDEX` and `CI_RUNNER_TOTAL` give the runner's position, and each tool has a `CI_IS_<TOOL>_RUNNER` flag inside its own block that only its steps read, so moving a tool to another runner is 1 edit. Every leg of the matrix in [`.github/workflows/test.yml`](.github/workflows/test.yml) covers a different Drupal and PHP pair, so every flag is `true`.

To shard a tool, add a matrix dimension and compare the tool's flag against it, after checking [branch protection](#branch-protection), since a renamed or duplicated leg renames its status check. If a test runner names its shards after the runner index, give a tool that needs its own runner the last one: excluding runner 0 orphans the first shard, which is often the catch-all for untagged tests.

### Allowing a CI step to fail

To let a lint or test step in [`.github/workflows/test.yml`](.github/workflows/test.yml) fail without failing its job, set its repository variable to `1` in **Settings** -> **Secrets and variables** -> **Actions** -> **Variables**. The step still runs and reports what it finds.

| Variable | Step |
|----------|------|
| `CI_CSPELL_IGNORE_FAILURE` | CSpell |
| `CI_PHPCS_IGNORE_FAILURE` | PHPCS |
| `CI_PHPSTAN_IGNORE_FAILURE` | PHPStan |
| `CI_RECTOR_IGNORE_FAILURE` | Rector |
| `CI_TWIGCSFIXER_IGNORE_FAILURE` | Twig CS Fixer |
| `CI_NODEJS_LINT_IGNORE_FAILURE` | ESLint and Stylelint |
| `CI_NODEJS_TEST_IGNORE_FAILURE` | Jest |
| `CI_TEST_IGNORE_FAILURE` | PHPUnit |

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

A patch that no longer applies fails the build, so a dependency update that conflicts with it can't slip through quietly. Where you declare a patch decides where it applies:

- `composer.dev.json` - the build only, locally and in CI. Use it for patches that only your tests need, and keep their files in the `patches` directory, which the build copies into `build/`.
- `composer.json` - the build, Drupal.org GitLab CI, which reads this format with composer-patches 1.x, and any site that installs your extension with composer-patches 2.x. Refer to these patches by a public URL, since a local `patches/` path doesn't exist on that site.

Patches declared for `drevops/eddy-tooling` itself stay out of the build: `scripts/eddy-tooling` applies them when it installs [the tooling package](#the-tooling-package).

### Providing `GITHUB_TOKEN`

Set `GITHUB_TOKEN` to a personal access token to get around GitHub API rate limits. The build passes it to its own Composer commands through `COMPOSER_AUTH`, uses an existing `COMPOSER_AUTH` as it is, and leaves your global Composer configuration alone.

### Debugging command output

The build hides the output of Composer, npm and Drush unless a command fails. Set `DEBUG=1` to stream all of it:

```bash
DEBUG=1 make build   # stream all command output
DEBUG=1 ahoy build   # same, with ahoy
```

### Optional dependencies

To test your extension together with other extensions, add them to the `suggest` section of `composer.json`. The build adds each suggested dependency to the codebase, and enables each `drupal/*` one as a module.

### Frontend dependencies

Add the frontend dependencies your tests need to `package.json`, and commit `package-lock.json` with it. `assemble` runs `npm ci` and `npm run build` in the `build` directory. Commit a `.skip_npm_build` file to skip both, which also turns off the JS/CSS linting (ESLint, Stylelint, Prettier) and the Jest tests. CSpell runs from the project root, so it still runs.

When you keep none of ESLint, Stylelint, CSpell and Jest, `install.php` removes `package.json`, `package-lock.json` and the Node.js steps of the CI lint job, so add a `package.json` of your own if your extension needs frontend dependencies later.

### Provisioning the website

`provision` installs the website from the `standard` profile with your extension and any `drupal/*` modules in `suggest` enabled. Set `DRUPAL_PROFILE` to use another profile. The database is an SQLite file at `/tmp/site_[EXTENSION_NAME].sqlite`, which [DB Browser for SQLite](https://sqlitebrowser.org/) can open, and `provision` prints a one-time login link when it finishes.

The website is served at `http://localhost:8000`, or at the first free port up to 8099 when another process uses 8000, and `start` prints the URL. Set `WEBSERVER_HOST` to change the hostname.

#### Webserver port

`start` takes the port from `WEBSERVER_PORT` in your shell or in the `.env` file of the project. When neither sets it, `start` picks the first free port from 8000 to 8099 and saves it to `.env`, so `stop`, `provision`, `drush` and `login` reuse it. `make` and `ahoy` load `.env` on their own, and git ignores the file. To pick a new port, delete the `WEBSERVER_PORT` line from `.env` and run `start` again.

`start`, `stop` and `reset` only stop the webserver that serves the project's own `build/web`. When another process holds the port, such as the server of another project that recorded the same port, `stop` and `reset` leave it running, and `start` fails and names it. Stop that process, or set `WEBSERVER_PORT` in `.env` to a free port. Once it launches the server, `start` waits up to `WEBSERVER_WAIT_TIMEOUT` seconds, 5 by default, for it to accept connections.

### Custom lifecycle scripts

`assemble`, `provision`, `start` and `stop` run your own shell scripts from the `scripts/` directory during their phase:

- `scripts/assemble-*.sh` runs at the end of `assemble`, after the dependencies are installed and the extension is symlinked into `build/`.
- `scripts/provision-*.sh` runs at the end of `provision`, after the site is installed, the extension is enabled and the caches are warmed.
- `scripts/start-*.sh` runs at the end of `start`, once the webserver is serving.
- `scripts/stop-*.sh` runs during `stop`, before the webserver stops, while it is still reachable.

The scripts run in lexicographic order from the project root, with the parent's environment, and a non-zero exit aborts the command. `.gitattributes` keeps `scripts/` out of the archives published to Drupal.org. The directory also holds `scripts/eddy-tooling`, the [tooling installer](#the-tooling-package), which isn't a hook.

`install.php` can keep 4 example scripts, 1 per phase, that print a marker line when their phase runs. Answer yes when it asks, if you want them as a starting point.

### Public HTTPS tunnel (Cloudflare)

A remote development environment, such as Codespaces, DevPod, a remote Docker host or an SSH dev box, can't reach the PHP server on `localhost`. The [tooling](#the-tooling-package) can expose it through a [Cloudflare quick tunnel](https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/do-more-with-tunnels/trycloudflare/), a public `*.trycloudflare.com` HTTPS URL that needs no account, DNS or configuration:

```bash
export CLOUDFLARE_TUNNEL=1
make build
```

> [!WARNING]
> Anyone with the URL can reach the site while the tunnel is up, and a local Drupal site usually has a known admin account and no firewall. Use disposable test data only, never real or sensitive content, and stop the tunnel with `make stop` when you're done.

With `CLOUDFLARE_TUNNEL` set, in the shell or in `.env`, and [`cloudflared`](https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/downloads/) on `PATH`, `start` starts a tunnel, or reuses a healthy one, and writes its URL to `.env` as `TUNNEL_URL`. `start`, `provision`, `info`, `drush` and `login` then use that URL. `provision` adds the reverse-proxy and trusted-host settings that Drupal needs behind the tunnel, and `stop` closes the tunnel and removes its URL from `.env`. Without `cloudflared`, `start` skips the tunnel with a note.

Any other tool that writes a `TUNNEL_URL` to `.env`, such as ngrok from a [custom lifecycle script](#custom-lifecycle-scripts), works the same way, and `stop` leaves a URL it didn't create alone.

`vendor/bin/eddy-qrcode <url>` draws any URL as a QR code in the terminal, which is handy for opening a login link on a phone. It needs [`qrencode`](https://fukuchi.org/works/qrencode/) on `PATH`. Set `LOGIN_QRCODE=1` in `.env` or your shell to have `make login` and `ahoy login` draw the link as a QR code too. The wrappers only check that the variable is set, so unset it to turn the QR code off rather than setting it to `0`.

### Step-debugging with XDebug

Install the [XDebug](https://xdebug.org/docs/install) PHP extension on your host (`php -v` should mention `with Xdebug`), then restart the development server with it on or off:

```bash
make debug      # restart with XDebug enabled
ahoy debug      # same, with ahoy

make start      # restart without XDebug
ahoy start      # same, with ahoy
```

`debug` is also available as `debug-on`, `xdebug` and `xdebug-on`, and `start` as `debug-off` and `xdebug-off`. `debug` skips the restart when the server already runs with XDebug. Code coverage stays on [pcov](https://github.com/krakjoe/pcov), because the XDebug settings apply only to the development server.

To start and stop debug sessions from the browser, install the Xdebug Helper extension for [Chrome](https://chromewebstore.google.com/detail/xdebug-helper-by-jetbrain/aoelhdemabeimdhedkidlnbkfhnhgnhm) or [Firefox](https://addons.mozilla.org/en-US/firefox/addon/xdebug-helper-by-jetbrains/).

## Coding standards

`make lint` or `ahoy lint` checks the codebase with these tools, whose configuration files sit in the root of the codebase:

- CSpell for spelling.
- PHPCS against the `Drupal`, `DrupalPractice`, `DrevOps` and `PHPCompatibility` standards.
- PHPStan for static analysis.
- Drupal Rector for deprecated code.
- Twig CS Fixer for Twig templates.
- ESLint for JavaScript and Stylelint for CSS.

`make lint-fix` or `ahoy lint-fix` runs the tools that can fix what they find. When an automatic fix is wrong, adjust the tool's configuration to suppress the issue or change the fix.

![Lint process](.eddy/assets/lint.svg)

## Testing

`make test` or `ahoy test` runs every PHPUnit test suite and the Jest tests. The tests live in `tests/src`. `phpunit.xml` configures PHPUnit, and `phpunit.d10.xml` replaces it in a Drupal 10 build. Both bootstrap Drupal with core's `web/core/tests/bootstrap.php`.

Each suite also has a command of its own:

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

FunctionalJavascript tests drive a real browser through WebDriver. By default they use the Google Chrome on your machine, with a matching `chromedriver` downloaded on the first run, so you don't need Docker:

```bash
ahoy start
ahoy provision
ahoy test-functional-javascript
ahoy browser-stop
```

`test` and `test-functional-javascript` start the browser first and leave it running, so the next run reuses it. Stop it with `browser-stop` when you're done. `browser-start` reuses any browser that already answers on the WebDriver port, and fails and names whatever else holds the port. `browser-stop` and `reset` stop only the project's own browser, so another project's browser on the same port keeps running.

To run the browser in a Docker Selenium container instead, set `WEBDRIVER_BACKEND=selenium`. The container can't reach the host's `localhost`, so start the webserver on all interfaces:

```bash
WEBSERVER_HOST=0.0.0.0 ahoy start
ahoy provision
WEBDRIVER_BACKEND=selenium ahoy test-functional-javascript
ahoy browser-stop
```

The browser reaches the webserver at `localhost` with the default backend, and at `host.docker.internal` (macOS) or `172.17.0.1` (other systems) from the Selenium container. Set `WEBDRIVER_HOST` to use a different address.

Each project gets its own WebDriver port, whichever backend it uses: a free one is claimed starting at 4444 and saved in `.env`, so several projects can run these tests at the same time. Set `WEBDRIVER_PORT` to pin a specific one. Tests reach the claimed port through the base class, so extend `YourExtensionFunctionalJavascriptTestBase` rather than `WebDriverTestBase`. A test that bypasses it keeps the default endpoint from `phpunit.xml` unless it exports its own `MINK_DRIVER_ARGS_WEBDRIVER`.

![Test process](.eddy/assets/test.svg)

### Running specific tests

Pass a test file or PHPUnit options such as `--filter` or `--group` to a test command. PHPUnit runs inside `build`, so a test path starts at your extension's symlink in the assembled site (`web/themes/custom/` for a theme). `make` reads an argument that holds `=` as a variable and fails on one that holds `:`, so separate an option from its value with a space, and pass `--filter` a class or method name rather than `Class::method`:

```bash
make test-unit web/modules/custom/your_extension/tests/src/Unit/MyUnitTest.php
make test-unit -- --group wip

ahoy test-unit web/modules/custom/your_extension/tests/src/Unit/MyUnitTest.php
ahoy test-unit -- --group wip
```

You can also run `phpunit` directly:

```bash
cd build
php -d pcov.directory=.. vendor/bin/phpunit \
  web/modules/custom/your_extension/tests/src/Unit/MyUnitTest.php
php -d pcov.directory=.. vendor/bin/phpunit --group=wip
```

### Code coverage

PHPUnit collects code coverage with [pcov](https://github.com/krakjoe/pcov) and Jest with V8, and both write their reports to `.logs/coverage`. CI uploads the reports as a workflow artifact, and to [Codecov](https://codecov.io) when the `CODECOV_TOKEN` repository secret is set.

### Deprecated code testing

The tests fail when they find deprecated code. To ignore some deprecations, add a `.deprecation-ignore.txt` file to the root of the codebase and set the `SYMFONY_DEPRECATIONS_HELPER` environment variable to `ignoreFile=../.deprecation-ignore.txt` in `phpunit.xml`, and in `phpunit.d10.xml` for Drupal 10. PHPUnit runs from the `build` directory, so the path is relative to it. See https://www.drupal.org/node/3285162 for details.

CI test jobs on PHP 8.4 or newer set `SYMFONY_DEPRECATIONS_HELPER` to `disabled`, because not every tested Drupal version fully supports the newer PHP versions yet. Adjust this to your project's deprecation policy.

## Branch protection

Set up [branch protection rules](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-protected-branches/managing-a-branch-protection-rule) or a [ruleset](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-rulesets/about-rulesets) in GitHub so that the tests must pass before a merge. Require the jobs of every Drupal major you selected in `install.php`:

| Drupal | Required jobs |
|--------|---------------|
| 10 | `lint-d10`, `test-php-min-d10-stable`, `test-php-max-d10-stable` |
| 11 | `lint-d11`, `test-php-min-d11-stable`, `test-php-max-d11-stable`, `test-php-min-d11-legacy`, `test-php-max-d11-canary` |
| 12 | `lint-d12`, `test-php-min-d12-stable`, `test-php-max-d12-stable`, `test-php-min-d12-legacy`, `test-php-max-d12-canary` |

## Deployment

Once the tests pass, CI mirrors the code to your extension's Drupal.org repository, or any other git remote. It deploys pushes to the `1.x` branch and release tags, and pull requests never deploy. To deploy another branch, such as `2.x`, add it to the `push` branches in `.github/workflows/test.yml`. Updating the scaffold with the [`update-consumer-eddy`](#updating-your-extension) skill keeps the branches you list there.

A branch push deploys the commit that CI tested to the same branch of the destination repository. Deployments of a branch never overlap: each one waits for the previous one, then checks the branch on GitHub right before it pushes. When the branch has moved on, or an older run is re-run, it skips the deployment, so an older commit never replaces a newer one. A release tag is deployed as that tag alone and leaves the destination branches untouched.

[drevops/eddy_destination](https://github.com/drevops/eddy_destination) shows a destination repository at work.

CI pushes with an SSH key. Add its public part to your [Drupal.org account](https://git.drupalcode.org/-/user_settings/ssh_keys) and its private part to your repository, and use a dedicated key for each project.

### Setting up SSH key for deployment

1. Generate a new SSH key without a passphrase:

   ```bash
   ssh-keygen -m PEM -t rsa -b 4096 -C "your_email+project_name@example.com"
   ```

2. Add the **public** key to your [Drupal.org account](https://git.drupalcode.org/-/user_settings/ssh_keys).
3. Add the **private** key as the `DEPLOY_SSH_KEY` secret in your repository's **Settings** -> **Secrets and variables** -> **Actions**.
4. Add these secrets in the same place:

   - `DEPLOY_REMOTE` - your extension's Drupal.org repository, such as `git@git.drupal.org:project/myextension.git`. Until it's set, the deployment job reports a notice and skips deployment.
   - `DEPLOY_USER_NAME` - the name of the user who commits to the remote repository, as on Drupal.org.
   - `DEPLOY_USER_EMAIL` - the email address of that user, as on Drupal.org.
   - `DEPLOY_PROCEED` - set to `1` once CI works and you're ready to deploy. Without it, the deployment job runs but doesn't push, which lets you test the job.

5. Optionally, set `DEPLOY_BRANCH` to the branch to push to in the destination repository. It isn't a secret: add it as a repository variable under **Settings** -> **Secrets and variables** -> **Actions** -> **Variables**. Without it, the code goes to the branch that triggered the build. Release tags are always pushed as tags.

### Drupal.org CI (DrupalCI)

Once your extension is mirrored to Drupal.org, its GitLab CI ("DrupalCI") runs on its own. Eddy's configuration works with it as it is, including the PHPStan error suppressions, which resolve even though DrupalCI runs the analysis from within the module directory.

PHPUnit needs 1 override there: turn off code coverage. DrupalCI symlinks your project back into the built site's `web/modules/custom/<name>/` directory, so PHPUnit's coverage scan follows that loop into `web/core/node_modules` and runs out of file descriptors. GitHub Actions collects coverage already, so DrupalCI can safely skip it. Add the [standard DrupalCI includes](https://git.drupalcode.org/project/gitlab_templates) to a `.gitlab-ci.yml` in your project root, with this override:

```yaml
variables:
  _PHPUNIT_EXTRA: '--no-coverage'
```

## Updating your extension

When this template changes, you can merge the changes into your extension.

An extension created from a 4.x release needs a one-off upgrade to 5.x first: its `AGENTS.md` can't fetch the update skill anymore, and the upgrade replaces `.devtools/` with the tooling package and CircleCI with GitHub Actions.

➡️ [MIGRATION.md](MIGRATION.md) walks you through the upgrade.

If you use Claude Code, ask Claude to "update scaffold" in your project. The [`update-consumer-eddy`](.eddy/skills/update-consumer-eddy/SKILL.md) skill renders the latest release and the one your project is on with your original answers, and merges the difference into your project. Your own files and your changes to the template stay as they are, apart from `scripts/eddy-tooling`, which it replaces with the release's copy, and only lines that both sides changed need a decision.

To update by hand:

1. Download the latest version of this template's code with **Code** -> **Download ZIP** in the GitHub UI.
2. Expand it into a new directory.
3. Run `php install.php` there and repeat the answers you gave during the initial setup.
4. Create a new branch in your extension's repository.
5. Copy all files into your extension's directory, overwriting the existing ones.
6. Resolve the differences between the new files and yours, and check the release notes for breaking changes. `install.php` ran outside your repository, so keep your own branches in the workflow branch filters and your own repository in the README badges. Keep `scripts/eddy-tooling` exactly as the release ships it.
7. [Build the website](#building-website), [check coding standards](#coding-standards) and [run the tests](#testing).
8. Commit and push to your GitHub repository, and check that every CI job passes.
9. Merge the new branch into your main branch, and check that the deployment job works.

## Renovate

This template includes a [Renovate](https://docs.renovatebot.com/) configuration ([`renovate.json`](renovate.json)) that keeps the npm packages in [`package.json`](package.json), and the GitHub Actions and Docker images in `.github/workflows/*.yml`, up to date:

- Minor and patch updates share 1 pull request on the `deps/all` branch, and major GitHub Actions updates share another on `deps/major-all`.
- Pull requests merge on their own when all CI checks pass.
- GitHub Actions and Docker images are pinned to SHA digests.
- Version ranges in `package.json` are bumped to the latest version, such as `^1.2` to `^1.3`.
- A Dependency Dashboard issue tracks pending updates, approval requests and detected problems.

Renovate leaves Composer packages and major npm versions alone. Update the dependencies in `composer.json` by hand, to keep them compatible with Drupal.org packaging, and apply major npm updates by hand too. Every build resolves the `composer.dev.json` ranges to their newest releases anyway (see [How versions are resolved](#how-versions-are-resolved)).

➡️ See the [Renovate documentation](https://docs.renovatebot.com/configuration-options/) for every option.

## Projects using Eddy

- [Testmode](https://github.com/AlexSkrypnyk/testmode) - Drupal module to alter existing site content and other configurations when running tests.
- [Generated Content](https://github.com/AlexSkrypnyk/generated_content) - Drupal module to programmatically generate content.
- [Integration Report](https://github.com/AlexSkrypnyk/integration_report) - Drupal module to report on availability status of 3rd party endpoints.
- [Drupal Helpers](https://github.com/alexSkrypnyk/drupal_helpers) - Helper utilities for Drupal.
- [Deploy_Steps](https://github.com/alexSkrypnyk/deploy_steps) - Runs repeatable run-on-every-deploy logic as discoverable plugins.

---

## Contributing

Contributions are welcome.

➡️ See [CONTRIBUTING.md](CONTRIBUTING.md) for how to build and test the scaffold, run its self-tests, and regenerate the snapshot fixtures.
