<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

use function DrevOps\Eddy\Assets\record_session;

/**
 * Tests recording a session with the asset generator.
 *
 * A mock of 'exec()' takes effect only before the generator first calls it,
 * so every test runs in its own process.
 */
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class UpdateAssetsRecordSessionTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    putenv('SCRIPT_RUN_SKIP=1');
    require_once dirname(__DIR__, 4) . '/.eddy/assets/update-assets.php';
  }

  public function testRecordSession(): void {
    $expect_script = self::$sut . '/lint.exp';
    $cast_file = self::$sut . '/lint.cast';
    touch($expect_script);

    $this->mockAsciinema($cast_file, '{"version":3,"term":{"cols":80,"rows":24}}' . "\n", 0, sprintf('asciinema rec --headless --quiet --return --command=%s --window-size=80x24 --overwrite %s 2>&1', escapeshellarg($expect_script), escapeshellarg($cast_file)));

    record_session($expect_script, $cast_file);

    $this->assertFileExists($cast_file);
  }

  public function testRecordSessionFailsWithSessionTail(): void {
    $expect_script = self::$sut . '/lint.exp';
    $cast_file = self::$sut . '/lint.cast';
    touch($expect_script);

    $cast = implode("\n", [
      '{"version":3,"term":{"cols":80,"rows":24}}',
      '[0.5,"o","\u001b[?2004h$ ahoy lint\r\r\n"]',
      '[0.1,"o","\u001b[31mpushd: build: No such file or directory\u001b[0m\r\r\n"]',
      '[0.1,"o","Timed out waiting for the session.\r\r\n"]',
      '[0.1,"x","1"]',
    ]);
    $this->mockAsciinema($cast_file, $cast, 1, NULL, ['asciinema: session ended']);

    try {
      record_session($expect_script, $cast_file);
      $this->fail('Expected the recording to fail.');
    }
    catch (\RuntimeException $exception) {
      $this->assertSame(implode(PHP_EOL, [
        'The session exited with code 1. Its last output:',
        '$ ahoy lint',
        'pushd: build: No such file or directory',
        'Timed out waiting for the session.',
        'asciinema: session ended',
      ]), $exception->getMessage());
    }
  }

  public function testRecordSessionFailsWithoutAsciinemaOutput(): void {
    $expect_script = self::$sut . '/init.exp';
    $cast_file = self::$sut . '/init.cast';
    touch($expect_script);

    $this->mockAsciinema($cast_file, '{"version":3,"term":{"cols":80,"rows":24}}' . "\n" . '[0.5,"o","The session ended unexpectedly.\r\n"]' . "\n", 1);

    try {
      record_session($expect_script, $cast_file);
      $this->fail('Expected the recording to fail.');
    }
    catch (\RuntimeException $exception) {
      $this->assertSame('The session exited with code 1. Its last output:' . PHP_EOL . 'The session ended unexpectedly.', $exception->getMessage());
    }
  }

  public function testRecordSessionFailsWithoutRecording(): void {
    $expect_script = self::$sut . '/lint.exp';
    $cast_file = self::$sut . '/lint.cast';
    touch($expect_script);

    $this->mockAsciinema($cast_file, NULL, 1, NULL, ['asciinema: no such file']);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage(sprintf('No recording was written to %s.', $cast_file) . PHP_EOL . 'asciinema: no such file');

    record_session($expect_script, $cast_file);
  }

  public function testRecordSessionFailsWithoutExpectScript(): void {
    $expect_script = self::$sut . '/missing.exp';

    $this->getFunctionMock('DrevOps\\Eddy\\Assets', 'exec')->expects($this->never());

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Missing expect script: ' . $expect_script);

    record_session($expect_script, self::$sut . '/missing.cast');
  }

  /**
   * Mock the 'asciinema rec' call.
   *
   * @param string $cast_file
   *   Path the recording is written to.
   * @param string|null $cast
   *   Recording to write, or NULL to write none.
   * @param int $exit_code
   *   Exit code asciinema returns.
   * @param string|null $command
   *   Command line expected, or NULL to accept any.
   * @param list<string> $output
   *   Lines asciinema prints.
   */
  protected function mockAsciinema(string $cast_file, ?string $cast, int $exit_code, ?string $command = NULL, array $output = []): void {
    $exec = $this->getFunctionMock('DrevOps\\Eddy\\Assets', 'exec');
    $exec->expects($this->once())->willReturnCallback(function (string $cmd, ?array &$cmd_output = NULL, ?int &$cmd_exit_code = NULL) use ($cast_file, $cast, $exit_code, $command, $output): string {
      if ($command !== NULL) {
        $this->assertSame($command, $cmd);
      }

      if ($cast !== NULL) {
        file_put_contents($cast_file, $cast);
      }

      $cmd_output = $output;
      $cmd_exit_code = $exit_code;

      return (string) end($output);
    });
  }

}
