@@ -8,22 +8,26 @@
 
 ## Development Commands
 
-**HARD RULE - use the provided command wrappers, never the tool binaries directly.** When `ahoy` exposes a command for a task, use that command; do not call the underlying binary directly. Each wrapper runs the tool where CI runs it, in `build/` for most tools, with the config, plugins, and environment that CI uses, so a raw invocation from the repository root silently diverges from CI - it can pass locally while CI fails (or vice versa), or crash outright when a relative path resolves against the wrong directory. If no wrapped command covers what you need, extend the `ahoy` target rather than making a one-off raw call; if that is not feasible, stop and ask.
+**HARD RULE - use the provided command wrappers, never the tool binaries directly.** When `make` or `ahoy` exposes a command for a task, use that command; do not call the underlying binary directly. Each wrapper runs the tool where CI runs it, in `build/` for most tools, with the config, plugins, and environment that CI uses, so a raw invocation from the repository root silently diverges from CI - it can pass locally while CI fails (or vice versa), or crash outright when a relative path resolves against the wrong directory. If no wrapped command covers what you need, extend the `make` or `ahoy` target rather than making a one-off raw call; if that is not feasible, stop and ask.
 
+Run each tool through its `make` wrapper, never the binary directly:
+
+- **Drush**: `make drush <command>` - never `build/vendor/bin/drush` directly.
+
 Run each tool through its `ahoy` wrapper, never the binary directly:
 
-- **PHPCS / PHPCBF**: `ahoy lint` / `ahoy lint-fix` - never `vendor/bin/phpcs` or `vendor/bin/phpcbf`.
-- **PHPStan**: `ahoy lint` - never `vendor/bin/phpstan`.
-- **Rector**: `ahoy lint` (dry-run) / `ahoy lint-fix` - never `vendor/bin/rector`.
-- **Twig CS Fixer**: `ahoy lint` / `ahoy lint-fix` - never `vendor/bin/twig-cs-fixer`.
-- **ESLint / Stylelint**: `ahoy lint` / `ahoy lint-fix` - never `npx eslint` or `npx stylelint`.
-- **CSpell**: `ahoy lint` - never `npx cspell`.
-- **PHPUnit**: `ahoy test` / `ahoy test-unit` / `ahoy test-kernel` / `ahoy test-functional` - never `vendor/bin/phpunit`.
-- **Jest**: `ahoy test-javascript` - never `npx jest`.
 - **Drush**: `ahoy drush <command>` - never `build/vendor/bin/drush` directly.
 
 ### Build and Environment Management
 
+**Using Make:**
+- `make build` - Complete build (stop → assemble → start → provision)
+- `make assemble` - Assemble codebase with dependencies
+- `make start` - Start PHP development server
+- `make stop` - Stop development server
+- `make provision` - Install/provision Drupal site
+- `make reset` - Clean build directory, logs and installed tooling (aliases: `make delete`, `make destroy`)
+
 **Using Ahoy:**
 - `ahoy build` - Complete build process
 - `ahoy assemble` - Assemble codebase
@@ -35,33 +39,31 @@
 ### Code Quality
 
 **Linting:**
+- `make lint` - Run all linting tools
+- `make lint-fix` - Auto-fix coding standards violations
 - `ahoy lint` - Run all linting tools
 - `ahoy lint-fix` - Auto-fix coding standards violations
 
 **Testing:**
+- `make test` - Run all tests
 - `ahoy test` - Run all tests
-- `ahoy test-unit` - Run unit tests only
-- `ahoy test-kernel` - Run kernel tests only
-- `ahoy test-functional` - Run functional tests only
-- `ahoy test-functional-javascript` - Run FunctionalJavascript tests (uses the local Chrome by default; set `WEBDRIVER_BACKEND=selenium` for Docker)
-- `ahoy test-javascript` - Run JavaScript unit tests with Jest (alias: `ahoy test-js`)
-- `ahoy browser-start` - Start the browser for FunctionalJavascript tests (local Chrome by default; set `WEBDRIVER_BACKEND=selenium` for Docker)
-- `ahoy browser-stop` - Stop the browser
 
 ### Drupal Commands
 
+- `make drush <command>` - Run Drush commands. `make` reads an argument that holds `=` as a variable, so separate an option from its value with a space, such as `--format json`
+- `make login` - Get one-time login link
 - `ahoy drush <command>` - Run Drush commands
 - `ahoy login` - Get one-time login link
 
 ### Diagnostics
 
