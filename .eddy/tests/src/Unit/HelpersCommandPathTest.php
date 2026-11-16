<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\command_exists_or_fail;
use function DrevOps\Eddy\Tooling\command_path;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[CoversFunction('DrevOps\Eddy\Tooling\command_path')]
#[CoversFunction('DrevOps\Eddy\Tooling\command_exists_or_fail')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersCommandPathTest extends UnitTestCase {

  #[DataProvider('dataProviderCommandPath')]
  public function testCommandPath(string $command, bool $expect_found): void {
    $this->mockHost();
    $result = command_path($command);
    if ($expect_found) {
      $this->assertIsString($result);
      $this->assertNotEmpty($result);
      $this->assertStringContainsString($command, $result);
    }
    else {
      $this->assertFalse($result);
    }
  }

  public static function dataProviderCommandPath(): \Iterator {
    yield 'existing command php' => ['command' => 'php', 'expect_found' => TRUE];
    yield 'existing command ls' => ['command' => 'ls', 'expect_found' => TRUE];
    yield 'non-existing command' => ['command' => 'nonexistent_command_12345', 'expect_found' => FALSE];
    yield 'non-existing command with special chars' => ['command' => 'fake_cmd_xyz', 'expect_found' => FALSE];
    yield 'command with shell injection' => ['command' => 'php; echo pwned', 'expect_found' => FALSE];
    yield 'command with backticks' => ['command' => '`whoami`', 'expect_found' => FALSE];
    yield 'command with subshell' => ['command' => '$(whoami)', 'expect_found' => FALSE];
  }

  #[DataProvider('dataProviderCommandPathProbe')]
  public function testCommandPathProbe(bool $windows, array $output, string $expected_probe, string|false $expected): void {
    $this->mockWindows($windows);
    $probes = [];
    $this->mockFunction('exec', function (string $command, ?array &$lines = NULL, ?int &$result_code = NULL) use (&$probes, $output): string {
      $probes[] = $command;
      $lines = $output;
      $result_code = $output === [] ? 1 : 0;

      return $output === [] ? '' : $output[array_key_last($output)];
    });

    $this->assertSame($expected, command_path('composer'));
    $this->assertSame([$expected_probe], $probes);
  }

  public static function dataProviderCommandPathProbe(): \Iterator {
    yield 'windows, first of several matches' => [TRUE, ['C:\\tools\\composer.bat', 'C:\\tools\\composer'], 'where composer 2>NUL', 'C:\\tools\\composer.bat'];
    yield 'windows, missing' => [TRUE, [], 'where composer 2>NUL', FALSE];
    yield 'posix' => [FALSE, ['/usr/bin/composer'], 'command -v composer 2>/dev/null', '/usr/bin/composer'];
  }

  public function testCommandExistsOrFailAvailable(): void {
    $this->mockHost();
    command_exists_or_fail('php');
    $this->expectNotToPerformAssertions();
  }

  public function testCommandExistsOrFailUnavailable(): void {
    $this->mockHost();
    $this->mockQuit(1);

    $output = $this->capture(fn() => command_exists_or_fail('nonexistent_command_12345'), 1);

    $this->assertStringContainsString("Command 'nonexistent_command_12345' is not available", $output);
  }

}
