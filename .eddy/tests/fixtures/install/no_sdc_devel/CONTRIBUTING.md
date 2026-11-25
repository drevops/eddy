@@ -100,8 +100,6 @@
 
 The provision command installs a Drupal site from the `standard` profile. It enables the extension and every `drupal/*` module and theme in the `suggest` section of `composer.json`. To install from another profile, set the `DRUPAL_PROFILE` environment variable.
 
-The provision command also enables SDC Devel, which validates the Single Directory Components.
-
 The start command serves the site on `localhost` and prints its URL. To serve the site on another host name, set the `WEBSERVER_HOST` environment variable.
 
 The start command resolves the port, `WEBSERVER_PORT`, in this order:
@@ -157,20 +155,6 @@
 ```bash
 ahoy lint-fix
 ```
-
-### Validating Single Directory Components
-
-[SDC Devel](https://www.drupal.org/project/sdc_devel) validates the `*.component.yml` definitions and Twig templates in `components/`. It runs on the provisioned site, so `lint` leaves it out. After you add a `components` directory, assemble and provision the site again, so the build links the directory and Drupal finds the components.
-
-To validate the components:
-
-```bash
-ahoy lint-sdc
-```
-
-The command prints every finding and fails on a Warning or a more severe finding. Notice, Info and Debug findings only print. To fail on Notice findings too, set `SDC_DEVEL_FAIL_LEVEL=notice`. The accepted levels are `emergency`, `alert`, `critical`, `error`, `warning`, `notice`, `info` and `debug`. An extension without a `components` directory passes without any check.
-
-In CI, the `test` jobs run it after they provision the site. To let the step fail without failing its job, set the `CI_SDC_DEVEL_IGNORE_FAILURE` repository variable to `1`. The job log still shows every finding.
 
 ## Testing
 
