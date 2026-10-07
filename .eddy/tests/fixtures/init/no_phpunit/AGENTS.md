@@ -19,7 +19,6 @@
 - **Twig CS Fixer**: `ahoy lint` / `ahoy lint-fix` - never `vendor/bin/twig-cs-fixer`.
 - **ESLint / Stylelint**: `ahoy lint` / `ahoy lint-fix` - never `npx eslint` or `npx stylelint`.
 - **CSpell**: `ahoy lint` - never `npx cspell`.
-- **PHPUnit**: `ahoy test` / `ahoy test-unit` / `ahoy test-kernel` / `ahoy test-functional` - never `vendor/bin/phpunit`.
 - **Jest**: `ahoy test-javascript` - never `npx jest`.
 - **Drush**: `ahoy drush <command>` - never `build/vendor/bin/drush` directly.
 
@@ -42,13 +41,7 @@
 
 **Testing:**
 - `ahoy test` - Run all tests
-- `ahoy test-unit` - Run unit tests only
-- `ahoy test-kernel` - Run kernel tests only
-- `ahoy test-functional` - Run functional tests only
-- `ahoy test-functional-javascript` - Run FunctionalJavascript tests (uses the local Chrome by default; set `WEBDRIVER_BACKEND=selenium` for Docker)
 - `ahoy test-javascript` - Run JavaScript unit tests with Jest (alias: `ahoy test-js`)
-- `ahoy browser-start` - Start the browser for FunctionalJavascript tests (local Chrome by default; set `WEBDRIVER_BACKEND=selenium` for Docker)
-- `ahoy browser-stop` - Stop the browser
 
 ### Drupal Commands
 
@@ -63,7 +56,6 @@
 
 **Key Directories:**
 - `src/` - Extension source code (services, forms, etc.)
-- `tests/src/` - PHPUnit tests (Unit/, Kernel/, Functional/)
 - `config/schema/` - Configuration schema definitions
 - `build/` - Assembled Drupal codebase (symlinked extension)
 - `vendor/` - The installed `drevops/eddy-tooling` package, whose commands (`vendor/bin/eddy-*`) assemble, start, provision and deploy the extension. Not committed and not part of the extension
@@ -86,9 +78,6 @@
 - `DRUPAL_VERSION` - Target Drupal version (e.g., `10`, `11`, `11@alpha`, `12@beta`). Defaults to `extra.eddy.drupal-version` in `composer.dev.json`
 - `WEBSERVER_HOST` - Development server host (default: localhost)
 - `WEBSERVER_PORT` - Development server port. Auto-discovered from range 8000-8099 and written to `.env` if not already set
-- `WEBDRIVER_BACKEND` - FunctionalJavascript WebDriver backend: `chromedriver` (default, drives the locally installed Chrome with no Docker) or `selenium` (Docker container)
-- `WEBDRIVER_PORT` - Port for the WebDriver endpoint (both backends). Auto-discovered from 4444 and written to `.env` if not already set, so several projects can run FunctionalJavascript tests simultaneously. The endpoint in `phpunit.xml` is the default for port 4444; tests reach the resolved port because the FunctionalJavascript base class rewrites the port in `MINK_DRIVER_ARGS_WEBDRIVER` from this variable, so FunctionalJavascript tests must extend that base class (or export their own `MINK_DRIVER_ARGS_WEBDRIVER`)
-- `WEBDRIVER_HOST` - Address the FunctionalJavascript browser uses to reach the PHP webserver. Defaults to `localhost` with `chromedriver`, and to `host.docker.internal` (macOS) or `__VERSION__.1` (other systems) with `selenium`
 - `GITHUB_TOKEN` - GitHub API token to avoid rate limits
 - `DEBUG` - Set to `1` to stream the full output of the underlying commands (Composer, npm, Drush). By default this output is suppressed and shown only when a command fails
 
