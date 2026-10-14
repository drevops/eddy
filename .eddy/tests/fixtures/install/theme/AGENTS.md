@@ -4,7 +4,7 @@
 
 ## Overview
 
-Force Crystal (`force_crystal`) is a Drupal module. The project provides a complete development environment with CI configuration, testing setup, and deployment automation for publishing to Drupal.org.
+Force Crystal (`force_crystal`) is a Drupal theme. The project provides a complete development environment with CI configuration, testing setup, and deployment automation for publishing to Drupal.org.
 
 ## Development Commands
 
@@ -62,9 +62,8 @@
 ## Project Structure
 
 **Key Directories:**
-- `src/` - Extension source code (services, forms, etc.)
-- `tests/src/` - PHPUnit tests (Unit/, Kernel/, Functional/)
-- `config/schema/` - Configuration schema definitions
+- `css/` and `js/` - Stylesheets and JavaScript behaviors of the theme's global library
+- `tests/src/` - PHPUnit tests (FunctionalJavascript/)
 - `build/` - Assembled Drupal codebase (symlinked extension)
 - `vendor/` - The installed `drevops/eddy-tooling` package, whose commands (`vendor/bin/eddy-*`) assemble, start, provision and deploy the extension. Not committed and not part of the extension
 - `scripts/eddy-tooling` - Installs the tooling package into `vendor/` from the constraint in `composer.dev.json`. The command wrappers and CI run it before the tooling commands
@@ -72,10 +71,9 @@
 
 ## Architecture
 
-- **Service-based architecture**: Main functionality in services registered via `*.services.yml`
-- **Configuration-driven**: Uses Drupal configuration system with schema validation
-- **Test coverage**: Unit, kernel, and functional test examples provided
-- **Form integration**: Admin forms in `src/Form/` for configuration
+- **No base theme**: `base theme: false`, so Drupal's default templates render every page until the theme overrides them in `templates/`
+- **Global library**: The `libraries` key in `force_crystal.info.yml` attaches the `global` library from `force_crystal.libraries.yml` to every page
+- **Test coverage**: FunctionalJavascript smoke test example provided, with the theme installed as the default theme
 
 ## Environment Variables
 
@@ -96,7 +94,7 @@
 ## Development Workflow
 
 1. Build environment: `ahoy build`
-2. Develop Force Crystal code in `src/`
+2. Develop your theme: Twig templates in `templates/`, styles in `css/` and behaviors in `js/`
 3. Check standards: `ahoy lint`
 4. Run tests: `ahoy test`
 5. Access site at the URL that `ahoy info` reports
@@ -122,7 +120,7 @@
 ## Important Notes
 
 - The `build/` directory contains the assembled Drupal site
-- Extension files are symlinked from root into `build/web/modules/custom/`
+- Extension files are symlinked from root into `build/web/themes/custom/`
 - SQLite database created in `/tmp/site_force_crystal.sqlite`
 - The PHP and JavaScript quality tools run from within the `build/` directory
 - CSpell runs from the project root
