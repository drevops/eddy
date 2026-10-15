<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Functional;

use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the 'eddy-assemble' command.
 */
#[Group('p2')]
final class AssembleTest extends ToolingTestCase {

  #[DataProvider('dataProviderAssemble')]
  public function testAssemble(string $drupal_version, string $init_message, bool $nvmrc): void {
    $env = $drupal_version !== '' ? ['DRUPAL_VERSION' => $drupal_version] : [];

    if ($nvmrc) {
      File::dump(self::$sut . '/.nvmrc', "22\n");
      $env['NVM_DIR'] = $this->createNvmStub();
    }

    $this->declareCorePatch('composer.json', 'add-composer-json-file.patch', self::coreFileAdditionDiff('eddy-composer-json.txt'));
    $this->declareCorePatch('composer.dev.json', 'add-composer-dev-json-file.patch', self::coreFileAdditionDiff('eddy-composer-dev-json.txt'));

    $this->installTooling();
    $this->assertProcessAnyOutputContains('Installed drevops/eddy-tooling.');

    $this->assertTrue(is_link(self::$sut . '/vendor/drevops/eddy-tooling'), 'The scaffold installs the package from .eddy/tooling as a symlink.');
    $this->assertSame(self::locationsRealpath(self::$sut . '/.eddy/tooling'), self::locationsRealpath(self::$sut . '/vendor/drevops/eddy-tooling'));

    $this->processRun('vendor/bin/eddy-assemble', [], [], $env, $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();

    $this->assertProcessAnyOutputContains($init_message);
    $this->assertProcessAnyOutputContains('ASSEMBLE COMPLETE');
    $this->assertProcessAnyOutputContains('[example] post-assemble script ran.');
    $this->assertDirectoryExists(self::$sut . '/build/vendor');
    $this->assertFileExists(self::$sut . '/build/composer.json');
    $this->assertFileExists(self::$sut . '/build/composer.lock');
    $this->assertFileExists(self::$sut . '/build/web/core/eddy-composer-json.txt');
    $this->assertFileExists(self::$sut . '/build/web/core/eddy-composer-dev-json.txt');
    $this->assertDirectoryDoesNotExist(self::$sut . '/build/vendor/drevops/eddy-tooling', 'The site build leaves the tooling package out.');
    $this->assertFileDoesNotExist(self::$sut . '/build/web/modules/custom/your_extension/vendor', 'The project-root vendor directory is not symlinked into the extension.');
    $this->assertProcessAnyOutputContains('Would run build');

    if ($nvmrc) {
      $this->assertProcessAnyOutputContains('Using the Node.js version in .nvmrc with nvm from ' . $env['NVM_DIR'] . '.');
      $this->assertSame("nvm use 22\nnvm use 22\n", File::read($env['NVM_DIR'] . '/calls.log'), 'Both npm commands run after nvm use in the project root.');
    }
    else {
      $this->assertProcessAnyOutputNotContains('.nvmrc');
    }

    // @see https://github.com/composer/composer/issues/12215
    $this->processRun('composer', ['--working-dir=' . self::$sut . '/build', 'require', '--dev', 'drupal/coder', '--with-all-dependencies', '--dry-run'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessAnyOutputNotContains('Upgrading');
  }

  public static function dataProviderAssemble(): \Iterator {
    yield 'Drupal 11' => ['', 'Creating Drupal 11 project', FALSE];
    yield 'Drupal 10 with .nvmrc' => ['10', 'Creating Drupal 10 project', TRUE];
  }

  public function testAssembleFailsOnPatchThatDoesNotApply(): void {
    $this->declareCorePatch('composer.json', 'broken.patch', implode("\n", [
      'diff --git a/core/eddy-missing.txt b/core/eddy-missing.txt',
      '--- a/core/eddy-missing.txt',
      '+++ b/core/eddy-missing.txt',
      '@@ -1 +1 @@',
      '-Original line.',
      '+Patched line.',
      '',
    ]));

    $this->installTooling();

    $this->processRun('vendor/bin/eddy-assemble', [], [], [], $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessFailed();

    $this->assertProcessAnyOutputContains('No available patcher was able to apply patch');
    $this->assertProcessAnyOutputContains('patches/broken.patch');
    $this->assertProcessAnyOutputNotContains('ASSEMBLE COMPLETE');
  }

  protected function installTooling(): void {
    $this->processRun('./scripts/eddy-tooling', [], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
  }

  /**
   * Create an nvm directory whose 'nvm' logs each call to 'calls.log'.
   *
   * Each log line holds the arguments and the '.nvmrc' of the directory that
   * the call runs in.
   *
   * @return string
   *   The directory.
   */
  protected function createNvmStub(): string {
    $dir = self::$tmp . '/nvm';
    File::dump($dir . '/nvm.sh', 'nvm() { echo "nvm $* $(cat .nvmrc)" >> "$NVM_DIR/calls.log"; }' . PHP_EOL);

    return $dir;
  }

  /**
   * Declare a local 'drupal/core' patch in a manifest of the project.
   *
   * @param string $manifest
   *   The manifest file, relative to the project root.
   * @param string $name
   *   The patch file name in the 'patches' directory.
   * @param string $diff
   *   The patch content.
   */
  protected function declareCorePatch(string $manifest, string $name, string $diff): void {
    File::dump(self::$sut . '/patches/' . $name, $diff);

    $manifest_path = self::$sut . '/' . $manifest;
    $config = json_decode(File::read($manifest_path), TRUE, 512, JSON_THROW_ON_ERROR);
    $this->assertIsArray($config);
    /** @var array{extra?: array{patches?: array<string, array<string, string>>}} $config */
    $config['extra']['patches']['drupal/core'][$name] = 'patches/' . $name;
    File::dump($manifest_path, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
  }

  /**
   * Build a 'drupal/core' patch that adds a file.
   *
   * Paths carry the 'core/' prefix of drupal.org patches, which
   * composer-patches strips for 'drupal/core'.
   */
  protected static function coreFileAdditionDiff(string $file): string {
    return implode("\n", [
      'diff --git a/core/' . $file . ' b/core/' . $file,
      'new file mode 100644',
      '--- /dev/null',
      '+++ b/core/' . $file,
      '@@ -0,0 +1 @@',
      '+Added by a patch.',
      '',
    ]);
  }

}