-- `ahoy info` - Print a read-only summary of PHP/Drupal/Composer/Drush/Node/npm versions, webserver host/port (with source), site URL, XDebug state, WebDriver port, build directory, database path, and active profile. (alias: `ahoy describe`)
+- `make info` - Print a read-only summary of PHP/Drupal/Composer/Drush/Node/npm versions, webserver host/port (with source), site URL, XDebug state, build directory, database path, and active profile. (alias: `make describe`)
+- `ahoy info` - Print a read-only summary of PHP/Drupal/Composer/Drush/Node/npm versions, webserver host/port (with source), site URL, XDebug state, build directory, database path, and active profile. (alias: `ahoy describe`)
 
 ## Project Structure
 
 **Key Directories:**
 - `src/` - Extension source code (services, forms, etc.)
-- `tests/src/` - PHPUnit tests (Unit/, Kernel/, Functional/)
 - `config/schema/` - Configuration schema definitions
 - `build/` - Assembled Drupal codebase (symlinked extension)
 - `vendor/` - The installed `drevops/eddy-tooling` package, whose commands (`vendor/bin/eddy-*`) assemble, start, provision and deploy the extension. Not committed and not part of the extension
@@ -72,7 +74,6 @@
 
 - **Service-based architecture**: Main functionality in services registered via `*.services.yml`
 - **Configuration-driven**: Uses Drupal configuration system with schema validation
-- **Test coverage**: Unit, kernel, and functional test examples provided
 - **Form integration**: Admin forms in `src/Form/` for configuration
 
 ## Environment Variables
@@ -80,9 +81,6 @@
 - `DRUPAL_VERSION` - Target Drupal version (e.g., `10`, `11`, `11@alpha`, `12@beta`). Defaults to `extra.eddy.drupal-version` in `composer.dev.json`
 - `WEBSERVER_HOST` - Development server host (default: localhost)
 - `WEBSERVER_PORT` - Development server port. Auto-discovered from range 8000-8099 and written to `.env` if not already set
-- `WEBDRIVER_BACKEND` - FunctionalJavascript WebDriver backend: `chromedriver` (default, drives the locally installed Chrome with no Docker) or `selenium` (Docker container)
-- `WEBDRIVER_PORT` - Port for the WebDriver endpoint (both backends). Auto-discovered from 4444 and written to `.env` if not already set, so several projects can run FunctionalJavascript tests simultaneously. The endpoint in `phpunit.xml` is the default for port 4444; tests reach the resolved port because the FunctionalJavascript base class rewrites the port in `MINK_DRIVER_ARGS_WEBDRIVER` from this variable, so FunctionalJavascript tests must extend that base class (or export their own `MINK_DRIVER_ARGS_WEBDRIVER`)
-- `WEBDRIVER_HOST` - Address the FunctionalJavascript browser uses to reach the PHP webserver. Defaults to `localhost` with `chromedriver`, and to `host.docker.internal` (macOS) or `__VERSION__.1` (other systems) with `selenium`
 - `WEBSERVER_WAIT_TIMEOUT` - Seconds that `start` waits for the development server to accept connections (default: 5)
 - `DRUPAL_PROFILE` - Install profile that `provision` installs the site from (default: `standard`)
 - `XDEBUG` - Set to `1` to start the development server with XDebug step-debugging
@@ -93,22 +91,14 @@
 
 ## Development Workflow
 
-1. Build environment: `ahoy build`
+1. Build environment: `make build` or `ahoy build`
 2. Develop Force Crystal code in `src/`
-3. Check standards: `ahoy lint`
-4. Run tests: `ahoy test`
-5. Access site at the URL that `ahoy info` reports
+3. Check standards: `make lint` or `ahoy lint`
+4. Run tests: `make test` or `ahoy test`
+5. Access site at the URL that `make info` or `ahoy info` reports
 
 ## Code Quality Tools
 
-- **CSpell**: Spell checking across the codebase (config at `.cspell.json`)
-- **PHPCS**: Drupal, DrupalPractice, DrevOps and PHPCompatibility standards
-- **PHPStan**: Static analysis with Drupal extensions
-- **Rector**: Automated refactoring and deprecation fixes
-- **Twig CS Fixer**: Twig template formatting
-- **ESLint**: JavaScript linting with Prettier formatting (config at `.eslintrc.json`)
-- **Stylelint**: CSS linting (config at `.stylelintrc.js`)
-
 ## CI/CD Support
 
 - **GitHub Actions**: `.github/workflows/test.yml` and deployment
@@ -123,7 +113,6 @@
 - Extension files are symlinked from root into `build/web/modules/custom/`
 - SQLite database created in `/tmp/site_force_crystal.sqlite`
 - The PHP and JavaScript quality tools run from within the `build/` directory
-- CSpell runs from the project root
 
 ## Updating the scaffold
 
