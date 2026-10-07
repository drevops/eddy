@@ -13,10 +13,6 @@
 
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
-- **PHPCS**: Drupal and DrupalPractice standards
-- **PHPStan**: Static analysis with Drupal extensions
-- **Rector**: Automated refactoring and deprecation fixes
-- **Twig CS Fixer**: Twig template formatting
 
 ## CI/CD Support
 
