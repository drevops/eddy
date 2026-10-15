<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the 'eddy-start' command.
 */
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class StartTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    self::envUnset('CLOUDFLARE_TUNNEL');
  }

  #[DataProvider('dataProviderStartSuccess')]
  public function testStartSuccess(array $env, string $expected_host, string $expected_port, int $expected_timeout): void {
    self::envSetMultiple($env);

    $cwd = '/test/project';

    $this->mockFunction('getcwd', fn(): string => $cwd);

    $this->mockPortListeners($expected_port);
    $this->mockPassthru(['cmd' => self::serverCommand($expected_host, $expected_port, $cwd)]);

    $this->mockClock();

    $fp = fopen('php://memory', 'r');
    $this->assertNotFalse($fp);

    $probes = [];
    $this->mockFunction('fsockopen', function (string $host, int $port) use (&$probes, $fp) {
      $probes[] = $host . ':' . $port;

      return $fp;
    });

    $this->mockFunction('fclose', fn(): true => TRUE);

    $context = stream_context_create();
    $this->mockFunction('stream_context_create', fn() => $context);

    $this->mockFunction('get_headers', fn(): array => ['HTTP/1.1 200 OK']);

    $output = $this->runCommand('eddy-start');

    $this->assertStringContainsString('START ENVIRONMENT', $output);
    $this->assertStringContainsString('Waiting up to ' . $expected_timeout . ' seconds for the server to be ready.', $output);
    $this->assertSame([$expected_host . ':' . $expected_port], $probes, 'Only the first probe runs when it connects.');
    $this->assertStringContainsString('Server started successfully', $output);
    $this->assertStringContainsString('Server can serve content', $output);
    $this->assertStringContainsString('ENVIRONMENT READY', $output);
    $this->assertStringContainsString($cwd . '/build/web', $output);
    $this->assertStringContainsString('http://' . $expected_host . ':' . $expected_port, $output);
    $this->assertStringNotContainsString('Cloudflare', $output);

    fclose($fp);
  }

  public static function dataProviderStartSuccess(): \Iterator {
    yield 'explicit default port' => [
      'env' => ['WEBSERVER_PORT' => '8000'],
      'expected_host' => 'localhost',
      'expected_port' => '8000',
      'expected_timeout' => 5,
    ];
    yield 'custom host and port' => [
      'env' => ['WEBSERVER_HOST' => '0.0.0.0', 'WEBSERVER_PORT' => '9000'],
      'expected_host' => '0.0.0.0',
      'expected_port' => '9000',
      'expected_timeout' => 5,
    ];
    yield 'custom timeout' => [
      'env' => ['WEBSERVER_PORT' => '8000', 'WEBSERVER_WAIT_TIMEOUT' => '10'],
      'expected_host' => 'localhost',
      'expected_port' => '8000',
      'expected_timeout' => 10,
    ];
  }

  #[DataProvider('dataProviderStartFailsOnInvalidWaitTimeout')]
  public function testStartFailsOnInvalidWaitTimeout(string $timeout): void {
    self::envSet('WEBSERVER_PORT', '8000');
    self::envSet('WEBSERVER_WAIT_TIMEOUT', $timeout);

    $this->mockShellExecOutputs([]);
    $this->mockPassthruNever();
    $this->mockQuit(1);

    $output = $this->runCommand('eddy-start', 1);

    $this->assertStringContainsString(sprintf('Invalid WEBSERVER_WAIT_TIMEOUT "%s". Expected a whole number of seconds, 1 or more.', $timeout), $output);
    $this->assertStringNotContainsString('START ENVIRONMENT', $output);
  }

  public static function dataProviderStartFailsOnInvalidWaitTimeout(): \Iterator {
    yield 'zero' => ['0'];
    yield 'negative' => ['-5'];
    yield 'not a number' => ['five'];
  }

  /**
   * @param array<int, string>|false $headers
   *   The headers get_headers() returns, or FALSE for a failed request.
   */
  #[DataProvider('dataProviderStartChecksServedContent')]
  public function testStartChecksServedContent(array|false $headers, bool $expected_served): void {
    self::envSet('WEBSERVER_PORT', '8000');
    $cwd = '/test/project';

    $this->mockFunction('getcwd', fn(): string => $cwd);

    $this->mockPortListeners('8000');
    $this->mockPassthru(['cmd' => self::serverCommand('localhost', '8000', $cwd)]);

    $this->mockClock();

    $fp = fopen('php://memory', 'r');
    $this->assertNotFalse($fp);
    $this->mockFunction('fsockopen', fn() => $fp);
    $this->mockFunction('fclose', fn(): true => TRUE);

    $context = stream_context_create();
    $this->mockFunction('stream_context_create', fn() => $context);

    $this->mockFunction('get_headers', fn(): array|false => $headers);

    if ($expected_served) {
      $output = $this->runCommand('eddy-start');

      $this->assertStringContainsString('ENVIRONMENT READY', $output);
      $this->assertStringContainsString('Server can serve content', $output);
    }
    else {
      $this->mockQuit(1);

      $output = $this->runCommand('eddy-start', 1);

      $this->assertStringContainsString('Server started successfully', $output);
      $this->assertStringContainsString('Server is started, but site cannot be served', $output);
    }

    fclose($fp);
  }

  public static function dataProviderStartChecksServedContent(): \Iterator {
    yield '302 response' => [['HTTP/1.1 302 Found'], TRUE];
    yield 'no headers' => [FALSE, FALSE];
    yield 'status 500' => [['HTTP/1.1 500 Internal Server Error'], FALSE];
    yield 'empty headers' => [[], FALSE];
  }

  public function testStartWaitsUntilServerAccepts(): void {
    self::envSet('WEBSERVER_PORT', '8000');
    $cwd = '/test/project';

    $this->mockFunction('getcwd', fn(): string => $cwd);

    $this->mockPortListeners('8000');
    $this->mockPassthru(['cmd' => self::serverCommand('localhost', '8000', $cwd)]);

    $this->mockClock();

    $fp = fopen('php://memory', 'r');
    $this->assertNotFalse($fp);

    // The server refuses the first 2 probes, as it does while it is binding.
    $probes = 0;
    $this->mockFunction('fsockopen', function () use (&$probes, $fp) {
      $probes++;

      return $probes < 3 ? FALSE : $fp;
    });
    $this->mockFunction('fclose', fn(): true => TRUE);

    $context = stream_context_create();
    $this->mockFunction('stream_context_create', fn() => $context);
    $this->mockFunction('get_headers', fn(): array => ['HTTP/1.1 200 OK']);

    $output = $this->runCommand('eddy-start');

    $this->assertSame(3, $probes);
    $this->assertStringContainsString('Server started successfully', $output);
    $this->assertStringContainsString('ENVIRONMENT READY', $output);

    fclose($fp);
  }

  public function testStartStopsOwnServerBeforeLaunch(): void {
    self::envSet('WEBSERVER_PORT', '8000');
    $cwd = '/test/project';

    $this->mockFunction('getcwd', fn(): string => $cwd);

    // The order of the calls is asserted: the running server and its worker
    // are stopped before the new server is launched.
    $this->mockPortListeners('8000', [1234 => self::webserverCommandLine($cwd), 1235 => self::webserverCommandLine($cwd)]);
    $this->mockPassthruMultiple([
      ['cmd' => self::killProcessesCommand(1234, 1235)],
      ['cmd' => self::serverCommand('localhost', '8000', $cwd)],
    ]);

    $this->mockClock();

    $fp = fopen('php://memory', 'r');
    $this->assertNotFalse($fp);
    $this->mockFunction('fsockopen', fn() => $fp);
    $this->mockFunction('fclose', fn(): true => TRUE);

    $context = stream_context_create();
    $this->mockFunction('stream_context_create', fn() => $context);
    $this->mockFunction('get_headers', fn(): array => ['HTTP/1.1 200 OK']);

    $output = $this->runCommand('eddy-start');

    $this->assertStringContainsString('ENVIRONMENT READY', $output);

    fclose($fp);
  }

  /**
   * @param array<int, string> $listeners
   *   The command line of each process listening on the port, keyed by
   *   process ID.
   * @param array<int, string> $expected_processes
   *   The processes that the failure names, as '<pid> <command line>'.
   */
  #[DataProvider('dataProviderStartFailsWhenAnotherProcessHoldsPort')]
  public function testStartFailsWhenAnotherProcessHoldsPort(array $listeners, array $expected_processes): void {
    self::envSet('WEBSERVER_PORT', '8000');

    $this->mockFunction('getcwd', fn(): string => '/test/project');

    $this->mockPortListeners('8000', $listeners);
    $this->mockPassthruNever();
    $this->mockQuit(1);

    $output = $this->runCommand('eddy-start', 1);

    $this->assertStringContainsString('Port 8000 is in use by another process. Stop it, or set WEBSERVER_PORT in .env to a free port.', $output);

    foreach ($expected_processes as $process) {
      $this->assertStringContainsString($process, $output);
    }

    $this->assertStringNotContainsString('/test/project/build/web', $output, 'The failure names only the processes of other projects.');
    $this->assertStringNotContainsString('Starting the PHP webserver', $output);
  }

  public static function dataProviderStartFailsWhenAnotherProcessHoldsPort(): \Iterator {
    $other = self::webserverCommandLine('/www/other');

    yield 'the server of another project' => [[5678 => $other], ['5678 ' . $other]];
    yield 'the server of another project with a worker' => [[5678 => $other, 5679 => $other], ['5678 ' . $other, '5679 ' . $other]];
    yield 'another program' => [[5678 => 'node server.js --port 8000'], ['5678 node server.js --port 8000']];
    yield 'the project server and another' => [[1234 => self::webserverCommandLine('/test/project'), 5678 => $other], ['5678 ' . $other]];
  }

  public function testStartCreatesLogsDirectory(): void {
    $project_dir = self::$tmp . '/start_logs_' . uniqid();
    File::mkdir($project_dir, 0755);
    chdir($project_dir);

    self::envSet('WEBSERVER_PORT', '8000');
    $cwd = '/test/project';

    $this->mockFunction('getcwd', fn(): string => $cwd);

    $this->mockPortListeners('8000');
    $this->mockPassthru(['cmd' => self::serverCommand('localhost', '8000', $cwd)]);

    $this->mockClock();

    $fp = fopen('php://memory', 'r');
    $this->assertNotFalse($fp);
    $this->mockFunction('fsockopen', fn() => $fp);
    $this->mockFunction('fclose', fn(): true => TRUE);

    $context = stream_context_create();
    $this->mockFunction('stream_context_create', fn() => $context);
    $this->mockFunction('get_headers', fn(): array => ['HTTP/1.1 200 OK']);

    $output = $this->runCommand('eddy-start');

    $this->assertStringContainsString('ENVIRONMENT READY', $output);
    $this->assertDirectoryExists($project_dir . '/.logs');

    fclose($fp);
  }

  public function testStartFailsWhenLogsDirectoryCannotBeCreated(): void {
    $project_dir = self::$tmp . '/start_logs_file_' . uniqid();
    File::mkdir($project_dir, 0755);
    chdir($project_dir);
    File::dump('.logs', '');

    self::envSet('WEBSERVER_PORT', '8000');

    // The running server of the project is stopped, and the new one is not
    // launched without its log directory.
    $this->mockPortListeners('8000', [1234 => self::webserverCommandLine($project_dir)]);
    $this->mockPassthru(['cmd' => self::killProcessesCommand(1234)]);
    $this->mockQuit(1);

    $output = $this->runCommand('eddy-start', 1);

    $this->assertStringContainsString('Unable to create directory .logs.', $output);
    $this->assertStringNotContainsString('ENVIRONMENT READY', $output);
  }

  /**
   * @param array<int, string> $expected_output
   *   The strings the output contains.
   */
  #[DataProvider('dataProviderStartFailsWhenServerNeverAccepts')]
  public function testStartFailsWhenServerNeverAccepts(string|false $log, array $expected_output): void {
    self::envSet('WEBSERVER_PORT', '8000');
    $cwd = '/test/project';

    $this->mockFunction('getcwd', fn(): string => $cwd);

    $this->mockPortListeners('8000');
    $this->mockPassthru(['cmd' => self::serverCommand('localhost', '8000', $cwd)]);

    $this->mockClock();

    $probes = 0;
    $this->mockFunction('fsockopen', function () use (&$probes): false {
      $probes++;

      return FALSE;
    });

    $this->mockFunction('file_get_contents', fn(string $file): string|false => $file === '.logs/php.log' ? $log : FALSE);

    $this->mockQuit(1);

    $output = $this->runCommand('eddy-start', 1);

    foreach ($expected_output as $expected) {
      $this->assertStringContainsString($expected, $output);
    }

    // The default 5-second timeout allows 1 probe every 100 milliseconds.
    $this->assertSame(50, $probes);
  }

  public static function dataProviderStartFailsWhenServerNeverAccepts(): \Iterator {
    yield 'with a log' => ['PHP Fatal error: some error', ['START ENVIRONMENT', 'Unable to start inbuilt PHP server', 'PHP Fatal error: some error']];
    yield 'without a log' => [FALSE, ['Unable to start inbuilt PHP server']];
  }

  public function testStartWaitEndsAtTimeoutWhenConnectionsAreDropped(): void {
    self::envSet('WEBSERVER_PORT', '8000');
    $cwd = '/test/project';

    $this->mockFunction('getcwd', fn(): string => $cwd);

    $this->mockPortListeners('8000');
    $this->mockPassthru(['cmd' => self::serverCommand('localhost', '8000', $cwd)]);

    $this->mockClock();

    // Each connection attempt lasts its whole timeout, as it does when the
    // host drops connection attempts.
    $probes = 0;
    $this->mockFunction('fsockopen', function (string $host, int $port, &$errno = NULL, &$errstr = NULL, ?float $timeout = NULL) use (&$probes): false {
      $probes++;
      $this->mockClockNanoseconds += (int) round((float) $timeout * 1000000000);

      return FALSE;
    });

    $this->mockFunction('file_get_contents', fn(): false => FALSE);

    $this->mockQuit(1);

    $output = $this->runCommand('eddy-start', 1);

    $this->assertStringContainsString('Waiting up to 5 seconds for the server to be ready.', $output);
    $this->assertStringContainsString('Unable to start inbuilt PHP server', $output);
    // A probe is a 100-millisecond pause and a 100-millisecond attempt.
    $this->assertSame(25, $probes);
    $this->assertSame(5000000000, $this->mockClockNanoseconds, 'The wait ends at the 5-second timeout.');
  }

  public function testStartReadsPortFromDotenvAndDoesNotRewrite(): void {
    $cwd = '/test/project';

    $this->mockFunction('getcwd', fn(): string => $cwd);

    $this->mockFunction('file_exists', fn(string $file): bool => $file === '.env');
    $this->mockFunction('file_get_contents', fn(): string => "WEBSERVER_PORT=8123\n");

    $put_called = FALSE;
    $this->mockFunction('file_put_contents', function () use (&$put_called): false {
      $put_called = TRUE;

      return FALSE;
    });

    $stream_called = FALSE;
    $this->mockFunction('stream_socket_server', function () use (&$stream_called): false {
      $stream_called = TRUE;

      return FALSE;
    });

    $this->mockPortListeners('8123');
    $this->mockPassthru(['cmd' => self::serverCommand('localhost', '8123', $cwd)]);

    $this->mockClock();

    $fp = fopen('php://memory', 'r');
    $this->assertNotFalse($fp);
    $this->mockFunction('fsockopen', fn() => $fp);
    $this->mockFunction('fclose', fn(): true => TRUE);

    $context = stream_context_create();
    $this->mockFunction('stream_context_create', fn() => $context);
    $this->mockFunction('get_headers', fn(): array => ['HTTP/1.1 200 OK']);

    $output = $this->runCommand('eddy-start');

    $this->assertStringContainsString('http://localhost:8123', $output);
    $this->assertFalse($put_called, 'file_put_contents should not be called when .env already has WEBSERVER_PORT.');
    $this->assertFalse($stream_called, 'stream_socket_server should not be called when .env already has WEBSERVER_PORT.');

    fclose($fp);
  }

  public function testStartAutoDiscoversPortAndPersistsToDotenv(): void {
    $cwd = '/test/project';

    $this->mockFunction('getcwd', fn(): string => $cwd);

    $this->mockFunction('file_exists', fn(): false => FALSE);

    // In find_free_port(), a successful stream_socket_client() connect means
    // the port is in use and a refused connect means it is free.
    $port_attempts = 0;
    $this->mockFunction('stream_socket_client', function (string $address) use (&$port_attempts) {
      $port_attempts++;
      if (str_contains($address, ':8000')) {
        return fopen('php://memory', 'r');
      }

      return FALSE;
    });

    $persisted_port = NULL;
    $persisted_file = NULL;
    $this->mockFunction('file_put_contents', function (string $file, string $content) use (&$persisted_port, &$persisted_file): int {
      $persisted_file = $file;
      if (preg_match('/WEBSERVER_PORT=(\d+)/', $content, $matches)) {
        $persisted_port = $matches[1];
      }

      return strlen($content);
    });

    $this->mockPortListeners('8001');
    $this->mockPassthru(['cmd' => self::serverCommand('localhost', '8001', $cwd)]);

    $this->mockClock();

    $fp = fopen('php://memory', 'r');
    $this->assertNotFalse($fp);
    $this->mockFunction('fsockopen', fn() => $fp);
    $this->mockFunction('fclose', fn(): true => TRUE);

    $context = stream_context_create();
    $this->mockFunction('stream_context_create', fn() => $context);
    $this->mockFunction('get_headers', fn(): array => ['HTTP/1.1 200 OK']);

    $output = $this->runCommand('eddy-start');

    $this->assertStringContainsString('http://localhost:8001', $output);
    $this->assertSame('.env', $persisted_file);
    $this->assertSame('8001', $persisted_port);
    $this->assertSame(2, $port_attempts);

    fclose($fp);
  }

  public function testStartDisplaysTunnelUrlWhenSet(): void {
    $cwd = '/test/project';
    $tunnel_url = 'https://random-words.trycloudflare.com';

    $this->mockFunction('getcwd', fn(): string => $cwd);

    // The server binds and is health-checked on the local host:port, but the
    // READY banner reports the public tunnel URL.
    $this->mockFunction('file_exists', fn(string $file): bool => $file === '.env');
    $this->mockFunction('file_get_contents', fn(): string => "WEBSERVER_PORT=8000\nTUNNEL_URL=" . $tunnel_url . "\n");

    $this->mockPortListeners('8000');
    $this->mockPassthru(['cmd' => self::serverCommand('localhost', '8000', $cwd)]);

    $this->mockClock();

    $fp = fopen('php://memory', 'r');
    $this->assertNotFalse($fp);
    $this->mockFunction('fsockopen', fn() => $fp);
    $this->mockFunction('fclose', fn(): true => TRUE);

    $context = stream_context_create();
    $this->mockFunction('stream_context_create', fn() => $context);
    $this->mockFunction('get_headers', fn(): array => ['HTTP/1.1 200 OK']);

    $output = $this->runCommand('eddy-start');

    $this->assertStringContainsString('URL       : ' . $tunnel_url, $output);
    $this->assertStringNotContainsString('URL       : http://localhost:8000', $output);

    fclose($fp);
  }

  public function testStartStartsTunnelWhenEnabled(): void {
    $project_dir = self::$tmp . '/start_tunnel_' . uniqid();
    File::mkdir($project_dir, 0755);
    chdir($project_dir);
    File::dump('.env', "WEBSERVER_PORT=8000\nCLOUDFLARE_TUNNEL=1\n");

    $cwd = '/test/project';
    $tunnel_url = 'https://seasonal-deck-organisms-sf.trycloudflare.com';

    $this->mockFunction('getcwd', fn(): string => $cwd);

    $this->mockPassthru(['cmd' => self::serverCommand('localhost', '8000', $cwd)]);

    $this->mockClock();

    $fp = fopen('php://memory', 'r');
    $this->assertNotFalse($fp);
    $this->mockFunction('fsockopen', fn() => $fp);
    $this->mockFunction('fclose', fn(): true => TRUE);

    $context = stream_context_create();
    $this->mockFunction('stream_context_create', fn() => $context);
    $this->mockFunction('get_headers', fn(): array => ['HTTP/1.1 200 OK']);

    $this->mockCommandAvailable('cloudflared', TRUE);
    $launch = "nohup cloudflared tunnel --url 'http://localhost:8000' --no-autoupdate >'.logs/cloudflared.log' 2>&1 & echo \$!";
    $this->mockPortListeners('8000', [], [
      $launch => function () use ($tunnel_url): string {
        File::dump('.logs/cloudflared.log', 'INF |  ' . $tunnel_url . '  |' . PHP_EOL);

        return "4242\n";
      },
    ]);

    $output = $this->runCommand('eddy-start');

    $served = strpos($output, 'Server can serve content');
    $tunnel = strpos($output, 'Tunnel started at ' . $tunnel_url . '.');
    $ready = strpos($output, 'ENVIRONMENT READY');
    $this->assertIsInt($served);
    $this->assertIsInt($tunnel);
    $this->assertIsInt($ready);
    $this->assertGreaterThan($served, $tunnel, 'The tunnel starts once the server serves content.');
    $this->assertGreaterThan($tunnel, $ready);
    $this->assertStringContainsString('URL       : ' . $tunnel_url, $output);
    $this->assertSame("WEBSERVER_PORT=8000\nCLOUDFLARE_TUNNEL=1\nTUNNEL_URL=" . $tunnel_url . "\n", File::read('.env'));

    fclose($fp);
  }

  public function testStartSkipsTunnelWithoutCloudflared(): void {
    self::envSet('CLOUDFLARE_TUNNEL', '1');
    self::envSet('WEBSERVER_PORT', '8000');
    $cwd = '/test/project';

    $this->mockFunction('getcwd', fn(): string => $cwd);

    $this->mockPortListeners('8000');
    $this->mockPassthru(['cmd' => self::serverCommand('localhost', '8000', $cwd)]);

    $this->mockClock();

    $fp = fopen('php://memory', 'r');
    $this->assertNotFalse($fp);
    $this->mockFunction('fsockopen', fn() => $fp);
    $this->mockFunction('fclose', fn(): true => TRUE);

    $context = stream_context_create();
    $this->mockFunction('stream_context_create', fn() => $context);
    $this->mockFunction('get_headers', fn(): array => ['HTTP/1.1 200 OK']);

    $this->mockCommandAvailable('cloudflared', FALSE);

    $output = $this->runCommand('eddy-start');

    $this->assertStringContainsString('cloudflared is not on PATH; skipping the tunnel.', $output);
    $this->assertStringContainsString('ENVIRONMENT READY', $output);
    $this->assertStringContainsString('URL       : http://localhost:8000', $output);

    fclose($fp);
  }

  protected static function serverCommand(string $host, string $port, string $cwd): string {
    return sprintf('nohup php -S %s:%s -t %s %s >%s 2>&1 &', escapeshellarg($host), escapeshellarg($port), escapeshellarg($cwd . '/build/web'), escapeshellarg($cwd . '/build/web/.ht.router.php'), escapeshellarg('.logs/php.log'));
  }

}
