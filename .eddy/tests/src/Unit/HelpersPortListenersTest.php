<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\kill_port;
use function DrevOps\Eddy\Tooling\port_listener_pids_command;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the port_listener_pids_command() and kill_port() helpers.
 *
 * phpcs:disable Drupal.Classes.FullyQualifiedNamespace.UseStatementMissing
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[CoversFunction('DrevOps\Eddy\Tooling\port_listener_pids_command')]
#[CoversFunction('DrevOps\Eddy\Tooling\kill_port')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersPortListenersTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';
  }

  #[DataProvider('dataProviderPortListenerPidsCommand')]
  public function testPortListenerPidsCommand(int|string $port, string $expected): void {
    $this->assertSame($expected, port_listener_pids_command($port));
  }

  public static function dataProviderPortListenerPidsCommand(): \Iterator {
    yield 'string port' => ['8000', "lsof -ti tcp:'8000' -sTCP:LISTEN 2>/dev/null"];
    yield 'integer port' => [4444, "lsof -ti tcp:'4444' -sTCP:LISTEN 2>/dev/null"];
    yield 'shell metacharacters stay quoted' => ['8000; rm -rf /', "lsof -ti tcp:'8000; rm -rf /' -sTCP:LISTEN 2>/dev/null"];
  }

  #[DataProvider('dataProviderKillPort')]
  public function testKillPort(int|string $port, string $expected): void {
    $this->mockPassthru(['cmd' => $expected]);

    kill_port($port);

    $this->mockPassthruAssertAllMocksConsumed();
  }

  public static function dataProviderKillPort(): \Iterator {
    yield 'string port' => ['8000', "lsof -ti tcp:'8000' -sTCP:LISTEN 2>/dev/null | xargs kill -9 2>/dev/null"];
    yield 'integer port' => [4444, "lsof -ti tcp:'4444' -sTCP:LISTEN 2>/dev/null | xargs kill -9 2>/dev/null"];
  }

}
