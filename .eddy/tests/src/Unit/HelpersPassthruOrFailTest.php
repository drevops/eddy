<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\passthru_or_fail;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[CoversFunction('DrevOps\Eddy\Tooling\passthru_or_fail')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersPassthruOrFailTest extends UnitTestCase {

  public function testSuccessNoMessage(): void {
    $this->mockPassthru(['cmd' => 'echo hello', 'result_code' => 0]);
    passthru_or_fail('echo hello');
  }

  public function testSuccessWithMessage(): void {
    $this->mockPassthru(['cmd' => 'echo hello', 'result_code' => 0]);
    passthru_or_fail('echo hello', 'Should not fail.');
  }

  public function testFailureNoMessage(): void {
    $this->mockPassthru(['cmd' => 'false', 'result_code' => 42]);
    $this->mockQuit(42);

    $this->capture(fn() => passthru_or_fail('false'), 42);
  }

  public function testFailureWithMessage(): void {
    // A result code other than 1 proves the failure-message branch passes the
    // real exit code through, not a hardcoded 1.
    $this->mockPassthru(['cmd' => 'false', 'result_code' => 42]);
    $this->mockQuit(42);

    $output = $this->capture(fn() => passthru_or_fail('false', 'Command failed.'), 42);

    $this->assertStringContainsString('Command failed.', $output);
  }

  public function testFailureWithFormatArgs(): void {
    // A result code other than 1 proves the failure-message branch passes the
    // real exit code through, not a hardcoded 1.
    $this->mockPassthru(['cmd' => 'curl http://example.com', 'result_code' => 7]);
    $this->mockQuit(7);

    $output = $this->capture(fn() => passthru_or_fail('curl http://example.com', 'Failed to download from %s.', 'http://example.com'), 7);

    $this->assertStringContainsString('Failed to download from http://example.com.', $output);
  }

  #[DataProvider('dataProviderOutputVisibility')]
  public function testOutputVisibility(bool $debug, int $result_code, bool $expect_visible): void {
    if ($debug) {
      self::envSet('DEBUG', '1');
    }
    else {
      self::envUnset('DEBUG');
    }

    $this->mockPassthru(['cmd' => 'run-tool', 'output' => 'TOOL_NOISE', 'result_code' => $result_code]);

    if ($result_code !== 0) {
      $this->mockQuit($result_code);
    }

    $output = $this->capture(fn() => passthru_or_fail('run-tool'), $result_code === 0 ? NULL : $result_code);

    if ($expect_visible) {
      $this->assertStringContainsString('TOOL_NOISE', $output);
    }
    else {
      $this->assertStringNotContainsString('TOOL_NOISE', $output);
    }
  }

  public static function dataProviderOutputVisibility(): \Iterator {
    yield 'debug streams output live' => ['debug' => TRUE, 'result_code' => 0, 'expect_visible' => TRUE];
    yield 'quiet suppresses output on success' => ['debug' => FALSE, 'result_code' => 0, 'expect_visible' => FALSE];
    yield 'quiet shows captured output on failure' => ['debug' => FALSE, 'result_code' => 1, 'expect_visible' => TRUE];
  }

}
