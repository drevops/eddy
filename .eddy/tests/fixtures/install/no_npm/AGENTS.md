@@ -16,10 +16,7 @@
 - **PHPStan**: `ahoy lint` - never `vendor/bin/phpstan`.
 - **Rector**: `ahoy lint` (dry-run) / `ahoy lint-fix` - never `vendor/bin/rector`.
 - **Twig CS Fixer**: `ahoy lint` / `ahoy lint-fix` - never `vendor/bin/twig-cs-fixer`.
-- **ESLint / Stylelint**: `ahoy lint` / `ahoy lint-fix` - never `npx eslint` or `npx stylelint`.
-- **CSpell**: `ahoy lint` - never `npx cspell`.
 - **PHPUnit**: `ahoy test` / `ahoy test-unit` / `ahoy test-kernel` / `ahoy test-functional` - never `vendor/bin/phpunit`.
-- **Jest**: `ahoy test-javascript` - never `npx jest`.
 - **Drush**: `ahoy drush <command>` - never `build/vendor/bin/drush` directly.
 
 ### Build and Environment Management
@@ -44,7 +41,6 @@
 - `ahoy test-kernel` - Run kernel tests only
 - `ahoy test-functional` - Run functional tests only
 - `ahoy test-functional-javascript` - Run FunctionalJavascript tests (uses the local Chrome by default; set `WEBDRIVER_BACKEND=selenium` for Docker)
-- `ahoy test-javascript` - Run JavaScript unit tests with Jest (alias: `ahoy test-js`)
 - `ahoy browser-start` - Start the browser for FunctionalJavascript tests (local Chrome by default; set `WEBDRIVER_BACKEND=selenium` for Docker)
 - `ahoy browser-stop` - Stop the browser
 
@@ -96,7 +92,6 @@
 
 ## Code Quality Tools
 
-- **CSpell**: Spell checking across the codebase (config at `.cspell.json`)
 - **PHPCS**: Drupal and DrupalPractice standards
 - **PHPStan**: Static analysis with Drupal extensions
 - **Rector**: Automated refactoring and deprecation fixes
