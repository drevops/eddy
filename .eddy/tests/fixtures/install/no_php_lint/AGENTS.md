@@ -14,10 +14,6 @@
 
 Run each tool through its `ahoy` wrapper, never the binary directly:
 
-- **PHPCS / PHPCBF**: `ahoy lint` / `ahoy lint-fix` - never `vendor/bin/phpcs` or `vendor/bin/phpcbf`.
-- **PHPStan**: `ahoy lint` - never `vendor/bin/phpstan`.
-- **Rector**: `ahoy lint` (dry-run) / `ahoy lint-fix` - never `vendor/bin/rector`.
-- **Twig CS Fixer**: `ahoy lint` / `ahoy lint-fix` - never `vendor/bin/twig-cs-fixer`.
 - **ESLint / Stylelint**: `ahoy lint` / `ahoy lint-fix` - never `npx eslint` or `npx stylelint`.
 - **CSpell**: `ahoy lint` - never `npx cspell`.
 - **PHPUnit**: `ahoy test` / `ahoy test-unit` / `ahoy test-kernel` / `ahoy test-functional` - never `vendor/bin/phpunit`.
@@ -104,10 +100,6 @@
 ## Code Quality Tools
 
 - **CSpell**: Spell checking across the codebase (config at `.cspell.json`)
-- **PHPCS**: Drupal, DrupalPractice, DrevOps and PHPCompatibility standards
-- **PHPStan**: Static analysis with Drupal extensions
-- **Rector**: Automated refactoring and deprecation fixes
-- **Twig CS Fixer**: Twig template formatting
 - **ESLint**: JavaScript linting with Prettier formatting (config at `.eslintrc.json`)
 - **Stylelint**: CSS linting (config at `.stylelintrc.js`)
 
@@ -124,7 +116,7 @@
 - The `build/` directory contains the assembled Drupal site
 - Extension files are symlinked from root into `build/web/modules/custom/`
 - SQLite database created in `/tmp/site_force_crystal.sqlite`
-- The PHP and JavaScript quality tools run from within the `build/` directory
+- The JavaScript quality tools run from within the `build/` directory
 - CSpell runs from the project root
 
 ## Updating the scaffold
