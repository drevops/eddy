<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\DevTools\command_path;
use function DrevOps\Eddy\DevTools\command_must_exist;
use DrevOps\Eddy\Tests\Exceptions\QuitErrorException;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[CoversFunction('DrevOps\Eddy\DevTools\command_path')]
#[CoversFunction('DrevOps\Eddy\DevTools\command_must_exist')]
#[Group('p0')]
final class HelpersCommandExistsTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';
  }

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
    $this->expectNotToPerformAssertions();
    command_must_exist('php');
  }

  public function testCommandMustExistUnavailable(): void {
    $this->mockQuit(1);
    ob_start();
    try {
      command_must_exist('nonexistent_command_12345');
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(1, $e->getCode());
    }
    finally {
      $output = ob_get_clean();
      $this->assertIsString($output);
      $this->assertStringContainsString("Command 'nonexistent_command_12345' is not available", $output);
    }
  }

}
