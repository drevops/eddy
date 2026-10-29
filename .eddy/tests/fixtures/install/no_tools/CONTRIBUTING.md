@@ -7,12 +7,15 @@
 1. Install the following tools:
    - PHP with SQLite support
    - Composer
-   - Node.js with npm, which the build uses
    - [Ahoy](https://github.com/ahoy-cli/ahoy)
 2. Clone this repository.
 3. Build the site:
 
    ```bash
+   make build
+   ```
+
+   ```bash
    ahoy build
    ```
 
@@ -37,6 +40,12 @@
 The `build` command is a wrapper for 3 commands that each run 1 stage:
 
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
@@ -46,7 +55,7 @@
 
 The `info` command prints a read-only summary of the environment, such as the PHP, Drupal and Drush versions, the site URL and the database path. The `login` command prints a new one-time login link.
 
-The `reset` command stops the development server and the browser that the FunctionalJavascript tests use, and removes the `build`, `.logs` and `vendor` directories.
+The `reset` command stops the development server and removes the `build`, `.logs` and `vendor` directories.
 
 ### Drupal versions
 
@@ -56,6 +65,20 @@
 
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
@@ -119,6 +142,11 @@
 The development server can run with [XDebug](https://xdebug.org/docs/install) step-debugging. Install the XDebug PHP extension on your host first, so that `php -v` prints `with Xdebug`. Then restart the server with XDebug turned on or off:
 
 ```bash
+make debug      # restart with XDebug enabled
+make start      # restart without XDebug
+```
+
+```bash
 ahoy debug      # restart with XDebug enabled
 ahoy start      # restart without XDebug
 ```
@@ -133,16 +161,11 @@
 
 ## Coding standards
 
-The following tools check the codebase, each with its configuration file in the root of the project:
-- PHPCS checks PHP code against the `Drupal`, `DrupalPractice`, `DrevOps` and `PHPCompatibility` standards.
-- PHPStan analyzes PHP code statically.
-- Drupal Rector finds deprecated PHP code and fixes it.
-- Twig CS Fixer checks Twig templates.
-- ESLint checks JavaScript code.
-- Stylelint checks CSS code.
-- CSpell checks the spelling.
+To run every check:
 
-To run every check:
+```bash
+make lint
+```
 
 ```bash
 ahoy lint
@@ -153,6 +176,10 @@
 To fix the issues that have an automatic fix, run the fixers:
 
 ```bash
+make lint-fix
+```
+
+```bash
 ahoy lint-fix
 ```
 
@@ -161,68 +188,9 @@
 To run every test:
 
 ```bash
-ahoy test
+make test
 ```
 
-The PHPUnit tests live in the `tests/src` directory. The `phpunit.xml` file configures PHPUnit to run the tests, and `phpunit.d10.xml` replaces it in a Drupal 10 build. Both use Drupal core's bootstrap file, `web/core/tests/bootstrap.php`, to boot Drupal before the tests run.
-
-To run 1 test suite:
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
-FunctionalJavascript tests drive a real browser through WebDriver. By default, they use the Google Chrome installed on your machine, with a matching `chromedriver` that the first run downloads.
-
-To run the FunctionalJavascript tests with Chrome:
-
-```bash
-ahoy start
-ahoy provision
-ahoy test-functional-javascript
-ahoy browser-stop
-```
-
-The `test` and `test-functional-javascript` commands start the browser before they run the tests. The browser keeps running for the next run to reuse, so stop it with `browser-stop` when you're done.
-
-The `browser-start` command reuses a browser of the selected backend that already answers on the WebDriver port. Otherwise, it replaces this project's own browser of either backend, so you can switch `WEBDRIVER_BACKEND` without running `browser-stop` first. When anything else holds the port, `browser-start` fails and names it. The `browser-stop` command stops only this project's own browser, so the browser of another project on the same port keeps running.
-
-To run the browser in a Selenium container, start Docker and set `WEBDRIVER_BACKEND=selenium`. The container can't reach the `localhost` of your host, so start the webserver on all interfaces with `WEBSERVER_HOST=__VERSION__.0`:
-
-```bash
-WEBSERVER_HOST=__VERSION__.0 ahoy start
-ahoy provision
-WEBDRIVER_BACKEND=selenium ahoy test-functional-javascript
-ahoy browser-stop
-```
-
-With Chrome, the browser reaches the webserver at `localhost`. From the Selenium container, the browser uses `host.docker.internal` on macOS and `__VERSION__.1` on other systems. To use another address, set `WEBDRIVER_HOST`.
-
-Each project uses its own WebDriver port with either backend, so several projects can run FunctionalJavascript tests at the same time. The first browser start claims the first free port from 4444 and stores it in `.env`. To use a specific port, set `WEBDRIVER_PORT`.
-
-The `ForceCrystalFunctionalJavascriptTestBase` base class points the WebDriver endpoint at that port, so extend it in every FunctionalJavascript test. A test that extends `WebDriverTestBase` directly uses the default endpoint from `phpunit.xml`, unless it exports its own `MINK_DRIVER_ARGS_WEBDRIVER`.
-
-### Running specific tests
-
-To run some of the tests, pass a test file path or a PHPUnit option, such as `--filter` or `--group`, to a test command. PHPUnit runs inside `build`, so a test path starts at the extension's symlink in the assembled site:
-
-```bash
-ahoy test-unit web/modules/custom/force_crystal/tests/src/Unit/MyUnitTest.php
-ahoy test-unit -- --group=wip
-ahoy test-unit -- --filter=MyUnitTest::testSomething
-```
-
-To run PHPUnit directly, run it from `build`:
-
-```bash
-cd build
-php -d pcov.directory=.. vendor/bin/phpunit \
-  web/modules/custom/force_crystal/tests/src/Unit/MyUnitTest.php
-php -d pcov.directory=.. vendor/bin/phpunit --group=wip
-php -d pcov.directory=.. vendor/bin/phpunit --filter=MyUnitTest::testSomething
+ahoy test
 ```
