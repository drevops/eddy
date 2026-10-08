@@ -10,7 +10,18 @@
 
 **HARD RULE - use the provided command wrappers, never the tool binaries directly.** When `make` or `ahoy` exposes a command for a task, use that command; do not call the underlying binary directly. Each wrapper `chdir`s into `build/` and runs the tool with the config, plugins, and environment that CI uses, so a raw invocation from the repository root silently diverges from CI - it can pass locally while CI fails (or vice versa), or crash outright when a relative path resolves against the wrong directory. If no wrapped command covers what you need, extend the `make` / `ahoy` target rather than making a one-off raw call; if that is not feasible, stop and ask.
 
+Run each tool through its `make` wrapper, never the binary directly:
 
+- **PHPCS / PHPCBF**: `make lint` / `make lint-fix` - never `vendor/bin/phpcs` or `vendor/bin/phpcbf`.
+- **PHPStan**: `make lint` - never `vendor/bin/phpstan`.
+- **Rector**: `make lint` (dry-run) / `make lint-fix` - never `vendor/bin/rector`.
+- **Twig CS Fixer**: `make lint` / `make lint-fix` - never `vendor/bin/twig-cs-fixer`.
+- **ESLint / Stylelint**: `make lint` / `make lint-fix` - never `npx eslint` or `npx stylelint`.
+- **CSpell**: `make lint` - never `npx cspell`.
+- **PHPUnit**: `make test` / `make test-unit` / `make test-kernel` / `make test-functional` - never `vendor/bin/phpunit`.
+- **Jest**: `make test-javascript` - never `npx jest`.
+- **Drush**: `make drush <command>` - never `build/vendor/bin/drush` directly.
+
 Run each tool through its `ahoy` wrapper, never the binary directly:
 
 - **PHPCS / PHPCBF**: `ahoy lint` / `ahoy lint-fix` - never `vendor/bin/phpcs` or `vendor/bin/phpcbf`.
@@ -25,6 +36,13 @@
 
 ### Build and Environment Management
 
+**Using Make (default):**
+- `make build` - Complete build (stop → assemble → start → provision)
+- `make assemble` - Assemble codebase with dependencies
+- `make start` - Start PHP development server
+- `make stop` - Stop development server
+- `make provision` - Install/provision Drupal site
+- `make reset` - Clean build directory, logs and installed tooling (aliases: `make delete`, `make destroy`)
 
 **Using Ahoy (alternative):**
 - `ahoy build` - Complete build process
@@ -37,10 +55,20 @@
 ### Code Quality
 
 **Linting:**
+- `make lint` - Run all linting tools
+- `make lint-fix` - Auto-fix coding standards violations
 - `ahoy lint` - Run all linting tools
 - `ahoy lint-fix` - Auto-fix coding standards violations
 
 **Testing:**
+- `make test` - Run all tests
+- `make test-unit` - Run unit tests only
+- `make test-kernel` - Run kernel tests only
+- `make test-functional` - Run functional tests only
+- `make test-functional-javascript` - Run FunctionalJavascript tests (uses the local Chrome by default; set `WEBDRIVER_BACKEND=selenium` for Docker)
+- `make test-javascript` - Run JavaScript unit tests with Jest (alias: `make test-js`)
+- `make browser-start` - Start the browser for FunctionalJavascript tests (local Chrome by default; set `WEBDRIVER_BACKEND=selenium` for Docker)
+- `make browser-stop` - Stop the browser
 - `ahoy test` - Run all tests
 - `ahoy test-unit` - Run unit tests only
 - `ahoy test-kernel` - Run kernel tests only
@@ -52,11 +80,14 @@
 
 ### Drupal Commands
 
+- `make drush <command>` - Run Drush commands
+- `make login` - Get one-time login link
 - `ahoy drush <command>` - Run Drush commands
 - `ahoy login` - Get one-time login link
 
 ### Diagnostics
 
+- `make info` - Print a read-only summary of PHP/Drupal/Composer/Drush/Node/npm versions, webserver host/port (with source), site URL, XDebug state, WebDriver port, build directory, database path, and active profile. (alias: `make describe`)
 - `ahoy info` - Print a read-only summary of PHP/Drupal/Composer/Drush/Node/npm versions, webserver host/port (with source), site URL, XDebug state, WebDriver port, build directory, database path, and active profile. (alias: `ahoy describe`)
 
 ## Project Structure
