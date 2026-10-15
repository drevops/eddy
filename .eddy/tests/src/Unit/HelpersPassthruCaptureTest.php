<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\passthru_capture;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[CoversFunction('DrevOps\Eddy\Tooling\passthru_capture')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersPassthruCaptureTest extends UnitTestCase {

  #[DataProvider('dataProviderWrapsCommand')]
  public function testWrapsCommand(string $command, string $expected_wrapped): void {
    $captured_command = NULL;
    $this->mockRawPassthru($captured_command, 'output', 0);

    passthru_capture($command);

    $this->assertSame($expected_wrapped, $captured_command);
  }

  public static function dataProviderWrapsCommand(): \Iterator {
    yield 'simple command' => [
      'command' => 'composer install',
      'expected_wrapped' => '{ composer install; } 2>&1',
    ];
    yield 'compound command' => [
      'command' => '. "$NVM_DIR/nvm.sh"; { nvm use || nvm install; } && npm ci',
      'expected_wrapped' => '{ . "$NVM_DIR/nvm.sh"; { nvm use || nvm install; } && npm ci; } 2>&1',
    ];
  }

  public function testReturnsCapturedOutputAndExitCode(): void {
    $captured_command = NULL;
    $this->mockRawPassthru($captured_command, 'combined stdout and stderr', 3);

    $exit_code = 0;
    $result = passthru_capture('some-command', $exit_code);

    $this->assertSame('combined stdout and stderr', $result);
    $this->assertSame(3, $exit_code);
  }

  public function testReturnsEmptyStringWhenNoOutput(): void {
    $captured_command = NULL;
    $this->mockRawPassthru($captured_command, '', 0);

    $exit_code = 99;
    $result = passthru_capture('quiet-command', $exit_code);

    $this->assertSame('', $result);
    $this->assertSame(0, $exit_code);
  }

  /**
   * Register a low-level passthru() mock that records the raw command.
   *
   * @param string|null &$captured_command
   *   Populated with the exact command string passed to passthru().
   * @param string $output
   *   Output the mocked command emits.
   * @param int $result_code
   *   Exit code the mocked command reports.
   */
  protected function mockRawPassthru(?string &$captured_command, string $output, int $result_code): void {
    $this->mockFunction('passthru', function ($command, &...$args) use (&$captured_command, $output, $result_code): null {
      $captured_command = $command;
      echo $output;
      if (count($args) > 0) {
        $args[0] = $result_code;
      }

      return NULL;
    });
  }

}
