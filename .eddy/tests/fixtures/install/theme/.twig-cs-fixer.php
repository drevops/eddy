@@ -10,7 +10,7 @@
 // including templates/, is a symlink), so the Finder must follow symlinks to
 // discover the templates it needs to lint.
 $finder->followLinks();
-$finder->in(__DIR__ . '/web/modules/custom');
+$finder->in(__DIR__ . '/web/themes/custom');
 
 $config = new TwigCsFixer\Config\Config();
 $config->setRuleset($ruleset);
