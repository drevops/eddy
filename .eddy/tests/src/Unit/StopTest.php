<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the 'eddy-stop' command.
 */
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class StopTest extends UnitTestCase {

  #[DataProvider('dataProviderStopResolvesPort')]
  public function testStopResolvesPort(?string $env_port, ?string $dotenv, string $expected_port): void {
    if ($env_port !== NULL) {
      self::envSet('WEBSERVER_PORT', $env_port);
    }

    $this->mockFunction('file_exists', fn(string $file): bool => $dotenv !== NULL && $file === '.env');

    if ($dotenv !== NULL) {
      $this->mockFunction('file_get_contents', fn(): string => $dotenv);
    }

    $this->mockPortListeners($expected_port);
    $this->mockPassthruNever();
    $this->mockSleep();

    $output = $this->runCommand('eddy-stop');

    $this->assertStringContainsString('STOP ENVIRONMENT', $output);
    $this->assertStringContainsString('Stopping previously started services on port ' . $expected_port, $output);
    $this->assertStringContainsString('Services stopped on port ' . $expected_port, $output);
    $this->assertStringContainsString('ENVIRONMENT STOPPED', $output);
  }

  public static function dataProviderStopResolvesPort(): \Iterator {
    yield 'default port' => [NULL, NULL, '8000'];
    yield 'port from the environment' => ['9000', NULL, '9000'];
    yield 'port from .env' => [NULL, "WEBSERVER_PORT=8123\n", '8123'];
  }

  /**
   * @param array<int, string> $listeners
   *   The command line of each process listening on the port, keyed by
   *   process ID.
   * @param array<int, int> $expected_killed
   *   The process IDs that are killed.
   * @param array<int, string> $expected_left
   *   The processes left running, as '<pid> <command line>'.
   */
  #[DataProvider('dataProviderStopKillsOnlyOwnServer')]
  public function testStopKillsOnlyOwnServer(array $listeners, array $expected_killed, array $expected_left): void {
    self::envSet('WEBSERVER_PORT', '8000');

    $this->mockFunction('getcwd', fn(): string => self::PROJECT_ROOT);

    $this->mockPortListeners('8000', $listeners);

    if ($expected_killed === []) {
      $this->mockPassthruNever();
    }
    else {
      $this->mockPassthru(['cmd' => self::killProcessesCommand(...$expected_killed)]);
    }

    $this->mockSleep();

    $output = $this->runCommand('eddy-stop');

    $this->assertStringContainsString('Services stopped on port 8000.', $output);
    $this->assertStringContainsString('ENVIRONMENT STOPPED', $output);

    if ($expected_left === []) {
      $this->assertStringNotContainsString('left running', $output);
    }
    else {
      $this->assertStringContainsString('Port 8000 is still in use by another process, which was left running:', $output);
    }

    foreach ($expected_left as $process) {
      $this->assertStringContainsString($process, $output);
    }

    $this->assertStringNotContainsString(self::PROJECT_ROOT . '/build/web', $output, 'The project server is not reported as left running.');
  }

  public static function dataProviderStopKillsOnlyOwnServer(): \Iterator {
    $own = self::webserverCommandLine(self::PROJECT_ROOT);
    $other = self::webserverCommandLine('/www/other');

    yield 'nothing listens' => [[], [], []];
    yield 'the project server' => [[1234 => $own], [1234], []];
    yield 'the project server with a worker' => [[1234 => $own, 1235 => $own], [1234, 1235], []];
    yield 'a process whose command line cannot be read' => [[1234 => ''], [1234], []];
    yield 'the server of another project' => [[5678 => $other], [], ['5678 ' . $other]];
    yield 'another program' => [[5678 => 'node server.js --port 8000'], [], ['5678 node server.js --port 8000']];
    yield 'the project server and another' => [[1234 => $own, 5678 => $other], [1234], ['5678 ' . $other]];
  }

  public function testStopStopsTunnelBeforeWebserver(): void {
    $project_dir = self::$tmp . '/stop_tunnel_' . uniqid();
    File::mkdir($project_dir . '/.logs', 0755);
    chdir($project_dir);
    File::dump('.logs/cloudflared.pid', "4242\n");
    File::dump('.env', "WEBSERVER_PORT=8000\nTUNNEL_URL=https://seasonal-deck-organisms-sf.trycloudflare.com\n");

    $this->mockPortListeners('8000', [1234 => self::webserverCommandLine($project_dir)], [
      'ps -p 4242 -o command= 2>/dev/null' => "cloudflared tunnel --url http://localhost:8000 --no-autoupdate\n",
    ]);

    // The order of the calls is asserted: the tunnel stops before the
    // webserver.
    $this->mockPassthruMultiple([
      ['cmd' => 'kill 4242 >/dev/null 2>&1'],
      ['cmd' => 'kill -0 4242 >/dev/null 2>&1', 'result_code' => 1],
      ['cmd' => self::killProcessesCommand(1234)],
    ]);
    $this->mockSleep();

    $output = $this->runCommand('eddy-stop');

    $this->assertStringContainsString('Tunnel stopped.', $output);
    $this->assertStringContainsString('Services stopped on port 8000', $output);
    $this->assertFileDoesNotExist('.logs/cloudflared.pid');
    $this->assertSame("WEBSERVER_PORT=8000\n", File::read('.env'));
  }

}
