@@ -13,6 +13,10 @@
 3. Build the site:
 
    ```bash
+   make build
+   ```
+
+   ```bash
    ahoy build
    ```
 
@@ -37,6 +41,12 @@
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
@@ -56,6 +66,20 @@
 
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
@@ -119,6 +143,11 @@
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
@@ -145,6 +174,10 @@
 To run every check:
 
 ```bash
+make lint
+```
+
+```bash
 ahoy lint
 ```
 
@@ -153,6 +186,10 @@
 To fix the issues that have an automatic fix, run the fixers:
 
 ```bash
+make lint-fix
+```
+
+```bash
 ahoy lint-fix
 ```
 
@@ -161,6 +198,10 @@
 To run every test:
 
 ```bash
+make test
+```
+
+```bash
 ahoy test
 ```
 
@@ -169,6 +210,13 @@
 To run 1 test suite:
 
 ```bash
+make test-unit                    # Run Unit tests
+make test-kernel                  # Run Kernel tests
+make test-functional              # Run Functional tests
+make test-functional-javascript   # Run FunctionalJavascript tests
+```
+
+```bash
 ahoy test-unit                    # Run Unit tests
 ahoy test-kernel                  # Run Kernel tests
 ahoy test-functional              # Run Functional tests
@@ -182,6 +230,13 @@
 To run the FunctionalJavascript tests with Chrome:
 
 ```bash
+make start
+make provision
+make test-functional-javascript
+make browser-stop
+```
+
+```bash
 ahoy start
 ahoy provision
 ahoy test-functional-javascript
@@ -195,6 +250,13 @@
 To run the browser in a Selenium container, start Docker and set `WEBDRIVER_BACKEND=selenium`. The container can't reach the `localhost` of your host, so start the webserver on all interfaces with `WEBSERVER_HOST=__VERSION__.0`:
 
 ```bash
+WEBSERVER_HOST=__VERSION__.0 make start
+make provision
+WEBDRIVER_BACKEND=selenium make test-functional-javascript
+make browser-stop
+```
+
+```bash
 WEBSERVER_HOST=__VERSION__.0 ahoy start
 ahoy provision
 WEBDRIVER_BACKEND=selenium ahoy test-functional-javascript
@@ -210,6 +272,14 @@
 ### Running specific tests
 
 To run some of the tests, pass a test file path or a PHPUnit option, such as `--filter` or `--group`, to a test command. PHPUnit runs inside `build`, so a test path starts at the extension's symlink in the assembled site:
+
+```bash
+make test-unit web/modules/custom/force_crystal/tests/src/Unit/MyUnitTest.php
+make test-unit -- --group=wip
+make test-unit -- --filter=MyUnitTest::testSomething
+```
+
+With `make`, put `--` before arguments that include an option, because `make` reads an option on its command line as its own. An argument can hold a space or name a `make` target, such as `test`, only as an option value joined with `=`: `--filter='my test'` or `--filter=test`. `make` keeps 1 value per option joined with `=`, so to repeat an option, separate it from each value with a space: `--group wip --group slow`.
 
 ```bash
 ahoy test-unit web/modules/custom/force_crystal/tests/src/Unit/MyUnitTest.php
