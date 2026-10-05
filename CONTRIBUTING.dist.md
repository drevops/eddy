# Contributing

This document explains how to set up a local development environment, build the site, check coding standards, and run the tests for this extension.

## Local development

1. Install PHP with SQLite support and Composer
2. Clone this repository
3. Build the website:
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
   .devtools/assemble     # Assemble the codebase
   .devtools/start        # Start the PHP server
   .devtools/provision    # Provision the Drupal website
   ```
<!-- #;> DEV_NO_COMMAND_WRAPPER -->

## Building website

Building the website assembles the codebase, starts the PHP server and provisions the Drupal website with this extension enabled. These operations are executed using scripts within [`.devtools`](.devtools) directory. CI uses the same scripts to build and test this extension.

The resulting codebase is then placed in the `build` directory. The extension files are symlinked into the Drupal site structure.
<!-- #;< DEV_COMMAND_WRAPPER -->

The `build` command is a wrapper for more granular commands:
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

The `provision` command is useful for re-installing the Drupal website without re-assembling the codebase.

### Drupal versions

The Drupal version used for the codebase assembly is determined by the `DRUPAL_VERSION` variable and defaults to the highest Drupal major this extension targets.

You can specify a different version by setting the `DRUPAL_VERSION` environment variable when building the website:
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
DRUPAL_VERSION=11 .devtools/assemble

# Newest Drupal 11.1.x patch release.
DRUPAL_VERSION=11.1.0 .devtools/assemble

# Newest Drupal 11 beta, release candidate or stable release.
DRUPAL_VERSION=11@beta .devtools/assemble

# Newest stable Drupal 12 release, or newest pre-release if none.
DRUPAL_VERSION=12 .devtools/assemble
```
<!-- #;> DEV_NO_COMMAND_WRAPPER -->

The build pins Drupal core to the newest release matching `DRUPAL_VERSION` and prints it. A version with no stable release yet resolves to its newest pre-release.

Every other dependency installs its most stable release that supports that core. A dependency falls back to a pre-release or a development branch only when no stable release fits, and the build output lists every dependency installed from a development branch.

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

A patch that no longer applies fails the build.

`composer.json` ships with the extension, so composer-patches 2.x on a site that installs the extension applies its patches too. Reference patches declared there by a public URL, since a local `patches/` path doesn't exist on that site.

### Providing `GITHUB_TOKEN`

To overcome GitHub API rate limits, you may provide a `GITHUB_TOKEN` environment variable with a personal access token.

### Provisioning the website

