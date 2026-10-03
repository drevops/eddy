<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the assemble devtools command.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[Group('p2')]
final class AssembleTest extends DevtoolsTestCase {

  #[DataProvider('dataProviderAssemble')]
  public function testAssemble(string $drupal_version, string $init_message): void {
    $env = $drupal_version !== '' ? ['DRUPAL_VERSION' => $drupal_version] : [];

    $this->declareCorePatch('composer.json', 'add-composer-json-file.patch', self::coreFileAdditionDiff('eddy-composer-json.txt'));
    $this->declareCorePatch('composer.dev.json', 'add-composer-dev-json-file.patch', self::coreFileAdditionDiff('eddy-composer-dev-json.txt'));

    $this->processRun('./.devtools/assemble', [], [], $env, $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();

    $this->assertProcessAnyOutputContains($init_message);
    $this->assertProcessAnyOutputContains('ASSEMBLE COMPLETE');
    $this->assertProcessAnyOutputContains('[example] post-assemble script ran.');
    $this->assertDirectoryExists(self::$sut . '/build/vendor');
    $this->assertFileExists(self::$sut . '/build/composer.json');
    $this->assertFileExists(self::$sut . '/build/composer.lock');
    $this->assertFileExists(self::$sut . '/build/web/core/eddy-composer-json.txt');
    $this->assertFileExists(self::$sut . '/build/web/core/eddy-composer-dev-json.txt');

    // @see https://github.com/composer/composer/issues/12215
    $this->processRun('composer', ['--working-dir=' . self::$sut . '/build', 'require', '--dev', 'drupal/coder', '--with-all-dependencies', '--dry-run'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessAnyOutputNotContains('Upgrading');
  }

  public static function dataProviderAssemble(): \Iterator {
    yield ['', 'Creating Drupal 11 project'];
    yield ['10', 'Creating Drupal 10 project'];
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

    $this->processRun('./.devtools/assemble', [], [], [], $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessFailed();

    $this->assertProcessAnyOutputContains('No available patcher was able to apply patch');
    $this->assertProcessAnyOutputContains('patches/broken.patch');
    $this->assertProcessAnyOutputNotContains('ASSEMBLE COMPLETE');
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
    if (!is_dir(self::$sut . '/patches')) {
      mkdir(self::$sut . '/patches');
    }

    file_put_contents(self::$sut . '/patches/' . $name, $diff);

    $manifest_path = self::$sut . '/' . $manifest;
    $config = json_decode((string) file_get_contents($manifest_path), TRUE, 512, JSON_THROW_ON_ERROR);
    $this->assertIsArray($config);
    /** @var array{extra?: array{patches?: array<string, array<string, string>>}} $config */
    $config['extra']['patches']['drupal/core'][$name] = 'patches/' . $name;
    file_put_contents($manifest_path, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
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
