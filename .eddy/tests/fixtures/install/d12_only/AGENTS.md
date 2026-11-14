@@ -115,8 +115,7 @@
 
 - **GitHub Actions**: `.github/workflows/test.yml` and deployment
 - **Matrix testing**:
-  - Drupal 10 on PHP 8.3-8.4
-  - Drupal 11 on PHP 8.3-8.5
+  - Drupal 12 on PHP 8.5
 - **Windows testing**: The `test-windows` job runs the tests on Windows for each Drupal version, on its highest PHP version. Its build uses Git Bash, and its database is `build/site_force_crystal.sqlite`
 - **Automated deployment**: Mirror to Drupal.org after CI passes
 
