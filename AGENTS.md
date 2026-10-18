# AGENTS.md

This file provides guidance to AI agents when working with code in this repository.

## Overview

<!-- #;< META -->
This is a Drupal extension scaffold template for creating contributed modules or themes. `install.php` turns the example extension below into yours.

<!-- #;> META -->
Your Extension (`your_extension`) is a Drupal module. The project provides a complete development environment with CI configuration, testing setup, and deployment automation for publishing to Drupal.org.

## Development Commands
<!-- #;< DEV_COMMAND_WRAPPER -->

**HARD RULE - use the provided command wrappers, never the tool binaries directly.** When `make` or `ahoy` exposes a command for a task, use that command; do not call the underlying binary directly. Each wrapper runs the tool where CI runs it, in `build/` for most tools, with the config, plugins, and environment that CI uses, so a raw invocation from the repository root silently diverges from CI - it can pass locally while CI fails (or vice versa), or crash outright when a relative path resolves against the wrong directory. If no wrapped command covers what you need, extend the `make` or `ahoy` target rather than making a one-off raw call; if that is not feasible, stop and ask.
<!-- #;> DEV_COMMAND_WRAPPER -->
<!-- #;< DEV_NO_COMMAND_WRAPPER -->

**HARD RULE - run each tool exactly as `CONTRIBUTING.md` shows.** This project has no command wrapper, so the commands below run directly. Most tools run from `build/`, which holds the config, plugins, and environment that CI uses, so a raw invocation from the repository root silently diverges from CI - it can pass locally while CI fails (or vice versa), or crash outright when a relative path resolves against the wrong directory.
<!-- #;> DEV_NO_COMMAND_WRAPPER -->

**HARD RULE - never modify `scripts/eddy-tooling`.** It comes from Eddy as-is, and a scaffold update replaces it with the release's copy. To change what a tooling command does, declare a patch for the `drevops/eddy-tooling` package in `composer.dev.json`.
<!-- #;< DEV_MAKEFILE -->

Run each tool through its `make` wrapper, never the binary directly:

<!-- #;< DEV_PHPCS -->
- **PHPCS / PHPCBF**: `make lint` / `make lint-fix` - never `vendor/bin/phpcs` or `vendor/bin/phpcbf`.
<!-- #;> DEV_PHPCS -->
<!-- #;< DEV_PHPSTAN -->
- **PHPStan**: `make lint` - never `vendor/bin/phpstan`.
<!-- #;> DEV_PHPSTAN -->
<!-- #;< DEV_RECTOR -->
- **Rector**: `make lint` (dry-run) / `make lint-fix` - never `vendor/bin/rector`.
<!-- #;> DEV_RECTOR -->
<!-- #;< DEV_TWIGCS -->
- **Twig CS Fixer**: `make lint` / `make lint-fix` - never `vendor/bin/twig-cs-fixer`.
<!-- #;> DEV_TWIGCS -->
<!-- #;< DEV_NODEJS_LINT -->
- **ESLint / Stylelint**: `make lint` / `make lint-fix` - never `npx eslint` or `npx stylelint`.
<!-- #;> DEV_NODEJS_LINT -->
<!-- #;< DEV_CSPELL -->
- **CSpell**: `make lint` - never `npx cspell`.
<!-- #;> DEV_CSPELL -->
<!-- #;< DEV_PHPUNIT -->
- **PHPUnit**: `make test` / `make test-unit` / `make test-kernel` / `make test-functional` - never `vendor/bin/phpunit`.
<!-- #;> DEV_PHPUNIT -->
<!-- #;< DEV_JEST -->
- **Jest**: `make test-javascript` - never `npx jest`.
<!-- #;> DEV_JEST -->
- **Drush**: `make drush <command>` - never `build/vendor/bin/drush` directly.
<!-- #;> DEV_MAKEFILE -->
<!-- #;< DEV_AHOY -->

Run each tool through its `ahoy` wrapper, never the binary directly:

