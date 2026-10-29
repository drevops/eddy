@@ -212,7 +212,7 @@
 To run some of the tests, pass a test file path or a PHPUnit option, such as `--filter` or `--group`, to a test command. PHPUnit runs inside `build`, so a test path starts at the extension's symlink in the assembled site:
 
 ```bash
-ahoy test-unit web/modules/custom/force_crystal/tests/src/Unit/MyUnitTest.php
+ahoy test-unit web/themes/custom/force_crystal/tests/src/Unit/MyUnitTest.php
 ahoy test-unit -- --group=wip
 ahoy test-unit -- --filter=MyUnitTest::testSomething
 ```
@@ -222,7 +222,7 @@
 ```bash
 cd build
 php -d pcov.directory=.. vendor/bin/phpunit \
-  web/modules/custom/force_crystal/tests/src/Unit/MyUnitTest.php
+  web/themes/custom/force_crystal/tests/src/Unit/MyUnitTest.php
 php -d pcov.directory=.. vendor/bin/phpunit --group=wip
 php -d pcov.directory=.. vendor/bin/phpunit --filter=MyUnitTest::testSomething
 ```
