<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\DevTools\passthru_or_fail;
use DrevOps\Eddy\Tests\Exceptions\QuitErrorException;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[CoversFunction('DrevOps\Eddy\DevTools\passthru_or_fail')]
#[Group('p0')]
final class HelpersPassthruOrFailTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';
  }

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
    try {
      passthru_or_fail('false');
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(42, $e->getCode());
    }
  }

  public function testFailureWithMessage(): void {
    // A result code other than 1 proves the failure-message branch passes the
    // real exit code through, not a hardcoded 1.
    $this->mockPassthru(['cmd' => 'false', 'result_code' => 42]);
    $this->mockQuit(42);
    ob_start();
    try {
      passthru_or_fail('false', 'Command failed.');
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(42, $e->getCode());
    }
    finally {
      $output = ob_get_clean();
      $this->assertIsString($output);
      $this->assertStringContainsString('Command failed.', $output);
    }
  }

  public function testFailureWithFormatArgs(): void {
    // A result code other than 1 proves the failure-message branch passes the
    // real exit code through, not a hardcoded 1.
    $this->mockPassthru(['cmd' => 'curl http://example.com', 'result_code' => 7]);
    $this->mockQuit(7);
    ob_start();
    try {
      passthru_or_fail('curl http://example.com', 'Failed to download from %s.', 'http://example.com');
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(7, $e->getCode());
    }
    finally {
      $output = ob_get_clean();
      $this->assertIsString($output);
      $this->assertStringContainsString('Failed to download from http://example.com.', $output);
    }
  }

  #[DataProvider('dataProviderOutputVisibility')]
  public function testOutputVisibility(bool $debug, int $result_code, bool $expect_visible): void {
    if ($debug) {
      $this->envSet('DEBUG', '1');
    }
    else {
      $this->envUnset('DEBUG');
    }

    $this->mockPassthru(['cmd' => 'run-tool', 'output' => 'TOOL_NOISE', 'result_code' => $result_code]);

    if ($result_code !== 0) {
      $this->mockQuit($result_code);
    }

    $quit_thrown = FALSE;
    ob_start();
    try {
      passthru_or_fail('run-tool');
    }
    catch (QuitErrorException) {
      $quit_thrown = TRUE;
    }
    finally {
      $output = ob_get_clean();
      $this->assertIsString($output);
    }

    $this->assertSame($result_code !== 0, $quit_thrown);

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
