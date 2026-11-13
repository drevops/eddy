<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use AlexSkrypnyk\File\File;
use function DrevOps\Eddy\Tooling\patch_package;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[CoversFunction('DrevOps\Eddy\Tooling\patch_package')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersPatchPackageTest extends UnitTestCase {

  protected const string UNPATCHED = "<?php\n\n\$app->addCommand(\$command);\n";

  protected const string PATCHED = "<?php\n\nstatic::addCommandToApplication(\$app, \$command);\n";

  protected const string UNRELATED = "<?php\n\n\$app->add(\$command);\n";

  protected const string PATCH = "--- a/src/Example.php\n+++ b/src/Example.php\n@@ -1,3 +1,3 @@\n <?php\n \n-\$app->addCommand(\$command);\n+static::addCommandToApplication(\$app, \$command);\n";

  #[DataProvider('dataProviderPatchesWithRealPatch')]
  public function testPatchesWithRealPatch(?string $installed, string $expected_result, ?string $expected_content): void {
    $package_dir = self::$tmp . '/vendor/acme/package';
    $patch_file = self::$tmp . '/example.patch';
    File::dump($patch_file, self::PATCH);

    if ($installed !== NULL) {
      File::dump($package_dir . '/src/Example.php', $installed);
    }

    $result = patch_package($package_dir, $patch_file);

    $this->assertSame($expected_result, $result);

    if ($expected_content === NULL) {
      $this->assertFalse(File::exists($package_dir));

      return;
    }

    $this->assertSame($expected_content, File::read($package_dir . '/src/Example.php'));
    $this->assertFalse(File::exists($package_dir . '/src/Example.php.orig'));
    $this->assertFalse(File::exists($package_dir . '/src/Example.php.rej'));
  }

  public static function dataProviderPatchesWithRealPatch(): \Iterator {
    yield 'release with the defect' => [self::UNPATCHED, 'applied', self::PATCHED];
    yield 'release that contains the change' => [self::PATCHED, 'not-applicable', self::PATCHED];
    yield 'release without the targeted code' => [self::UNRELATED, 'not-applicable', self::UNRELATED];
    yield 'package not installed' => [NULL, 'not-installed', NULL];
  }

  #[DataProvider('dataProviderFailsWhenPatchIsUnreadable')]
  public function testFailsWhenPatchIsUnreadable(?string $patch_content): void {
    $package_dir = self::$tmp . '/vendor/acme/package';
    $patch_file = self::$tmp . '/example.patch';
    File::dump($package_dir . '/src/Example.php', self::UNPATCHED);

    if ($patch_content !== NULL) {
      File::dump($patch_file, $patch_content);
    }

    $this->mockQuit(1);

    $output = $this->capture(fn(): string => patch_package($package_dir, $patch_file), 1);

    $this->assertStringContainsString(sprintf('Unable to check %s against %s.', $patch_file, $package_dir), $output);
    $this->assertSame(self::UNPATCHED, File::read($package_dir . '/src/Example.php'));
  }

  public static function dataProviderFailsWhenPatchIsUnreadable(): \Iterator {
    yield 'file without a diff' => ["This file holds no diff at all.\n"];
    yield 'missing file' => [NULL];
  }

  public function testFailsWithoutPatchCommand(): void {
    $package_dir = self::$tmp . '/vendor/acme/package';
    File::mkdir($package_dir);
    $this->mockExecOutput('command -v patch 2>/dev/null', [], 1);
    $this->mockQuit(1);

    $output = $this->capture(fn(): string => patch_package($package_dir, self::$tmp . '/example.patch'), 1);

    $this->assertStringContainsString("Command 'patch' is not available.", $output);
  }

  public function testFailsWhenApplyFailsAfterDryRun(): void {
    $package_dir = self::$tmp . '/vendor/acme/package';
    $patch_file = self::$tmp . '/example.patch';
    File::mkdir($package_dir);
    $this->mockExecOutput('command -v patch 2>/dev/null', ['/usr/bin/patch']);

    $command = sprintf('patch --forward --batch --silent -p1 -d %s -i %s', escapeshellarg($package_dir), escapeshellarg($patch_file));
    $this->mockPassthruMultiple([['cmd' => $command . ' --dry-run'], ['cmd' => $command, 'result_code' => 2]]);
    $this->mockQuit(2);

    $output = $this->capture(fn(): string => patch_package($package_dir, $patch_file), 2);

    $this->assertStringContainsString(sprintf('Unable to apply %s to %s.', $patch_file, $package_dir), $output);
  }

}
