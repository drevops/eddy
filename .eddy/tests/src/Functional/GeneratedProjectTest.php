<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Functional;

use DrevOps\Eddy\Tests\Traits\ComposerHomeTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests a theme that 'install.php' generates, through the 'make' wrapper.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[Group('p4')]
final class ThemeTest extends ToolingTestCase {

  use ComposerHomeTrait;

  public function testTheme(): void {
    $this->processRun(self::$sut . '/install.php', [], [], [
      'INSTALL_NAME' => 'Force Crystal',
      'INSTALL_MACHINE_NAME' => 'force_crystal',
      'INSTALL_TYPE' => 'theme',
      'INSTALL_DRUPAL_VERSION' => '11',
      'INSTALL_COMMAND_WRAPPER' => 'makefile',
      'INSTALL_TOOLS' => 'phpcs,phpstan,rector,twigcs,eslint,stylelint,cspell,jest,phpunit,functional_javascript,renovate',
      'INSTALL_EXAMPLES' => 'false',
      'INSTALL_REMOVE_SELF' => 'true',
      'INSTALL_PROCEED' => 'true',
    ], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertFileContainsString(self::$sut . '/force_crystal.info.yml', 'type: theme');
    $this->assertFileExists(self::$sut . '/tests/src/FunctionalJavascript/ForceCrystalSmokeFunctionalJavascriptTest.php');
    $this->assertDirectoryDoesNotExist(self::$sut . '/src');

    $this->installTooling();

    $this->processRun('make', ['assemble'], [], [], $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('ASSEMBLE COMPLETE');
    $this->assertDirectoryExists(self::$sut . '/build/web/themes/custom/force_crystal');

    $this->processRun('make', ['start'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('ENVIRONMENT READY');

    $this->processRun('make', ['provision'], [], [], $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('PROVISION COMPLETE');

    $this->processRun('make', ['lint'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();

    $this->processRun('make', ['test'], [], [], $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('OK (1 test,');
  }

  /**
   * Install the in-tree tooling as the release the theme requires.
   *
   * 'install.php' removes '.eddy', so the generated project installs the
   * tooling from a release, as every project created from the template does.
   */
  protected function installTooling(): void {
    $manifest = json_decode((string) file_get_contents(self::$sut . '/composer.dev.json'), TRUE);
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
