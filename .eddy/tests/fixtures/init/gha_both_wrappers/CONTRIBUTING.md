@@ -9,6 +9,10 @@
 3. Build the website:
 
    ```bash
+   make build
+   ```
+
+   ```bash
    ahoy build
    ```
 
@@ -21,6 +25,12 @@
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
@@ -36,6 +46,20 @@
 
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
@@ -83,6 +107,11 @@
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
@@ -110,6 +139,10 @@
 Run all checks with:
 
 ```bash
+make lint
+```
+
+```bash
 ahoy lint
 ```
 
@@ -118,6 +151,10 @@
 To fix coding standards issues automatically, run the same tools with the `--fix` option (for the tools that support it):
 
 ```bash
+make lint-fix
+```
+
+```bash
 ahoy lint-fix
 ```
 
@@ -126,6 +163,10 @@
 Run the tests for this extension with:
 
 ```bash
+make test
+```
+
+```bash
 ahoy test
 ```
 
@@ -134,6 +175,13 @@
 The `test` command is a wrapper for multiple test commands:
 
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
@@ -145,6 +193,13 @@
 FunctionalJavascript tests need a real browser driven via WebDriver. By default they use the Google Chrome already installed on your machine - a matching `chromedriver` is downloaded automatically on first run, so no Docker is required:
 
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
@@ -154,6 +209,13 @@
 To run the browser in a Docker Selenium container instead, set `WEBDRIVER_BACKEND=selenium`. The container cannot reach the host's `localhost`, so start the webserver on all interfaces:
 
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
@@ -167,6 +229,11 @@
 ### Running specific tests
 
 You can run specific tests by passing a path to the test file or PHPUnit CLI option (`--filter`, `--group`, etc.) to the test commands. PHPUnit runs inside `build`, so a test path starts at the extension's symlink in the assembled site (`web/themes/custom/` for a theme):
+
+```bash
+make test-unit web/modules/custom/force_crystal/tests/src/Unit/MyUnitTest.php
+make test-unit -- --group=wip
+```
 
 ```bash
 ahoy test-unit web/modules/custom/force_crystal/tests/src/Unit/MyUnitTest.php
