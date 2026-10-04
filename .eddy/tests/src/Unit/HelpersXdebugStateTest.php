<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\DevTools\xdebug_state;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the xdebug_state() helper.
 *
 * phpcs:disable Drupal.Classes.FullyQualifiedNamespace.UseStatementMissing
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[CoversFunction('DrevOps\Eddy\DevTools\xdebug_state')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersXdebugStateTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';
  }

  #[DataProvider('dataProviderXdebugState')]
  public function testXdebugState(string $ps_output, string $expected): void {
    $captured_cmd = NULL;
    $this->registerMock('shell_exec', 'DrevOps\\Eddy\\DevTools', function (string $cmd) use (&$captured_cmd, $ps_output): string {
      $captured_cmd = $cmd;

      return $ps_output;
    });

    $this->assertSame($expected, xdebug_state('8000'));
    $this->assertNotNull($captured_cmd);
    $this->assertStringContainsString("lsof -ti:'8000'", $captured_cmd);
  }

  public static function dataProviderXdebugState(): \Iterator {
    yield 'no server listening' => [
      'ps_output' => '',
      'expected' => '-',
    ];
    yield 'server running without xdebug' => [
      'ps_output' => "php -S localhost:8000\n",
      'expected' => 'disabled',
    ];
    yield 'server running with xdebug' => [
      'ps_output' => "php -d xdebug.mode=debug -d xdebug.start_with_request=yes -S localhost:8000\n",
      'expected' => 'enabled',
    ];
    yield 'output is whitespace only' => [
      'ps_output' => "   \n",
      'expected' => '-',
    ];
  }

  public function testPortIsShellEscaped(): void {
    $captured_cmd = NULL;
    $this->registerMock('shell_exec', 'DrevOps\\Eddy\\DevTools', function (string $cmd) use (&$captured_cmd): string {
      $captured_cmd = $cmd;

      return '';
    });

    xdebug_state('8000; rm -rf /');

    $this->assertNotNull($captured_cmd);
    $this->assertStringContainsString("'8000; rm -rf /'", $captured_cmd, 'Port is wrapped in escapeshellarg quotes.');
    $this->assertStringNotContainsString('; rm -rf /;', $captured_cmd, 'Embedded command substitution must not break out of the quotes.');
  }

}
