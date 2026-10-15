<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\command_path;
use function DrevOps\Eddy\Tooling\command_must_exist;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[CoversFunction('DrevOps\Eddy\Tooling\command_path')]
#[CoversFunction('DrevOps\Eddy\Tooling\command_must_exist')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersCommandPathTest extends UnitTestCase {

  #[DataProvider('dataProviderCommandPath')]
  public function testCommandPath(string $command, bool $expect_found): void {
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

  public function testCommandMustExistAvailable(): void {
    command_must_exist('php');
    $this->expectNotToPerformAssertions();
  }

  public function testCommandMustExistUnavailable(): void {
    $this->mockQuit(1);

    $output = $this->capture(fn() => command_must_exist('nonexistent_command_12345'), 1);

    $this->assertStringContainsString("Command 'nonexistent_command_12345' is not available", $output);
  }

}
