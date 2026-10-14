<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Functional;

use AlexSkrypnyk\File\File;
use DrevOps\Eddy\Tests\Traits\ComposerHomeTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the projects that 'install.php' generates, through the 'make' wrapper.
 *
 * Each project bundles extensions of the other type: 1 that ships with it and
 * 1 that only its tests use, laid out as the bundled extensions guide shows.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[Group('p4')]
final class GeneratedProjectTest extends ToolingTestCase {

  use ComposerHomeTrait;

  #[DataProvider('dataProviderGeneratedProject')]
  public function testGeneratedProject(string $type, string $bundled_dir, string $bundled_type, string $bundled, int $tests): void {
    $this->processRun(self::$sut . '/install.php', [], [], [
      'INSTALL_NAME' => 'Force Crystal',
      'INSTALL_MACHINE_NAME' => 'force_crystal',
      'INSTALL_TYPE' => $type,
      'INSTALL_DRUPAL_VERSION' => '11',
      'INSTALL_COMMAND_WRAPPER' => 'makefile',
      'INSTALL_TOOLS' => 'phpcs,phpstan,rector,twigcs,eslint,stylelint,cspell,jest,phpunit,functional_javascript,renovate',
      'INSTALL_EXAMPLES' => 'false',
      'INSTALL_REMOVE_SELF' => 'true',
      'INSTALL_PROCEED' => 'true',
    ], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertFileContainsString(self::$sut . '/force_crystal.info.yml', 'type: ' . $type);

    // The bundled extensions, a Kernel test that uses them and a provision
    // script that enables the one that ships.
    File::copy(dirname(__DIR__, 2) . '/fixtures/bundled/' . $type, self::$sut);

    $this->installTooling();

    $this->processRun('make', ['assemble'], [], [], $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('ASSEMBLE COMPLETE');

    $extension_dir = self::$sut . '/build/web/' . ($type === 'theme' ? 'themes' : 'modules') . '/custom/force_crystal';
    $other_custom_dir = self::$sut . '/build/web/' . ($type === 'theme' ? 'modules' : 'themes') . '/custom';
    $this->assertTrue(is_link($extension_dir . '/' . $bundled_dir), 'The directory of the bundled extensions is symlinked into the extension.');
    $this->assertTrue(is_link($extension_dir . '/tests'), 'The directory of the test-only extensions is symlinked into the extension.');
    $this->assertFileExists($extension_dir . '/' . $bundled_dir . '/' . $bundled . '/' . $bundled . '.info.yml');
    $this->assertTrue(File::dirIsEmpty($other_custom_dir), 'Nothing is mapped into the custom directory of the other type.');

    $this->processRun('make', ['start'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('ENVIRONMENT READY');

    $this->processRun('make', ['provision'], [], [], $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('PROVISION COMPLETE');

    $this->processRun('make', ['drush', '--', 'pml', '--type', $bundled_type, '--status', 'enabled', '--format', 'json'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('"' . $bundled . '"');

    $this->processRun('make', ['lint'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();

    $this->processRun('make', ['test'], [], [], $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('OK (' . $tests . ' tests,');
  }

  public static function dataProviderGeneratedProject(): \Iterator {
    // The FunctionalJavascript test and the Kernel test of the bundled modules.
    yield 'theme bundling modules' => ['theme', 'modules', 'module', 'force_crystal_companion', 2];
    // The 4 Unit, the Kernel, the Functional and the FunctionalJavascript
    // tests, and the Kernel test of the bundled themes.
    yield 'module bundling themes' => ['module', 'themes', 'theme', 'force_crystal_admin', 8];
  }

  /**
   * Install the in-tree tooling as the release the project requires.
   *
   * 'install.php' removes '.eddy', so the generated project installs the
   * tooling from a release, as every project created from the template does.
   */
  protected function installTooling(): void {
    $manifest = json_decode(File::read(self::$sut . '/composer.dev.json'), TRUE);
    $this->assertIsArray($manifest);
    $require_dev = $manifest['require-dev'] ?? NULL;
    $this->assertIsArray($require_dev);
    $constraint = $require_dev['drevops/eddy-tooling'] ?? NULL;
    $this->assertIsString($constraint);

    if (preg_match('/\d+(?:\.\d+)*/', $constraint, $matches) !== 1) {
      $this->fail(sprintf('The tooling constraint "%s" names no version.', $constraint));
    }

    $this->composerHomeWrite($matches[0], FALSE);

    $this->processRun('./scripts/eddy-tooling', [], [], ['COMPOSER_HOME' => self::composerHomeDir()], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('Installed drevops/eddy-tooling.');
  }

}
