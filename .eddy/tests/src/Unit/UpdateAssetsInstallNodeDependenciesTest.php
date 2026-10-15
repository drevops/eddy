<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

use function DrevOps\Eddy\Assets\install_node_dependencies;

#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class UpdateAssetsInstallNodeDependenciesTest extends UnitTestCase {

  protected const string NAMESPACE = 'DrevOps\\Eddy\\Assets';

  protected function setUp(): void {
    parent::setUp();
    self::envSet('SCRIPT_RUN_SKIP', '1');
    require_once self::rootDir() . '/.eddy/assets/update-assets.php';
  }

  public function testInstallNodeDependenciesSkipsInstalledPackage(): void {
    File::mkdir(self::$sut . '/node_modules/svg-term', 0755);
    $this->getFunctionMock(self::NAMESPACE, 'shell_exec')->expects($this->never());

    $output = $this->capture(fn() => install_node_dependencies(self::$sut));

    $this->assertStringContainsString('svg-term already installed.', $output);
  }

  public function testInstallNodeDependencies(): void {
    $this->mockNpmInstall('added 1 package', TRUE);

    $output = $this->capture(fn() => install_node_dependencies(self::$sut));

    $this->assertStringContainsString('svg-term installed.', $output);
  }

  #[DataProvider('dataProviderInstallNodeDependenciesFails')]
  public function testInstallNodeDependenciesFails(?string $npm_output, string $expected_message): void {
    $this->mockNpmInstall($npm_output, FALSE);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected_message);

    $this->capture(fn() => install_node_dependencies(self::$sut));
  }

  public static function dataProviderInstallNodeDependenciesFails(): \Iterator {
    yield 'npm output' => ['npm ERR! 404', 'Failed to install svg-term: npm ERR! 404'];
    yield 'no output' => [NULL, 'Failed to install svg-term: unknown error'];
  }

  protected function mockNpmInstall(?string $output, bool $installs): void {
    $shell_exec = $this->getFunctionMock(self::NAMESPACE, 'shell_exec');
    $shell_exec->expects($this->once())->with(sprintf('npm install --prefix %s svg-term@1.3.1 2>&1', escapeshellarg(self::$sut)))->willReturnCallback(static function () use ($output, $installs): ?string {
      if ($installs) {
        File::mkdir(self::$sut . '/node_modules/svg-term', 0755);
      }

      return $output;
    });
  }

}
