<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Functional;

use AlexSkrypnyk\PhpunitHelpers\Traits\ProcessTrait;
use DrevOps\Eddy\Tests\Unit\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Functional tests for the 'scripts/eddy-tooling' installer.
 *
 * Each test runs the real installer and Composer in a sandbox project that has
 * no '.eddy/tooling' directory, so the installer resolves the package the way
 * a generated project does. A Composer home with a global path repository
 * serves the in-tree package as a release, so no published release is needed.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[Group('p2')]
final class ToolingInstallerTest extends UnitTestCase {

  use ProcessTrait;

  protected int $defaultTimeout = 300;

  protected int $defaultIdleTimeout = 120;

  /**
   * A patch that adds a file to the package.
   */
  protected const string PATCH = "diff --git a/PATCHED.txt b/PATCHED.txt\nnew file mode 100644\n--- /dev/null\n+++ b/PATCHED.txt\n@@ -0,0 +1 @@\n+patched\n";

  protected function setUp(): void {
    parent::setUp();

    mkdir(self::$sut . '/scripts', 0755, TRUE);
    copy(dirname(__DIR__, 4) . '/scripts/eddy-tooling', self::$sut . '/scripts/eddy-tooling');
    chmod(self::$sut . '/scripts/eddy-tooling', 0755);

    $this->processCwd = self::$sut;
  }

  protected function tearDown(): void {
    $this->processTearDown();

    parent::tearDown();
  }

  public function testInstall(): void {
    $this->writeComposerHome('1.0.0', FALSE);
    $this->writeDevManifest(['require-dev' => ['drevops/eddy-tooling' => '~1.0.0']]);

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

    $this->writeComposerHome('1.1.0', FALSE);
    $this->writeDevManifest(['require-dev' => ['drevops/eddy-tooling' => '~1.1.0']]);

    $this->runInstaller();
    $this->assertProcessSuccessful();
    $this->assertProcessOutputContains('[INFO] Installing drevops/eddy-tooling ~1.1.0.');
    $this->assertSame('1.1.0', $this->lockedVersion(), 'A raised constraint installs the newer release.');
  }

  public function testPatches(): void {
    // The patch plugin comes from Packagist.
    $this->writeComposerHome('1.0.0', TRUE);

    mkdir(self::$sut . '/patches', 0755, TRUE);
    file_put_contents(self::$sut . '/patches/eddy-tooling-add-file.patch', self::PATCH);

    $this->writeDevManifest([
      'require-dev' => ['drevops/eddy-tooling' => '~1.0.0'],
      'extra' => ['patches' => ['drevops/eddy-tooling' => ['Add a file' => 'patches/eddy-tooling-add-file.patch']]],
    ]);

    $this->runInstaller();
    $this->assertProcessSuccessful();
    $this->assertFileExists(self::$sut . '/vendor/drevops/eddy-tooling/PATCHED.txt', 'The patch is applied, with its path resolved against the project root.');
    $this->assertFileDoesNotExist(self::$sut . '/eddy-tooling-patches.lock.json', 'The patch plugin keeps its lock inside vendor/.');

    $this->writeDevManifest(['require-dev' => ['drevops/eddy-tooling' => '~1.0.0']]);

    $this->runInstaller();
    $this->assertProcessSuccessful();
    $this->assertProcessOutputContains('[INFO] Installing drevops/eddy-tooling ~1.0.0.');
    $this->assertFileDoesNotExist(self::$sut . '/vendor/drevops/eddy-tooling/PATCHED.txt', 'Dropping the patch installs the package again without it.');
  }

  public function testFailure(): void {
    $this->writeComposerHome('1.0.0', FALSE);
    $this->writeDevManifest(['require-dev' => ['drevops/eddy-tooling' => '~2.0.0']]);

    $this->runInstaller();
    $this->assertProcessFailed();
    $this->assertProcessOutputContains('drevops/eddy-tooling');
    $this->assertProcessOutputContains('[FAIL] Unable to install drevops/eddy-tooling.');
    $this->assertFileDoesNotExist(self::$sut . '/vendor/eddy-tooling.json', 'A failed install leaves no manifest, so the next run installs again.');
  }

  protected function runInstaller(): void {
    $this->processRun('./scripts/eddy-tooling', [], [], ['COMPOSER_HOME' => self::$tmp . '/composer-home'], $this->defaultTimeout, $this->defaultIdleTimeout);
  }

  /**
   * Write a Composer home that serves the in-tree package as a release.
   *
   * @param string $version
   *   The version the package is served as.
   * @param bool $packagist
   *   Whether Packagist stays available for other packages.
   */
  protected function writeComposerHome(string $version, bool $packagist): void {
    $repositories = [
      [
        'type' => 'path',
        'url' => dirname(__DIR__, 4) . '/.eddy/tooling',
        'options' => ['symlink' => FALSE, 'versions' => ['drevops/eddy-tooling' => $version]],
      ],
    ];

    if (!$packagist) {
      $repositories[] = ['packagist.org' => FALSE];
    }

    if (!is_dir(self::$tmp . '/composer-home')) {
      mkdir(self::$tmp . '/composer-home', 0755, TRUE);
    }

    file_put_contents(self::$tmp . '/composer-home/config.json', json_encode(['repositories' => $repositories], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
  }

  protected function writeDevManifest(array $contents): void {
    file_put_contents(self::$sut . '/composer.dev.json', json_encode($contents, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
  }

  /**
   * List the commands the in-tree package declares.
   *
   * @return array<int, string>
   *   The command names.
   */
  protected function packageBins(): array {
    $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 4) . '/.eddy/tooling/composer.json'), TRUE);
    $this->assertIsArray($manifest);
    $this->assertIsArray($manifest['bin'] ?? NULL);

    return array_map(static fn(mixed $bin): string => basename((string) $bin), $manifest['bin']);
  }

  /**
   * Read the installed package version from the lock file.
   */
  protected function lockedVersion(): string {
    $lock = json_decode((string) file_get_contents(self::$sut . '/vendor/eddy-tooling.lock'), TRUE);
    $this->assertIsArray($lock);

    foreach ((array) ($lock['packages'] ?? []) as $package) {
      if (is_array($package) && ($package['name'] ?? NULL) === 'drevops/eddy-tooling') {
        return (string) ($package['version'] ?? '');
      }
    }

    $this->fail('The lock file does not list drevops/eddy-tooling.');
  }

}
