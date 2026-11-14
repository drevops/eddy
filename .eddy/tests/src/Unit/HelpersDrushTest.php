<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\drush;
use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[CoversFunction('DrevOps\Eddy\Tooling\drush')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersDrushTest extends UnitTestCase {

  #[DataProvider('dataProviderDrush')]
  public function testDrush(string $command, string|array|null $args, string $expected_command_suffix, string $mock_output, string $expected_output): void {
    $cwd = File::cwd();
    $expected_command = 'build/vendor/bin/drush -r ' . escapeshellarg($cwd . '/build/web') . ' -y ' . $expected_command_suffix;
    $this->mockPassthru(['cmd' => $expected_command, 'output' => $mock_output, 'result_code' => 0]);
    $exit_code = 0;
    $result = drush($command, $args, $exit_code);
    $this->assertSame($expected_output, $result);
    $this->assertSame(0, $exit_code);
  }

  public static function dataProviderDrush(): \Iterator {
    yield 'no args' => [
      'command' => 'status',
      'args' => NULL,
      'expected_command_suffix' => 'status',
      'mock_output' => 'Drupal version : 10',
      'expected_output' => 'Drupal version : 10',
    ];
    yield 'string args' => [
      'command' => 'pm:enable %s',
      'args' => 'my_module',
      'expected_command_suffix' => 'pm:enable ' . escapeshellarg('my_module'),
      'mock_output' => '',
      'expected_output' => '',
    ];
    yield 'array args' => [
      'command' => 'config:set %s %s',
      'args' => ['system.site', 'name'],
      'expected_command_suffix' => 'config:set ' . escapeshellarg('system.site') . ' ' . escapeshellarg('name'),
      'mock_output' => 'Set',
      'expected_output' => 'Set',
    ];
    yield 'empty array args' => [
      'command' => 'status',
      'args' => [],
      'expected_command_suffix' => 'status',
      'mock_output' => 'OK',
      'expected_output' => 'OK',
    ];
  }

  /**
   * @param array<int, string> $expected_output
   *   The strings that the output contains.
   */
  #[DataProvider('dataProviderDrushFailureExits')]
  public function testDrushFailureExits(bool $debug, string $mock_output, array $expected_output): void {
    if ($debug) {
      self::envSet('DEBUG', '1');
    }

    $expected_command = 'build/vendor/bin/drush -r ' . escapeshellarg(File::cwd() . '/build/web') . ' -y bad-command';
    $this->mockPassthru(['cmd' => $expected_command, 'output' => $mock_output, 'result_code' => 1]);
    $this->mockQuit(1);

    $output = $this->capture(fn(): string => drush('bad-command'), 1);

    foreach ($expected_output as $expected) {
      $this->assertStringContainsString($expected, $output);
    }
  }

  public static function dataProviderDrushFailureExits(): \Iterator {
    yield 'quiet' => [FALSE, '', ['Drush command failed']];
    yield 'debug prints the captured output' => [
      TRUE,
      'Command "bad-command" is not defined.',
      ['Command "bad-command" is not defined.', 'Drush command failed'],
    ];
  }

  public function testDrushFailsWithExitCodeRefDoesNotExit(): void {
    $cwd = File::cwd();
    $expected_command = 'build/vendor/bin/drush -r ' . escapeshellarg($cwd . '/build/web') . ' -y bad-command';
    $this->mockPassthru(['cmd' => $expected_command, 'output' => '', 'result_code' => 2]);
    $exit_code = 0;
    $result = drush('bad-command', NULL, $exit_code);
    $this->assertSame('', $result);
    $this->assertSame(2, $exit_code);
  }

  public function testDrushStreamsLiveInDebugMode(): void {
    self::envSet('DEBUG', '1');
    $cwd = File::cwd();
    $expected_command = 'build/vendor/bin/drush -r ' . escapeshellarg($cwd . '/build/web') . ' -y status';
    $this->mockPassthru(['cmd' => $expected_command, 'output' => 'Drupal version : 11', 'result_code' => 0]);
    $exit_code = 0;
    $result = drush('status', NULL, $exit_code);
    $this->assertSame('Drupal version : 11', $result);
    $this->assertSame(0, $exit_code);
  }

}