<!-- #;< DEV_PHPCS -->
- **PHPCS / PHPCBF**: `ahoy lint` / `ahoy lint-fix` - never `vendor/bin/phpcs` or `vendor/bin/phpcbf`.
<!-- #;> DEV_PHPCS -->
<!-- #;< DEV_PHPSTAN -->
- **PHPStan**: `ahoy lint` - never `vendor/bin/phpstan`.
<!-- #;> DEV_PHPSTAN -->
<!-- #;< DEV_RECTOR -->
- **Rector**: `ahoy lint` (dry-run) / `ahoy lint-fix` - never `vendor/bin/rector`.
<!-- #;> DEV_RECTOR -->
<!-- #;< DEV_TWIGCS -->
- **Twig CS Fixer**: `ahoy lint` / `ahoy lint-fix` - never `vendor/bin/twig-cs-fixer`.
<!-- #;> DEV_TWIGCS -->
<!-- #;< DEV_NODEJS_LINT -->
- **ESLint / Stylelint**: `ahoy lint` / `ahoy lint-fix` - never `npx eslint` or `npx stylelint`.
<!-- #;> DEV_NODEJS_LINT -->
<!-- #;< DEV_CSPELL -->
- **CSpell**: `ahoy lint` - never `npx cspell`.
<!-- #;> DEV_CSPELL -->
<!-- #;< DEV_PHPUNIT -->
- **PHPUnit**: `ahoy test` / `ahoy test-unit` / `ahoy test-kernel` / `ahoy test-functional` - never `vendor/bin/phpunit`.
<!-- #;> DEV_PHPUNIT -->
<!-- #;< DEV_JEST -->
- **Jest**: `ahoy test-javascript` - never `npx jest`.
<!-- #;> DEV_JEST -->
- **Drush**: `ahoy drush <command>` - never `build/vendor/bin/drush` directly.
<!-- #;> DEV_AHOY -->

### Build and Environment Management
<!-- #;< DEV_MAKEFILE -->

**Using Make:**
- `make build` - Complete build (stop → assemble → start → provision)
- `make assemble` - Assemble codebase with dependencies
- `make start` - Start PHP development server
- `make stop` - Stop development server
- `make provision` - Install/provision Drupal site
- `make reset` - Clean build directory, logs and installed tooling (aliases: `make delete`, `make destroy`)
<!-- #;> DEV_MAKEFILE -->
<!-- #;< DEV_AHOY -->

**Using Ahoy:**
- `ahoy build` - Complete build process
- `ahoy assemble` - Assemble codebase
- `ahoy start` - Start development server
- `ahoy stop` - Stop development server
- `ahoy provision` - Provision Drupal site
- `ahoy reset` - Clean build directory, logs and installed tooling (aliases: `ahoy delete`, `ahoy destroy`)
<!-- #;> DEV_AHOY -->
<!-- #;< DEV_NO_COMMAND_WRAPPER -->

- `scripts/eddy-tooling` - Install the tooling commands into `vendor/bin/`. Run it before the commands below
- `vendor/bin/eddy-assemble` - Assemble codebase with dependencies
- `vendor/bin/eddy-start` - Start PHP development server
- `vendor/bin/eddy-stop` - Stop development server
- `vendor/bin/eddy-provision` - Install/provision Drupal site
<!-- #;> DEV_NO_COMMAND_WRAPPER -->

### Code Quality
<!-- #;< DEV_COMMAND_WRAPPER -->

**Linting:**
<!-- #;< DEV_MAKEFILE -->
- `make lint` - Run all linting tools
- `make lint-fix` - Auto-fix coding standards violations
<!-- #;> DEV_MAKEFILE -->
<!-- #;< DEV_AHOY -->
- `ahoy lint` - Run all linting tools
- `ahoy lint-fix` - Auto-fix coding standards violations
<!-- #;> DEV_AHOY -->

**Testing:**
<!-- #;< DEV_MAKEFILE -->
- `make test` - Run all tests
<!-- #;< DEV_PHPUNIT -->
- `make test-unit` - Run unit tests only
- `make test-kernel` - Run kernel tests only
- `make test-functional` - Run functional tests only
<!-- #;> DEV_PHPUNIT -->
<!-- #;< DEV_FUNCTIONAL_JAVASCRIPT -->
- `make test-functional-javascript` - Run FunctionalJavascript tests (uses the local Chrome by default; set `WEBDRIVER_BACKEND=selenium` for Docker)
<!-- #;> DEV_FUNCTIONAL_JAVASCRIPT -->
<!-- #;< DEV_JEST -->
- `make test-javascript` - Run JavaScript unit tests with Jest (alias: `make test-js`)
<!-- #;> DEV_JEST -->
<!-- #;< DEV_FUNCTIONAL_JAVASCRIPT -->
- `make browser-start` - Start the browser for FunctionalJavascript tests (local Chrome by default; set `WEBDRIVER_BACKEND=selenium` for Docker)
- `make browser-stop` - Stop the browser
<!-- #;> DEV_FUNCTIONAL_JAVASCRIPT -->
<!-- #;> DEV_MAKEFILE -->
<!-- #;< DEV_AHOY -->
- `ahoy test` - Run all tests
<!-- #;< DEV_PHPUNIT -->
- `ahoy test-unit` - Run unit tests only
- `ahoy test-kernel` - Run kernel tests only
- `ahoy test-functional` - Run functional tests only
<!-- #;> DEV_PHPUNIT -->
<!-- #;< DEV_FUNCTIONAL_JAVASCRIPT -->
- `ahoy test-functional-javascript` - Run FunctionalJavascript tests (uses the local Chrome by default; set `WEBDRIVER_BACKEND=selenium` for Docker)
<!-- #;> DEV_FUNCTIONAL_JAVASCRIPT -->
<!-- #;< DEV_JEST -->
- `ahoy test-javascript` - Run JavaScript unit tests with Jest (alias: `ahoy test-js`)
<!-- #;> DEV_JEST -->
<!-- #;< DEV_FUNCTIONAL_JAVASCRIPT -->
- `ahoy browser-start` - Start the browser for FunctionalJavascript tests (local Chrome by default; set `WEBDRIVER_BACKEND=selenium` for Docker)
- `ahoy browser-stop` - Stop the browser
<!-- #;> DEV_FUNCTIONAL_JAVASCRIPT -->
<!-- #;> DEV_AHOY -->
<!-- #;> DEV_COMMAND_WRAPPER -->
<!-- #;< DEV_NO_COMMAND_WRAPPER -->

