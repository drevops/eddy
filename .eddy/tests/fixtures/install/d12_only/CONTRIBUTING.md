@@ -153,7 +153,7 @@
 ahoy test
 ```
 
-The tests are located in the `tests/src` directory. The `phpunit.xml` file configures PHPUnit to run the tests, and `phpunit.d10.xml` replaces it in a Drupal 10 build. Both use Drupal core's bootstrap file `web/core/tests/bootstrap.php` to bootstrap the Drupal environment before running the tests.
+The tests are located in the `tests/src` directory. The `phpunit.xml` file configures PHPUnit to run the tests. It uses Drupal core's bootstrap file `web/core/tests/bootstrap.php` to bootstrap the Drupal environment before running the tests.
 
 Each test suite can also be run on its own:
 
