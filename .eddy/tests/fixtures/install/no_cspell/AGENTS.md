@@ -17,7 +17,6 @@
 - **Rector**: `ahoy lint` (dry-run) / `ahoy lint-fix` - never `vendor/bin/rector`.
 - **Twig CS Fixer**: `ahoy lint` / `ahoy lint-fix` - never `vendor/bin/twig-cs-fixer`.
 - **ESLint / Stylelint**: `ahoy lint` / `ahoy lint-fix` - never `npx eslint` or `npx stylelint`.
-- **CSpell**: `ahoy lint` - never `npx cspell`.
 - **PHPUnit**: `ahoy test` / `ahoy test-unit` / `ahoy test-kernel` / `ahoy test-functional` - never `vendor/bin/phpunit`.
 - **Jest**: `ahoy test-javascript` - never `npx jest`.
 - **Drush**: `ahoy drush <command>` - never `build/vendor/bin/drush` directly.
@@ -101,7 +100,6 @@
 
 ## Code Quality Tools
 
-- **CSpell**: Spell checking across the codebase (config at `.cspell.json`)
 - **PHPCS**: Drupal, DrupalPractice, DrevOps and PHPCompatibility standards
 - **PHPStan**: Static analysis with Drupal extensions
 - **Rector**: Automated refactoring and deprecation fixes
@@ -123,7 +121,6 @@
 - Extension files are symlinked from root into `build/web/modules/custom/`
 - SQLite database created in `/tmp/site_force_crystal.sqlite`
 - The PHP and JavaScript quality tools run from within the `build/` directory
-- CSpell runs from the project root
 
 ## Updating the scaffold
 
