<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the 'eddy-stop' command.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class StopTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';
  }

  public function testStopDefaultPortWhenNoEnvAndNoDotenv(): void {
    $this->registerMock('file_exists', 'DrevOps\\Eddy\\DevTools', fn(): false => FALSE);

    $this->mockPassthru([
      'cmd' => "lsof -ti:'8000' 2>/dev/null | xargs kill -9 2>/dev/null",
    ]);
    $this->mockSleep();

    ob_start();
    require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-stop';
    $output = ob_get_clean();

    $this->assertIsString($output);
    $this->assertStringContainsString('STOP ENVIRONMENT', $output);
    $this->assertStringContainsString('Stopping previously started services on port 8000', $output);
    $this->assertStringContainsString('Services stopped on port 8000', $output);
    $this->assertStringContainsString('ENVIRONMENT STOPPED', $output);
  }

  public function testStopCustomPortFromEnv(): void {
    $this->envSet('WEBSERVER_PORT', '9000');

    $this->mockPassthru([
      'cmd' => "lsof -ti:'9000' 2>/dev/null | xargs kill -9 2>/dev/null",
    ]);
    $this->mockSleep();

    ob_start();
    require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-stop';
    $output = ob_get_clean();

    $this->assertIsString($output);
    $this->assertStringContainsString('STOP ENVIRONMENT', $output);
    $this->assertStringContainsString('Services stopped on port 9000', $output);
    $this->assertStringContainsString('ENVIRONMENT STOPPED', $output);
  }

  public function testStopReadsPortFromDotenvWhenEnvUnset(): void {
    $this->registerMock('file_exists', 'DrevOps\\Eddy\\DevTools', fn(string $file): bool => $file === '.env');
    $this->registerMock('file_get_contents', 'DrevOps\\Eddy\\DevTools', fn(): string => "WEBSERVER_PORT=8123\n");

    $this->mockPassthru([
      'cmd' => "lsof -ti:'8123' 2>/dev/null | xargs kill -9 2>/dev/null",
    ]);
    $this->mockSleep();

    ob_start();
    require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-stop';
    $output = ob_get_clean();

    $this->assertIsString($output);
    $this->assertStringContainsString('Services stopped on port 8123', $output);
    $this->assertStringContainsString('ENVIRONMENT STOPPED', $output);
  }

  public function testStopStopsTunnelBeforeWebserver(): void {
    $project_dir = self::$tmp . '/stop_tunnel_' . uniqid();
    mkdir($project_dir . '/.logs', 0755, TRUE);
    chdir($project_dir);
    file_put_contents('.logs/cloudflared.pid', "4242\n");
    file_put_contents('.env', "WEBSERVER_PORT=8000\nTUNNEL_URL=https://seasonal-deck-organisms-sf.trycloudflare.com\n");

    $this->registerMock('shell_exec', 'DrevOps\\Eddy\\DevTools', fn(string $command): string => $command === 'ps -p 4242 -o command= 2>/dev/null' ? "cloudflared tunnel --url http://localhost:8000 --no-autoupdate\n" : '');

    // The order of the calls is asserted: the tunnel stops before the port is
    // freed.
    $this->mockPassthruMultiple([
      ['cmd' => 'kill 4242 >/dev/null 2>&1'],
      ['cmd' => 'kill -0 4242 >/dev/null 2>&1', 'result_code' => 1],
      ['cmd' => "lsof -ti:'8000' 2>/dev/null | xargs kill -9 2>/dev/null"],
    ]);
    $this->mockSleep();

    ob_start();
    require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-stop';
    $output = ob_get_clean();

    $this->assertIsString($output);
    $this->assertStringContainsString('Tunnel stopped.', $output);
    $this->assertStringContainsString('Services stopped on port 8000', $output);
    $this->assertFileDoesNotExist('.logs/cloudflared.pid');
    $this->assertSame("WEBSERVER_PORT=8000\n", file_get_contents('.env'));
  }

}
