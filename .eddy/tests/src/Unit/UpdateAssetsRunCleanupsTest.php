<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

use function DrevOps\Eddy\Assets\run_cleanups;

/**
 * Tests running cleanup commands with the asset generator.
 *
 * A mock of 'exec()' takes effect only before the generator first calls it,
 * so every test runs in its own process.
 */
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class UpdateAssetsRunCleanupsTest extends UnitTestCase {

  /**
   * Commands passed to the mocked 'exec()', in call order.
   *
   * @var list<string>
   */
  protected array $commands = [];

  protected function setUp(): void {
    parent::setUp();
    putenv('SCRIPT_RUN_SKIP=1');
    require_once dirname(__DIR__, 4) . '/.eddy/assets/update-assets.php';
  }

  /**
   * @param list<string> $cleanups
   *   Cleanup commands in the order they were registered.
   * @param array<string, array{0: int, 1: list<string>}> $results
   *   Exit code and output lines of each command, keyed by command.
   * @param list<string> $expected_commands
   *   Commands expected to run, in order.
   * @param list<string> $expected_failures
   *   Failure messages expected back.
   */
  #[DataProvider('dataProviderRunCleanups')]
  public function testRunCleanups(array $cleanups, array $results, array $expected_commands, array $expected_failures): void {
    $exec = $this->getFunctionMock('DrevOps\\Eddy\\Assets', 'exec');
    $exec->expects($this->exactly(count($expected_commands)))->willReturnCallback(function (string $cmd, ?array &$output = NULL, ?int &$exit_code = NULL) use ($results): string {
      $this->commands[] = $cmd;
      $command = (string) preg_replace("/^cd '[^']*' && (.*) 2>&1$/", '$1', $cmd);
      [$exit_code, $output] = $results[$command] ?? [0, []];

      return (string) end($output);
    });

    $failures = run_cleanups('/work space', $cleanups);

    $this->assertSame(array_map(static fn(string $command): string => "cd '/work space' && " . $command . ' 2>&1', $expected_commands), $this->commands);
    $this->assertSame($expected_failures, $failures);
  }

  public static function dataProviderRunCleanups(): \Iterator {
    yield 'none' => [[], [], [], []];

    yield 'last registered runs first' => [
      ['ahoy stop', 'ahoy browser-stop'],
      [],
      ['ahoy browser-stop', 'ahoy stop'],
      [],
    ];

    yield 'a failure does not stop the next command' => [
      ['ahoy stop', 'ahoy browser-stop'],
      ['ahoy browser-stop' => [1, ['Browser is not running.', 'Exiting.']]],
      ['ahoy browser-stop', 'ahoy stop'],
      ["Cleanup 'ahoy browser-stop' failed with exit code 1." . PHP_EOL . 'Browser is not running.' . PHP_EOL . 'Exiting.'],
    ];

    yield 'every failure is reported' => [
      ['ahoy stop', 'ahoy browser-stop'],
      ['ahoy stop' => [2, []], 'ahoy browser-stop' => [127, ['ahoy: command not found']]],
      ['ahoy browser-stop', 'ahoy stop'],
      [
        "Cleanup 'ahoy browser-stop' failed with exit code 127." . PHP_EOL . 'ahoy: command not found',
        "Cleanup 'ahoy stop' failed with exit code 2.",
      ],
    ];
  }

}
