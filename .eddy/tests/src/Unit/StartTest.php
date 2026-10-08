<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use DrevOps\Eddy\Tests\Exceptions\QuitErrorException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the 'eddy-start' command.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class StartTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';
    $this->envUnset('CLOUDFLARE_TUNNEL');
  }

  #[DataProvider('dataProviderStartSuccess')]
  public function testStartSuccess(array $env, string $expected_host, string $expected_port, int $expected_timeout): void {
    foreach ($env as $name => $value) {
      $this->envSet($name, $value);
    }

    $cwd = '/test/project';

    $this->registerMock('getcwd', 'DrevOps\\Eddy\\DevTools', fn(): string => $cwd);

    $this->mockPassthruMultiple([
      ['cmd' => sprintf('lsof -ti:%s 2>/dev/null | xargs kill -9 2>/dev/null', escapeshellarg($expected_port))],
      ['cmd' => self::serverCommand($expected_host, $expected_port, $cwd)],
    ]);

    $this->mockSleep();

    $fp = fopen('php://memory', 'r');
    $this->assertNotFalse($fp);
    $this->registerMock('fsockopen', 'DrevOps\\Eddy\\DevTools', fn() => $fp);

    $this->registerMock('fclose', 'DrevOps\\Eddy\\DevTools', fn(): true => TRUE);

    $context = stream_context_create();
    $this->registerMock('stream_context_create', 'DrevOps\\Eddy\\DevTools', fn() => $context);

    $this->registerMock('get_headers', 'DrevOps\\Eddy\\DevTools', fn(): array => ['HTTP/1.1 200 OK']);

    ob_start();
    require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-start';
    $output = ob_get_clean();

    $this->assertIsString($output);
    $this->assertStringContainsString('START ENVIRONMENT', $output);
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

  public function testStartServerWith302Response(): void {
    $this->envSet('WEBSERVER_PORT', '8000');
    $cwd = '/test/project';

    $this->registerMock('getcwd', 'DrevOps\\Eddy\\DevTools', fn(): string => $cwd);

    $this->mockPassthruMultiple([
      ['cmd' => "lsof -ti:'8000' 2>/dev/null | xargs kill -9 2>/dev/null"],
      ['cmd' => self::serverCommand('localhost', '8000', $cwd)],
    ]);

    $this->mockSleep();

    $fp = fopen('php://memory', 'r');
    $this->assertNotFalse($fp);
    $this->registerMock('fsockopen', 'DrevOps\\Eddy\\DevTools', fn() => $fp);
    $this->registerMock('fclose', 'DrevOps\\Eddy\\DevTools', fn(): true => TRUE);

    $context = stream_context_create();
    $this->registerMock('stream_context_create', 'DrevOps\\Eddy\\DevTools', fn() => $context);

    $this->registerMock('get_headers', 'DrevOps\\Eddy\\DevTools', fn(): array => ['HTTP/1.1 302 Found']);

    ob_start();
    require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-start';
    $output = ob_get_clean();

    $this->assertIsString($output);
    $this->assertStringContainsString('ENVIRONMENT READY', $output);
    $this->assertStringContainsString('Server can serve content', $output);

    fclose($fp);
  }

  public function testStartCreatesLogsDirectory(): void {
    $project_dir = self::$tmp . '/start_logs_' . uniqid();
    mkdir($project_dir, 0755, TRUE);
    chdir($project_dir);

    $this->envSet('WEBSERVER_PORT', '8000');
    $cwd = '/test/project';

    $this->registerMock('getcwd', 'DrevOps\\Eddy\\DevTools', fn(): string => $cwd);

    // The server output is redirected into '.logs', so the directory must
    // exist before the server is launched.
    $this->mockPassthruMultiple([
      ['cmd' => "lsof -ti:'8000' 2>/dev/null | xargs kill -9 2>/dev/null"],
      ['cmd' => self::serverCommand('localhost', '8000', $cwd)],
    ]);

    $this->mockSleep();

    $fp = fopen('php://memory', 'r');
    $this->assertNotFalse($fp);
    $this->registerMock('fsockopen', 'DrevOps\\Eddy\\DevTools', fn() => $fp);
    $this->registerMock('fclose', 'DrevOps\\Eddy\\DevTools', fn(): true => TRUE);

    $context = stream_context_create();
    $this->registerMock('stream_context_create', 'DrevOps\\Eddy\\DevTools', fn() => $context);
    $this->registerMock('get_headers', 'DrevOps\\Eddy\\DevTools', fn(): array => ['HTTP/1.1 200 OK']);

    ob_start();
    require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-start';
    $output = (string) ob_get_clean();

    $this->assertStringContainsString('ENVIRONMENT READY', $output);
    $this->assertDirectoryExists($project_dir . '/.logs');

    fclose($fp);
  }

  public function testStartFsockopenFailure(): void {
    $this->envSet('WEBSERVER_PORT', '8000');
    $cwd = '/test/project';

    $this->registerMock('getcwd', 'DrevOps\\Eddy\\DevTools', fn(): string => $cwd);

    $this->mockPassthruMultiple([
      ['cmd' => "lsof -ti:'8000' 2>/dev/null | xargs kill -9 2>/dev/null"],
      ['cmd' => self::serverCommand('localhost', '8000', $cwd)],
    ]);

    $this->mockSleep();

    $this->registerMock('fsockopen', 'DrevOps\\Eddy\\DevTools', fn(): false => FALSE);

    $this->registerMock('file_get_contents', 'DrevOps\\Eddy\\DevTools', fn(string $file): string|false => $file === '.logs/php.log' ? 'PHP Fatal error: some error' : FALSE);

    $this->mockQuit(1);

    ob_start();
    try {
      require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-start';
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(1, $e->getCode());
    }
    finally {
      $output = ob_get_clean();
      $this->assertIsString($output);
      $this->assertStringContainsString('START ENVIRONMENT', $output);
      $this->assertStringContainsString('Unable to start inbuilt PHP server', $output);
      $this->assertStringContainsString('PHP Fatal error: some error', $output);
    }
  }

  public function testStartFsockopenFailureNoLog(): void {
    $this->envSet('WEBSERVER_PORT', '8000');
    $cwd = '/test/project';

    $this->registerMock('getcwd', 'DrevOps\\Eddy\\DevTools', fn(): string => $cwd);

    $this->mockPassthruMultiple([
      ['cmd' => "lsof -ti:'8000' 2>/dev/null | xargs kill -9 2>/dev/null"],
      ['cmd' => self::serverCommand('localhost', '8000', $cwd)],
    ]);

    $this->mockSleep();

    $this->registerMock('fsockopen', 'DrevOps\\Eddy\\DevTools', fn(): false => FALSE);

    $this->registerMock('file_get_contents', 'DrevOps\\Eddy\\DevTools', fn(): false => FALSE);

    $this->mockQuit(1);

    ob_start();
    try {
      require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-start';
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(1, $e->getCode());
    }
    finally {
      $output = ob_get_clean();
      $this->assertIsString($output);
      $this->assertStringContainsString('Unable to start inbuilt PHP server', $output);
    }
  }

  public function testStartGetHeadersFailure(): void {
    $this->envSet('WEBSERVER_PORT', '8000');
    $cwd = '/test/project';

    $this->registerMock('getcwd', 'DrevOps\\Eddy\\DevTools', fn(): string => $cwd);

    $this->mockPassthruMultiple([
      ['cmd' => "lsof -ti:'8000' 2>/dev/null | xargs kill -9 2>/dev/null"],
      ['cmd' => self::serverCommand('localhost', '8000', $cwd)],
    ]);

    $this->mockSleep();

    $fp = fopen('php://memory', 'r');
    $this->assertNotFalse($fp);
    $this->registerMock('fsockopen', 'DrevOps\\Eddy\\DevTools', fn() => $fp);
    $this->registerMock('fclose', 'DrevOps\\Eddy\\DevTools', fn(): true => TRUE);

    $context = stream_context_create();
    $this->registerMock('stream_context_create', 'DrevOps\\Eddy\\DevTools', fn() => $context);

    $this->registerMock('get_headers', 'DrevOps\\Eddy\\DevTools', fn(): false => FALSE);

    $this->mockQuit(1);

    ob_start();
    try {
      require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-start';
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(1, $e->getCode());
    }
    finally {
      $output = ob_get_clean();
      $this->assertIsString($output);
      $this->assertStringContainsString('Server started successfully', $output);
      $this->assertStringContainsString('Server is started, but site cannot be served', $output);
    }

    fclose($fp);
  }

  public function testStartGetHeadersNon200Non302(): void {
    $this->envSet('WEBSERVER_PORT', '8000');
    $cwd = '/test/project';

    $this->registerMock('getcwd', 'DrevOps\\Eddy\\DevTools', fn(): string => $cwd);

    $this->mockPassthruMultiple([
      ['cmd' => "lsof -ti:'8000' 2>/dev/null | xargs kill -9 2>/dev/null"],
      ['cmd' => self::serverCommand('localhost', '8000', $cwd)],
    ]);

    $this->mockSleep();

    $fp = fopen('php://memory', 'r');
    $this->assertNotFalse($fp);
    $this->registerMock('fsockopen', 'DrevOps\\Eddy\\DevTools', fn() => $fp);
    $this->registerMock('fclose', 'DrevOps\\Eddy\\DevTools', fn(): true => TRUE);

    $context = stream_context_create();
    $this->registerMock('stream_context_create', 'DrevOps\\Eddy\\DevTools', fn() => $context);

    $this->registerMock('get_headers', 'DrevOps\\Eddy\\DevTools', fn(): array => ['HTTP/1.1 500 Internal Server Error']);

    $this->mockQuit(1);

    ob_start();
    try {
      require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-start';
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(1, $e->getCode());
    }
    finally {
      $output = ob_get_clean();
      $this->assertIsString($output);
      $this->assertStringContainsString('Server is started, but site cannot be served', $output);
    }

    fclose($fp);
  }

  public function testStartReadsPortFromDotenvAndDoesNotRewrite(): void {
    $cwd = '/test/project';

    $this->registerMock('getcwd', 'DrevOps\\Eddy\\DevTools', fn(): string => $cwd);

    $this->registerMock('file_exists', 'DrevOps\\Eddy\\DevTools', fn(string $file): bool => $file === '.env');
    $this->registerMock('file_get_contents', 'DrevOps\\Eddy\\DevTools', fn(): string => "WEBSERVER_PORT=8123\n");

    $put_called = FALSE;
    $this->registerMock('file_put_contents', 'DrevOps\\Eddy\\DevTools', function () use (&$put_called): false {
      $put_called = TRUE;

      return FALSE;
    });

    $stream_called = FALSE;
    $this->registerMock('stream_socket_server', 'DrevOps\\Eddy\\DevTools', function () use (&$stream_called): false {
      $stream_called = TRUE;

      return FALSE;
    });

    $this->mockPassthruMultiple([
      ['cmd' => "lsof -ti:'8123' 2>/dev/null | xargs kill -9 2>/dev/null"],
      ['cmd' => self::serverCommand('localhost', '8123', $cwd)],
    ]);

    $this->mockSleep();

    $fp = fopen('php://memory', 'r');
    $this->assertNotFalse($fp);
    $this->registerMock('fsockopen', 'DrevOps\\Eddy\\DevTools', fn() => $fp);
    $this->registerMock('fclose', 'DrevOps\\Eddy\\DevTools', fn(): true => TRUE);

    $context = stream_context_create();
    $this->registerMock('stream_context_create', 'DrevOps\\Eddy\\DevTools', fn() => $context);
    $this->registerMock('get_headers', 'DrevOps\\Eddy\\DevTools', fn(): array => ['HTTP/1.1 200 OK']);

    ob_start();
    require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-start';
    $output = ob_get_clean();

    $this->assertIsString($output);
    $this->assertStringContainsString('http://localhost:8123', $output);
    $this->assertFalse($put_called, 'file_put_contents should not be called when .env already has WEBSERVER_PORT.');
    $this->assertFalse($stream_called, 'stream_socket_server should not be called when .env already has WEBSERVER_PORT.');

    fclose($fp);
  }

  public function testStartAutoDiscoversPortAndPersistsToDotenv(): void {
    $cwd = '/test/project';

    $this->registerMock('getcwd', 'DrevOps\\Eddy\\DevTools', fn(): string => $cwd);

    $this->registerMock('file_exists', 'DrevOps\\Eddy\\DevTools', fn(): false => FALSE);

    // In find_free_port(), a successful stream_socket_client() connect means
    // the port is in use and a refused connect means it is free.
    $port_attempts = 0;
    $this->registerMock('stream_socket_client', 'DrevOps\\Eddy\\DevTools', function (string $address) use (&$port_attempts) {
      $port_attempts++;
      if (str_contains($address, ':8000')) {
        return fopen('php://memory', 'r');
      }

      return FALSE;
    });

    $persisted_port = NULL;
    $persisted_file = NULL;
    $this->registerMock('file_put_contents', 'DrevOps\\Eddy\\DevTools', function (string $file, string $contents) use (&$persisted_port, &$persisted_file): int {
      $persisted_file = $file;
      if (preg_match('/WEBSERVER_PORT=(\d+)/', $contents, $matches)) {
        $persisted_port = $matches[1];
      }

      return strlen($contents);
    });

    $this->mockPassthruMultiple([
      ['cmd' => "lsof -ti:'8001' 2>/dev/null | xargs kill -9 2>/dev/null"],
      ['cmd' => self::serverCommand('localhost', '8001', $cwd)],
    ]);

    $this->mockSleep();

    $fp = fopen('php://memory', 'r');
    $this->assertNotFalse($fp);
    $this->registerMock('fsockopen', 'DrevOps\\Eddy\\DevTools', fn() => $fp);
    $this->registerMock('fclose', 'DrevOps\\Eddy\\DevTools', fn(): true => TRUE);

    $context = stream_context_create();
    $this->registerMock('stream_context_create', 'DrevOps\\Eddy\\DevTools', fn() => $context);
    $this->registerMock('get_headers', 'DrevOps\\Eddy\\DevTools', fn(): array => ['HTTP/1.1 200 OK']);

    ob_start();
    require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-start';
    $output = ob_get_clean();

    $this->assertIsString($output);
    $this->assertStringContainsString('http://localhost:8001', $output);
    $this->assertSame('.env', $persisted_file);
    $this->assertSame('8001', $persisted_port);
    // The probe runs twice: localhost:8000 is in use and localhost:8001 is
    // free.
    $this->assertSame(2, $port_attempts);

    fclose($fp);
  }

  public function testStartDisplaysTunnelUrlWhenSet(): void {
    $cwd = '/test/project';
    $tunnel_url = 'https://random-words.trycloudflare.com';

    $this->registerMock('getcwd', 'DrevOps\\Eddy\\DevTools', fn(): string => $cwd);

    // The server binds and is health-checked on the local host:port, but the
    // READY banner reports the public tunnel URL.
    $this->registerMock('file_exists', 'DrevOps\\Eddy\\DevTools', fn(string $file): bool => $file === '.env');
    $this->registerMock('file_get_contents', 'DrevOps\\Eddy\\DevTools', fn(): string => "WEBSERVER_PORT=8000\nTUNNEL_URL=" . $tunnel_url . "\n");

    $this->mockPassthruMultiple([
      ['cmd' => "lsof -ti:'8000' 2>/dev/null | xargs kill -9 2>/dev/null"],
      ['cmd' => self::serverCommand('localhost', '8000', $cwd)],
    ]);

    $this->mockSleep();

    $fp = fopen('php://memory', 'r');
    $this->assertNotFalse($fp);
    $this->registerMock('fsockopen', 'DrevOps\\Eddy\\DevTools', fn() => $fp);
    $this->registerMock('fclose', 'DrevOps\\Eddy\\DevTools', fn(): true => TRUE);

    $context = stream_context_create();
    $this->registerMock('stream_context_create', 'DrevOps\\Eddy\\DevTools', fn() => $context);
    $this->registerMock('get_headers', 'DrevOps\\Eddy\\DevTools', fn(): array => ['HTTP/1.1 200 OK']);

    ob_start();
    require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-start';
    $output = ob_get_clean();

    $this->assertIsString($output);
    $this->assertStringContainsString('URL       : ' . $tunnel_url, $output);
    $this->assertStringNotContainsString('URL       : http://localhost:8000', $output);

    fclose($fp);
  }

  public function testStartStartsTunnelWhenEnabled(): void {
    $project_dir = self::$tmp . '/start_tunnel_' . uniqid();
    mkdir($project_dir, 0755, TRUE);
    chdir($project_dir);
    file_put_contents('.env', "WEBSERVER_PORT=8000\nCLOUDFLARE_TUNNEL=1\n");

    $cwd = '/test/project';
    $tunnel_url = 'https://seasonal-deck-organisms-sf.trycloudflare.com';

    $this->registerMock('getcwd', 'DrevOps\\Eddy\\DevTools', fn(): string => $cwd);

    $this->mockPassthruMultiple([
      ['cmd' => "lsof -ti:'8000' 2>/dev/null | xargs kill -9 2>/dev/null"],
      ['cmd' => self::serverCommand('localhost', '8000', $cwd)],
    ]);

    $this->mockSleep();

    $fp = fopen('php://memory', 'r');
    $this->assertNotFalse($fp);
    $this->registerMock('fsockopen', 'DrevOps\\Eddy\\DevTools', fn() => $fp);
    $this->registerMock('fclose', 'DrevOps\\Eddy\\DevTools', fn(): true => TRUE);

    $context = stream_context_create();
    $this->registerMock('stream_context_create', 'DrevOps\\Eddy\\DevTools', fn() => $context);
    $this->registerMock('get_headers', 'DrevOps\\Eddy\\DevTools', fn(): array => ['HTTP/1.1 200 OK']);

    $this->mockCommandAvailable('cloudflared', TRUE);
    $this->registerMock('shell_exec', 'DrevOps\\Eddy\\DevTools', function (string $command) use ($tunnel_url): string {
      if (!str_starts_with($command, 'nohup cloudflared tunnel --url ')) {
        throw new \RuntimeException(sprintf('shell_exec() called with unexpected command "%s".', $command));
      }

      file_put_contents('.logs/cloudflared.log', 'INF |  ' . $tunnel_url . '  |' . PHP_EOL);

      return "4242\n";
    });

    ob_start();
    require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-start';
    $output = (string) ob_get_clean();

    $served = strpos($output, 'Server can serve content');
    $tunnel = strpos($output, 'Tunnel started at ' . $tunnel_url . '.');
    $ready = strpos($output, 'ENVIRONMENT READY');
    $this->assertIsInt($served);
    $this->assertIsInt($tunnel);
    $this->assertIsInt($ready);
    $this->assertGreaterThan($served, $tunnel, 'The tunnel starts once the server serves content.');
    $this->assertGreaterThan($tunnel, $ready);
    $this->assertStringContainsString('URL       : ' . $tunnel_url, $output);
    $this->assertSame("WEBSERVER_PORT=8000\nCLOUDFLARE_TUNNEL=1\nTUNNEL_URL=" . $tunnel_url . "\n", file_get_contents('.env'));

    fclose($fp);
  }

  public function testStartSkipsTunnelWithoutCloudflared(): void {
    $this->envSet('CLOUDFLARE_TUNNEL', '1');
    $this->envSet('WEBSERVER_PORT', '8000');
    $cwd = '/test/project';

    $this->registerMock('getcwd', 'DrevOps\\Eddy\\DevTools', fn(): string => $cwd);

    $this->mockPassthruMultiple([
      ['cmd' => "lsof -ti:'8000' 2>/dev/null | xargs kill -9 2>/dev/null"],
      ['cmd' => self::serverCommand('localhost', '8000', $cwd)],
    ]);

    $this->mockSleep();

    $fp = fopen('php://memory', 'r');
    $this->assertNotFalse($fp);
    $this->registerMock('fsockopen', 'DrevOps\\Eddy\\DevTools', fn() => $fp);
    $this->registerMock('fclose', 'DrevOps\\Eddy\\DevTools', fn(): true => TRUE);

    $context = stream_context_create();
    $this->registerMock('stream_context_create', 'DrevOps\\Eddy\\DevTools', fn() => $context);
    $this->registerMock('get_headers', 'DrevOps\\Eddy\\DevTools', fn(): array => ['HTTP/1.1 200 OK']);

    $this->mockCommandAvailable('cloudflared', FALSE);

    ob_start();
    require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-start';
    $output = (string) ob_get_clean();

    $this->assertStringContainsString('cloudflared is not on PATH; skipping the tunnel.', $output);
    $this->assertStringContainsString('ENVIRONMENT READY', $output);
    $this->assertStringContainsString('URL       : http://localhost:8000', $output);

    fclose($fp);
  }

  public function testStartGetHeadersEmptyArray(): void {
    $this->envSet('WEBSERVER_PORT', '8000');
    $cwd = '/test/project';

    $this->registerMock('getcwd', 'DrevOps\\Eddy\\DevTools', fn(): string => $cwd);

    $this->mockPassthruMultiple([
      ['cmd' => "lsof -ti:'8000' 2>/dev/null | xargs kill -9 2>/dev/null"],
      ['cmd' => self::serverCommand('localhost', '8000', $cwd)],
    ]);

    $this->mockSleep();

    $fp = fopen('php://memory', 'r');
    $this->assertNotFalse($fp);
    $this->registerMock('fsockopen', 'DrevOps\\Eddy\\DevTools', fn() => $fp);
    $this->registerMock('fclose', 'DrevOps\\Eddy\\DevTools', fn(): true => TRUE);

    $context = stream_context_create();
    $this->registerMock('stream_context_create', 'DrevOps\\Eddy\\DevTools', fn() => $context);

    $this->registerMock('get_headers', 'DrevOps\\Eddy\\DevTools', fn(): array => []);

    $this->mockQuit(1);

    ob_start();
    try {
      require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-start';
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(1, $e->getCode());
    }
    finally {
      $output = ob_get_clean();
      $this->assertIsString($output);
      $this->assertStringContainsString('Server is started, but site cannot be served', $output);
    }

    fclose($fp);
  }

  /**
   * Build the command that launches the PHP webserver.
   */
  protected static function serverCommand(string $host, string $port, string $cwd): string {
    return sprintf('nohup php -S %s:%s -t %s/build/web %s/build/web/.ht.router.php >%s 2>&1 &', escapeshellarg($host), escapeshellarg($port), escapeshellarg($cwd), escapeshellarg($cwd), escapeshellarg('.logs/php.log'));
  }

}
