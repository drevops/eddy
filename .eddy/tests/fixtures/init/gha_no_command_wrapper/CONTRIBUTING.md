@@ -9,7 +9,10 @@
 3. Build the website:
 
    ```bash
-   ahoy build
+   scripts/eddy-tooling          # Install the tooling
+   vendor/bin/eddy-assemble      # Assemble the codebase
+   vendor/bin/eddy-start         # Start the PHP server
+   vendor/bin/eddy-provision     # Provision the Drupal website
    ```
 
 ## Building website
@@ -20,14 +23,6 @@
 
 The resulting codebase is then placed in the `build` directory. The extension files are symlinked into the Drupal site structure.
 
-The `build` command is a wrapper for more granular commands:
-
-```bash
-ahoy assemble     # Assemble the codebase
-ahoy start        # Start the PHP server
-ahoy provision    # Provision the Drupal website
-```
-
 The `provision` command is useful for re-installing the Drupal website without re-assembling the codebase.
 
 ### Drupal versions
@@ -38,16 +33,16 @@
 
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
 ```
 
 The build pins Drupal core to the newest release matching `DRUPAL_VERSION` and prints it. A version with no stable release yet resolves to its newest pre-release.
@@ -99,14 +94,10 @@
 PHP step-debugging is supported via [XDebug](https://xdebug.org/docs/install). Install the XDebug PHP extension on your host (`php -v` should mention `with Xdebug`), then toggle it on the development server:
 
 ```bash
-ahoy debug      # restart with XDebug enabled
-ahoy start      # restart without XDebug
+XDEBUG=1 vendor/bin/eddy-start    # restart with XDebug enabled
+vendor/bin/eddy-start             # restart without XDebug
 ```
 
-`debug` is also available as `debug-on`, `xdebug` and `xdebug-on`, and `start` as `debug-off` and `xdebug-off`.
-
-The `debug` command probes the running PHP server's command line for `xdebug.mode=debug` and skips the restart if XDebug is already enabled.
-
 Code coverage stays on [pcov](https://github.com/krakjoe/pcov) because the XDebug settings apply only to the development server, not to the test commands.
 
 To start and stop debug sessions from the browser, install the Xdebug Helper extension: [Chrome](https://chromewebstore.google.com/detail/xdebug-helper-by-jetbrain/aoelhdemabeimdhedkidlnbkfhnhgnhm) / [Firefox](https://addons.mozilla.org/en-US/firefox/addon/xdebug-helper-by-jetbrains/).
@@ -126,7 +117,15 @@
 Run all checks with:
 
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
@@ -134,7 +133,11 @@
 To fix coding standards issues automatically, run the same tools with the `--fix` option (for the tools that support it):
 
 ```bash
-ahoy lint-fix
+cd build
+vendor/bin/rector --clear-cache
+vendor/bin/phpcbf
+vendor/bin/twig-cs-fixer --no-cache --fix
+npm run lint-fix
 ```
 
 ## Testing
@@ -142,18 +145,20 @@
 Run the tests for this extension with:
 
 ```bash
-ahoy test
+cd build
+php -d pcov.directory=.. vendor/bin/phpunit
+npm test
 ```
 
 The tests are located in the `tests/src` directory. The `phpunit.xml` file configures PHPUnit to run the tests. It uses Drupal core's bootstrap file `web/core/tests/bootstrap.php` to bootstrap the Drupal environment before running the tests.
 
-The `test` command is a wrapper for multiple test commands:
+Each test suite can also be run on its own:
 
 ```bash
-ahoy test-unit                    # Run Unit tests
-ahoy test-kernel                  # Run Kernel tests
-ahoy test-functional              # Run Functional tests
-ahoy test-functional-javascript   # Run FunctionalJavascript tests
+cd build
+php -d pcov.directory=.. vendor/bin/phpunit --testsuite unit         # Run Unit tests
+php -d pcov.directory=.. vendor/bin/phpunit --testsuite kernel       # Run Kernel tests
+php -d pcov.directory=.. vendor/bin/phpunit --testsuite functional   # Run Functional tests
 ```
 
 ### Running FunctionalJavascript tests
@@ -161,19 +166,27 @@
 FunctionalJavascript tests need a real browser driven via WebDriver. By default they use the Google Chrome already installed on your machine - a matching `chromedriver` is downloaded automatically on first run, so no Docker is required:
 
 ```bash
-ahoy start
-ahoy provision
-ahoy test-functional-javascript
-ahoy browser-stop
+vendor/bin/eddy-start
+vendor/bin/eddy-provision
+vendor/bin/eddy-browser start
+export WEBDRIVER_PORT="$(vendor/bin/eddy-info webdriver-port)"
+cd build
+php -d pcov.directory=.. vendor/bin/phpunit --testsuite functional-javascript
+cd ..
+vendor/bin/eddy-browser stop
 ```
 
 To run the browser in a Docker Selenium container instead, set `WEBDRIVER_BACKEND=selenium`. The container cannot reach the host's `localhost`, so start the webserver on all interfaces:
 
 ```bash
-WEBSERVER_HOST=__VERSION__.0 ahoy start
-ahoy provision
-WEBDRIVER_BACKEND=selenium ahoy test-functional-javascript
-ahoy browser-stop
+WEBSERVER_HOST=__VERSION__.0 vendor/bin/eddy-start
+vendor/bin/eddy-provision
+WEBDRIVER_BACKEND=selenium vendor/bin/eddy-browser start
+export WEBDRIVER_PORT="$(vendor/bin/eddy-info webdriver-port)"
+cd build
+WEBDRIVER_BACKEND=selenium php -d pcov.directory=.. vendor/bin/phpunit --testsuite functional-javascript
+cd ..
+vendor/bin/eddy-browser stop
 ```
 
 The browser reaches the webserver at `localhost` with the default backend, and at `host.docker.internal` (macOS) or `__VERSION__.1` (other systems) from the Selenium container. Set `WEBDRIVER_HOST` to use a different address.
@@ -183,13 +196,6 @@
 ### Running specific tests
 
 You can run specific tests by passing a path to the test file or PHPUnit CLI option (`--filter`, `--group`, etc.) to the test commands. PHPUnit runs inside `build`, so a test path starts at the extension's symlink in the assembled site (`web/themes/custom/` for a theme):
-
-```bash
-ahoy test-unit web/modules/custom/force_crystal/tests/src/Unit/MyUnitTest.php
-ahoy test-unit -- --group=wip
-```
-
-You may also run tests using the `phpunit` command directly:
 
 ```bash
 cd build
