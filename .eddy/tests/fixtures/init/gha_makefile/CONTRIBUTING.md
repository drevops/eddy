@@ -9,7 +9,7 @@
 3. Build the website:
 
    ```bash
-   ahoy build
+   make build
    ```
 
 ## Building website
@@ -21,9 +21,9 @@
 The `build` command is a wrapper for more granular commands:
 
 ```bash
-ahoy assemble     # Assemble the codebase
-ahoy start        # Start the PHP server
-ahoy provision    # Provision the Drupal website
+make assemble     # Assemble the codebase
+make start        # Start the PHP server
+make provision    # Provision the Drupal website
 ```
 
 The `provision` command is useful for re-installing the Drupal website without re-assembling the codebase.
@@ -36,16 +36,16 @@
 
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
 
 The build pins Drupal core to the newest release matching `DRUPAL_VERSION` and prints it. A version with no stable release yet resolves to its newest pre-release.
@@ -97,8 +97,8 @@
 PHP step-debugging is supported via [XDebug](https://xdebug.org/docs/install). Install the XDebug PHP extension on your host (`php -v` should mention `with Xdebug`), then toggle it on the development server:
 
 ```bash
-ahoy debug      # restart with XDebug enabled
-ahoy start      # restart without XDebug
+make debug      # restart with XDebug enabled
+make start      # restart without XDebug
 ```
 
 `debug` is also available as `debug-on`, `xdebug` and `xdebug-on`, and `start` as `debug-off` and `xdebug-off`.
@@ -124,7 +124,7 @@
 Run all checks with:
 
 ```bash
-ahoy lint
+make lint
 ```
 
 ### Fixing coding standards issues
@@ -132,7 +132,7 @@
 To fix coding standards issues automatically, run the same tools with the `--fix` option (for the tools that support it):
 
 ```bash
-ahoy lint-fix
+make lint-fix
 ```
 
 ## Testing
@@ -140,7 +140,7 @@
 Run the tests for this extension with:
 
 ```bash
-ahoy test
+make test
 ```
 
 The tests are located in the `tests/src` directory. The `phpunit.xml` file configures PHPUnit to run the tests. It uses Drupal core's bootstrap file `web/core/tests/bootstrap.php` to bootstrap the Drupal environment before running the tests.
@@ -148,10 +148,10 @@
 The `test` command is a wrapper for multiple test commands:
 
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
@@ -159,19 +159,19 @@
 FunctionalJavascript tests need a real browser driven via WebDriver. By default they use the Google Chrome already installed on your machine - a matching `chromedriver` is downloaded automatically on first run, so no Docker is required:
 
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
 
 To run the browser in a Docker Selenium container instead, set `WEBDRIVER_BACKEND=selenium`. The container cannot reach the host's `localhost`, so start the webserver on all interfaces:
 
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
 
 The browser reaches the webserver at `localhost` with the default backend, and at `host.docker.internal` (macOS) or `__VERSION__.1` (other systems) from the Selenium container. Set `WEBDRIVER_HOST` to use a different address.
@@ -183,8 +183,8 @@
 You can run specific tests by passing a path to the test file or PHPUnit CLI option (`--filter`, `--group`, etc.) to the test commands. PHPUnit runs inside `build`, so a test path starts at the extension's symlink in the assembled site (`web/themes/custom/` for a theme):
 
 ```bash
-ahoy test-unit web/modules/custom/force_crystal/tests/src/Unit/MyUnitTest.php
-ahoy test-unit -- --group=wip
+make test-unit web/modules/custom/force_crystal/tests/src/Unit/MyUnitTest.php
+make test-unit -- --group=wip
 ```
 
 You may also run tests using the `phpunit` command directly:
