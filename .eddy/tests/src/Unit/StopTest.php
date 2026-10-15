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

  public function testStopDefaultPortWhenNoEnvAndNoDotenv(): void {
    $this->registerMock('file_exists', 'DrevOps\\Eddy\\Tooling', fn(): false => FALSE);

    $this->mockPortListeners('8000');
    $this->mockPassthruNever();
    $this->mockSleep();

    ob_start();
    require self::toolingDir() . '/src/eddy-stop';
    $output = ob_get_clean();

    $this->assertIsString($output);
    $this->assertStringContainsString('STOP ENVIRONMENT', $output);
    $this->assertStringContainsString('Stopping previously started services on port 8000', $output);
    $this->assertStringContainsString('Services stopped on port 8000', $output);
    $this->assertStringContainsString('ENVIRONMENT STOPPED', $output);
  }

  public function testStopCustomPortFromEnv(): void {
    self::envSet('WEBSERVER_PORT', '9000');

    $this->mockPortListeners('9000');
    $this->mockPassthruNever();
    $this->mockSleep();

    ob_start();
    require self::toolingDir() . '/src/eddy-stop';
    $output = ob_get_clean();

    $this->assertIsString($output);
    $this->assertStringContainsString('STOP ENVIRONMENT', $output);
    $this->assertStringContainsString('Services stopped on port 9000', $output);
    $this->assertStringContainsString('ENVIRONMENT STOPPED', $output);
  }

  public function testStopReadsPortFromDotenvWhenEnvUnset(): void {
    $this->registerMock('file_exists', 'DrevOps\\Eddy\\Tooling', fn(string $file): bool => $file === '.env');
    $this->registerMock('file_get_contents', 'DrevOps\\Eddy\\Tooling', fn(): string => "WEBSERVER_PORT=8123\n");

    $this->mockPortListeners('8123');
    $this->mockPassthruNever();
    $this->mockSleep();

    ob_start();
    require self::toolingDir() . '/src/eddy-stop';
    $output = ob_get_clean();

    $this->assertIsString($output);
    $this->assertStringContainsString('Services stopped on port 8123', $output);
    $this->assertStringContainsString('ENVIRONMENT STOPPED', $output);
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

    $this->registerMock('getcwd', 'DrevOps\\Eddy\\Tooling', fn(): string => '/test/project');

    $this->mockPortListeners('8000', $listeners);

    if ($expected_killed === []) {
      $this->mockPassthruNever();
    }
    else {
      $this->mockPassthru(['cmd' => self::killProcessesCommand(...$expected_killed)]);
    }

    $this->mockSleep();

    ob_start();
    require self::toolingDir() . '/src/eddy-stop';
    $output = (string) ob_get_clean();

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

    $this->assertStringNotContainsString('/test/project/build/web', $output, 'The project server is not reported as left running.');
  }

  public static function dataProviderStopKillsOnlyOwnServer(): \Iterator {
    $own = self::webserverCommandLine('/test/project');
    $other = 'php -S localhost:8000 -t /www/other/build/web /www/other/build/web/.ht.router.php';

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

    ob_start();
    require self::toolingDir() . '/src/eddy-stop';
    $output = ob_get_clean();

    $this->assertIsString($output);
    $this->assertStringContainsString('Tunnel stopped.', $output);
    $this->assertStringContainsString('Services stopped on port 8000', $output);
    $this->assertFileDoesNotExist('.logs/cloudflared.pid');
    $this->assertSame("WEBSERVER_PORT=8000\n", File::read('.env'));
  }

}
