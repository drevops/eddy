@@ -164,7 +164,7 @@
 ahoy test
 ```
 
-The PHPUnit tests live in the `tests/src` directory. The `phpunit.xml` file configures PHPUnit to run the tests, and `phpunit.d10.xml` replaces it in a Drupal 10 build. Both use Drupal core's bootstrap file, `web/core/tests/bootstrap.php`, to boot Drupal before the tests run.
+The PHPUnit tests live in the `tests/src` directory. The `phpunit.xml` file configures PHPUnit to run the tests. It uses Drupal core's bootstrap file, `web/core/tests/bootstrap.php`, to boot Drupal before the tests run.
 
 To run 1 test suite:
 
