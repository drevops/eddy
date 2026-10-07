# AGENTS.md

This file provides guidance to AI agents when working with code in this repository.

## Overview

This is a Force Crystal template for creating contributed modules or themes. The project provides a complete development environment with CI configuration, testing setup, and deployment automation for publishing to Drupal.org.

## Development Commands

**HARD RULE - use the provided command wrappers, never the tool binaries directly.** When `make` or `ahoy` exposes a command for a task, use that command; do not call the underlying binary directly. Each wrapper `chdir`s into `build/` and runs the tool with the config, plugins, and environment that CI uses, so a raw invocation from the repository root silently diverges from CI - it can pass locally while CI fails (or vice versa), or crash outright when a relative path resolves against the wrong directory. If no wrapped command covers what you need, extend the `make` / `ahoy` target rather than making a one-off raw call; if that is not feasible, stop and ask.


Run each tool through its `ahoy` wrapper, never the binary directly:

- **PHPCS / PHPCBF**: `ahoy lint` / `ahoy lint-fix` - never `vendor/bin/phpcs` or `vendor/bin/phpcbf`.
- **PHPStan**: `ahoy lint` - never `vendor/bin/phpstan`.
- **Rector**: `ahoy lint` (dry-run) / `ahoy lint-fix` - never `vendor/bin/rector`.
- **Twig CS Fixer**: `ahoy lint` / `ahoy lint-fix` - never `vendor/bin/twig-cs-fixer`.
- **ESLint / Stylelint**: `ahoy lint` / `ahoy lint-fix` - never `npx eslint` or `npx stylelint`.
- **CSpell**: `ahoy lint` - never `npx cspell`.
- **PHPUnit**: `ahoy test` / `ahoy test-unit` / `ahoy test-kernel` / `ahoy test-functional` - never `vendor/bin/phpunit`.
- **Jest**: `ahoy test-javascript` - never `npx jest`.
- **Drush**: `ahoy drush <command>` - never `build/vendor/bin/drush` directly.

### Build and Environment Management


**Using Ahoy (alternative):**
- `ahoy build` - Complete build process
- `ahoy assemble` - Assemble codebase
- `ahoy start` - Start development server
- `ahoy stop` - Stop development server
- `ahoy provision` - Provision Drupal site
- `ahoy reset` - Clean build directory, logs and installed tooling (aliases: `ahoy delete`, `ahoy destroy`)

### Code Quality

**Linting:**
- `ahoy lint` - Run all linting tools
- `ahoy lint-fix` - Auto-fix coding standards violations

**Testing:**
- `ahoy test` - Run all tests
- `ahoy test-unit` - Run unit tests only
- `ahoy test-kernel` - Run kernel tests only
- `ahoy test-functional` - Run functional tests only
- `ahoy test-functional-javascript` - Run FunctionalJavascript tests (uses the local Chrome by default; set `WEBDRIVER_BACKEND=selenium` for Docker)
- `ahoy test-javascript` - Run JavaScript unit tests with Jest (alias: `ahoy test-js`)
- `ahoy browser-start` - Start the browser for FunctionalJavascript tests (local Chrome by default; set `WEBDRIVER_BACKEND=selenium` for Docker)
- `ahoy browser-stop` - Stop the browser

### Drupal Commands

- `ahoy drush <command>` - Run Drush commands
- `ahoy login` - Get one-time login link

### Diagnostics

- `ahoy info` - Print a read-only summary of PHP/Drupal/Composer/Drush/Node versions, webserver host/port (with source), XDebug state, build directory, database path, and active profile. (alias: `ahoy describe`)

## Project Structure

**Key Directories:**
- `src/` - Extension source code (services, forms, etc.)
- `tests/src/` - PHPUnit tests (Unit/, Kernel/, Functional/)
- `config/schema/` - Configuration schema definitions
- `build/` - Assembled Drupal codebase (symlinked extension)
- `vendor/` - The installed `drevops/eddy-tooling` package, whose commands (`vendor/bin/eddy-*`) assemble, start, provision and deploy the extension. Not committed and not part of the extension
- `scripts/eddy-tooling` - Installs the tooling package into `vendor/` from the constraint in `composer.dev.json`. The command wrappers and CI run it before the tooling commands
- `scripts/` - Custom lifecycle hooks: post-assemble (`assemble-*.sh`), post-provision (`provision-*.sh`), post-start (`start-*.sh`), and pre-stop (`stop-*.sh`). Run automatically during each phase in lexicographic order; non-zero exit aborts the parent. Excluded from distribution archives via `.gitattributes`

