<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\DevTools\passthru_capture;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[CoversFunction('DrevOps\Eddy\DevTools\passthru_capture')]
#[Group('p0')]
final class HelpersPassthruCaptureTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';
  }

  #[DataProvider('dataProviderWrapsCommand')]
  public function testWrapsCommand(string $cmd, string $expected_wrapped): void {
    $captured_cmd = NULL;
    $this->mockRawPassthru($captured_cmd, 'output', 0);

    passthru_capture($cmd);

    $this->assertSame($expected_wrapped, $captured_cmd);
  }

  public static function dataProviderWrapsCommand(): \Iterator {
    yield 'simple command' => [
      'cmd' => 'composer install',
      'expected_wrapped' => '{ composer install; } 2>&1',
    ];
    yield 'compound command' => [
      'cmd' => 'nvm use && npm ci && npm run build',
      'expected_wrapped' => '{ nvm use && npm ci && npm run build; } 2>&1',
    ];
  }

  public function testReturnsCapturedOutputAndExitCode(): void {
    $captured_cmd = NULL;
    $this->mockRawPassthru($captured_cmd, 'combined stdout and stderr', 3);

    $exit_code = 0;
    $result = passthru_capture('some-command', $exit_code);

    $this->assertSame('combined stdout and stderr', $result);
    $this->assertSame(3, $exit_code);
  }

  public function testReturnsEmptyStringWhenNoOutput(): void {
    $captured_cmd = NULL;
    $this->mockRawPassthru($captured_cmd, '', 0);

    $exit_code = 99;
    $result = passthru_capture('quiet-command', $exit_code);

    $this->assertSame('', $result);
    $this->assertSame(0, $exit_code);
  }

  /**
   * Register a low-level passthru() mock that records the raw command.
   *
   * @param string|null &$captured_cmd
   *   Populated with the exact command string passed to passthru().
   * @param string $output
   *   Output the mocked command emits.
   * @param int $result_code
   *   Exit code the mocked command reports.
   */
  protected function mockRawPassthru(?string &$captured_cmd, string $output, int $result_code): void {
    $this->registerMock('passthru', 'DrevOps\\Eddy\\DevTools', function ($cmd, &...$args) use (&$captured_cmd, $output, $result_code): null {
      $captured_cmd = $cmd;
      echo $output;
      if (count($args) > 0) {
        $args[0] = $result_code;
      }

      return NULL;
    });
  }

}
