@@ -18,7 +18,6 @@
 - **PHPStan**: `ahoy lint` - never `vendor/bin/phpstan`.
 - **Rector**: `ahoy lint` (dry-run) / `ahoy lint-fix` - never `vendor/bin/rector`.
 - **Twig CS Fixer**: `ahoy lint` / `ahoy lint-fix` - never `vendor/bin/twig-cs-fixer`.
-- **ESLint / Stylelint**: `ahoy lint` / `ahoy lint-fix` - never `npx eslint` or `npx stylelint`.
 - **CSpell**: `ahoy lint` - never `npx cspell`.
 - **PHPUnit**: `ahoy test` / `ahoy test-unit` / `ahoy test-kernel` / `ahoy test-functional` - never `vendor/bin/phpunit`.
 - **Jest**: `ahoy test-javascript` - never `npx jest`.
@@ -108,8 +107,6 @@
 - **PHPStan**: Static analysis with Drupal extensions
 - **Rector**: Automated refactoring and deprecation fixes
 - **Twig CS Fixer**: Twig template formatting
-- **ESLint**: JavaScript linting with Prettier formatting (config at `.eslintrc.json`)
-- **Stylelint**: CSS linting (config at `.stylelintrc.js`)
 
 ## CI/CD Support
 
