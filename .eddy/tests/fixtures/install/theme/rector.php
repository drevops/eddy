@@ -105,7 +105,7 @@
   // (each is passed to the finder as its own root, which a symlinked root
   // resolves).
   ->withPaths([
-    __DIR__ . '/web/modules/custom/*/*',
+    __DIR__ . '/web/themes/custom/*/*',
   ])
   // Configure Drupal autoloading.
   ->withAutoloadPaths((function (): array {
