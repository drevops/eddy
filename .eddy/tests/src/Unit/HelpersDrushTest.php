<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\DevTools\drush;
use DrevOps\Eddy\Tests\Exceptions\QuitErrorException;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[CoversFunction('DrevOps\Eddy\DevTools\drush')]
#[Group('p0')]
final class HelpersDrushTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';
  }

  #[DataProvider('dataProviderDrush')]
  public function testDrush(string $command, string|array|null $args, string $expected_cmd_suffix, string $mock_output, string $expected_output): void {
    $cwd = getcwd();
    $expected_cmd = 'build/vendor/bin/drush -r ' . escapeshellarg($cwd . '/build/web') . ' -y ' . $expected_cmd_suffix;
    $this->mockPassthru(['cmd' => $expected_cmd, 'output' => $mock_output, 'result_code' => 0]);
    $exit_code = 0;
    $result = drush($command, $args, $exit_code);
    $this->assertSame($expected_output, $result);
    $this->assertSame(0, $exit_code);
  }

  public static function dataProviderDrush(): \Iterator {
    yield 'no args' => [
      'command' => 'status',
      'args' => NULL,
      'expected_cmd_suffix' => 'status',
      'mock_output' => 'Drupal version : 10',
      'expected_output' => 'Drupal version : 10',
    ];
    yield 'string args' => [
      'command' => 'pm:enable %s',
      'args' => 'my_module',
      'expected_cmd_suffix' => 'pm:enable ' . escapeshellarg('my_module'),
      'mock_output' => '',
      'expected_output' => '',
    ];
    yield 'array args' => [
      'command' => 'config:set %s %s',
      'args' => ['system.site', 'name'],
      'expected_cmd_suffix' => 'config:set ' . escapeshellarg('system.site') . ' ' . escapeshellarg('name'),
      'mock_output' => 'Set',
      'expected_output' => 'Set',
    ];
    yield 'empty array args' => [
      'command' => 'status',
      'args' => [],
      'expected_cmd_suffix' => 'status',
      'mock_output' => 'OK',
      'expected_output' => 'OK',
    ];
  }

  public function testDrushFailsWithoutExitCodeRef(): void {
    $cwd = getcwd();
    $expected_cmd = 'build/vendor/bin/drush -r ' . escapeshellarg($cwd . '/build/web') . ' -y bad-command';
    $this->mockPassthru(['cmd' => $expected_cmd, 'output' => '', 'result_code' => 1]);
    $this->mockQuit(1);
    ob_start();
    try {
      drush('bad-command');
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(1, $e->getCode());
    }
    finally {
      $output = ob_get_clean();
      $this->assertIsString($output);
      $this->assertStringContainsString('Drush command failed', $output);
    }
  }

  public function testDrushFailsWithExitCodeRefDoesNotExit(): void {
    $cwd = getcwd();
    $expected_cmd = 'build/vendor/bin/drush -r ' . escapeshellarg($cwd . '/build/web') . ' -y bad-command';
    $this->mockPassthru(['cmd' => $expected_cmd, 'output' => '', 'result_code' => 2]);
    $exit_code = 0;
    $result = drush('bad-command', NULL, $exit_code);
    $this->assertSame('', $result);
    $this->assertSame(2, $exit_code);
  }

  public function testDrushFailurePrintsCapturedOutputInDebugMode(): void {
    $this->envSet('DEBUG', '1');
    $cwd = getcwd();
    $expected_cmd = 'build/vendor/bin/drush -r ' . escapeshellarg($cwd . '/build/web') . ' -y bad-command';
    $this->mockPassthru(['cmd' => $expected_cmd, 'output' => 'Command "bad-command" is not defined.', 'result_code' => 1]);
    $this->mockQuit(1);
    ob_start();
    try {
      drush('bad-command');
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(1, $e->getCode());
    }
    finally {
      $output = ob_get_clean();
      $this->assertIsString($output);
      $this->assertStringContainsString('Command "bad-command" is not defined.', $output);
      $this->assertStringContainsString('Drush command failed', $output);
    }
  }

  public function testDrushStreamsLiveInDebugMode(): void {
    $this->envSet('DEBUG', '1');
    $cwd = getcwd();
    $expected_cmd = 'build/vendor/bin/drush -r ' . escapeshellarg($cwd . '/build/web') . ' -y status';
    $this->mockPassthru(['cmd' => $expected_cmd, 'output' => 'Drupal version : 11', 'result_code' => 0]);
    $exit_code = 0;
    $result = drush('status', NULL, $exit_code);
    $this->assertSame('Drupal version : 11', $result);
    $this->assertSame(0, $exit_code);
  }

}
