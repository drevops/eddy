@@ -19,7 +19,6 @@
 - **Rector**: `ahoy lint` (dry-run) / `ahoy lint-fix` - never `vendor/bin/rector`.
 - **Twig CS Fixer**: `ahoy lint` / `ahoy lint-fix` - never `vendor/bin/twig-cs-fixer`.
 - **SDC Devel**: `ahoy lint-sdc`, on a provisioned site - never `drush sdc-devel:validate`, which exits with code 0 whatever it finds.
-- **ESLint / Stylelint**: `ahoy lint` / `ahoy lint-fix` - never `npx eslint` or `npx stylelint`.
 - **CSpell**: `ahoy lint` - never `npx cspell`.
 - **PHPUnit**: `ahoy test` / `ahoy test-unit` / `ahoy test-kernel` / `ahoy test-functional` - never `vendor/bin/phpunit`.
 - **Jest**: `ahoy test-javascript` - never `npx jest`.
@@ -112,8 +111,6 @@
 - **Rector**: Automated refactoring and deprecation fixes
 - **Twig CS Fixer**: Twig template formatting
 - **SDC Devel**: Single Directory Component validation of the `*.component.yml` definitions and Twig templates in `components/`, on the provisioned site
-- **ESLint**: JavaScript linting with Prettier formatting (config at `.eslintrc.json`)
-- **Stylelint**: CSS linting (config at `.stylelintrc.js`)
 
 ## CI/CD Support
 
@@ -128,7 +125,7 @@
 - The `build/` directory contains the assembled Drupal site
 - Extension files are symlinked from root into `build/web/modules/custom/`
 - SQLite database created in `/tmp/site_force_crystal.sqlite`
-- The PHP and JavaScript quality tools run from within the `build/` directory
+- The PHP quality tools run from within the `build/` directory
 - CSpell runs from the project root
 
 ## Updating the scaffold
