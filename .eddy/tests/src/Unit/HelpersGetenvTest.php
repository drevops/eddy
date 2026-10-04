<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\DevTools\getenv_default;
use function DrevOps\Eddy\DevTools\getenv_required;
use DrevOps\Eddy\Tests\Exceptions\QuitErrorException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersGetenvTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';
  }

  #[DataProvider('dataProviderGetenvDefault')]
  public function testGetenvDefault(array $env_vars, array $args, string $expected): void {
    foreach ($env_vars as $name => $value) {
      $this->envSet($name, $value);
    }
    $result = getenv_default(...$args);
    $this->assertSame($expected, $result);
  }

  public static function dataProviderGetenvDefault(): \Iterator {
    yield 'first var set' => [
      'env_vars' => ['FIRST_VAR' => 'first_value'],
      'args' => ['FIRST_VAR', 'SECOND_VAR', 'default'],
      'expected' => 'first_value',
    ];
    yield 'second var set' => [
      'env_vars' => ['SECOND_VAR' => 'second_value'],
      'args' => ['FIRST_VAR', 'SECOND_VAR', 'default'],
      'expected' => 'second_value',
    ];
    yield 'both vars set returns first' => [
      'env_vars' => ['FIRST_VAR' => 'first_value', 'SECOND_VAR' => 'second_value'],
      'args' => ['FIRST_VAR', 'SECOND_VAR', 'default'],
      'expected' => 'first_value',
    ];
    yield 'no vars set returns default' => [
      'env_vars' => [],
      'args' => ['UNSET_VAR_1', 'UNSET_VAR_2', 'default_value'],
      'expected' => 'default_value',
    ];
    yield 'single var set' => [
      'env_vars' => ['SINGLE_VAR' => 'single_value'],
      'args' => ['SINGLE_VAR', 'default'],
      'expected' => 'single_value',
    ];
    yield 'single var not set returns default' => [
      'env_vars' => [],
      'args' => ['UNSET_VAR', 'default'],
      'expected' => 'default',
    ];
    yield 'empty string skipped for non-empty' => [
      'env_vars' => ['EMPTY_VAR' => '', 'NON_EMPTY_VAR' => 'value'],
      'args' => ['EMPTY_VAR', 'NON_EMPTY_VAR', 'default'],
      'expected' => 'value',
    ];
    yield 'all empty strings returns default' => [
      'env_vars' => ['EMPTY_VAR_1' => '', 'EMPTY_VAR_2' => ''],
      'args' => ['EMPTY_VAR_1', 'EMPTY_VAR_2', 'default'],
      'expected' => 'default',
    ];
    yield 'var set to false string' => [
      'env_vars' => ['FALSE_VAR' => 'false'],
      'args' => ['FALSE_VAR', 'default'],
      'expected' => 'false',
    ];
    yield 'var set to true string' => [
      'env_vars' => ['TRUE_VAR' => 'true'],
      'args' => ['TRUE_VAR', 'default'],
      'expected' => 'true',
    ];
    yield 'no vars set returns null default as empty string' => [
      'env_vars' => [],
      'args' => ['UNSET_VAR', NULL],
      'expected' => '',
    ];
    yield 'no vars set returns false default as empty string' => [
      'env_vars' => [],
      'args' => ['UNSET_VAR', FALSE],
      'expected' => '',
    ];
    yield 'var set overrides null default' => [
      'env_vars' => ['SET_VAR' => 'value'],
      'args' => ['SET_VAR', NULL],
      'expected' => 'value',
    ];
    yield 'var set overrides false default' => [
      'env_vars' => ['SET_VAR' => 'value'],
      'args' => ['SET_VAR', FALSE],
      'expected' => 'value',
    ];
  }

  public function testGetenvDefaultInvalidArgumentsThrowsException(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('getenv_default() requires at least 2 arguments');
    getenv_default('SINGLE_ARG');
  }

  #[DataProvider('dataProviderGetenvRequired')]
  public function testGetenvRequired(array $env_vars, array $args, string $expected): void {
    foreach ($env_vars as $name => $value) {
      $this->envSet($name, $value);
    }
    $result = getenv_required(...$args);
    $this->assertSame($expected, $result);
  }

  public static function dataProviderGetenvRequired(): \Iterator {
    yield 'first var set' => [
      'env_vars' => ['FIRST_VAR' => 'first_value'],
      'args' => ['FIRST_VAR', 'SECOND_VAR'],
      'expected' => 'first_value',
    ];
    yield 'second var set' => [
      'env_vars' => ['SECOND_VAR' => 'second_value'],
      'args' => ['FIRST_VAR', 'SECOND_VAR'],
      'expected' => 'second_value',
    ];
    yield 'both vars set returns first' => [
      'env_vars' => ['FIRST_VAR' => 'first_value', 'SECOND_VAR' => 'second_value'],
      'args' => ['FIRST_VAR', 'SECOND_VAR'],
      'expected' => 'first_value',
    ];
    yield 'single var set' => [
      'env_vars' => ['REQUIRED_VAR' => 'required_value'],
      'args' => ['REQUIRED_VAR'],
      'expected' => 'required_value',
    ];
    yield 'empty string skipped for non-empty' => [
      'env_vars' => ['EMPTY_VAR' => '', 'NON_EMPTY_VAR' => 'value'],
      'args' => ['EMPTY_VAR', 'NON_EMPTY_VAR'],
      'expected' => 'value',
    ];
    yield 'var set to false string' => [
      'env_vars' => ['FALSE_VAR' => 'false'],
      'args' => ['FALSE_VAR'],
      'expected' => 'false',
    ];
    yield 'var set to true string' => [
      'env_vars' => ['TRUE_VAR' => 'true'],
      'args' => ['TRUE_VAR'],
      'expected' => 'true',
    ];
  }

  #[DataProvider('dataProviderGetenvRequiredFails')]
  public function testGetenvRequiredFails(array $env_vars, array $args, string $expected_message): void {
    foreach ($env_vars as $name => $value) {
      $this->envSet($name, $value);
    }
    $this->mockQuit(1);
    ob_start();
    try {
      getenv_required(...$args);
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(1, $e->getCode());
    }
    finally {
      $output = ob_get_clean();
      $this->assertIsString($output);
      $this->assertStringContainsString($expected_message, $output);
    }
  }

  public static function dataProviderGetenvRequiredFails(): \Iterator {
    yield 'no vars set' => [
      'env_vars' => [],
      'args' => ['UNSET_VAR_1', 'UNSET_VAR_2'],
      'expected_message' => 'Missing required value for UNSET_VAR_1, UNSET_VAR_2',
    ];
    yield 'all empty strings' => [
      'env_vars' => ['EMPTY_VAR_1' => '', 'EMPTY_VAR_2' => ''],
      'args' => ['EMPTY_VAR_1', 'EMPTY_VAR_2'],
      'expected_message' => 'Missing required value for EMPTY_VAR_1, EMPTY_VAR_2',
    ];
    yield 'single var not set' => [
      'env_vars' => [],
      'args' => ['UNSET_VAR'],
      'expected_message' => 'Missing required value for UNSET_VAR',
    ];
  }

  public function testGetenvRequiredInvalidArgumentsThrowsException(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('getenv_required() requires at least 1 argument');
    getenv_required();
  }

}
