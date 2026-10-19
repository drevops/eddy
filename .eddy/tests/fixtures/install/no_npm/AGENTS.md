@@ -18,10 +18,7 @@
 - **PHPStan**: `ahoy lint` - never `vendor/bin/phpstan`.
 - **Rector**: `ahoy lint` (dry-run) / `ahoy lint-fix` - never `vendor/bin/rector`.
 - **Twig CS Fixer**: `ahoy lint` / `ahoy lint-fix` - never `vendor/bin/twig-cs-fixer`.
-- **ESLint / Stylelint**: `ahoy lint` / `ahoy lint-fix` - never `npx eslint` or `npx stylelint`.
-- **CSpell**: `ahoy lint` - never `npx cspell`.
 - **PHPUnit**: `ahoy test` / `ahoy test-unit` / `ahoy test-kernel` / `ahoy test-functional` - never `vendor/bin/phpunit`.
-- **Jest**: `ahoy test-javascript` - never `npx jest`.
 - **Drush**: `ahoy drush <command>` - never `build/vendor/bin/drush` directly.
 
 ### Build and Environment Management
@@ -46,7 +43,6 @@
 - `ahoy test-kernel` - Run kernel tests only
 - `ahoy test-functional` - Run functional tests only
 - `ahoy test-functional-javascript` - Run FunctionalJavascript tests (uses the local Chrome by default; set `WEBDRIVER_BACKEND=selenium` for Docker)
-- `ahoy test-javascript` - Run JavaScript unit tests with Jest (alias: `ahoy test-js`)
 - `ahoy browser-start` - Start the browser for FunctionalJavascript tests (local Chrome by default; set `WEBDRIVER_BACKEND=selenium` for Docker)
 - `ahoy browser-stop` - Stop the browser
 
@@ -91,7 +87,7 @@
 - `CLOUDFLARE_TUNNEL` - Set to `1` to expose the development server through a public Cloudflare quick tunnel. `start` writes the tunnel URL to `.env` as `TUNNEL_URL`
 - `LOGIN_QRCODE` - Set to `1` to also render the `login` link as a terminal QR code (needs `qrencode`)
 - `GITHUB_TOKEN` - GitHub API token to avoid rate limits
-- `DEBUG` - Set to `1` to stream the full output of the underlying commands (Composer, npm, Drush). By default this output is suppressed and shown only when a command fails
+- `DEBUG` - Set to `1` to stream the full output of the underlying commands (Composer, Drush). By default this output is suppressed and shown only when a command fails
 
 ## Development Workflow
 
@@ -103,13 +99,10 @@
 
 ## Code Quality Tools
 
-- **CSpell**: Spell checking across the codebase (config at `.cspell.json`)
 - **PHPCS**: Drupal, DrupalPractice, DrevOps and PHPCompatibility standards
 - **PHPStan**: Static analysis with Drupal extensions
 - **Rector**: Automated refactoring and deprecation fixes
 - **Twig CS Fixer**: Twig template formatting
-- **ESLint**: JavaScript linting with Prettier formatting (config at `.eslintrc.json`)
-- **Stylelint**: CSS linting (config at `.stylelintrc.js`)
 
 ## CI/CD Support
 
@@ -124,8 +117,7 @@
 - The `build/` directory contains the assembled Drupal site
 - Extension files are symlinked from root into `build/web/modules/custom/`
 - SQLite database created in `/tmp/site_force_crystal.sqlite`
-- The PHP and JavaScript quality tools run from within the `build/` directory
-- CSpell runs from the project root
+- The PHP quality tools run from within the `build/` directory
 
 ## Updating the scaffold
 
