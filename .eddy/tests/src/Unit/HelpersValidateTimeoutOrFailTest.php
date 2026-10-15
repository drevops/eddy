<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\validate_timeout_or_fail;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for validate_timeout_or_fail() helper.
 */
#[CoversFunction('DrevOps\Eddy\Tooling\validate_timeout_or_fail')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersValidateTimeoutOrFailTest extends UnitTestCase {

  #[DataProvider('dataProviderValidTimeout')]
  public function testValidTimeout(string $value, int $expected): void {
    $this->assertSame($expected, validate_timeout_or_fail($value, 'WEBSERVER_WAIT_TIMEOUT'));
  }

  public static function dataProviderValidTimeout(): \Iterator {
    yield 'lowest valid' => ['value' => '1', 'expected' => 1];
    yield 'default' => ['value' => '5', 'expected' => 5];
    yield 'several minutes' => ['value' => '300', 'expected' => 300];
  }

  #[DataProvider('dataProviderInvalidTimeoutFails')]
  public function testInvalidTimeoutFails(string $value): void {
    $this->mockQuit(1);

    $output = $this->capture(fn(): int => validate_timeout_or_fail($value, 'WEBSERVER_WAIT_TIMEOUT'), 1);

    $this->assertStringContainsString(sprintf('Invalid WEBSERVER_WAIT_TIMEOUT "%s". Expected a whole number of seconds, 1 or more.', $value), $output);
  }

  public static function dataProviderInvalidTimeoutFails(): \Iterator {
    yield 'empty string' => ['value' => ''];
    yield 'zero' => ['value' => '0'];
    yield 'negative' => ['value' => '-1'];
    yield 'non-numeric' => ['value' => 'abc'];
    yield 'decimal' => ['value' => '1.5'];
    yield 'with whitespace' => ['value' => '5 '];
    yield 'with a unit' => ['value' => '5s'];
  }

  public function testUsesProvidedSourceNameInErrorMessage(): void {
    $this->mockQuit(1);

    $output = $this->capture(fn(): int => validate_timeout_or_fail('bogus', 'CUSTOM_TIMEOUT_VAR'), 1);

    $this->assertStringContainsString('Invalid CUSTOM_TIMEOUT_VAR "bogus"', $output);
  }

}
