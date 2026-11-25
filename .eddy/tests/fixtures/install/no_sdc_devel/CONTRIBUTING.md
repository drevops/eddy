@@ -156,20 +156,6 @@
 ahoy lint-fix
 ```
 
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
-
 ## Testing
 
 To run every test:
