<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\DevTools\tunnel_enabled;
use function DrevOps\Eddy\DevTools\tunnel_kill;
use function DrevOps\Eddy\DevTools\tunnel_start;
use function DrevOps\Eddy\DevTools\tunnel_stop;
use function DrevOps\Eddy\DevTools\tunnel_write_settings;
use DrevOps\Eddy\Tests\Exceptions\QuitErrorException;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the Cloudflare quick tunnel helpers.
 *
 * Each test runs in an empty project root, so the PID file, the log, '.env'
 * and 'settings.php' are real files. Only the process calls are mocked.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[CoversFunction('DrevOps\Eddy\DevTools\tunnel_enabled')]
#[CoversFunction('DrevOps\Eddy\DevTools\tunnel_start')]
#[CoversFunction('DrevOps\Eddy\DevTools\tunnel_stop')]
#[CoversFunction('DrevOps\Eddy\DevTools\tunnel_write_settings')]
#[CoversFunction('DrevOps\Eddy\DevTools\tunnel_pid')]
#[CoversFunction('DrevOps\Eddy\DevTools\tunnel_log_url')]
#[CoversFunction('DrevOps\Eddy\DevTools\tunnel_responds')]
#[CoversFunction('DrevOps\Eddy\DevTools\tunnel_kill')]
#[CoversFunction('DrevOps\Eddy\DevTools\tunnel_forget')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersTunnelTest extends UnitTestCase {

  protected const URL = 'https://seasonal-deck-organisms-sf.trycloudflare.com';

  protected const NEW_URL = 'https://quiet-river-stone-xyz.trycloudflare.com';

  protected const LAUNCH = "nohup cloudflared tunnel --url 'http://localhost:8000' --no-autoupdate >'.logs/cloudflared.log' 2>&1 & echo \$!";

  protected const PS = 'ps -p 4242 -o command= 2>/dev/null';

  protected const API_ERROR = '2026-10-08T00:00:01Z ERR failed to request quick Tunnel: Post "https://api.trycloudflare.com/tunnel": dial tcp: lookup api.trycloudflare.com: no such host' . PHP_EOL;

  protected string $originalCwd;

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';

    $this->envUnset('CLOUDFLARE_TUNNEL');

    $this->originalCwd = (string) getcwd();
    $project_dir = self::$tmp . '/tunnel_' . uniqid();
    mkdir($project_dir, 0755, TRUE);
    chdir($project_dir);
  }

  protected function tearDown(): void {
    chdir($this->originalCwd);
    parent::tearDown();
  }

  #[DataProvider('dataProviderTunnelEnabled')]
  public function testTunnelEnabled(?string $env, ?string $dotenv, bool $expected): void {
    if ($env !== NULL) {
      $this->envSet('CLOUDFLARE_TUNNEL', $env);
    }

    if ($dotenv !== NULL) {
      file_put_contents('.env', 'CLOUDFLARE_TUNNEL=' . $dotenv . PHP_EOL);
    }

    $this->assertSame($expected, tunnel_enabled());
  }

  public static function dataProviderTunnelEnabled(): \Iterator {
    yield 'unset' => [NULL, NULL, FALSE];
    yield 'empty' => ['', NULL, FALSE];
    yield '1' => ['1', NULL, TRUE];
    yield 'true' => ['true', NULL, TRUE];
    yield 'yes' => ['yes', NULL, TRUE];
    yield 'on' => ['on', NULL, TRUE];
    yield '0' => ['0', NULL, FALSE];
    yield 'false' => ['false', NULL, FALSE];
    yield 'no' => ['no', NULL, FALSE];
    yield 'off' => ['off', NULL, FALSE];
    yield 'upper case FALSE' => ['FALSE', NULL, FALSE];
    yield 'mixed case Off' => ['Off', NULL, FALSE];
    yield 'surrounding whitespace' => [' 0 ', NULL, FALSE];
    yield 'enabled in .env' => [NULL, '1', TRUE];
    yield 'disabled in .env' => [NULL, '0', FALSE];
    yield 'env overrides .env' => ['0', '1', FALSE];
    yield 'empty env falls back to .env' => ['', '1', TRUE];
  }

  public function testTunnelStartWithoutCloudflared(): void {
    $this->mockCommandAvailable('cloudflared', FALSE);
    $this->mockShellExec([]);
    $this->mockPassthruNever();

    $output = $this->capture(static fn() => tunnel_start('8000'));

    $this->assertStringContainsString('Starting the Cloudflare quick tunnel.', $output);
    $this->assertStringContainsString('cloudflared is not on PATH; skipping the tunnel.', $output);
    $this->assertFileDoesNotExist('.env');
    $this->assertDirectoryDoesNotExist('.logs');
  }

  public function testTunnelStartStartsTunnel(): void {
    file_put_contents('.env', "WEBSERVER_PORT=8000\n");
    $this->mockCommandAvailable('cloudflared', TRUE);
    $this->mockShellExec([self::LAUNCH => "4242\n"], self::banner(self::URL));
    $this->mockPassthruNever();
    $sleeps = 0;
    $this->mockSleepCounted($sleeps);

    $output = $this->capture(static fn() => tunnel_start('8000'));

    $this->assertStringContainsString('Waiting for the tunnel URL.', $output);
    $this->assertStringContainsString('Tunnel started at ' . self::URL . '.', $output);
    $this->assertSame(0, $sleeps);
    $this->assertSame("4242\n", file_get_contents('.logs/cloudflared.pid'));
    $this->assertSame("WEBSERVER_PORT=8000\nTUNNEL_URL=" . self::URL . "\n", file_get_contents('.env'));
  }

  public function testTunnelStartWaitsForUrl(): void {
    $this->mockCommandAvailable('cloudflared', TRUE);
    $this->mockShellExec([self::LAUNCH => "4242\n"]);
    $this->mockPassthruNever();
    $sleeps = 0;
    $this->mockSleepCounted($sleeps, [2 => self::banner(self::URL)]);

    $output = $this->capture(static fn() => tunnel_start('8000'));

    $this->assertStringContainsString('Tunnel started at ' . self::URL . '.', $output);
    $this->assertSame(2, $sleeps);
    $this->assertSame('TUNNEL_URL=' . self::URL . "\n", file_get_contents('.env'));
  }

  public function testTunnelStartEmptiesPreviousLog(): void {
    mkdir('.logs');
    file_put_contents('.logs/cloudflared.log', self::banner(self::URL));
    $this->mockCommandAvailable('cloudflared', TRUE);
    $this->mockShellExec([self::LAUNCH => "4242\n"]);
    $this->mockPassthruNever();
    $sleeps = 0;
    $this->mockSleepCounted($sleeps, [1 => self::banner(self::NEW_URL)]);

    $output = $this->capture(static fn() => tunnel_start('8000'));

    $this->assertStringContainsString('Tunnel started at ' . self::NEW_URL . '.', $output);
    $this->assertSame(1, $sleeps);
    $this->assertSame('TUNNEL_URL=' . self::NEW_URL . "\n", file_get_contents('.env'));
  }

  #[DataProvider('dataProviderTunnelStartWithoutUrl')]
  public function testTunnelStartWithoutUrl(string $log): void {
    file_put_contents('.env', "WEBSERVER_PORT=8000\nTUNNEL_URL=" . self::URL . "\n");
    $this->mockCommandAvailable('cloudflared', TRUE);
    $this->mockShellExec([self::LAUNCH => "4242\n"]);
    $this->mockPassthru(['cmd' => 'kill 4242 >/dev/null 2>&1']);
    $sleeps = 0;
    $this->mockSleepCounted($sleeps, [1 => $log]);

    $output = $this->capture(static fn() => tunnel_start('8000'));

    $this->assertStringContainsString('The tunnel published no URL; see .logs/cloudflared.log. Continuing without the tunnel.', $output);
    $this->assertStringNotContainsString('Tunnel started', $output);
    $this->assertSame(30, $sleeps);
    $this->assertFileDoesNotExist('.logs/cloudflared.pid');
    $this->assertSame("WEBSERVER_PORT=8000\n", file_get_contents('.env'));
  }

  public static function dataProviderTunnelStartWithoutUrl(): \Iterator {
    yield 'nothing logged' => [''];
    yield 'failed request logs the API URL' => [self::API_ERROR];
  }

  public function testTunnelStartWithoutPid(): void {
    $this->mockCommandAvailable('cloudflared', TRUE);
    $this->mockShellExec([self::LAUNCH => NULL]);
    // 'kill 0' would signal this process group, so nothing may be killed.
    $this->mockPassthruNever();
    $sleeps = 0;
    $this->mockSleepCounted($sleeps);

    $output = $this->capture(static fn() => tunnel_start('8000'));

    $this->assertStringContainsString('The tunnel published no URL', $output);
    $this->assertSame(30, $sleeps);
    $this->assertFileDoesNotExist('.logs/cloudflared.pid');
  }

  #[DataProvider('dataProviderTunnelStartChecksRunningTunnel')]
  public function testTunnelStartChecksRunningTunnel(array|false $headers, bool $expect_reused): void {
    mkdir('.logs');
    file_put_contents('.logs/cloudflared.pid', "4242\n");
    file_put_contents('.logs/cloudflared.log', self::banner(self::URL));
    $this->mockCommandAvailable('cloudflared', TRUE);

    $requested = NULL;
    $options = NULL;
    $this->registerMock('get_headers', 'DrevOps\\Eddy\\DevTools', function (string $url, bool $associative, mixed $context) use (&$requested, &$options, $headers): array|false {
      $requested = $url;
      $options = is_resource($context) ? stream_context_get_options($context) : NULL;

      return $headers;
    });

    if ($expect_reused) {
      $this->mockShellExec([self::PS => "cloudflared tunnel --url http://localhost:8000 --no-autoupdate\n"]);
      $this->mockPassthruNever();
    }
    else {
      $this->mockShellExec([self::PS => "cloudflared tunnel --url http://localhost:8000 --no-autoupdate\n", self::LAUNCH => "5151\n"], self::banner(self::NEW_URL));
      $this->mockPassthru(['cmd' => 'kill 4242 >/dev/null 2>&1']);
    }

    $output = $this->capture(static fn() => tunnel_start('8000'));

    $this->assertSame(self::URL, $requested);
    $this->assertSame(['http' => ['method' => 'HEAD', 'timeout' => 5, 'ignore_errors' => TRUE, 'follow_location' => 0]], $options);

    if ($expect_reused) {
      $this->assertStringContainsString('Reusing the tunnel at ' . self::URL . '.', $output);
      $this->assertSame("4242\n", file_get_contents('.logs/cloudflared.pid'));
      $this->assertSame('TUNNEL_URL=' . self::URL . "\n", file_get_contents('.env'));
    }
    else {
      $this->assertStringContainsString('The running tunnel does not respond; restarting it.', $output);
      $this->assertStringContainsString('Tunnel started at ' . self::NEW_URL . '.', $output);
      $this->assertSame("5151\n", file_get_contents('.logs/cloudflared.pid'));
      $this->assertSame('TUNNEL_URL=' . self::NEW_URL . "\n", file_get_contents('.env'));
    }
  }

  public static function dataProviderTunnelStartChecksRunningTunnel(): \Iterator {
    yield 'ok' => [['HTTP/1.1 200 OK'], TRUE];
    yield 'redirect' => [['HTTP/1.1 302 Found'], TRUE];
    yield 'HTTP/2 no content' => [['HTTP/2 204'], TRUE];
    yield 'not found' => [['HTTP/1.1 404 Not Found'], FALSE];
    yield 'bad gateway' => [['HTTP/1.1 502 Bad Gateway'], FALSE];
    yield 'origin unreachable' => [['HTTP/1.1 530'], FALSE];
    yield 'no response' => [FALSE, FALSE];
    yield 'no status line' => [[], FALSE];
  }

  public function testTunnelStartRestartsTunnelWithoutLoggedUrl(): void {
    mkdir('.logs');
    file_put_contents('.logs/cloudflared.pid', "4242\n");
    file_put_contents('.logs/cloudflared.log', self::API_ERROR);
    $this->mockCommandAvailable('cloudflared', TRUE);
    $this->mockShellExec([self::PS => "cloudflared tunnel --url http://localhost:8000 --no-autoupdate\n", self::LAUNCH => "5151\n"], self::banner(self::NEW_URL));
    $this->mockPassthru(['cmd' => 'kill 4242 >/dev/null 2>&1']);
    $this->registerMock('get_headers', 'DrevOps\\Eddy\\DevTools', function (): never {
      throw new \RuntimeException('A tunnel without a URL must not be requested.');
    });

    $output = $this->capture(static fn() => tunnel_start('8000'));

    $this->assertStringContainsString('The running tunnel does not respond; restarting it.', $output);
    $this->assertStringContainsString('Tunnel started at ' . self::NEW_URL . '.', $output);
    $this->assertSame("5151\n", file_get_contents('.logs/cloudflared.pid'));
  }

  public function testTunnelStartIgnoresRecycledPid(): void {
    mkdir('.logs');
    file_put_contents('.logs/cloudflared.pid', "4242\n");
    $this->mockCommandAvailable('cloudflared', TRUE);
    $this->mockShellExec([self::PS => "php -S localhost:8000 -t build/web\n", self::LAUNCH => "5151\n"], self::banner(self::URL));
    // The process that holds the PID now is not the tunnel, so it is left
    // running.
    $this->mockPassthruNever();

    $output = $this->capture(static fn() => tunnel_start('8000'));

    $this->assertStringNotContainsString('restarting', $output);
    $this->assertStringContainsString('Tunnel started at ' . self::URL . '.', $output);
    $this->assertSame("5151\n", file_get_contents('.logs/cloudflared.pid'));
  }

  public function testTunnelStopWithoutTunnel(): void {
    file_put_contents('.env', "WEBSERVER_PORT=8000\n");
    $this->mockShellExec([]);
    $this->mockPassthruNever();

    $output = $this->capture(static fn() => tunnel_stop());

    $this->assertSame('', $output);
    $this->assertSame("WEBSERVER_PORT=8000\n", file_get_contents('.env'));
  }

  /**
   * @param array<int, int> $exit_codes
   *   The exit code of each 'kill -0' check.
   */
  #[DataProvider('dataProviderTunnelStopStopsTunnel')]
  public function testTunnelStopStopsTunnel(array $exit_codes, int $expected_sleeps): void {
    mkdir('.logs');
    file_put_contents('.logs/cloudflared.pid', "4242\n");
    file_put_contents('.env', "WEBSERVER_PORT=8000\nTUNNEL_URL=" . self::URL . "\n");
    $this->mockShellExec([self::PS => "cloudflared tunnel --url http://localhost:8000 --no-autoupdate\n"]);

    $responses = [['cmd' => 'kill 4242 >/dev/null 2>&1']];
    foreach ($exit_codes as $exit_code) {
      $responses[] = ['cmd' => 'kill -0 4242 >/dev/null 2>&1', 'result_code' => $exit_code];
    }
    $this->mockPassthruMultiple($responses);

    $sleeps = 0;
    $this->mockSleepCounted($sleeps);

    $output = $this->capture(static fn() => tunnel_stop());

    $this->assertStringContainsString('Stopping the Cloudflare quick tunnel.', $output);
    $this->assertStringContainsString('Tunnel stopped.', $output);
    $this->assertSame($expected_sleeps, $sleeps);
    $this->assertFileDoesNotExist('.logs/cloudflared.pid');
    $this->assertSame("WEBSERVER_PORT=8000\n", file_get_contents('.env'));
  }

  public static function dataProviderTunnelStopStopsTunnel(): \Iterator {
    yield 'exits at once' => [[1], 0];
    yield 'exits after 1 second' => [[0, 1], 1];
    yield 'still running after 3 seconds' => [[0, 0, 0], 3];
  }

  public function testTunnelStopForgetsExitedTunnel(): void {
    mkdir('.logs');
    file_put_contents('.logs/cloudflared.pid', "4242\n");
    file_put_contents('.env', 'TUNNEL_URL=' . self::URL . "\nOTHER=1\n");
    $this->mockShellExec([self::PS => '']);
    $this->mockPassthruNever();

    $output = $this->capture(static fn() => tunnel_stop());

    $this->assertSame('', $output);
    $this->assertFileDoesNotExist('.logs/cloudflared.pid');
    $this->assertSame("OTHER=1\n", file_get_contents('.env'));
  }

  #[DataProvider('dataProviderTunnelStopIgnoresInvalidPidFile')]
  public function testTunnelStopIgnoresInvalidPidFile(string $pid): void {
    mkdir('.logs');
    file_put_contents('.logs/cloudflared.pid', $pid);
    $this->mockShellExec([]);
    $this->mockPassthruNever();

    $output = $this->capture(static fn() => tunnel_stop());

    $this->assertSame('', $output);
    $this->assertFileDoesNotExist('.logs/cloudflared.pid');
  }

  public static function dataProviderTunnelStopIgnoresInvalidPidFile(): \Iterator {
    yield 'empty' => [''];
    yield 'not a number' => ['abc'];
    yield 'trailing text' => ["4242abc\n"];
    yield 'zero' => ["0\n"];
    yield 'negative' => ["-1\n"];
  }

  public function testTunnelStopKeepsUrlOfAnotherTunnel(): void {
    file_put_contents('.env', "TUNNEL_URL=https://example.ngrok.app\n");
    $this->mockShellExec([]);
    $this->mockPassthruNever();

    $this->capture(static fn() => tunnel_stop());

    $this->assertSame("TUNNEL_URL=https://example.ngrok.app\n", file_get_contents('.env'));
  }

  public function testTunnelWriteSettingsWithoutSettingsFile(): void {
    $output = $this->capture(static fn() => tunnel_write_settings('build/web/sites/default/settings.php'));

    $this->assertStringContainsString('build/web/sites/default/settings.php not found; skipping the tunnel settings.', $output);
    $this->assertFileDoesNotExist('build/web/sites/default/settings.php');
  }

  public function testTunnelWriteSettingsAppendsOnce(): void {
    $file = 'build/web/sites/default/settings.php';
    mkdir(dirname($file), 0755, TRUE);
    file_put_contents($file, "<?php\n\n\$databases = [];\n");
    // The installer leaves the file read-only.
    chmod($file, 0444);

    $expected = <<<'PHP'
<?php

$databases = [];

# Cloudflare quick tunnel settings.
$settings['reverse_proxy'] = TRUE;
$settings['reverse_proxy_addresses'] = ['127.0.0.1', '::1'];
$settings['trusted_host_patterns'][] = '^[a-z0-9-]+\.trycloudflare\.com$';

PHP;

    $output = $this->capture(static fn() => tunnel_write_settings($file));

    $this->assertStringContainsString('Adding the tunnel settings to ' . $file . '.', $output);
    $this->assertStringContainsString('Tunnel settings added.', $output);
    $this->assertSame($expected, file_get_contents($file));
    $this->assertSame(0644, fileperms($file) & 0777);

    $output = $this->capture(static fn() => tunnel_write_settings($file));

    $this->assertSame('', $output);
    $this->assertSame($expected, file_get_contents($file));
  }

  public function testTunnelWriteSettingsWriteFailure(): void {
    $file = 'build/web/sites/default/settings.php';
    mkdir(dirname($file), 0755, TRUE);
    file_put_contents($file, "<?php\n");
    $this->registerMock('file_put_contents', 'DrevOps\\Eddy\\DevTools', fn(): false => FALSE);
    $this->mockQuit(1);

    ob_start();
    try {
      tunnel_write_settings($file);
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(1, $e->getCode());
    }
    finally {
      $output = (string) ob_get_clean();
    }

    $this->assertStringContainsString('Unable to write ' . $file . '.', $output);
  }

  /**
   * @param array<int, string> $expected
   *   The commands expected to run.
   */
  #[DataProvider('dataProviderTunnelKill')]
  public function testTunnelKill(int $pid, array $expected): void {
    $commands = [];
    $this->registerMock('passthru', 'DrevOps\\Eddy\\DevTools', function (string $command) use (&$commands): void {
      $commands[] = $command;
    });

    tunnel_kill($pid);

    $this->assertSame($expected, $commands);
  }

  public static function dataProviderTunnelKill(): \Iterator {
    yield 'process' => [4242, ['kill 4242 >/dev/null 2>&1']];
    // 'kill' signals a process group for 0 and negative IDs.
    yield 'zero' => [0, []];
    yield 'negative' => [-1, []];
  }

  /**
   * Build the 'cloudflared' log lines that announce a quick tunnel.
   */
  protected static function banner(string $url): string {
    return implode(PHP_EOL, [
      '2026-10-08T00:00:00Z INF Requesting new quick Tunnel on trycloudflare.com...',
      '2026-10-08T00:00:01Z INF |  Your quick Tunnel has been created! Visit it at (it may take some time to be reachable):  |',
      '2026-10-08T00:00:01Z INF |  ' . $url . '  |',
    ]) . PHP_EOL;
  }

  /**
   * Run a callback and return its output.
   */
  protected function capture(callable $callback): string {
    ob_start();

    try {
      $callback();
    }
    finally {
      $output = (string) ob_get_clean();
    }

    return $output;
  }

  /**
   * Mock shell_exec() with the output of each expected command.
   *
   * @param array<string, string|null> $outputs
   *   Output keyed by command. Any other command fails the test.
   * @param string|null $log
   *   Log content written when the tunnel is launched, the way 'cloudflared'
   *   prints its banner.
   */
  protected function mockShellExec(array $outputs, ?string $log = NULL): void {
    $this->registerMock('shell_exec', 'DrevOps\\Eddy\\DevTools', function (string $command) use ($outputs, $log): ?string {
      if (!array_key_exists($command, $outputs)) {
        throw new \RuntimeException(sprintf('shell_exec() called with unexpected command "%s".', $command));
      }

      if ($log !== NULL && $command === self::LAUNCH) {
        file_put_contents('.logs/cloudflared.log', $log);
      }

      return $outputs[$command];
    });
  }

  /**
   * Mock sleep() and count the calls.
   *
   * @param int $count
   *   Populated by reference with the number of calls.
   * @param array<int, string> $logs
   *   Log content written by call number, the way 'cloudflared' prints its
   *   banner while the command waits.
   */
  protected function mockSleepCounted(int &$count, array $logs = []): void {
    $this->registerMock('sleep', 'DrevOps\\Eddy\\DevTools', function () use (&$count, $logs): int {
      $count++;

      if (isset($logs[$count])) {
        file_put_contents('.logs/cloudflared.log', $logs[$count]);
      }

      return 0;
    });
  }

}
