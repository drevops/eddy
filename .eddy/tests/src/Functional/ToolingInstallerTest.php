<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Functional;

use AlexSkrypnyk\File\File;
use AlexSkrypnyk\PhpunitHelpers\Traits\ProcessTrait;
use DrevOps\Eddy\Tests\Traits\ComposerHomeTrait;
use DrevOps\Eddy\Tests\Unit\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Functional tests for the 'scripts/eddy-tooling' installer.
 *
 * Each test runs the real installer and Composer in a sandbox without
 * '.eddy/tooling', so the installer resolves the package as a generated
 * project does. A Composer home with a global path repository serves the
 * in-tree package as a release, so no published release is needed.
 */
#[Group('p2')]
final class ToolingInstallerTest extends UnitTestCase {

  use ComposerHomeTrait;
  use ProcessTrait;

  protected int $defaultTimeout = 300;

  protected int $defaultIdleTimeout = 120;

  protected const string PATCH = "diff --git a/PATCHED.txt b/PATCHED.txt\nnew file mode 100644\n--- /dev/null\n+++ b/PATCHED.txt\n@@ -0,0 +1 @@\n+patched\n";

  protected function setUp(): void {
    parent::setUp();

    File::copy(self::rootDir() . '/scripts/eddy-tooling', self::$sut . '/scripts/eddy-tooling');
    chmod(self::$sut . '/scripts/eddy-tooling', 0755);

    $this->processCwd = self::$sut;
  }

  protected function tearDown(): void {
    $this->processTearDown();

    parent::tearDown();
  }

  public function testInstall(): void {
    self::composerHomeWrite('1.0.0', FALSE);
    self::writeDevManifest(['require-dev' => ['drevops/eddy-tooling' => '~1.0.0']]);

    $this->runInstaller();
    $this->assertProcessSuccessful();
    $this->assertProcessOutputContains('[INFO] Installing drevops/eddy-tooling ~1.0.0.');
    $this->assertProcessOutputContains('[ OK ] Installed drevops/eddy-tooling.');

    $this->assertDirectoryExists(self::$sut . '/vendor/drevops/eddy-tooling/src');
    $this->assertFalse(is_link(self::$sut . '/vendor/drevops/eddy-tooling'), 'A release is installed as a copy.');

    foreach ($this->packageBins() as $bin) {
      $this->assertFileExists(self::$sut . '/vendor/bin/' . $bin);
    }

    $this->processRun('vendor/bin/eddy-info', ['drupal-profile'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertSame('standard', trim($this->processGet()->getOutput()), 'The installed commands run through their proxies.');

    $this->runInstaller();
    $this->assertProcessSuccessful();
    $this->assertSame('', $this->processGet()->getOutput(), 'A current install prints nothing.');

    self::composerHomeWrite('1.1.0', FALSE);
    self::writeDevManifest(['require-dev' => ['drevops/eddy-tooling' => '~1.1.0']]);

    $this->runInstaller();
    $this->assertProcessSuccessful();
    $this->assertProcessOutputContains('[INFO] Installing drevops/eddy-tooling ~1.1.0.');
    $this->assertSame('1.1.0', $this->lockedVersion(), 'A raised constraint installs the newer release.');
  }

  public function testPatches(): void {
    // The patch plugin comes from Packagist.
    self::composerHomeWrite('1.0.0', TRUE);

    File::dump(self::$sut . '/patches/eddy-tooling-add-file.patch', self::PATCH);

    self::writeDevManifest([
      'require-dev' => ['drevops/eddy-tooling' => '~1.0.0'],
      'extra' => ['patches' => ['drevops/eddy-tooling' => ['Add a file' => 'patches/eddy-tooling-add-file.patch']]],
    ]);

    $this->runInstaller();
    $this->assertProcessSuccessful();
    $this->assertFileExists(self::$sut . '/vendor/drevops/eddy-tooling/PATCHED.txt', 'The patch is applied, with its path resolved against the project root.');
    $this->assertFileDoesNotExist(self::$sut . '/eddy-tooling-patches.lock.json', 'The patch plugin keeps its lock inside vendor/.');

    File::dump(self::$sut . '/patches/eddy-tooling-add-file.patch', str_replace('+patched', '+patched again', self::PATCH));

    $this->runInstaller();
    $this->assertProcessSuccessful();
    $this->assertProcessOutputContains('[INFO] Installing drevops/eddy-tooling ~1.0.0.');
    $this->assertStringEqualsFile(self::$sut . '/vendor/drevops/eddy-tooling/PATCHED.txt', "patched again\n", 'A patch edited in place is applied again.');

    self::writeDevManifest(['require-dev' => ['drevops/eddy-tooling' => '~1.0.0']]);

    $this->runInstaller();
    $this->assertProcessSuccessful();
    $this->assertProcessOutputContains('[INFO] Installing drevops/eddy-tooling ~1.0.0.');
    $this->assertFileDoesNotExist(self::$sut . '/vendor/drevops/eddy-tooling/PATCHED.txt', 'Dropping the patch installs the package again without it.');
  }

  public function testFailure(): void {
    self::composerHomeWrite('1.0.0', FALSE);
    self::writeDevManifest(['require-dev' => ['drevops/eddy-tooling' => '~2.0.0']]);

    $this->runInstaller();
    $this->assertProcessFailed();
    $this->assertProcessOutputContains('drevops/eddy-tooling');
    $this->assertProcessOutputContains('[FAIL] Unable to install drevops/eddy-tooling.');
    $this->assertFileDoesNotExist(self::$sut . '/vendor/eddy-tooling.json', 'A failed install leaves no manifest, so the next run installs again.');
  }

  protected function runInstaller(): void {
    $this->processRun('./scripts/eddy-tooling', [], [], ['COMPOSER_HOME' => self::composerHomeDir()], $this->defaultTimeout, $this->defaultIdleTimeout);
  }

  protected static function writeDevManifest(array $contents): void {
    File::dump(self::$sut . '/composer.dev.json', json_encode($contents, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
  }

  /**
   * List the commands the in-tree package declares.
   *
   * @return array<int, string>
   *   The command names.
   */
  protected function packageBins(): array {
    $manifest = json_decode(File::read(self::toolingDir() . '/composer.json'), TRUE);
    $this->assertIsArray($manifest);

    $bins = $manifest['bin'] ?? NULL;
    $this->assertIsArray($bins);

    $names = [];
    foreach ($bins as $bin) {
      $this->assertIsString($bin);
      $names[] = basename($bin);
    }

    return $names;
  }

  protected function lockedVersion(): string {
    $lock = json_decode(File::read(self::$sut . '/vendor/eddy-tooling.lock'), TRUE);
    $this->assertIsArray($lock);

    $packages = $lock['packages'] ?? NULL;
    $this->assertIsArray($packages);

    foreach ($packages as $package) {
      if (is_array($package) && ($package['name'] ?? NULL) === 'drevops/eddy-tooling') {
        $version = $package['version'] ?? NULL;
        $this->assertIsString($version);

        return $version;
      }
    }

    $this->fail('The lock file does not list drevops/eddy-tooling.');
  }

}
