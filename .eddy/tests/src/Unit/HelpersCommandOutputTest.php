<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\command_output;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the command_output() helper.
 *
 * The commands run as real processes, so the pipe and process handling is
 * exercised the same way the tooling uses it.
 */
#[CoversFunction('DrevOps\Eddy\Tooling\command_output')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersCommandOutputTest extends UnitTestCase {

  /**
   * @param list<string> $command
   *   The executable followed by its arguments.
   */
  #[DataProvider('dataProviderCommandOutput')]
  public function testCommandOutput(array $command, string $expected): void {
    $start = microtime(TRUE);
    $output = command_output($command, 10);
    $elapsed = microtime(TRUE) - $start;

    $this->assertSame($expected, $output);
    $this->assertLessThan(5, $elapsed, 'A command that exits must be returned from without waiting out the time limit.');
  }

  public static function dataProviderCommandOutput(): \Iterator {
    yield 'output' => [
      'command' => ['printf', '%s', 'Google Chrome 150.0.7871.129'],
      'expected' => 'Google Chrome 150.0.7871.129',
    ];
    yield 'standard error discarded' => [
      'command' => [PHP_BINARY, '-r', 'fwrite(STDERR, "error"); echo "output";'],
      'expected' => 'output',
    ];
    yield 'standard input closed' => [
      'command' => ['cat'],
      'expected' => '',
    ];
    yield 'non-zero exit' => [
      'command' => [PHP_BINARY, '-r', 'echo "output"; exit(3);'],
      'expected' => 'output',
    ];
    yield 'output larger than a pipe buffer' => [
      'command' => [PHP_BINARY, '-r', 'echo str_repeat("x", 200000);'],
      'expected' => str_repeat('x', 200000),
    ];
    yield 'missing executable' => [
      'command' => ['/nonexistent/eddy-command-output'],
      'expected' => '',
    ];
  }

  /**
   * @param list<string> $command
   *   The executable followed by its arguments.
   */
  #[DataProvider('dataProviderCommandOutputTimesOut')]
  public function testCommandOutputTimesOut(array $command): void {
    // Sleep 10 ms per poll instead of 100 ms, so the command can set up,
    // such as its SIGTERM trap, before it is killed.
    $sleeps = [];
    $this->mockFunction('usleep', function (int $microseconds) use (&$sleeps): void {
      $sleeps[] = $microseconds;
      \usleep(10000);
    });

    $start = microtime(TRUE);
    $output = command_output($command, 2);
    $elapsed = microtime(TRUE) - $start;

    $this->assertNull($output);
    $this->assertSame(array_fill(0, 20, 100000), $sleeps, 'A 2-second time limit must be polled 10 times a second.');
    // proc_close() waits for the command to exit, so a command left running
    // would take 30 seconds.
    $this->assertLessThan(10, $elapsed, 'The command must be killed rather than waited for.');
  }

  public static function dataProviderCommandOutputTimesOut(): \Iterator {
    yield 'never exits' => [
      'command' => ['sleep', '30'],
    ];
    yield 'ignores SIGTERM' => [
      'command' => ['sh', '-c', 'trap "" TERM; exec sleep 30'],
    ];
    yield 'writes output, then never exits' => [
      'command' => [PHP_BINARY, '-r', 'echo "output"; sleep(30);'],
    ];
  }

}
