<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

use function DrevOps\Eddy\Assets\check_dependencies;

#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class UpdateAssetsCheckDependenciesTest extends UnitTestCase {

  protected const string NAMESPACE = 'DrevOps\\Eddy\\Assets';

  protected function setUp(): void {
    parent::setUp();
    self::envSet('SCRIPT_RUN_SKIP', '1');
    require_once self::rootDir() . '/.eddy/assets/update-assets.php';
  }

  public function testCheckDependencies(): void {
    $this->mockWhich([]);

    $output = $this->capture(fn() => check_dependencies());

    $this->assertSame('All dependencies found.' . PHP_EOL, $output);
  }

  /**
   * @param list<string> $missing
   *   The dependencies that 'which' does not find.
   */
  #[DataProvider('dataProviderCheckDependenciesFails')]
  public function testCheckDependenciesFails(array $missing, string $expected_message): void {
    $this->mockWhich($missing);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected_message);

    check_dependencies();
  }

  public static function dataProviderCheckDependenciesFails(): \Iterator {
    yield 'some missing' => [['expect', 'npm'], 'Missing required dependencies: expect, npm'];
    yield 'all missing' => [['asciinema', 'expect', 'node', 'npm'], 'Missing required dependencies: asciinema, expect, node, npm'];
  }

  /**
   * @param list<string> $missing
   *   The dependencies that 'which' does not find.
   */
  protected function mockWhich(array $missing): void {
    $shell_exec = $this->getFunctionMock(self::NAMESPACE, 'shell_exec');
    $shell_exec->expects($this->exactly(4))->willReturnCallback(static function (string $command) use ($missing): ?string {
      $dependency = (string) preg_replace("/^which '([^']+)' 2>\\/dev\\/null$/", '$1', $command);

      return in_array($dependency, $missing, TRUE) ? NULL : '/usr/bin/' . $dependency . PHP_EOL;
    });
  }

}