Run the linters and the tests with the commands that `CONTRIBUTING.md` lists.
<!-- #;> DEV_NO_COMMAND_WRAPPER -->

### Drupal Commands

<!-- #;< DEV_MAKEFILE -->
- `make drush <command>` - Run Drush commands. Put `--` before the arguments when they include an option, such as `make drush -- config:get system.site --format=json`, because `make` reads an option on its command line as its own. An argument can't hold a space or name a `make` target, such as `help`, unless it's an option value joined with `=`
- `make login` - Get one-time login link
<!-- #;> DEV_MAKEFILE -->
<!-- #;< DEV_AHOY -->
- `ahoy drush <command>` - Run Drush commands
- `ahoy login` - Get one-time login link
<!-- #;> DEV_AHOY -->
<!-- #;< DEV_NO_COMMAND_WRAPPER -->
- `build/vendor/bin/drush -l "$(vendor/bin/eddy-info site-url)" <command>` - Run Drush commands
- `build/vendor/bin/drush -l "$(vendor/bin/eddy-info site-url)" uli` - Get one-time login link
<!-- #;> DEV_NO_COMMAND_WRAPPER -->

### Diagnostics

<!-- #;< DEV_MAKEFILE -->
- `make info` - Print a read-only summary of PHP/Drupal/Composer/Drush/Node/npm versions, webserver host/port (with source), site URL, XDebug state, WebDriver port, build directory, database path, and active profile. (alias: `make describe`)
<!-- #;> DEV_MAKEFILE -->
<!-- #;< DEV_AHOY -->
- `ahoy info` - Print a read-only summary of PHP/Drupal/Composer/Drush/Node/npm versions, webserver host/port (with source), site URL, XDebug state, WebDriver port, build directory, database path, and active profile. (alias: `ahoy describe`)
<!-- #;> DEV_AHOY -->
<!-- #;< DEV_NO_COMMAND_WRAPPER -->
- `vendor/bin/eddy-info` - Print a read-only summary of PHP/Drupal/Composer/Drush/Node/npm versions, webserver host/port (with source), site URL, XDebug state, WebDriver port, build directory, database path, and active profile.
<!-- #;> DEV_NO_COMMAND_WRAPPER -->

## Project Structure

**Key Directories:**
<!-- #;< EXTENSION_MODULE -->
- `src/` - Extension source code (services, forms, etc.)
<!-- #;< DEV_PHPUNIT -->
- `tests/src/` - PHPUnit tests (Unit/, Kernel/, Functional/)
<!-- #;> DEV_PHPUNIT -->
- `config/schema/` - Configuration schema definitions
<!-- #;> EXTENSION_MODULE -->
<!-- #;< EXTENSION_THEME -->
- `css/` and `js/` - Stylesheets and JavaScript behaviors of the theme's global library
<!-- #;< DEV_PHPUNIT -->
- `tests/src/` - PHPUnit tests (Unit/, FunctionalJavascript/)
<!-- #;> DEV_PHPUNIT -->
<!-- #;> EXTENSION_THEME -->
- `build/` - Assembled Drupal codebase (symlinked extension)
- `vendor/` - The installed `drevops/eddy-tooling` package, whose commands (`vendor/bin/eddy-*`) assemble, start, provision and deploy the extension. Not committed and not part of the extension
- `scripts/eddy-tooling` - Installs the tooling package into `vendor/` from the constraint in `composer.dev.json`. The command wrappers and CI run it before the tooling commands
- `scripts/` - Custom lifecycle hooks: post-assemble (`assemble-*.sh`), post-provision (`provision-*.sh`), post-start (`start-*.sh`), and pre-stop (`stop-*.sh`). Run automatically during each phase in lexicographic order; non-zero exit aborts the parent. Excluded from distribution archives via `.gitattributes`

