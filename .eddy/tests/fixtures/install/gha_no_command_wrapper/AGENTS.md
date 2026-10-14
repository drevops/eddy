@@ -8,56 +8,30 @@
 
 ## Development Commands
 
-**HARD RULE - use the provided command wrappers, never the tool binaries directly.** When `ahoy` exposes a command for a task, use that command; do not call the underlying binary directly. Each wrapper runs the tool where CI runs it, in `build/` for most tools, with the config, plugins, and environment that CI uses, so a raw invocation from the repository root silently diverges from CI - it can pass locally while CI fails (or vice versa), or crash outright when a relative path resolves against the wrong directory. If no wrapped command covers what you need, extend the `ahoy` target rather than making a one-off raw call; if that is not feasible, stop and ask.
+**HARD RULE - run each tool exactly as `CONTRIBUTING.md` shows.** This project has no command wrapper, so the commands below run directly. Most tools run from `build/`, which holds the config, plugins, and environment that CI uses, so a raw invocation from the repository root silently diverges from CI - it can pass locally while CI fails (or vice versa), or crash outright when a relative path resolves against the wrong directory.
 
 **HARD RULE - never modify `scripts/eddy-tooling`.** It comes from Eddy as-is, and a scaffold update replaces it with the release's copy. To change what a tooling command does, declare a patch for the `drevops/eddy-tooling` package in `composer.dev.json`.
 
-Run each tool through its `ahoy` wrapper, never the binary directly:
-
-- **PHPCS / PHPCBF**: `ahoy lint` / `ahoy lint-fix` - never `vendor/bin/phpcs` or `vendor/bin/phpcbf`.
-- **PHPStan**: `ahoy lint` - never `vendor/bin/phpstan`.
-- **Rector**: `ahoy lint` (dry-run) / `ahoy lint-fix` - never `vendor/bin/rector`.
-- **Twig CS Fixer**: `ahoy lint` / `ahoy lint-fix` - never `vendor/bin/twig-cs-fixer`.
-- **ESLint / Stylelint**: `ahoy lint` / `ahoy lint-fix` - never `npx eslint` or `npx stylelint`.
-- **CSpell**: `ahoy lint` - never `npx cspell`.
-- **PHPUnit**: `ahoy test` / `ahoy test-unit` / `ahoy test-kernel` / `ahoy test-functional` - never `vendor/bin/phpunit`.
-- **Jest**: `ahoy test-javascript` - never `npx jest`.
-- **Drush**: `ahoy drush <command>` - never `build/vendor/bin/drush` directly.
-
 ### Build and Environment Management
 
-**Using Ahoy:**
-- `ahoy build` - Complete build process
-- `ahoy assemble` - Assemble codebase
-- `ahoy start` - Start development server
-- `ahoy stop` - Stop development server
-- `ahoy provision` - Provision Drupal site
-- `ahoy reset` - Clean build directory, logs and installed tooling (aliases: `ahoy delete`, `ahoy destroy`)
+- `scripts/eddy-tooling` - Install the tooling commands into `vendor/bin/`. Run it before the commands below
+- `vendor/bin/eddy-assemble` - Assemble codebase with dependencies
+- `vendor/bin/eddy-start` - Start PHP development server
+- `vendor/bin/eddy-stop` - Stop development server
+- `vendor/bin/eddy-provision` - Install/provision Drupal site
 
 ### Code Quality
 
-**Linting:**
-- `ahoy lint` - Run all linting tools
-- `ahoy lint-fix` - Auto-fix coding standards violations
+Run the linters and the tests with the commands that `CONTRIBUTING.md` lists.
 