The `provision` command installs the Drupal website from the `standard` profile with the extension (and any `suggest`'ed extensions) enabled. The profile can be changed by setting the `DRUPAL_PROFILE` environment variable.

The website will be available at http://localhost:8000 by default. The hostname can be changed by setting the `WEBSERVER_HOST` environment variable.

The `WEBSERVER_PORT` is resolved with the following precedence:

1. **`WEBSERVER_PORT` exported in the shell** - used as-is. Useful for one-off runs: prefix a command with `WEBSERVER_PORT=9000`.
2. **`WEBSERVER_PORT` line in the project-root `.env` file** - used as-is. The `start` script does not modify `.env` when this entry is already present, so the same port is reused across `start`, `stop`, `provision`, `drush` and `login` commands.
3. **Neither is set** - the `start` script discovers the first free port in the range `8000-8099` and writes it to `.env` as `WEBSERVER_PORT=NNNN`. Subsequent commands read this value from `.env`.

To force re-discovery, delete `.env` (or just the `WEBSERVER_PORT` line in it) and re-run the `start` command.

An SQLite database is created in `/tmp/site_[EXTENSION_NAME].sqlite` file. You can browse the contents of the created SQLite database using [DB Browser for SQLite](https://sqlitebrowser.org/).

A one-time login link will be printed to the console.

### Step-debugging with XDebug

PHP step-debugging is supported via [XDebug](https://xdebug.org/docs/install). Install the XDebug PHP extension on your host (`php -v` should mention `with Xdebug`), then toggle it on the development server:
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
XDEBUG=1 .devtools/start    # restart with XDebug enabled
.devtools/start             # restart without XDebug
```
<!-- #;> DEV_NO_COMMAND_WRAPPER -->
<!-- #;< DEV_COMMAND_WRAPPER -->

`debug` is also available as `debug-on`, `xdebug` and `xdebug-on`, and `start` as `debug-off` and `xdebug-off`.

The `debug` command probes the running PHP server's command line for `xdebug.mode=debug` and skips the restart if XDebug is already enabled.
<!-- #;> DEV_COMMAND_WRAPPER -->

Code coverage stays on [pcov](https://github.com/krakjoe/pcov) because the XDebug settings apply only to the development server, not to the test commands.

To start and stop debug sessions from the browser, install the Xdebug Helper extension: [Chrome](https://chromewebstore.google.com/detail/xdebug-helper-by-jetbrain/aoelhdemabeimdhedkidlnbkfhnhgnhm) / [Firefox](https://addons.mozilla.org/en-US/firefox/addon/xdebug-helper-by-jetbrains/).

## Coding standards

The codebase is checked using multiple tools:
<!-- #;< DEV_PHPCS -->
- PHP code standards checking against `Drupal` and `DrupalPractice` standards.
<!-- #;> DEV_PHPCS -->
<!-- #;< DEV_PHPSTAN -->
- PHP code static analysis with PHPStan.
<!-- #;> DEV_PHPSTAN -->
<!-- #;< DEV_RECTOR -->
- PHP deprecated code analysis and auto-fixing with Drupal Rector.
<!-- #;> DEV_RECTOR -->
<!-- #;< DEV_TWIGCS -->
- Twig code analysis with Twig CS Fixer.
<!-- #;> DEV_TWIGCS -->
<!-- #;< DEV_ESLINT -->
- JavaScript code analysis with ESLint.
<!-- #;> DEV_ESLINT -->
<!-- #;< DEV_STYLELINT -->
- CSS code analysis with Stylelint.
<!-- #;> DEV_STYLELINT -->

The configuration files for these tools are located in the root of the codebase.

Run all checks with:
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

To fix coding standards issues automatically, run the same tools with the `--fix` option (for the tools that support it):
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

Run the tests for this extension with:
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

The tests are located in the `tests/src` directory. The `phpunit.xml` file configures PHPUnit to run the tests. It uses Drupal core's bootstrap file `web/core/tests/bootstrap.php` to bootstrap the Drupal environment before running the tests.
<!-- #;< DEV_COMMAND_WRAPPER -->

The `test` command is a wrapper for multiple test commands:
<!-- #;> DEV_COMMAND_WRAPPER -->
<!-- #;< DEV_NO_COMMAND_WRAPPER -->

Each test suite can also be run on its own:
<!-- #;> DEV_NO_COMMAND_WRAPPER -->
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
cd build
php -d pcov.directory=.. vendor/bin/phpunit --testsuite unit         # Run Unit tests
php -d pcov.directory=.. vendor/bin/phpunit --testsuite kernel       # Run Kernel tests
php -d pcov.directory=.. vendor/bin/phpunit --testsuite functional   # Run Functional tests
```
<!-- #;> DEV_NO_COMMAND_WRAPPER -->

<!-- #;> DEV_PHPUNIT -->
<!-- #;< DEV_FUNCTIONAL_JAVASCRIPT -->
### Running FunctionalJavascript tests

FunctionalJavascript tests need a real browser driven via WebDriver. By default they use the Google Chrome already installed on your machine - a matching `chromedriver` is downloaded automatically on first run, so no Docker is required:
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
.devtools/start
.devtools/provision
.devtools/browser start
export WEBDRIVER_PORT="$(.devtools/info webdriver-port)"
cd build
php -d pcov.directory=.. vendor/bin/phpunit --testsuite functional-javascript
cd ..
.devtools/browser stop
```
<!-- #;> DEV_NO_COMMAND_WRAPPER -->

To run the browser in a Docker Selenium container instead, set `WEBDRIVER_BACKEND=selenium`. The container cannot reach the host's `localhost`, so start the webserver on all interfaces:
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
WEBSERVER_HOST=0.0.0.0 .devtools/start
.devtools/provision
WEBDRIVER_BACKEND=selenium .devtools/browser start
export WEBDRIVER_PORT="$(.devtools/info webdriver-port)"
cd build
WEBDRIVER_BACKEND=selenium php -d pcov.directory=.. vendor/bin/phpunit --testsuite functional-javascript
cd ..
.devtools/browser stop
```
<!-- #;> DEV_NO_COMMAND_WRAPPER -->

The browser reaches the webserver at `localhost` with the default backend, and at `host.docker.internal` (macOS) or `172.17.0.1` (other systems) from the Selenium container. Set `WEBDRIVER_HOST` to use a different address.

Either backend gets its own WebDriver port: a free one is claimed starting at 4444 and stored in `.env`, so several projects can run FunctionalJavascript tests at the same time. Set `WEBDRIVER_PORT` to pin a specific one. Tests reach the claimed port because the base class applies it to the WebDriver endpoint, so extend `YourExtensionFunctionalJavascriptTestBase` rather than `WebDriverTestBase` directly - a test that bypasses it keeps the default endpoint from `phpunit.xml` unless it exports its own `MINK_DRIVER_ARGS_WEBDRIVER`.

<!-- #;> DEV_FUNCTIONAL_JAVASCRIPT -->
<!-- #;< DEV_PHPUNIT -->
### Running specific tests

You can run specific tests by passing a path to the test file or PHPUnit CLI option (`--filter`, `--group`, etc.) to the test commands. PHPUnit runs inside `build`, so a test path starts at the extension's symlink in the assembled site (`web/themes/custom/` for a theme):
<!-- #;< DEV_MAKEFILE -->

```bash
make test-unit web/modules/custom/your_extension/tests/src/Unit/MyUnitTest.php
make test-unit -- --group=wip
```
<!-- #;> DEV_MAKEFILE -->
<!-- #;< DEV_AHOY -->

```bash
ahoy test-unit web/modules/custom/your_extension/tests/src/Unit/MyUnitTest.php
ahoy test-unit -- --group=wip
```
<!-- #;> DEV_AHOY -->
<!-- #;< DEV_COMMAND_WRAPPER -->

You may also run tests using the `phpunit` command directly:
<!-- #;> DEV_COMMAND_WRAPPER -->

```bash
cd build
php -d pcov.directory=.. vendor/bin/phpunit \
  web/modules/custom/your_extension/tests/src/Unit/MyUnitTest.php
php -d pcov.directory=.. vendor/bin/phpunit --group=wip
```
<!-- #;> DEV_PHPUNIT -->
