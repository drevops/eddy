@@ -197,7 +197,7 @@
 You can run specific tests by passing a path to the test file or PHPUnit CLI option (`--filter`, `--group`, etc.) to the test commands. PHPUnit runs inside `build`, so a test path starts at the extension's symlink in the assembled site:
 
 ```bash
-ahoy test-unit web/modules/custom/force_crystal/tests/src/Unit/MyUnitTest.php
+ahoy test-unit web/themes/custom/force_crystal/tests/src/Unit/MyUnitTest.php
 ahoy test-unit -- --group=wip
 ahoy test-unit -- --filter=MyUnitTest::testSomething
 ```
@@ -207,7 +207,7 @@
 ```bash
 cd build
 php -d pcov.directory=.. vendor/bin/phpunit \
-  web/modules/custom/force_crystal/tests/src/Unit/MyUnitTest.php
+  web/themes/custom/force_crystal/tests/src/Unit/MyUnitTest.php
 php -d pcov.directory=.. vendor/bin/phpunit --group=wip
 php -d pcov.directory=.. vendor/bin/phpunit --filter=MyUnitTest::testSomething
 ```