**Template Files (before init):**
- `force_crystal.*` - Template extension files
- `ForceCrystalService.php` - Main service class template

## Architecture

- **Service-based architecture**: Main functionality in services registered via `*.services.yml`
- **Configuration-driven**: Uses Drupal configuration system with schema validation
- **Test coverage**: Unit, kernel, and functional test examples provided
- **Form integration**: Admin forms in `src/Form/` for configuration

## Environment Variables

- `DRUPAL_VERSION` - Target Drupal version (e.g., `10`, `11`, `11@alpha`, `12@beta`). Defaults to `extra.eddy.drupal-version` in `composer.dev.json`
- `WEBSERVER_HOST` - Development server host (default: localhost)
- `WEBSERVER_PORT` - Development server port. Auto-discovered from range 8000-8099 and written to `.env` if not already set
- `WEBDRIVER_BACKEND` - FunctionalJavascript WebDriver backend: `chromedriver` (default, drives the locally installed Chrome with no Docker) or `selenium` (Docker container)
- `WEBDRIVER_PORT` - Port for the WebDriver endpoint (both backends). Auto-discovered from 4444 and written to `.env` if not already set, so several projects can run FunctionalJavascript tests simultaneously. The endpoint in `phpunit.xml` is the default for port 4444; tests reach the resolved port because the FunctionalJavascript base class rewrites the port in `MINK_DRIVER_ARGS_WEBDRIVER` from this variable, so FunctionalJavascript tests must extend that base class (or export their own `MINK_DRIVER_ARGS_WEBDRIVER`)
- `WEBDRIVER_HOST` - Address the FunctionalJavascript browser uses to reach the PHP webserver. Defaults to `localhost` with `chromedriver`, and to `host.docker.internal` (macOS) or `__VERSION__.1` (other systems) with `selenium`
- `GITHUB_TOKEN` - GitHub API token to avoid rate limits
- `DEBUG` - Set to `1` to stream the full output of the underlying commands (Composer, npm, Drush). By default this output is suppressed and shown only when a command fails

## Development Workflow

1. Run `php init.php` to customize template for Force Crystal
2. Build environment: `make build` or `ahoy build`
3. Develop Force Crystal code in `src/`
4. Check standards: `make lint` or `ahoy lint`
5. Run tests: `make test` or `ahoy test`
6. Access site at http://localhost:8000

## Code Quality Tools

- **CSpell**: Spell checking across the codebase (config at `.cspell.json`)
- **PHPCS**: Drupal and DrupalPractice standards
- **PHPStan**: Static analysis with Drupal extensions
- **Rector**: Automated refactoring and deprecation fixes
- **Twig CS Fixer**: Twig template formatting

## CI/CD Support

- **GitHub Actions**: `.github/workflows/test.yml` and deployment
- **Matrix testing**: PHP 8.3-8.5, Drupal 10-12
- **Automated deployment**: Mirror to Drupal.org after CI passes

## Important Notes

- The `build/` directory contains the assembled Drupal site
- Extension files are symlinked from root into `build/web/modules/custom/` (module) or `build/web/themes/custom/` (theme)
- SQLite database created in `/tmp/site_force_crystal.sqlite`
- All quality tools run from within `build/` directory

## Updating the scaffold

When the user asks to update this project's scaffold (e.g. "update scaffold" or "update Eddy"), fetch the update skill from GitHub into the local `.claude/skills/` directory, then invoke it:

1. Create the target directory if it does not exist:

   ```bash
   mkdir -p .claude/skills/update-consumer-eddy
   ```

2. Download the skill:

   ```bash
   curl -fsSL https://raw.githubusercontent.com/drevops/eddy/1.x/.eddy/skills/update-consumer-eddy/SKILL.md -o .claude/skills/update-consumer-eddy/SKILL.md
   ```

3. Invoke the `update-consumer-eddy` skill and follow its steps.

The skill directory is git-ignored - it is fetched on demand and not committed to the project.
