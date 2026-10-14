@@ -6,12 +6,14 @@
 
 1. Install PHP with SQLite support and Composer
    - Install Node.js with npm, which the build and the linters use
-   - Install [Ahoy](https://github.com/ahoy-cli/ahoy)
 2. Clone this repository
 3. Build the website:
 
    ```bash
-   ahoy build
+   scripts/eddy-tooling          # Install the tooling
+   vendor/bin/eddy-assemble      # Assemble the codebase
+   vendor/bin/eddy-start         # Start the PHP server
+   vendor/bin/eddy-provision     # Provision the Drupal website
    ```
 
 ## Building website
@@ -24,17 +26,9 @@
 
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
 
-The `info` command prints a read-only summary of the environment, such as the PHP, Drupal and Drush versions, the site URL and the database path. The `reset` command stops the development server and removes the `build`, `.logs` and `vendor` directories.
+`vendor/bin/eddy-info` prints a read-only summary of the environment, such as the PHP, Drupal and Drush versions, the site URL and the database path.
 
 ### Drupal versions
 
@@ -44,16 +38,20 @@
 
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
 
 The build pins Drupal core to the newest release matching `DRUPAL_VERSION` and prints it. A version with no stable release yet resolves to its newest pre-release. When that release requires a newer PHP than the one running the build, the build stops before it changes anything and says which PHP the release needs.
@@ -94,8 +92,6 @@
 2. **`WEBSERVER_PORT` line in the project-root `.env` file** - used as-is. The `start` script does not modify `.env` when this entry is already present, so the same port is reused across `start`, `stop`, `provision`, `drush` and `login` commands.
 3. **Neither is set** - the `start` script discovers the first free port in the range `8000-8099` and writes it to `.env` as `WEBSERVER_PORT=NNNN`. Subsequent commands read this value from `.env`.
 
-The command wrapper loads `.env` into the environment of every command, so through the wrapper a `WEBSERVER_PORT` line in `.env` wins over the shell. Edit that line to change a saved port.
-
 To force re-discovery, delete `.env` (or just the `WEBSERVER_PORT` line in it) and re-run the `start` command.
 
 The `start` and `stop` commands only stop the webserver that serves the project's own `build/web`. When another process holds the port, such as the server of another project that recorded the same port, `stop` leaves it running and `start` fails and names it. Stop that process, or set `WEBSERVER_PORT` in `.env` to a free port.
@@ -109,14 +105,10 @@
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
@@ -136,7 +128,15 @@
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
@@ -144,7 +144,11 @@
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
@@ -152,7 +156,13 @@
 Run the tests for this extension with:
 
 ```bash
-ahoy test
+vendor/bin/eddy-browser-start
+export WEBDRIVER_PORT="$(vendor/bin/eddy-info webdriver-port)"
+export WEBSERVER_PORT="$(vendor/bin/eddy-info webserver-port)"
+export SIMPLETEST_BASE_URL="http://localhost:${WEBSERVER_PORT}"
+cd build
+php -d pcov.directory=.. vendor/bin/phpunit
+npm test
 ```
 
 The tests are located in the `tests/src` directory. The `phpunit.xml` file configures PHPUnit to run the tests, and `phpunit.d10.xml` replaces it in a Drupal 10 build. Both use Drupal core's bootstrap file `web/core/tests/bootstrap.php` to bootstrap the Drupal environment before running the tests.
@@ -160,10 +170,12 @@
 Each test suite can also be run on its own:
 
 ```bash
-ahoy test-unit                    # Run Unit tests
-ahoy test-kernel                  # Run Kernel tests
-ahoy test-functional              # Run Functional tests
-ahoy test-functional-javascript   # Run FunctionalJavascript tests
+export WEBSERVER_PORT="$(vendor/bin/eddy-info webserver-port)"
+export SIMPLETEST_BASE_URL="http://localhost:${WEBSERVER_PORT}"
+cd build
+php -d pcov.directory=.. vendor/bin/phpunit --testsuite unit
+php -d pcov.directory=.. vendor/bin/phpunit --testsuite kernel
+php -d pcov.directory=.. vendor/bin/phpunit --testsuite functional
 ```
 
 ### Running FunctionalJavascript tests
@@ -171,21 +183,32 @@
 FunctionalJavascript tests need a real browser driven via WebDriver. By default they use the Google Chrome already installed on your machine - a matching `chromedriver` is downloaded automatically on first run, so no Docker is required:
 
 ```bash
-ahoy start
-ahoy provision
-ahoy test-functional-javascript
-ahoy browser-stop
+vendor/bin/eddy-start
+vendor/bin/eddy-provision
+vendor/bin/eddy-browser-start
+export WEBDRIVER_PORT="$(vendor/bin/eddy-info webdriver-port)"
+export WEBSERVER_PORT="$(vendor/bin/eddy-info webserver-port)"
+export SIMPLETEST_BASE_URL="http://localhost:${WEBSERVER_PORT}"
+cd build
+php -d pcov.directory=.. vendor/bin/phpunit --testsuite functional-javascript
+cd ..
+vendor/bin/eddy-browser-stop
 ```
 
-The `test` and `test-functional-javascript` commands both start the browser before they run the suite. It keeps running afterwards, so the next run reuses it - stop it with `browser-stop` when you're done. The `browser-start` command reuses any browser that already answers on the WebDriver port, and fails and names whatever else holds the port. The `browser-stop` command stops only the project's own browser, so the browser of another project that recorded the same port keeps running.
-
 To run the browser in a Docker Selenium container instead, set `WEBDRIVER_BACKEND=selenium`. The container cannot reach the host's `localhost`, so start the webserver on all interfaces:
 
 ```bash
-WEBSERVER_HOST=__VERSION__.0 ahoy start
-ahoy provision
-WEBDRIVER_BACKEND=selenium ahoy test-functional-javascript
-ahoy browser-stop
+WEBSERVER_HOST=__VERSION__.0 vendor/bin/eddy-start
+vendor/bin/eddy-provision
+WEBDRIVER_BACKEND=selenium vendor/bin/eddy-browser-start
+export WEBDRIVER_PORT="$(vendor/bin/eddy-info webdriver-port)"
+export WEBSERVER_PORT="$(vendor/bin/eddy-info webserver-port)"
+export SIMPLETEST_BASE_URL="http://localhost:${WEBSERVER_PORT}"
+cd build
+WEBDRIVER_BACKEND=selenium php -d pcov.directory=.. vendor/bin/phpunit \
+  --testsuite functional-javascript
+cd ..
+vendor/bin/eddy-browser-stop
 ```
 
 The browser reaches the webserver at `localhost` with the default backend, and at `host.docker.internal` (macOS) or `__VERSION__.1` (other systems) from the Selenium container. Set `WEBDRIVER_HOST` to use a different address.
@@ -195,13 +218,6 @@
 ### Running specific tests
 
 You can run specific tests by passing a path to the test file or PHPUnit CLI option (`--filter`, `--group`, etc.) to the test commands. PHPUnit runs inside `build`, so a test path starts at the extension's symlink in the assembled site:
-
-```bash
-ahoy test-unit web/modules/custom/force_crystal/tests/src/Unit/MyUnitTest.php
-ahoy test-unit -- --group wip
-```
-
-You may also run tests using the `phpunit` command directly:
 
 ```bash
 cd build
