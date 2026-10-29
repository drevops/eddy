@@ -8,12 +8,11 @@
    - PHP with SQLite support
    - Composer
    - Node.js with npm, which the build uses
-   - [Ahoy](https://github.com/ahoy-cli/ahoy)
 2. Clone this repository.
 3. Build the site:
 
    ```bash
-   ahoy build
+   make build
    ```
 
    The build ends by printing the site URL and a one-time login link.
@@ -37,9 +36,9 @@
 The `build` command is a wrapper for 3 commands that each run 1 stage:
 
 ```bash
-ahoy assemble     # Assemble the codebase
-ahoy start        # Start the PHP server
-ahoy provision    # Provision the Drupal website
+make assemble     # Assemble the codebase
+make start        # Start the PHP server
+make provision    # Provision the Drupal website
 ```
 
 To reinstall the Drupal site without assembling the codebase again, run the `provision` command on its own.
@@ -56,16 +55,16 @@
 
 ```bash
 # Newest stable Drupal 11 release.
-DRUPAL_VERSION=11 ahoy build
+DRUPAL_VERSION=11 make build
 
 # Newest Drupal 11.1.x patch release.
-DRUPAL_VERSION=__VERSION__ ahoy build
+DRUPAL_VERSION=__VERSION__ make build
 
 # Newest Drupal 11 beta, release candidate or stable release.
-DRUPAL_VERSION=11@beta ahoy build
+DRUPAL_VERSION=11@beta make build
 
 # Newest stable Drupal 12 release, or newest pre-release if none.
-DRUPAL_VERSION=12 ahoy build
+DRUPAL_VERSION=12 make build
 ```
 
 The build pins Drupal core to the newest release that matches `DRUPAL_VERSION` and prints that release. A version without a stable release resolves to its newest pre-release.
@@ -119,8 +118,8 @@
 The development server can run with [XDebug](https://xdebug.org/docs/install) step-debugging. Install the XDebug PHP extension on your host first, so that `php -v` prints `with Xdebug`. Then restart the server with XDebug turned on or off:
 
 ```bash
-ahoy debug      # restart with XDebug enabled
-ahoy start      # restart without XDebug
+make debug      # restart with XDebug enabled
+make start      # restart without XDebug
 ```
 
 The `debug` command has the aliases `debug-on`, `xdebug` and `xdebug-on`. The `start` command has the aliases `debug-off` and `xdebug-off`.
@@ -145,7 +144,7 @@
 To run every check:
 
 ```bash
-ahoy lint
+make lint
 ```
 
 ### Fixing coding standards issues
@@ -153,7 +152,7 @@
 To fix the issues that have an automatic fix, run the fixers:
 
 ```bash
-ahoy lint-fix
+make lint-fix
 ```
 
 ## Testing
@@ -161,7 +160,7 @@
 To run every test:
 
 ```bash
-ahoy test
+make test
 ```
 
 The PHPUnit tests live in the `tests/src` directory. The `phpunit.xml` file configures PHPUnit to run the tests, and `phpunit.d10.xml` replaces it in a Drupal 10 build. Both use Drupal core's bootstrap file, `web/core/tests/bootstrap.php`, to boot Drupal before the tests run.
@@ -169,10 +168,10 @@
 To run 1 test suite:
 
 ```bash
-ahoy test-unit                    # Run Unit tests
-ahoy test-kernel                  # Run Kernel tests
-ahoy test-functional              # Run Functional tests
-ahoy test-functional-javascript   # Run FunctionalJavascript tests
+make test-unit                    # Run Unit tests
+make test-kernel                  # Run Kernel tests
+make test-functional              # Run Functional tests
+make test-functional-javascript   # Run FunctionalJavascript tests
 ```
 
 ### Running FunctionalJavascript tests
@@ -182,10 +181,10 @@
 To run the FunctionalJavascript tests with Chrome:
 
 ```bash
-ahoy start
-ahoy provision
-ahoy test-functional-javascript
-ahoy browser-stop
+make start
+make provision
+make test-functional-javascript
+make browser-stop
 ```
 
 The `test` and `test-functional-javascript` commands start the browser before they run the tests. The browser keeps running for the next run to reuse, so stop it with `browser-stop` when you're done.
@@ -195,10 +194,10 @@
 To run the browser in a Selenium Docker container, set `WEBDRIVER_BACKEND=selenium`. The container can't reach the `localhost` of your host, so start the webserver on all interfaces with `WEBSERVER_HOST=__VERSION__.0`:
 
 ```bash
-WEBSERVER_HOST=__VERSION__.0 ahoy start
-ahoy provision
-WEBDRIVER_BACKEND=selenium ahoy test-functional-javascript
-ahoy browser-stop
+WEBSERVER_HOST=__VERSION__.0 make start
+make provision
+WEBDRIVER_BACKEND=selenium make test-functional-javascript
+make browser-stop
 ```
 
 With Chrome, the browser reaches the webserver at `localhost`. From the Selenium container, the browser uses `host.docker.internal` on macOS and `__VERSION__.1` on other systems. To use another address, set `WEBDRIVER_HOST`.
@@ -212,10 +211,12 @@
 To run some of the tests, pass a test file path or a PHPUnit option, such as `--filter` or `--group`, to a test command. PHPUnit runs inside `build`, so a test path starts at the extension's symlink in the assembled site:
 
 ```bash
-ahoy test-unit web/modules/custom/force_crystal/tests/src/Unit/MyUnitTest.php
-ahoy test-unit -- --group=wip
-ahoy test-unit -- --filter=MyUnitTest::testSomething
+make test-unit web/modules/custom/force_crystal/tests/src/Unit/MyUnitTest.php
+make test-unit -- --group=wip
+make test-unit -- --filter=MyUnitTest::testSomething
 ```
+
+With `make`, put `--` before arguments that include an option, because `make` reads an option on its command line as its own. An argument can hold a space or name a `make` target, such as `test`, only as an option value joined with `=`: `--filter='my test'` or `--filter=test`. `make` keeps 1 value per option joined with `=`, so to repeat an option, separate it from each value with a space: `--group wip --group slow`.
 
 To run PHPUnit directly, run it from `build`:
 
