<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\passthru_verbose_or_fail;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
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

  /**
   * @param array<int, string> $args
   *   The values for the placeholders in the format.
   */
  #[DataProvider('dataProviderFailureWithMessage')]
  public function testFailureWithMessage(string $command, int $result_code, string $format, array $args, string $expected_message): void {
    $this->mockPassthru(['cmd' => $command, 'result_code' => $result_code]);
    $this->mockQuit($result_code);

    $output = $this->capture(fn() => passthru_verbose_or_fail($command, $format, ...$args), $result_code);

    $this->assertStringContainsString($expected_message, $output);
  }

  public static function dataProviderFailureWithMessage(): \Iterator {
    yield 'message' => ['false', 42, 'Command failed.', [], 'Command failed.'];
    yield 'format arguments' => [
      'curl http://example.com',
      7,
      'Failed to download from %s.',
      ['http://example.com'],
      'Failed to download from http://example.com.',
    ];
  }

}
