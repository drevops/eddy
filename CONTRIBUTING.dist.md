# Contributing

This guide is for developers working on this extension. Follow it to build a local Drupal site with the extension, check the coding standards and run the tests.

## Local development

1. Install the following tools:
   - PHP with SQLite support
   - Composer
<!-- #;< DEV_NPM -->
   - Node.js with npm, which the build uses
<!-- #;> DEV_NPM -->
<!-- #;< DEV_AHOY -->
   - [Ahoy](https://github.com/ahoy-cli/ahoy)
<!-- #;> DEV_AHOY -->
2. Clone this repository.
3. Build the site:
<!-- #;< DEV_MAKEFILE -->

   ```bash
   make build
   ```
<!-- #;> DEV_MAKEFILE -->
<!-- #;< DEV_AHOY -->

   ```bash
   ahoy build
   ```
<!-- #;> DEV_AHOY -->
<!-- #;< DEV_NO_COMMAND_WRAPPER -->

   ```bash
   scripts/eddy-tooling          # Install the tooling
   vendor/bin/eddy-assemble      # Assemble the codebase
   vendor/bin/eddy-start         # Start the PHP server
   vendor/bin/eddy-provision     # Provision the Drupal website
   ```
<!-- #;> DEV_NO_COMMAND_WRAPPER -->

   The build ends by printing the site URL and a one-time login link.

## Building website

The build runs in 3 stages: it assembles the codebase, starts the PHP server and provisions a Drupal site with this extension enabled. The [`drevops/eddy-tooling`](https://github.com/drevops/eddy-tooling) package provides a command for each stage. `scripts/eddy-tooling` installs that package into `vendor/`. CI runs the same commands to build and test this extension.

Never modify `scripts/eddy-tooling`, by hand or with AI. It comes from Eddy. A scaffold update replaces it with the copy from the new release. To change what a tooling command does, declare a patch for the package, as [Patching dependencies](#patching-dependencies) shows.

`scripts/eddy-tooling` installs the newest release that the `drevops/eddy-tooling` constraint in `composer.dev.json` allows. It runs Composer again only in these cases:

- The constraint changes.
- The patches declared for the package change, or a local patch file changes.
- A command of the package is missing from `vendor/bin`.

To pick up a newer release that the constraint allows, remove `vendor/` and build again.

The assembled codebase goes in the `build` directory, with the extension's files symlinked into the Drupal site. Before the assemble command replaces an earlier build, it stops the PHP server that serves that build.
<!-- #;< DEV_COMMAND_WRAPPER -->

The `build` command is a wrapper for 3 commands that each run 1 stage:
<!-- #;> DEV_COMMAND_WRAPPER -->
<!-- #;< DEV_MAKEFILE -->

```bash
make assemble     # Assemble the codebase
make start        # Start the PHP server
make provision    # Provision the Drupal website
```
<!-- #;> DEV_MAKEFILE -->
<!-- #;< DEV_AHOY -->

```bash
ahoy assemble     # Assemble the codebase
ahoy start        # Start the PHP server
ahoy provision    # Provision the Drupal website
```
<!-- #;> DEV_AHOY -->
<!-- #;< DEV_COMMAND_WRAPPER -->

To reinstall the Drupal site without assembling the codebase again, run the `provision` command on its own.

The `info` command prints a read-only summary of the environment, such as the PHP, Drupal and Drush versions, the site URL and the database path. The `login` command prints a new one-time login link.

The `reset` command stops the development server and the browser that the FunctionalJavascript tests use, and removes the `build`, `.logs` and `vendor` directories.
<!-- #;> DEV_COMMAND_WRAPPER -->
<!-- #;< DEV_NO_COMMAND_WRAPPER -->

To reinstall the Drupal site without assembling the codebase again, run `vendor/bin/eddy-provision` on its own.

`vendor/bin/eddy-info` prints a read-only summary of the environment, such as the PHP, Drupal and Drush versions, the site URL and the database path. `vendor/bin/eddy-login` prints a new one-time login link.
<!-- #;> DEV_NO_COMMAND_WRAPPER -->

### Drupal versions

The `DRUPAL_VERSION` variable sets the Drupal version that the build assembles. It defaults to `extra.eddy.drupal-version` in `composer.dev.json`, the newest Drupal major this extension targets that has a stable release.

To build another Drupal version, set `DRUPAL_VERSION` when you run the build:
<!-- #;< DEV_MAKEFILE -->

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
<!-- #;> DEV_MAKEFILE -->
<!-- #;< DEV_AHOY -->

```bash
# Newest stable Drupal 11 release.
DRUPAL_VERSION=11 ahoy build

# Newest Drupal 11.1.x patch release.
DRUPAL_VERSION=11.1.0 ahoy build

# Newest Drupal 11 beta, release candidate or stable release.
DRUPAL_VERSION=11@beta ahoy build

# Newest stable Drupal 12 release, or newest pre-release if none.
DRUPAL_VERSION=12 ahoy build
```
<!-- #;> DEV_AHOY -->
<!-- #;< DEV_NO_COMMAND_WRAPPER -->

```bash
# Newest stable Drupal 11 release.
DRUPAL_VERSION=11 vendor/bin/eddy-assemble

# Newest Drupal 11.1.x patch release.
DRUPAL_VERSION=11.1.0 vendor/bin/eddy-assemble

# Newest Drupal 11 beta, release candidate or stable release.
DRUPAL_VERSION=11@beta vendor/bin/eddy-assemble

# Newest stable Drupal 12 release, or newest pre-release if none.
DRUPAL_VERSION=12 vendor/bin/eddy-assemble

# After any of them, start the server and install the site.
vendor/bin/eddy-start
vendor/bin/eddy-provision
```
<!-- #;> DEV_NO_COMMAND_WRAPPER -->

The build pins Drupal core to the newest release that matches `DRUPAL_VERSION` and prints that release. A version without a stable release resolves to its newest pre-release.

The build resolves its dependencies for 1 PHP version: the `config.platform.php` of `composer.dev.json` or `composer.json` when either sets one, and the PHP that runs the build otherwise. A `config.platform.php` in your global Composer configuration doesn't apply. Before the build changes anything, it checks that PHP version and the PHP that runs the build against the requirement of the Drupal release. When either doesn't meet the requirement, the build stops and names the PHP version that the release requires.

Every other dependency installs its most stable release that supports that core and that PHP version. It installs a pre-release or a development branch only when no stable release fits. The build output lists every dependency from a development branch.

### Patching dependencies

The build installs [`cweagans/composer-patches`](https://github.com/cweagans/composer-patches) 2.x and applies the patches declared in the `patches` section of `composer.dev.json` or `composer.json`. Keep local patch files in the `patches` directory and reference them from `composer.dev.json` by their path:

```json
"extra": {
    "patches": {
        "drupal/core": {
            "Describe what the patch fixes": "patches/core-fix.patch"
        }
    }
}
```

A patch that no longer applies fails the build. `scripts/eddy-tooling` applies the patches declared for `drevops/eddy-tooling` when it installs the tooling into `vendor/`, so those patches stay out of the build.

`composer.json` ships with the extension, so a site that installs the extension with composer-patches 2.x applies the patches declared there too. That site has no `patches/` directory, so reference each patch in `composer.json` by a public URL.

### Providing `GITHUB_TOKEN`

To avoid GitHub API rate limits, set the `GITHUB_TOKEN` environment variable to a GitHub personal access token. The build passes the token to its own Composer commands through `COMPOSER_AUTH` and leaves your global Composer configuration as it is.

### Provisioning the website

The provision command installs a Drupal site from the `standard` profile. It enables the extension and every `drupal/*` module and theme in the `suggest` section of `composer.json`. To install from another profile, set the `DRUPAL_PROFILE` environment variable.

The start command serves the site on `localhost` and prints its URL. To serve the site on another host name, set the `WEBSERVER_HOST` environment variable.

The start command resolves the port, `WEBSERVER_PORT`, in this order:

1. **`WEBSERVER_PORT` exported in the shell** - used as is. For a one-off run, prefix a command with `WEBSERVER_PORT=9000`.
2. **A `WEBSERVER_PORT` line in the `.env` file of the project root** - used as is. The start command leaves the line unchanged, so the `start`, `stop`, `provision`, `drush` and `login` commands all use the same port.
3. **Neither** - the start command finds the first free port from 8000 to 8099 and writes it to `.env` as `WEBSERVER_PORT=NNNN`.

To pick a new port, delete the `WEBSERVER_PORT` line from `.env` and run the start command again.

The assemble, start and stop commands stop only the webserver that serves the `build/web` directory of this project. Another process on the port keeps running, such as the server of another project that recorded the same port. In that case, the start command fails and names the process. Stop that process, or set `WEBSERVER_PORT` in `.env` to a free port.

The site's database is the SQLite file `/tmp/site_[EXTENSION_NAME].sqlite`. To browse its tables, open the file in [DB Browser for SQLite](https://sqlitebrowser.org/).

### Step-debugging with XDebug

The development server can run with [XDebug](https://xdebug.org/docs/install) step-debugging. Install the XDebug PHP extension on your host first, so that `php -v` prints `with Xdebug`. Then restart the server with XDebug turned on or off:
<!-- #;< DEV_MAKEFILE -->

```bash
make debug      # restart with XDebug enabled
make start      # restart without XDebug
```
<!-- #;> DEV_MAKEFILE -->
<!-- #;< DEV_AHOY -->

```bash
ahoy debug      # restart with XDebug enabled
ahoy start      # restart without XDebug
```
<!-- #;> DEV_AHOY -->
<!-- #;< DEV_NO_COMMAND_WRAPPER -->

```bash
XDEBUG=1 vendor/bin/eddy-start    # restart with XDebug enabled
vendor/bin/eddy-start             # restart without XDebug
```
<!-- #;> DEV_NO_COMMAND_WRAPPER -->
<!-- #;< DEV_COMMAND_WRAPPER -->

The `debug` command has the aliases `debug-on`, `xdebug` and `xdebug-on`. The `start` command has the aliases `debug-off` and `xdebug-off`.

When the command line of the running server already holds `xdebug.mode=debug`, the `debug` command skips the restart.
<!-- #;> DEV_COMMAND_WRAPPER -->

The XDebug settings apply only to the development server, so the tests still collect code coverage with [pcov](https://github.com/krakjoe/pcov).

With XDebug on, every request starts a debug session, so a browser extension that sets the XDebug trigger has no effect. To skip debugging, stop listening in your IDE.

## Coding standards

<!-- #;< DEV_LINT -->
The following tools check the codebase, each with its configuration file in the root of the project:
<!-- #;> DEV_LINT -->
<!-- #;< DEV_PHPCS -->
- PHPCS checks PHP code against the `Drupal`, `DrupalPractice`, `DrevOps` and `PHPCompatibility` standards.
<!-- #;> DEV_PHPCS -->
<!-- #;< DEV_PHPSTAN -->
- PHPStan analyzes PHP code statically.
<!-- #;> DEV_PHPSTAN -->
<!-- #;< DEV_RECTOR -->
- Drupal Rector finds deprecated PHP code and fixes it.
<!-- #;> DEV_RECTOR -->
<!-- #;< DEV_TWIGCS -->
- Twig CS Fixer checks Twig templates.
<!-- #;> DEV_TWIGCS -->
<!-- #;< DEV_ESLINT -->
- ESLint checks JavaScript code.
<!-- #;> DEV_ESLINT -->
<!-- #;< DEV_STYLELINT -->
- Stylelint checks CSS code.
<!-- #;> DEV_STYLELINT -->
<!-- #;< DEV_CSPELL -->
- CSpell checks the spelling.
<!-- #;> DEV_CSPELL -->

To run every check:
<!-- #;< DEV_MAKEFILE -->

```bash
make lint
```
<!-- #;> DEV_MAKEFILE -->
<!-- #;< DEV_AHOY -->

```bash
ahoy lint
```
<!-- #;> DEV_AHOY -->
<!-- #;< DEV_NO_COMMAND_WRAPPER -->

```bash
cd build
#;< DEV_PHPCS
vendor/bin/phpcs
#;> DEV_PHPCS
#;< DEV_PHPSTAN
vendor/bin/phpstan
#;> DEV_PHPSTAN
#;< DEV_RECTOR
vendor/bin/rector --clear-cache --dry-run
#;> DEV_RECTOR
#;< DEV_TWIGCS
vendor/bin/twig-cs-fixer
#;> DEV_TWIGCS
#;< DEV_NODEJS_LINT
npm run lint
#;> DEV_NODEJS_LINT
#;< DEV_CSPELL
cd ..
npm install
npm run lint-spell
#;> DEV_CSPELL
```
<!-- #;> DEV_NO_COMMAND_WRAPPER -->

### Fixing coding standards issues

To fix the issues that have an automatic fix, run the fixers:
<!-- #;< DEV_MAKEFILE -->

```bash
make lint-fix
```
<!-- #;> DEV_MAKEFILE -->
<!-- #;< DEV_AHOY -->

```bash
ahoy lint-fix
```
<!-- #;> DEV_AHOY -->
<!-- #;< DEV_NO_COMMAND_WRAPPER -->

```bash
cd build
#;< DEV_RECTOR
vendor/bin/rector --clear-cache
#;> DEV_RECTOR
#;< DEV_PHPCS
vendor/bin/phpcbf
#;> DEV_PHPCS
#;< DEV_TWIGCS
vendor/bin/twig-cs-fixer --no-cache --fix
#;> DEV_TWIGCS
#;< DEV_NODEJS_LINT
npm run lint-fix
#;> DEV_NODEJS_LINT
```
<!-- #;> DEV_NO_COMMAND_WRAPPER -->

## Testing

To run every test:
<!-- #;< DEV_MAKEFILE -->

```bash
make test
```
<!-- #;> DEV_MAKEFILE -->
<!-- #;< DEV_AHOY -->

```bash
ahoy test
```
<!-- #;> DEV_AHOY -->
<!-- #;< DEV_NO_COMMAND_WRAPPER -->

```bash
#;< DEV_FUNCTIONAL_JAVASCRIPT
vendor/bin/eddy-browser-start
export WEBDRIVER_PORT="$(vendor/bin/eddy-info webdriver-port)"
export WEBDRIVER_BACKEND="$(vendor/bin/eddy-info webdriver-backend)"
export WEBDRIVER_HOST="$(vendor/bin/eddy-info webdriver-host)"
#;> DEV_FUNCTIONAL_JAVASCRIPT
#;< DEV_PHPUNIT
export WEBSERVER_PORT="$(vendor/bin/eddy-info webserver-port)"
export SIMPLETEST_BASE_URL="http://localhost:${WEBSERVER_PORT}"
export BROWSERTEST_OUTPUT_DIRECTORY="$PWD/.logs/browser_output"
#;> DEV_PHPUNIT
cd build
#;< DEV_PHPUNIT
php -d pcov.directory=.. vendor/bin/phpunit
#;> DEV_PHPUNIT
#;< DEV_JEST
npm test
#;> DEV_JEST
```
<!-- #;> DEV_NO_COMMAND_WRAPPER -->
<!-- #;< DEV_PHPUNIT -->

The PHPUnit tests live in the `tests/src` directory. The `phpunit.xml` file configures PHPUnit to run the tests, and `phpunit.d10.xml` replaces it in a Drupal 10 build. Both use Drupal core's bootstrap file, `web/core/tests/bootstrap.php`, to boot Drupal before the tests run.

To run 1 test suite:
<!-- #;< DEV_MAKEFILE -->

```bash
make test-unit                    # Run Unit tests
make test-kernel                  # Run Kernel tests
make test-functional              # Run Functional tests
#;< DEV_FUNCTIONAL_JAVASCRIPT
make test-functional-javascript   # Run FunctionalJavascript tests
#;> DEV_FUNCTIONAL_JAVASCRIPT
```
<!-- #;> DEV_MAKEFILE -->
<!-- #;< DEV_AHOY -->

```bash
ahoy test-unit                    # Run Unit tests
ahoy test-kernel                  # Run Kernel tests
ahoy test-functional              # Run Functional tests
#;< DEV_FUNCTIONAL_JAVASCRIPT
ahoy test-functional-javascript   # Run FunctionalJavascript tests
#;> DEV_FUNCTIONAL_JAVASCRIPT
```
<!-- #;> DEV_AHOY -->
<!-- #;< DEV_NO_COMMAND_WRAPPER -->

```bash
export WEBSERVER_PORT="$(vendor/bin/eddy-info webserver-port)"
export SIMPLETEST_BASE_URL="http://localhost:${WEBSERVER_PORT}"
export BROWSERTEST_OUTPUT_DIRECTORY="$PWD/.logs/browser_output"
cd build
php -d pcov.directory=.. vendor/bin/phpunit --testsuite unit
php -d pcov.directory=.. vendor/bin/phpunit --testsuite kernel
php -d pcov.directory=.. vendor/bin/phpunit --testsuite functional
```
<!-- #;> DEV_NO_COMMAND_WRAPPER -->

<!-- #;> DEV_PHPUNIT -->
<!-- #;< DEV_FUNCTIONAL_JAVASCRIPT -->
### Running FunctionalJavascript tests

FunctionalJavascript tests drive a real browser through WebDriver. By default, they use the Google Chrome installed on your machine, with a matching `chromedriver` that the first run downloads.

To run the FunctionalJavascript tests with Chrome:
<!-- #;< DEV_MAKEFILE -->

```bash
make start
make provision
make test-functional-javascript
make browser-stop
```
<!-- #;> DEV_MAKEFILE -->
<!-- #;< DEV_AHOY -->

```bash
ahoy start
ahoy provision
ahoy test-functional-javascript
ahoy browser-stop
```
<!-- #;> DEV_AHOY -->
<!-- #;< DEV_NO_COMMAND_WRAPPER -->

```bash
vendor/bin/eddy-start
vendor/bin/eddy-provision
vendor/bin/eddy-browser-start
export WEBDRIVER_PORT="$(vendor/bin/eddy-info webdriver-port)"
export WEBDRIVER_BACKEND="$(vendor/bin/eddy-info webdriver-backend)"
export WEBDRIVER_HOST="$(vendor/bin/eddy-info webdriver-host)"
export WEBSERVER_PORT="$(vendor/bin/eddy-info webserver-port)"
export SIMPLETEST_BASE_URL="http://localhost:${WEBSERVER_PORT}"
export BROWSERTEST_OUTPUT_DIRECTORY="$PWD/.logs/browser_output"
cd build
php -d pcov.directory=.. vendor/bin/phpunit --testsuite functional-javascript
cd ..
vendor/bin/eddy-browser-stop
```
<!-- #;> DEV_NO_COMMAND_WRAPPER -->
<!-- #;< DEV_COMMAND_WRAPPER -->

The `test` and `test-functional-javascript` commands start the browser before they run the tests. The browser keeps running for the next run to reuse, so stop it with `browser-stop` when you're done.

The `browser-start` command reuses a browser of the selected backend that already answers on the WebDriver port. Otherwise, it replaces this project's own browser of either backend, so you can switch `WEBDRIVER_BACKEND` without running `browser-stop` first. When anything else holds the port, `browser-start` fails and names it. The `browser-stop` command stops only this project's own browser, so the browser of another project on the same port keeps running.
<!-- #;> DEV_COMMAND_WRAPPER -->

To run the browser in a Selenium container, start Docker and set `WEBDRIVER_BACKEND=selenium`. The container can't reach the `localhost` of your host, so start the webserver on all interfaces with `WEBSERVER_HOST=0.0.0.0`:
<!-- #;< DEV_MAKEFILE -->

```bash
WEBSERVER_HOST=0.0.0.0 make start
make provision
WEBDRIVER_BACKEND=selenium make test-functional-javascript
make browser-stop
```
<!-- #;> DEV_MAKEFILE -->
<!-- #;< DEV_AHOY -->

```bash
WEBSERVER_HOST=0.0.0.0 ahoy start
ahoy provision
WEBDRIVER_BACKEND=selenium ahoy test-functional-javascript
ahoy browser-stop
```
<!-- #;> DEV_AHOY -->
<!-- #;< DEV_NO_COMMAND_WRAPPER -->

```bash
WEBSERVER_HOST=0.0.0.0 vendor/bin/eddy-start
vendor/bin/eddy-provision
WEBDRIVER_BACKEND=selenium vendor/bin/eddy-browser-start
export WEBDRIVER_PORT="$(vendor/bin/eddy-info webdriver-port)"
export WEBDRIVER_HOST="$(vendor/bin/eddy-info webdriver-host)"
export WEBSERVER_PORT="$(vendor/bin/eddy-info webserver-port)"
export SIMPLETEST_BASE_URL="http://localhost:${WEBSERVER_PORT}"
export BROWSERTEST_OUTPUT_DIRECTORY="$PWD/.logs/browser_output"
cd build
WEBDRIVER_BACKEND=selenium php -d pcov.directory=.. vendor/bin/phpunit \
  --testsuite functional-javascript
cd ..
vendor/bin/eddy-browser-stop
```
<!-- #;> DEV_NO_COMMAND_WRAPPER -->

With Chrome, the browser reaches the webserver at `localhost`. From the Selenium container, the browser uses `host.docker.internal` on macOS and `172.17.0.1` on other systems. To use another address, set `WEBDRIVER_HOST`.

Each project uses its own WebDriver port with either backend, so several projects can run FunctionalJavascript tests at the same time. The first browser start claims the first free port from 4444 and stores it in `.env`. To use a specific port, set `WEBDRIVER_PORT`.

The `YourExtensionFunctionalJavascriptTestBase` base class points the WebDriver endpoint at that port, so extend it in every FunctionalJavascript test. A test that extends `WebDriverTestBase` directly uses the default endpoint from `phpunit.xml`, unless it exports its own `MINK_DRIVER_ARGS_WEBDRIVER`.
<!-- #;< DEV_NO_COMMAND_WRAPPER -->

The commands in this section export the backend, host and port that `eddy-info` reads from your shell and `.env`, so `.env` values reach the tests.
<!-- #;> DEV_NO_COMMAND_WRAPPER -->

<!-- #;> DEV_FUNCTIONAL_JAVASCRIPT -->
<!-- #;< DEV_PHPUNIT -->
### Running specific tests

To run some of the tests, pass a test file path or a PHPUnit option, such as `--filter` or `--group`, to a test command. PHPUnit runs inside `build`, so a test path starts at the extension's symlink in the assembled site (`web/themes/custom/` for a theme):
<!-- #;< DEV_MAKEFILE -->

```bash
make test-unit web/modules/custom/your_extension/tests/src/Unit/MyUnitTest.php
make test-unit -- --group=wip
make test-unit -- --filter=MyUnitTest::testSomething
```

With `make`, put `--` before arguments that include an option, because `make` reads an option on its command line as its own. An argument can hold a space or name a `make` target, such as `test`, only as an option value joined with `=`: `--filter='my test'` or `--filter=test`. `make` keeps 1 value per option joined with `=`, so to repeat an option, separate it from each value with a space: `--group wip --group slow`.
<!-- #;> DEV_MAKEFILE -->
<!-- #;< DEV_AHOY -->

```bash
ahoy test-unit web/modules/custom/your_extension/tests/src/Unit/MyUnitTest.php
ahoy test-unit -- --group=wip
ahoy test-unit -- --filter=MyUnitTest::testSomething
```
<!-- #;> DEV_AHOY -->
<!-- #;< DEV_COMMAND_WRAPPER -->

To run PHPUnit directly, run it from `build`:
<!-- #;> DEV_COMMAND_WRAPPER -->

```bash
cd build
php -d pcov.directory=.. vendor/bin/phpunit \
  web/modules/custom/your_extension/tests/src/Unit/MyUnitTest.php
php -d pcov.directory=.. vendor/bin/phpunit --group=wip
php -d pcov.directory=.. vendor/bin/phpunit --filter=MyUnitTest::testSomething
```
<!-- #;> DEV_PHPUNIT -->