<!-- #;< META -->
**Template Files (before installation):**
- `your_extension.*` - Template extension files
- `YourExtensionService.php` - Main service class template

<!-- #;> META -->
## Architecture

<!-- #;< EXTENSION_MODULE -->
- **Service-based architecture**: Main functionality in services registered via `*.services.yml`
- **Configuration-driven**: Uses Drupal configuration system with schema validation
<!-- #;< DEV_PHPUNIT -->
- **Test coverage**: Unit, kernel, and functional test examples provided
<!-- #;> DEV_PHPUNIT -->
- **Form integration**: Admin forms in `src/Form/` for configuration
<!-- #;> EXTENSION_MODULE -->
<!-- #;< EXTENSION_THEME -->
- **No base theme**: `base theme: false`, so Drupal's default templates render every page until the theme overrides them in `templates/`
- **Theme hooks**: `your_extension.theme` adds a `div[data-your-extension-time]` element to every page, which `js/your_extension.js` fills with the page load time
- **Global library**: The `libraries` key in `your_extension.info.yml` attaches the `global` library from `your_extension.libraries.yml` to every page
<!-- #;< DEV_PHPUNIT -->
- **Test coverage**: Unit test example for the theme hooks provided, plus a FunctionalJavascript smoke test example with the theme installed as the default theme
<!-- #;> DEV_PHPUNIT -->
<!-- #;> EXTENSION_THEME -->

## Environment Variables

- `DRUPAL_VERSION` - Target Drupal version (e.g., `10`, `11`, `11@alpha`, `12@beta`). Defaults to `extra.eddy.drupal-version` in `composer.dev.json`
- `WEBSERVER_HOST` - Development server host (default: localhost)
- `WEBSERVER_PORT` - Development server port. Auto-discovered from range 8000-8099 and written to `.env` if not already set
<!-- #;< DEV_FUNCTIONAL_JAVASCRIPT -->
- `WEBDRIVER_BACKEND` - FunctionalJavascript WebDriver backend: `chromedriver` (default, drives the locally installed Chrome with no Docker) or `selenium` (Docker container)
- `WEBDRIVER_PORT` - Port for the WebDriver endpoint (both backends). Auto-discovered from 4444 and written to `.env` if not already set, so several projects can run FunctionalJavascript tests simultaneously. The endpoint in `phpunit.xml` is the default for port 4444; tests reach the resolved port because the FunctionalJavascript base class rewrites the port in `MINK_DRIVER_ARGS_WEBDRIVER` from this variable, so FunctionalJavascript tests must extend that base class (or export their own `MINK_DRIVER_ARGS_WEBDRIVER`)
- `WEBDRIVER_HOST` - Address the FunctionalJavascript browser uses to reach the PHP webserver. Defaults to `localhost` with `chromedriver`, and to `host.docker.internal` (macOS) or `172.17.0.1` (other systems) with `selenium`
<!-- #;> DEV_FUNCTIONAL_JAVASCRIPT -->
- `WEBSERVER_WAIT_TIMEOUT` - Seconds that `start` waits for the development server to accept connections (default: 5)
- `DRUPAL_PROFILE` - Install profile that `provision` installs the site from (default: `standard`)
- `XDEBUG` - Set to `1` to start the development server with XDebug step-debugging
- `CLOUDFLARE_TUNNEL` - Set to `1` to expose the development server through a public Cloudflare quick tunnel. `start` writes the tunnel URL to `.env` as `TUNNEL_URL`
<!-- #;< DEV_COMMAND_WRAPPER -->
- `LOGIN_QRCODE` - Set to `1` to also render the `login` link as a terminal QR code (needs `qrencode`)
<!-- #;> DEV_COMMAND_WRAPPER -->
- `GITHUB_TOKEN` - GitHub API token to avoid rate limits
- `DEBUG` - Set to `1` to stream the full output of the underlying commands (Composer, npm, Drush). By default this output is suppressed and shown only when a command fails

## Development Workflow

<!-- #;< META -->
Run `php install.php` once, in a fresh copy of the template, to turn it into your extension. The script deletes itself when it finishes, unless you answer no to "Remove this script". Then:

