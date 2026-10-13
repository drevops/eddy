@@ -8,54 +8,54 @@
 
 ## Development Commands
 
-**HARD RULE - use the provided command wrappers, never the tool binaries directly.** When `ahoy` exposes a command for a task, use that command; do not call the underlying binary directly. Each wrapper runs the tool where CI runs it, in `build/` for most tools, with the config, plugins, and environment that CI uses, so a raw invocation from the repository root silently diverges from CI - it can pass locally while CI fails (or vice versa), or crash outright when a relative path resolves against the wrong directory. If no wrapped command covers what you need, extend the `ahoy` target rather than making a one-off raw call; if that is not feasible, stop and ask.
+**HARD RULE - use the provided command wrappers, never the tool binaries directly.** When `make` exposes a command for a task, use that command; do not call the underlying binary directly. Each wrapper runs the tool where CI runs it, in `build/` for most tools, with the config, plugins, and environment that CI uses, so a raw invocation from the repository root silently diverges from CI - it can pass locally while CI fails (or vice versa), or crash outright when a relative path resolves against the wrong directory. If no wrapped command covers what you need, extend the `make` target rather than making a one-off raw call; if that is not feasible, stop and ask.
 
-Run each tool through its `ahoy` wrapper, never the binary directly:
+Run each tool through its `make` wrapper, never the binary directly:
 
-- **PHPCS / PHPCBF**: `ahoy lint` / `ahoy lint-fix` - never `vendor/bin/phpcs` or `vendor/bin/phpcbf`.
-- **PHPStan**: `ahoy lint` - never `vendor/bin/phpstan`.
-- **Rector**: `ahoy lint` (dry-run) / `ahoy lint-fix` - never `vendor/bin/rector`.
-- **Twig CS Fixer**: `ahoy lint` / `ahoy lint-fix` - never `vendor/bin/twig-cs-fixer`.
-- **ESLint / Stylelint**: `ahoy lint` / `ahoy lint-fix` - never `npx eslint` or `npx stylelint`.
-- **CSpell**: `ahoy lint` - never `npx cspell`.
-- **PHPUnit**: `ahoy test` / `ahoy test-unit` / `ahoy test-kernel` / `ahoy test-functional` - never `vendor/bin/phpunit`.
-- **Jest**: `ahoy test-javascript` - never `npx jest`.
-- **Drush**: `ahoy drush <command>` - never `build/vendor/bin/drush` directly.
+- **PHPCS / PHPCBF**: `make lint` / `make lint-fix` - never `vendor/bin/phpcs` or `vendor/bin/phpcbf`.
+- **PHPStan**: `make lint` - never `vendor/bin/phpstan`.
+- **Rector**: `make lint` (dry-run) / `make lint-fix` - never `vendor/bin/rector`.
+- **Twig CS Fixer**: `make lint` / `make lint-fix` - never `vendor/bin/twig-cs-fixer`.
+- **ESLint / Stylelint**: `make lint` / `make lint-fix` - never `npx eslint` or `npx stylelint`.
+- **CSpell**: `make lint` - never `npx cspell`.
+- **PHPUnit**: `make test` / `make test-unit` / `make test-kernel` / `make test-functional` - never `vendor/bin/phpunit`.
+- **Jest**: `make test-javascript` - never `npx jest`.
+- **Drush**: `make drush <command>` - never `build/vendor/bin/drush` directly.
 
 ### Build and Environment Management
 
-**Using Ahoy:**
-- `ahoy build` - Complete build process
-- `ahoy assemble` - Assemble codebase
-- `ahoy start` - Start development server
-- `ahoy stop` - Stop development server
-- `ahoy provision` - Provision Drupal site
-- `ahoy reset` - Clean build directory, logs and installed tooling (aliases: `ahoy delete`, `ahoy destroy`)
+**Using Make:**
+- `make build` - Complete build (stop → assemble → start → provision)
+- `make assemble` - Assemble codebase with dependencies
+- `make start` - Start PHP development server
+- `make stop` - Stop development server
+- `make provision` - Install/provision Drupal site
+- `make reset` - Clean build directory, logs and installed tooling (aliases: `make delete`, `make destroy`)
 
 ### Code Quality
 
 **Linting:**
-- `ahoy lint` - Run all linting tools
-- `ahoy lint-fix` - Auto-fix coding standards violations
+- `make lint` - Run all linting tools
+- `make lint-fix` - Auto-fix coding standards violations
 
 **Testing:**
-- `ahoy test` - Run all tests
-- `ahoy test-unit` - Run unit tests only
-- `ahoy test-kernel` - Run kernel tests only
-- `ahoy test-functional` - Run functional tests only
-- `ahoy test-functional-javascript` - Run FunctionalJavascript tests (uses the local Chrome by default; set `WEBDRIVER_BACKEND=selenium` for Docker)
-- `ahoy test-javascript` - Run JavaScript unit tests with Jest (alias: `ahoy test-js`)
-- `ahoy browser-start` - Start the browser for FunctionalJavascript tests (local Chrome by default; set `WEBDRIVER_BACKEND=selenium` for Docker)
-- `ahoy browser-stop` - Stop the browser
+- `make test` - Run all tests
+- `make test-unit` - Run unit tests only
+- `make test-kernel` - Run kernel tests only
+- `make test-functional` - Run functional tests only
+- `make test-functional-javascript` - Run FunctionalJavascript tests (uses the local Chrome by default; set `WEBDRIVER_BACKEND=selenium` for Docker)
+- `make test-javascript` - Run JavaScript unit tests with Jest (alias: `make test-js`)
+- `make browser-start` - Start the browser for FunctionalJavascript tests (local Chrome by default; set `WEBDRIVER_BACKEND=selenium` for Docker)
+- `make browser-stop` - Stop the browser
 
 ### Drupal Commands
 
-- `ahoy drush <command>` - Run Drush commands
-- `ahoy login` - Get one-time login link
+- `make drush <command>` - Run Drush commands. Put `--` before options and separate an option from its value with a space, such as `make drush -- status --format json`, because `make` reads an argument that holds `=` as a variable. An argument that holds `:` fails, so use the command's alias, such as `cget` for `config:get`
+- `make login` - Get one-time login link
 
 ### Diagnostics
 
-- `ahoy info` - Print a read-only summary of PHP/Drupal/Composer/Drush/Node/npm versions, webserver host/port (with source), site URL, XDebug state, WebDriver port, build directory, database path, and active profile. (alias: `ahoy describe`)
+- `make info` - Print a read-only summary of PHP/Drupal/Composer/Drush/Node/npm versions, webserver host/port (with source), site URL, XDebug state, WebDriver port, build directory, database path, and active profile. (alias: `make describe`)
 
 ## Project Structure
 
@@ -93,11 +93,11 @@
 
 ## Development Workflow
 
-1. Build environment: `ahoy build`
+1. Build environment: `make build`
 2. Develop Force Crystal code in `src/`
-3. Check standards: `ahoy lint`
-4. Run tests: `ahoy test`
-5. Access site at the URL that `ahoy info` reports
+3. Check standards: `make lint`
+4. Run tests: `make test`
+5. Access site at the URL that `make info` reports
 
 ## Code Quality Tools
 
