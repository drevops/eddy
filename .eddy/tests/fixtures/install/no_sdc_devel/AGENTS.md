@@ -18,7 +18,6 @@
 - **PHPStan**: `ahoy lint` - never `vendor/bin/phpstan`.
 - **Rector**: `ahoy lint` (dry-run) / `ahoy lint-fix` - never `vendor/bin/rector`.
 - **Twig CS Fixer**: `ahoy lint` / `ahoy lint-fix` - never `vendor/bin/twig-cs-fixer`.
-- **SDC Devel**: `ahoy lint-sdc`, on a provisioned site - never `drush sdc-devel:validate`, which exits with code 0 whatever it finds.
 - **ESLint / Stylelint**: `ahoy lint` / `ahoy lint-fix` - never `npx eslint` or `npx stylelint`.
 - **CSpell**: `ahoy lint` - never `npx cspell`.
 - **PHPUnit**: `ahoy test` / `ahoy test-unit` / `ahoy test-kernel` / `ahoy test-functional` - never `vendor/bin/phpunit`.
@@ -40,7 +39,6 @@
 **Linting:**
 - `ahoy lint` - Run all linting tools
 - `ahoy lint-fix` - Auto-fix coding standards violations
-- `ahoy lint-sdc` - Validate Single Directory Components on the provisioned site. `lint` leaves it out, because it needs a site
 
 **Testing:**
 - `ahoy test` - Run all tests
@@ -91,7 +89,6 @@
 - `DRUPAL_PROFILE` - Install profile that `provision` installs the site from (default: `standard`)
 - `XDEBUG` - Set to `1` to start the development server with XDebug step-debugging
 - `CLOUDFLARE_TUNNEL` - Set to `1` to expose the development server through a public Cloudflare quick tunnel. `start` writes the tunnel URL to `.env` as `TUNNEL_URL`
-- `SDC_DEVEL_FAIL_LEVEL` - Least severe Single Directory Component finding that fails `vendor/bin/eddy-lint-sdc`: `emergency`, `alert`, `critical`, `error`, `warning`, `notice`, `info` or `debug`. Defaults to `warning`
 - `LOGIN_QRCODE` - Set to `1` to also render the `login` link as a terminal QR code (needs `qrencode`)
 - `GITHUB_TOKEN` - GitHub API token to avoid rate limits
 - `DEBUG` - Set to `1` to stream the full output of the underlying commands (Composer, npm, Drush). By default this output is suppressed and shown only when a command fails
@@ -111,7 +108,6 @@
 - **PHPStan**: Static analysis with Drupal extensions
 - **Rector**: Automated refactoring and deprecation fixes
 - **Twig CS Fixer**: Twig template formatting
-- **SDC Devel**: Single Directory Component validation of the `*.component.yml` definitions and Twig templates in `components/`, on the provisioned site
 - **ESLint**: JavaScript linting with Prettier formatting (config at `.eslintrc.json`)
 - **Stylelint**: CSS linting (config at `.stylelintrc.js`)
 