<!-- #;> META -->
<!-- #;< DEV_COMMAND_WRAPPER -->
1. Build environment: `make build` or `ahoy build`
<!-- #;> DEV_COMMAND_WRAPPER -->
<!-- #;< DEV_NO_COMMAND_WRAPPER -->
1. Build environment: `scripts/eddy-tooling`, then `vendor/bin/eddy-assemble`, `vendor/bin/eddy-start` and `vendor/bin/eddy-provision`
<!-- #;> DEV_NO_COMMAND_WRAPPER -->
<!-- #;< EXTENSION_MODULE -->
2. Develop your extension code in `src/`
<!-- #;> EXTENSION_MODULE -->
<!-- #;< EXTENSION_THEME -->
2. Develop your theme: Twig templates in `templates/`, hooks in `your_extension.theme`, styles in `css/` and behaviors in `js/`
<!-- #;> EXTENSION_THEME -->
<!-- #;< DEV_COMMAND_WRAPPER -->
3. Check standards: `make lint` or `ahoy lint`
4. Run tests: `make test` or `ahoy test`
5. Access site at the URL that `make info` or `ahoy info` reports
<!-- #;> DEV_COMMAND_WRAPPER -->
<!-- #;< DEV_NO_COMMAND_WRAPPER -->
3. Check standards: run the linters that `CONTRIBUTING.md` lists
4. Run tests: run the tests that `CONTRIBUTING.md` lists
5. Access site at the URL that `vendor/bin/eddy-info site-url` prints
<!-- #;> DEV_NO_COMMAND_WRAPPER -->

## Code Quality Tools

<!-- #;< DEV_CSPELL -->
- **CSpell**: Spell checking across the codebase (config at `.cspell.json`)
<!-- #;> DEV_CSPELL -->
<!-- #;< DEV_PHPCS -->
- **PHPCS**: Drupal, DrupalPractice, DrevOps and PHPCompatibility standards
<!-- #;> DEV_PHPCS -->
<!-- #;< DEV_PHPSTAN -->
- **PHPStan**: Static analysis with Drupal extensions
<!-- #;> DEV_PHPSTAN -->
<!-- #;< DEV_RECTOR -->
- **Rector**: Automated refactoring and deprecation fixes
<!-- #;> DEV_RECTOR -->
<!-- #;< DEV_TWIGCS -->
- **Twig CS Fixer**: Twig template formatting
<!-- #;> DEV_TWIGCS -->
<!-- #;< DEV_ESLINT -->
- **ESLint**: JavaScript linting with Prettier formatting (config at `.eslintrc.json`)
<!-- #;> DEV_ESLINT -->
<!-- #;< DEV_STYLELINT -->
- **Stylelint**: CSS linting (config at `.stylelintrc.js`)
<!-- #;> DEV_STYLELINT -->

## CI/CD Support

- **GitHub Actions**: `.github/workflows/test.yml` and deployment
- **Matrix testing**:
<!-- #;< DRUPAL_10 -->
  - Drupal 10 on PHP 8.3-8.4
<!-- #;> DRUPAL_10 -->
<!-- #;< DRUPAL_11 -->
  - Drupal 11 on PHP 8.3-8.5
<!-- #;> DRUPAL_11 -->
<!-- #;< DRUPAL_12 -->
  - Drupal 12 on PHP 8.5
<!-- #;> DRUPAL_12 -->
- **Automated deployment**: Mirror to Drupal.org after CI passes

## Important Notes

- The `build/` directory contains the assembled Drupal site
- Extension files are symlinked from root into `build/web/modules/custom/` (module) or `build/web/themes/custom/` (theme)
- SQLite database created in `/tmp/site_[EXTENSION_NAME].sqlite`
- The PHP and JavaScript quality tools run from within the `build/` directory
<!-- #;< DEV_CSPELL -->
- CSpell runs from the project root
<!-- #;> DEV_CSPELL -->

## Updating the scaffold

When the user asks to update this project's scaffold (e.g. "update scaffold" or "update Eddy"), fetch the update skill from GitHub into the local `.claude/skills/` directory, then invoke it:

1. Create the target directory if it does not exist:

   ```bash
   mkdir -p .claude/skills/update-consumer-eddy
   ```

2. Download the skill:

   ```bash
   curl -fsSL -o .claude/skills/update-consumer-eddy/SKILL.md \
     https://raw.githubusercontent.com/drevops/eddy/main/.eddy/skills/update-consumer-eddy/SKILL.md
   ```

3. Invoke the `update-consumer-eddy` skill and follow its steps.

The skill directory is git-ignored - it is fetched on demand and not committed to the project.
