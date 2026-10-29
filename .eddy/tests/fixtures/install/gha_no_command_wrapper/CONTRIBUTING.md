@@ -8,12 +8,14 @@
    - PHP with SQLite support
    - Composer
    - Node.js with npm, which the build uses
-   - [Ahoy](https://github.com/ahoy-cli/ahoy)
 2. Clone this repository.
 3. Build the site:
 
    ```bash
-   ahoy build
+   scripts/eddy-tooling          # Install the tooling
+   vendor/bin/eddy-assemble      # Assemble the codebase
+   vendor/bin/eddy-start         # Start the PHP server
+   vendor/bin/eddy-provision     # Provision the Drupal website
    ```
 
    The build ends by printing the site URL and a one-time login link.
@@ -34,20 +36,10 @@
 
 The assembled codebase goes in the `build` directory, with the extension's files symlinked into the Drupal site. Before the assemble command replaces an earlier build, it stops the PHP server that serves that build.
 
-The `build` command is a wrapper for 3 commands that each run 1 stage:
+To reinstall the Drupal site without assembling the codebase again, run `vendor/bin/eddy-provision` on its own.
 
-```bash
-ahoy assemble     # Assemble the codebase
-ahoy start        # Start the PHP server
-ahoy provision    # Provision the Drupal website
-```
+`vendor/bin/eddy-info` prints a read-only summary of the environment, such as the PHP, Drupal and Drush versions, the site URL and the database path.
 
-To reinstall the Drupal site without assembling the codebase again, run the `provision` command on its own.
-
-The `info` command prints a read-only summary of the environment, such as the PHP, Drupal and Drush versions, the site URL and the database path.
-
-The `reset` command stops the development server and the browser that the FunctionalJavascript tests use, and removes the `build`, `.logs` and `vendor` directories.
-
 ### Drupal versions
 
 The `DRUPAL_VERSION` variable sets the Drupal version that the build assembles. It defaults to `extra.eddy.drupal-version` in `composer.dev.json`, the newest Drupal major this extension targets that has a stable release.
@@ -56,16 +48,20 @@
 
 ```bash
 # Newest stable Drupal 11 release.
-DRUPAL_VERSION=11 ahoy build
+DRUPAL_VERSION=11 vendor/bin/eddy-assemble
 
 # Newest Drupal 11.1.x patch release.
-DRUPAL_VERSION=__VERSION__ ahoy build
+DRUPAL_VERSION=__VERSION__ vendor/bin/eddy-assemble
 
 # Newest Drupal 11 beta, release candidate or stable release.
-DRUPAL_VERSION=11@beta ahoy build
+DRUPAL_VERSION=11@beta vendor/bin/eddy-assemble
 
 # Newest stable Drupal 12 release, or newest pre-release if none.
-DRUPAL_VERSION=12 ahoy build
+DRUPAL_VERSION=12 vendor/bin/eddy-assemble
+
+# After any of them, start the server and install the site.
+vendor/bin/eddy-start
+vendor/bin/eddy-provision
 ```
 
 The build pins Drupal core to the newest release that matches `DRUPAL_VERSION` and prints that release. A version without a stable release resolves to its newest pre-release.
@@ -119,14 +115,10 @@
 The development server can run with [XDebug](https://xdebug.org/docs/install) step-debugging. Install the XDebug PHP extension on your host first, so that `php -v` prints `with Xdebug`. Then restart the server with XDebug turned on or off:
 
 ```bash
-ahoy debug      # restart with XDebug enabled
-ahoy start      # restart without XDebug
+XDEBUG=1 vendor/bin/eddy-start    # restart with XDebug enabled
+vendor/bin/eddy-start             # restart without XDebug
 ```
 
-The `debug` command has the aliases `debug-on`, `xdebug` and `xdebug-on`. The `start` command has the aliases `debug-off` and `xdebug-off`.
-
-When the command line of the running server already holds `xdebug.mode=debug`, the `debug` command skips the restart.
-
 The XDebug settings apply only to the development server, so the tests still collect code coverage with [pcov](https://github.com/krakjoe/pcov).
 
 To start and stop debug sessions from your browser, install the Xdebug Helper extension for [Chrome](https://chromewebstore.google.com/detail/xdebug-helper-by-jetbrain/aoelhdemabeimdhedkidlnbkfhnhgnhm) or [Firefox](https://addons.mozilla.org/en-US/firefox/addon/xdebug-helper-by-jetbrains/).
@@ -145,7 +137,15 @@
 To run every check:
 
 ```bash
-ahoy lint
+cd build
+vendor/bin/phpcs
+vendor/bin/phpstan
+vendor/bin/rector --clear-cache --dry-run
+vendor/bin/twig-cs-fixer
+npm run lint
+cd ..
+npm install
+npm run lint-spell
 ```
 
 ### Fixing coding standards issues
@@ -153,7 +153,11 @@
 To fix the issues that have an automatic fix, run the fixers:
 
 ```bash
-ahoy lint-fix
+cd build
+vendor/bin/rector --clear-cache
+vendor/bin/phpcbf
+vendor/bin/twig-cs-fixer --no-cache --fix
+npm run lint-fix
 ```
 
 ## Testing
@@ -161,7 +165,16 @@
 To run every test:
 
 ```bash
-ahoy test
+vendor/bin/eddy-browser-start
+export WEBDRIVER_PORT="$(vendor/bin/eddy-info webdriver-port)"
+export WEBDRIVER_BACKEND="$(vendor/bin/eddy-info webdriver-backend)"
+export WEBDRIVER_HOST="$(vendor/bin/eddy-info webdriver-host)"
+export WEBSERVER_PORT="$(vendor/bin/eddy-info webserver-port)"
+export SIMPLETEST_BASE_URL="http://localhost:${WEBSERVER_PORT}"
+export BROWSERTEST_OUTPUT_DIRECTORY="$PWD/.logs/browser_output"
+cd build
+php -d pcov.directory=.. vendor/bin/phpunit
+npm test
 ```
 
 The PHPUnit tests live in the `tests/src` directory. The `phpunit.xml` file configures PHPUnit to run the tests, and `phpunit.d10.xml` replaces it in a Drupal 10 build. Both use Drupal core's bootstrap file, `web/core/tests/bootstrap.php`, to boot Drupal before the tests run.
@@ -169,10 +182,13 @@
 To run 1 test suite:
 
 ```bash
-ahoy test-unit                    # Run Unit tests
-ahoy test-kernel                  # Run Kernel tests
-ahoy test-functional              # Run Functional tests
-ahoy test-functional-javascript   # Run FunctionalJavascript tests
+export WEBSERVER_PORT="$(vendor/bin/eddy-info webserver-port)"
+export SIMPLETEST_BASE_URL="http://localhost:${WEBSERVER_PORT}"
+export BROWSERTEST_OUTPUT_DIRECTORY="$PWD/.logs/browser_output"
+cd build
+php -d pcov.directory=.. vendor/bin/phpunit --testsuite unit
+php -d pcov.directory=.. vendor/bin/phpunit --testsuite kernel
+php -d pcov.directory=.. vendor/bin/phpunit --testsuite functional
 ```
 
 ### Running FunctionalJavascript tests
@@ -182,23 +198,37 @@
 To run the FunctionalJavascript tests with Chrome:
 
 ```bash
-ahoy start
-ahoy provision
-ahoy test-functional-javascript
-ahoy browser-stop
+vendor/bin/eddy-start
+vendor/bin/eddy-provision
+vendor/bin/eddy-browser-start
+export WEBDRIVER_PORT="$(vendor/bin/eddy-info webdriver-port)"
+export WEBDRIVER_BACKEND="$(vendor/bin/eddy-info webdriver-backend)"
+export WEBDRIVER_HOST="$(vendor/bin/eddy-info webdriver-host)"
+export WEBSERVER_PORT="$(vendor/bin/eddy-info webserver-port)"
+export SIMPLETEST_BASE_URL="http://localhost:${WEBSERVER_PORT}"
+export BROWSERTEST_OUTPUT_DIRECTORY="$PWD/.logs/browser_output"
+cd build
+php -d pcov.directory=.. vendor/bin/phpunit --testsuite functional-javascript
+cd ..
+vendor/bin/eddy-browser-stop
 ```
 
-The `test` and `test-functional-javascript` commands start the browser before they run the tests. The browser keeps running for the next run to reuse, so stop it with `browser-stop` when you're done.
-
-The `browser-start` command reuses a browser of the selected backend that already answers on the WebDriver port. Otherwise, it replaces this project's own browser of either backend, so you can switch `WEBDRIVER_BACKEND` without running `browser-stop` first. When anything else holds the port, `browser-start` fails and names it. The `browser-stop` command stops only this project's own browser, so the browser of another project on the same port keeps running.
-
 To run the browser in a Selenium Docker container, set `WEBDRIVER_BACKEND=selenium`. The container can't reach the `localhost` of your host, so start the webserver on all interfaces with `WEBSERVER_HOST=__VERSION__.0`:
 
 ```bash
-WEBSERVER_HOST=__VERSION__.0 ahoy start
-ahoy provision
-WEBDRIVER_BACKEND=selenium ahoy test-functional-javascript
-ahoy browser-stop
+WEBSERVER_HOST=__VERSION__.0 vendor/bin/eddy-start
+vendor/bin/eddy-provision
+WEBDRIVER_BACKEND=selenium vendor/bin/eddy-browser-start
+export WEBDRIVER_PORT="$(vendor/bin/eddy-info webdriver-port)"
+export WEBDRIVER_HOST="$(vendor/bin/eddy-info webdriver-host)"
+export WEBSERVER_PORT="$(vendor/bin/eddy-info webserver-port)"
+export SIMPLETEST_BASE_URL="http://localhost:${WEBSERVER_PORT}"
+export BROWSERTEST_OUTPUT_DIRECTORY="$PWD/.logs/browser_output"
+cd build
+WEBDRIVER_BACKEND=selenium php -d pcov.directory=.. vendor/bin/phpunit \
+  --testsuite functional-javascript
+cd ..
+vendor/bin/eddy-browser-stop
 ```
 
 With Chrome, the browser reaches the webserver at `localhost`. From the Selenium container, the browser uses `host.docker.internal` on macOS and `__VERSION__.1` on other systems. To use another address, set `WEBDRIVER_HOST`.
@@ -207,17 +237,11 @@
 
 The `ForceCrystalFunctionalJavascriptTestBase` base class points the WebDriver endpoint at that port, so extend it in every FunctionalJavascript test. A test that extends `WebDriverTestBase` directly uses the default endpoint from `phpunit.xml`, unless it exports its own `MINK_DRIVER_ARGS_WEBDRIVER`.
 
+The commands in this section export the backend, host and port that `eddy-info` reads from your shell and `.env`, so a value set in `.env` reaches the tests too.
+
 ### Running specific tests
 
 To run some of the tests, pass a test file path or a PHPUnit option, such as `--filter` or `--group`, to a test command. PHPUnit runs inside `build`, so a test path starts at the extension's symlink in the assembled site:
-
-```bash
-ahoy test-unit web/modules/custom/force_crystal/tests/src/Unit/MyUnitTest.php
-ahoy test-unit -- --group=wip
-ahoy test-unit -- --filter=MyUnitTest::testSomething
-```
-
-To run PHPUnit directly, run it from `build`:
 
 ```bash
 cd build
