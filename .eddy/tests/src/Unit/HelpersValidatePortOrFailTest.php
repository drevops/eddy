<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\validate_port_or_fail;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[CoversFunction('DrevOps\Eddy\Tooling\is_positive_integer')]
#[CoversFunction('DrevOps\Eddy\Tooling\validate_port_or_fail')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersValidatePortOrFailTest extends UnitTestCase {

  #[DataProvider('dataProviderValidPortDoesNotFail')]
  public function testValidPortDoesNotFail(string $value): void {
    validate_port_or_fail($value, 'WEBSERVER_PORT');
    $this->expectNotToPerformAssertions();
  }

  public static function dataProviderValidPortDoesNotFail(): \Iterator {
    yield 'lowest valid' => ['value' => '1'];
    yield 'common default' => ['value' => '8000'];
    yield 'highest valid' => ['value' => '65535'];
    yield 'mid range' => ['value' => '12345'];
  }

  #[DataProvider('dataProviderInvalidPortFails')]
  public function testInvalidPortFails(string $value): void {
    $this->mockQuit(1);

    $output = $this->capture(fn() => validate_port_or_fail($value, 'WEBSERVER_PORT'), 1);

    $this->assertStringContainsString('Invalid WEBSERVER_PORT', $output);
    $this->assertStringContainsString('Expected integer in range 1-65535', $output);
  }

  public static function dataProviderInvalidPortFails(): \Iterator {
    yield 'empty string' => ['value' => ''];
    yield 'zero' => ['value' => '0'];
    yield 'too high' => ['value' => '65536'];
    yield 'negative' => ['value' => '-1'];
    yield 'non-numeric' => ['value' => 'abc'];
    yield 'mixed alphanumeric' => ['value' => '80a0'];
    yield 'with shell metacharacter' => ['value' => '8000; rm -rf /'];
    yield 'with whitespace' => ['value' => '8000 '];
    yield 'decimal' => ['value' => '80.0'];
  }

  public function testUsesProvidedSourceNameInErrorMessage(): void {
    $this->mockQuit(1);

    $output = $this->capture(fn() => validate_port_or_fail('bogus', 'CUSTOM_PORT_VAR'), 1);

    $this->assertStringContainsString('Invalid CUSTOM_PORT_VAR', $output);
  }

}
