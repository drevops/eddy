@@ -5,12 +5,15 @@
 ## Local development
 
 1. Install PHP with SQLite support and Composer
-   - Install Node.js with npm, which the build and the linters use
    - Install [Ahoy](https://github.com/ahoy-cli/ahoy)
 2. Clone this repository
 3. Build the website:
 
    ```bash
+   make build
+   ```
+
+   ```bash
    ahoy build
    ```
 
@@ -27,6 +30,12 @@
 The `build` command is a wrapper for more granular commands:
 
 ```bash
+make assemble     # Assemble the codebase
+make start        # Start the PHP server
+make provision    # Provision the Drupal website
+```
+
+```bash
 ahoy assemble     # Assemble the codebase
 ahoy start        # Start the PHP server
 ahoy provision    # Provision the Drupal website
@@ -44,6 +53,20 @@
 
 ```bash
 # Newest stable Drupal 11 release.
+DRUPAL_VERSION=11 make build
+
+# Newest Drupal 11.1.x patch release.
+DRUPAL_VERSION=__VERSION__ make build
+
+# Newest Drupal 11 beta, release candidate or stable release.
+DRUPAL_VERSION=11@beta make build
+
+# Newest stable Drupal 12 release, or newest pre-release if none.
+DRUPAL_VERSION=12 make build
+```
+
+```bash
+# Newest stable Drupal 11 release.
 DRUPAL_VERSION=11 ahoy build
 
 # Newest Drupal 11.1.x patch release.
@@ -109,6 +132,11 @@
 PHP step-debugging is supported via [XDebug](https://xdebug.org/docs/install). Install the XDebug PHP extension on your host (`php -v` should mention `with Xdebug`), then toggle it on the development server:
 
 ```bash
+make debug      # restart with XDebug enabled
+make start      # restart without XDebug
+```
+
+```bash
 ahoy debug      # restart with XDebug enabled
 ahoy start      # restart without XDebug
 ```
@@ -124,12 +152,6 @@
 ## Coding standards
 
 The codebase is checked using multiple tools:
-- PHP code standards checking against `Drupal`, `DrupalPractice`, `DrevOps` and `PHPCompatibility` standards.
-- PHP code static analysis with PHPStan.
-- PHP deprecated code analysis and auto-fixing with Drupal Rector.
-- Twig code analysis with Twig CS Fixer.
-- JavaScript code analysis with ESLint.
-- CSS code analysis with Stylelint.
 
 The configuration files for these tools are located in the root of the codebase.
 
@@ -136,6 +158,10 @@
 Run all checks with:
 
 ```bash
+make lint
+```
+
+```bash
 ahoy lint
 ```
 
@@ -144,6 +170,10 @@
 To fix coding standards issues automatically, run the same tools with the `--fix` option (for the tools that support it):
 
 ```bash
+make lint-fix
+```
+
+```bash
 ahoy lint-fix
 ```
 
@@ -152,60 +182,9 @@
 Run the tests for this extension with:
 
 ```bash
-ahoy test
+make test
 ```
 
-The tests are located in the `tests/src` directory. The `phpunit.xml` file configures PHPUnit to run the tests, and `phpunit.d10.xml` replaces it in a Drupal 10 build. Both use Drupal core's bootstrap file `web/core/tests/bootstrap.php` to bootstrap the Drupal environment before running the tests.
-
-Each test suite can also be run on its own:
-
 ```bash
-ahoy test-unit                    # Run Unit tests
-ahoy test-kernel                  # Run Kernel tests
-ahoy test-functional              # Run Functional tests
-ahoy test-functional-javascript   # Run FunctionalJavascript tests
-```
-
-### Running FunctionalJavascript tests
-
-FunctionalJavascript tests need a real browser driven via WebDriver. By default they use the Google Chrome already installed on your machine - a matching `chromedriver` is downloaded automatically on first run, so no Docker is required:
-
-```bash
-ahoy start
-ahoy provision
-ahoy test-functional-javascript
-ahoy browser-stop
-```
-
-The `test` and `test-functional-javascript` commands both start the browser before they run the suite. It keeps running afterwards, so the next run reuses it - stop it with `browser-stop` when you're done. The `browser-start` command reuses any browser that already answers on the WebDriver port, and fails and names whatever else holds the port. The `browser-stop` command stops only the project's own browser, so the browser of another project that recorded the same port keeps running.
-
-To run the browser in a Docker Selenium container instead, set `WEBDRIVER_BACKEND=selenium`. The container cannot reach the host's `localhost`, so start the webserver on all interfaces:
-
-```bash
-WEBSERVER_HOST=__VERSION__.0 ahoy start
-ahoy provision
-WEBDRIVER_BACKEND=selenium ahoy test-functional-javascript
-ahoy browser-stop
-```
-
-The browser reaches the webserver at `localhost` with the default backend, and at `host.docker.internal` (macOS) or `__VERSION__.1` (other systems) from the Selenium container. Set `WEBDRIVER_HOST` to use a different address.
-
-Each project gets its own WebDriver port, whichever backend it uses: a free one is claimed starting at 4444 and stored in `.env`, so several projects can run FunctionalJavascript tests at the same time. Set `WEBDRIVER_PORT` to pin a specific one. Tests reach the claimed port because the base class applies it to the WebDriver endpoint, so extend `ForceCrystalFunctionalJavascriptTestBase` rather than `WebDriverTestBase` directly - a test that bypasses it keeps the default endpoint from `phpunit.xml` unless it exports its own `MINK_DRIVER_ARGS_WEBDRIVER`.
-
-### Running specific tests
-
-You can run specific tests by passing a path to the test file or PHPUnit CLI option (`--filter`, `--group`, etc.) to the test commands. PHPUnit runs inside `build`, so a test path starts at the extension's symlink in the assembled site:
-
-```bash
-ahoy test-unit web/modules/custom/force_crystal/tests/src/Unit/MyUnitTest.php
-ahoy test-unit -- --group wip
-```
-
-You may also run tests using the `phpunit` command directly:
-
-```bash
-cd build
-php -d pcov.directory=.. vendor/bin/phpunit \
-  web/modules/custom/force_crystal/tests/src/Unit/MyUnitTest.php
-php -d pcov.directory=.. vendor/bin/phpunit --group=wip
+ahoy test
 ```