-**Testing:**
-- `ahoy test` - Run all tests
-- `ahoy test-unit` - Run unit tests only
-- `ahoy test-kernel` - Run kernel tests only
-- `ahoy test-functional` - Run functional tests only
-- `ahoy test-functional-javascript` - Run FunctionalJavascript tests (uses the local Chrome by default; set `WEBDRIVER_BACKEND=selenium` for Docker)
-- `ahoy test-javascript` - Run JavaScript unit tests with Jest (alias: `ahoy test-js`)
-- `ahoy browser-start` - Start the browser for FunctionalJavascript tests (local Chrome by default; set `WEBDRIVER_BACKEND=selenium` for Docker)
-- `ahoy browser-stop` - Stop the browser
-
 ### Drupal Commands
 
-- `ahoy drush <command>` - Run Drush commands
-- `ahoy login` - Get one-time login link
+- `build/vendor/bin/drush -l "$(vendor/bin/eddy-info site-url)" <command>` - Run Drush commands
+- `build/vendor/bin/drush -l "$(vendor/bin/eddy-info site-url)" uli` - Get one-time login link
 
 ### Diagnostics
 
-- `ahoy info` - Print a read-only summary of PHP/Drupal/Composer/Drush/Node/npm versions, webserver host/port (with source), site URL, XDebug state, WebDriver port, build directory, database path, and active profile. (alias: `ahoy describe`)
+- `vendor/bin/eddy-info` - Print a read-only summary of PHP/Drupal/Composer/Drush/Node/npm versions, webserver host/port (with source), site URL, XDebug state, WebDriver port, build directory, database path, and active profile.
 
 ## Project Structure
 
@@ -67,7 +41,7 @@
 - `config/schema/` - Configuration schema definitions
 - `build/` - Assembled Drupal codebase (symlinked extension)
 - `vendor/` - The installed `drevops/eddy-tooling` package, whose commands (`vendor/bin/eddy-*`) assemble, start, provision and deploy the extension. Not committed and not part of the extension
-- `scripts/eddy-tooling` - Installs the tooling package into `vendor/` from the constraint in `composer.dev.json`. The command wrappers and CI run it before the tooling commands
+- `scripts/eddy-tooling` - Installs the tooling package into `vendor/` from the constraint in `composer.dev.json`. CI runs it before the tooling commands
 - `scripts/` - Custom lifecycle hooks: post-assemble (`assemble-*.sh`), post-provision (`provision-*.sh`), post-start (`start-*.sh`), and pre-stop (`stop-*.sh`). Run automatically during each phase in lexicographic order; non-zero exit aborts the parent. Excluded from distribution archives via `.gitattributes`
 
 ## Architecture
@@ -89,17 +63,16 @@
 - `DRUPAL_PROFILE` - Install profile that `provision` installs the site from (default: `standard`)
 - `XDEBUG` - Set to `1` to start the development server with XDebug step-debugging
 - `CLOUDFLARE_TUNNEL` - Set to `1` to expose the development server through a public Cloudflare quick tunnel. `start` writes the tunnel URL to `.env` as `TUNNEL_URL`
-- `LOGIN_QRCODE` - Set to `1` to also render the `login` link as a terminal QR code (needs `qrencode`)
 - `GITHUB_TOKEN` - GitHub API token to avoid rate limits
 - `DEBUG` - Set to `1` to stream the full output of the underlying commands (Composer, npm, Drush). By default this output is suppressed and shown only when a command fails
 
 ## Development Workflow
 
-1. Build environment: `ahoy build`
+1. Build environment: `scripts/eddy-tooling`, then `vendor/bin/eddy-assemble`, `vendor/bin/eddy-start` and `vendor/bin/eddy-provision`
 2. Develop Force Crystal code in `src/`
-3. Check standards: `ahoy lint`
-4. Run tests: `ahoy test`
-5. Access site at the URL that `ahoy info` reports
+3. Check standards: run the linters that `CONTRIBUTING.md` lists
+4. Run tests: run the tests that `CONTRIBUTING.md` lists
+5. Access site at the URL that `vendor/bin/eddy-info site-url` prints
 
 ## Code Quality Tools
 
