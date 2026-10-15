<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\passthru_verbose_or_fail;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[CoversFunction('DrevOps\Eddy\Tooling\passthru_verbose_or_fail')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersPassthruVerboseOrFailTest extends UnitTestCase {

  public function testShowsOutputOnSuccess(): void {
    $this->mockPassthru(['cmd' => 'echo hello', 'output' => 'BUILD_OUTPUT', 'result_code' => 0]);
    $output = $this->capture(fn() => passthru_verbose_or_fail('echo hello'));
    $this->assertStringContainsString('BUILD_OUTPUT', $output);
  }

  public function testFailureNoMessage(): void {
    $this->mockPassthru(['cmd' => 'false', 'result_code' => 42]);
    $this->mockQuit(42);

    $this->capture(fn() => passthru_verbose_or_fail('false'), 42);
  }

  public function testFailureWithMessage(): void {
    $this->mockPassthru(['cmd' => 'false', 'result_code' => 42]);
    $this->mockQuit(42);

    $output = $this->capture(fn() => passthru_verbose_or_fail('false', 'Command failed.'), 42);

    $this->assertStringContainsString('Command failed.', $output);
  }

  public function testFailureWithFormatArgs(): void {
    $this->mockPassthru(['cmd' => 'curl http://example.com', 'result_code' => 7]);
    $this->mockQuit(7);

    $output = $this->capture(fn() => passthru_verbose_or_fail('curl http://example.com', 'Failed to download from %s.', 'http://example.com'), 7);

    $this->assertStringContainsString('Failed to download from http://example.com.', $output);
  }

}
