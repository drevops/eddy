<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use DrevOps\Eddy\Tests\Exceptions\QuitErrorException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the 'eddy-browser-start' command.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class BrowserStartTest extends UnitTestCase {

  protected string $envFileContent = "WEBDRIVER_PORT=4444\n";

  protected string $installLogContent = "chromedriver@150.0.7871.129 /cache/chromedriver\n";

  /**
   * @var array<int, bool>
   */
  protected array $readySequence = [FALSE];

  protected int $readyIndex = 0;

  /**
   * @var array<int, string>
   */
  protected array $createdDirs = [];

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';
    $this->envUnset('WEBDRIVER_PORT');
    $this->envUnset('WEBDRIVER_BACKEND');

    // The mocked '.env' supplies port 4444, so the port resolves without the
    // free-port probe.
    $this->registerMock('file_exists', 'DrevOps\\Eddy\\DevTools', fn(string $f): bool => $f === '.env');
    $this->registerMock('file_get_contents', 'DrevOps\\Eddy\\DevTools', function (string $f): string|false {
      if ($f === '.env') {
        return $this->envFileContent;
      }

      if (str_contains($f, 'install.log')) {
        return $this->installLogContent === '' ? FALSE : $this->installLogContent;
      }

      if (str_starts_with($f, 'http://localhost:')) {
        $ready = $this->readySequence[$this->readyIndex] ?? end($this->readySequence);
        $this->readyIndex++;

        return $ready ? '{"value": {"ready": true, "message": "ready"}}' : FALSE;
      }

      return FALSE;
    });
    $this->registerMock('mkdir', 'DrevOps\\Eddy\\DevTools', function (string $dir): true {
      $this->createdDirs[] = $dir;

      return TRUE;
    });
    $this->mockSleep();
  }

  public function testBrowserStartFailsOnUnknownBackend(): void {
    $this->envSet('WEBDRIVER_BACKEND', 'firefox');
    $commands = [];
    $this->recordPassthru($commands);

    $output = $this->runBrowserStart(1);

    $this->assertStringContainsString("Unknown WEBDRIVER_BACKEND 'firefox'", $output);
    $this->assertSame([], $commands, 'An unknown backend must fail before any process is started or removed.');
  }

  public function testBrowserStartFailsOnInvalidPort(): void {
    $this->envSet('WEBDRIVER_PORT', '99999');
    $commands = [];
    $this->recordPassthru($commands);

    $output = $this->runBrowserStart(1);

    $this->assertStringContainsString('Invalid WEBDRIVER_PORT "99999"', $output);
    $this->assertSame([], $commands, 'An invalid port must fail before any process is started or removed.');
  }

  public function testBrowserStartAlreadyRunning(): void {
    $this->mockReady([TRUE]);

    $output = $this->runBrowserStart();

    $this->assertStringContainsString('START BROWSER', $output);
    $this->assertStringContainsString('chromedriver is already running on port 4444', $output);
  }

  public function testBrowserStartUsesMatchingInstalledDriver(): void {
    $this->mockChrome(FALSE);
    $this->mockCommands(['google-chrome' => '/usr/bin/google-chrome', 'chromedriver' => '/usr/local/bin/chromedriver']);
    $this->mockVersions('Google Chrome 150.0.7871.129', 'ChromeDriver 150.0.7871.129 (abc)');
    $this->mockReady([FALSE, TRUE]);
    $commands = [];
    $this->recordPassthru($commands);

    $output = $this->runBrowserStart();

    $this->assertStringContainsString('Using installed chromedriver at /usr/local/bin/chromedriver', $output);
    $this->assertStringContainsString('chromedriver is ready on port 4444', $output);
    $this->assertNotEmpty($commands);
    $this->assertStringContainsString('nohup', $commands[0]);
    $this->assertStringContainsString('--port=', $commands[0]);
    $this->assertStringContainsString(">'.logs/chromedriver.log' 2>&1", $commands[0], 'chromedriver must log to the project logs directory.');
    $this->assertContains('.logs', $this->createdDirs, 'The logs directory must exist before chromedriver is launched.');
    foreach ($commands as $command) {
      $this->assertStringNotContainsString('npx', $command, 'A matching installed driver must not trigger an npx download.');
    }
  }

  public function testBrowserStartFallsBackToNpxOnVersionMismatch(): void {
    $this->mockChrome(FALSE);
    $this->mockCommands(['google-chrome' => '/usr/bin/google-chrome', 'chromedriver' => '/usr/local/bin/chromedriver', 'npx' => '/usr/bin/npx']);
    $this->mockVersions('Google Chrome 150.0.7871.129', 'ChromeDriver 151.0.7922.34 (abc)');
    $this->mockReady([FALSE, TRUE]);
    $commands = [];
    $this->recordPassthru($commands);

    $output = $this->runBrowserStart();

    $this->assertStringContainsString('does not match Chrome 150.0.7871.129', $output);
    $this->assertStringContainsString('chromedriver is ready on port 4444', $output);
    $this->assertTrue((bool) array_filter($commands, fn(string $c): bool => str_contains($c, 'npx')), 'A version mismatch must trigger an npx download.');
    $this->assertTrue((bool) array_filter($commands, fn(string $c): bool => str_contains($c, 'nohup') && str_contains($c, '/cache/chromedriver')), 'The freshly fetched binary must be launched.');
  }

  public function testBrowserStartUsesMacChromeWhenPresent(): void {
    $this->mockChrome(TRUE);
    $this->mockCommands(['chromedriver' => '/usr/local/bin/chromedriver']);
    $this->mockVersions('Google Chrome 150.0.7871.129', 'ChromeDriver 150.0.7871.129 (abc)');
    $this->mockReady([FALSE, TRUE]);
    $commands = [];
    $this->recordPassthru($commands);

    $output = $this->runBrowserStart();

    $this->assertStringContainsString('chromedriver is ready on port 4444', $output);
  }

  public function testBrowserStartFailsWhenChromeMissing(): void {
    $this->mockChrome(FALSE);
    $this->mockCommands([]);
    $this->mockVersions('', '');
    $this->mockReady([FALSE]);

    $output = $this->runBrowserStart(1);

    $this->assertStringContainsString('Google Chrome or Chromium was not found', $output);
  }

  public function testBrowserStartFailsWhenMismatchAndNoNpx(): void {
    $this->mockChrome(FALSE);
    $this->mockCommands(['google-chrome' => '/usr/bin/google-chrome', 'chromedriver' => '/usr/local/bin/chromedriver']);
    $this->mockVersions('Google Chrome 150.0.7871.129', 'ChromeDriver 151.0.7922.34 (abc)');
    $this->mockReady([FALSE]);

    $output = $this->runBrowserStart(1);

    $this->assertStringContainsString('npx is unavailable', $output);
  }

  public function testBrowserStartFailsWhenNpxInstallFails(): void {
    $this->mockChrome(FALSE);
    $this->mockCommands(['google-chrome' => '/usr/bin/google-chrome', 'npx' => '/usr/bin/npx']);
    $this->mockVersions('Google Chrome 150.0.7871.129', '');
    $this->mockReady([FALSE]);
    $commands = [];
    $this->recordPassthru($commands, npx_exit: 1);

    $output = $this->runBrowserStart(1);

    $this->assertStringContainsString('Failed to install chromedriver', $output);
  }

  public function testBrowserStartFailsWhenBinaryPathUnresolved(): void {
    $this->installLogContent = '';
    $this->mockChrome(FALSE);
    $this->mockCommands(['google-chrome' => '/usr/bin/google-chrome', 'npx' => '/usr/bin/npx']);
    $this->mockVersions('Google Chrome 150.0.7871.129', '');
    $this->mockReady([FALSE]);
    $commands = [];
    $this->recordPassthru($commands);

    $output = $this->runBrowserStart(1);

    $this->assertStringContainsString('Could not determine the chromedriver binary path', $output);
  }

  public function testBrowserStartFailsWhenNeverReady(): void {
    $this->mockChrome(FALSE);
    $this->mockCommands(['google-chrome' => '/usr/bin/google-chrome', 'chromedriver' => '/usr/local/bin/chromedriver']);
    $this->mockVersions('Google Chrome 150.0.7871.129', 'ChromeDriver 150.0.7871.129 (abc)');
    $this->mockReady([FALSE]);
    $commands = [];
    $this->recordPassthru($commands);

    $output = $this->runBrowserStart(1);

    $this->assertStringContainsString('failed to become ready on port 4444 after 30 seconds; see .logs/chromedriver.log.', $output);
  }

  public function testBrowserStartAutoDiscoversPort(): void {
    $this->envFileContent = "WEBSERVER_PORT=8000\n";
    $this->mockChrome(FALSE);
    $this->mockCommands(['google-chrome' => '/usr/bin/google-chrome', 'chromedriver' => '/usr/local/bin/chromedriver']);
    $this->mockVersions('Google Chrome 150.0.7871.129', 'ChromeDriver 150.0.7871.129 (abc)');
    $this->mockPortsInUse([4444]);
    $persisted = NULL;
    $this->recordDotenvWrites($persisted);
    $this->mockReady([FALSE, TRUE]);
    $commands = [];
    $this->recordPassthru($commands);

    $output = $this->runBrowserStart();

    $this->assertStringContainsString('chromedriver is ready on port 4445', $output);
    $this->assertSame('4445', $persisted, 'The discovered port must be persisted to .env.');
    $this->assertNotEmpty($commands);
    $this->assertStringContainsString("--port='4445'", $commands[0], 'chromedriver must bind the discovered port, not the occupied default.');
  }

  public function testBrowserStartSeleniumAlreadyRunning(): void {
    $this->envSet('WEBDRIVER_BACKEND', 'selenium');
    $this->mockReady([TRUE]);

    $output = $this->runBrowserStart();

    $this->assertStringContainsString('Selenium is already running on port 4444', $output);
  }

  public function testBrowserStartSeleniumStartsContainer(): void {
    $this->envSet('WEBDRIVER_BACKEND', 'selenium');
    $this->mockCommands(['docker' => '/usr/bin/docker']);
    $this->mockReady([FALSE, TRUE]);
    $commands = [];
    $this->recordPassthru($commands);

    $output = $this->runBrowserStart();

    $this->assertStringContainsString('Selenium is ready on port 4444', $output);
    $this->assertTrue((bool) array_filter($commands, fn(string $c): bool => str_contains($c, 'docker rm -f')), 'A stale container must be removed before starting a new one.');
    $run_commands = array_filter($commands, fn(string $c): bool => str_contains($c, 'docker run'));
    $this->assertNotEmpty($run_commands, 'The Selenium container must be started.');
    $run_command = (string) reset($run_commands);
    $this->assertStringContainsString("'4444':4444", $run_command, 'The container must publish the resolved WebDriver port.');
    $this->assertStringContainsString('--shm-size=2g', $run_command, 'The container must get the shared memory size Chromium needs.');
    $this->assertStringContainsString('standalone-chromium', $run_command);
  }

  public function testBrowserStartSeleniumAutoDiscoversPort(): void {
    $this->envFileContent = "WEBSERVER_PORT=8000\n";
    $this->envSet('WEBDRIVER_BACKEND', 'selenium');
    $this->mockCommands(['docker' => '/usr/bin/docker']);
    $this->mockPortsInUse([4444]);
    $persisted = NULL;
    $this->recordDotenvWrites($persisted);
    $this->mockReady([FALSE, TRUE]);
    $commands = [];
    $this->recordPassthru($commands);

    $output = $this->runBrowserStart();

    $this->assertStringContainsString('Selenium is ready on port 4445', $output);
    $this->assertSame('4445', $persisted, 'The discovered port must be persisted to .env.');
    $run_commands = array_filter($commands, fn(string $c): bool => str_contains($c, 'docker run'));
    $this->assertNotEmpty($run_commands, 'The Selenium container must be started.');
    $run_command = (string) reset($run_commands);
    $this->assertStringContainsString("'4445':4444", $run_command, 'The container must publish the discovered port, not the occupied default.');
    $this->assertStringContainsString("--name 'selenium-4445'", $run_command, "The container name must be scoped to the endpoint port, or a second project removes the first project's container.");
    $this->assertTrue((bool) array_filter($commands, fn(string $c): bool => str_contains($c, "docker rm -f 'selenium-4445'")), "Only this project's stale container may be removed before starting a new one.");
  }

  public function testBrowserStartSeleniumFailsWithoutDocker(): void {
    $this->envSet('WEBDRIVER_BACKEND', 'selenium');
    $this->mockCommands([]);
    $this->mockReady([FALSE]);

    $output = $this->runBrowserStart(1);

    $this->assertStringContainsString('docker was not found', $output);
  }

  public function testBrowserStartSeleniumFailsWhenDockerRunFails(): void {
    $this->envSet('WEBDRIVER_BACKEND', 'selenium');
    $this->mockCommands(['docker' => '/usr/bin/docker']);
    $this->mockReady([FALSE]);
    $commands = [];
    $this->recordPassthru($commands, docker_run_exit: 1);

    $output = $this->runBrowserStart(1);

    $this->assertStringContainsString('Failed to start the Selenium container', $output);
  }

  public function testBrowserStartSeleniumFailsWhenNeverReady(): void {
    $this->envSet('WEBDRIVER_BACKEND', 'selenium');
    $this->mockCommands(['docker' => '/usr/bin/docker']);
    $this->mockReady([FALSE]);
    $commands = [];
    $this->recordPassthru($commands);

    $output = $this->runBrowserStart(1);

    $this->assertStringContainsString('Selenium failed to become ready on port 4444', $output);
  }

  /**
   * Run the command and return its output.
   *
   * The completion banner is asserted here for every run: it is printed when
   * the backend is ready and never on a failure.
   */
  protected function runBrowserStart(int $expected_exit = 0): string {
    $this->mockQuit($expected_exit);

    ob_start();
    try {
      require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-browser-start';

      if ($expected_exit !== 0) {
        $this->fail('Expected eddy-browser-start to fail.');
      }
    }
    catch (QuitErrorException $e) {
      $this->assertSame($expected_exit, $e->getCode());
    }
    finally {
      $output = (string) ob_get_clean();
    }

    if ($expected_exit === 0) {
      $this->assertStringContainsString('BROWSER READY', $output);
    }
    else {
      $this->assertStringNotContainsString('BROWSER READY', $output);
    }

    return $output;
  }

  /**
   * @param array<int, bool> $sequence
   *   Readiness result per call; the last value repeats for further calls.
   */
  protected function mockReady(array $sequence): void {
    $this->readySequence = $sequence;
    $this->readyIndex = 0;
  }

  /**
   * @param array<int, int> $ports
   *   Ports that answer a connect probe; every other port reads as free.
   */
  protected function mockPortsInUse(array $ports): void {
    $this->registerMock('stream_socket_client', 'DrevOps\\Eddy\\DevTools', function (string $address, &$errno = NULL, &$errstr = NULL, ?float $timeout = NULL) use ($ports) {
      if (preg_match('/:(\d+)$/', $address, $matches) !== 1 || !in_array((int) $matches[1], $ports, TRUE)) {
        $errno = 61;
        $errstr = 'Connection refused';

        return FALSE;
      }

      $stream = fopen('php://memory', 'r');
      if ($stream === FALSE) {
        throw new \RuntimeException('Unable to open php://memory stream for mock.');
      }

      return $stream;
    });
  }

  /**
   * @param string|null $persisted
   *   Populated by reference with the WEBDRIVER_PORT value written to '.env'.
   */
  protected function recordDotenvWrites(?string &$persisted): void {
    $this->registerMock('file_put_contents', 'DrevOps\\Eddy\\DevTools', function (string $file, string $body) use (&$persisted): int {
      if (preg_match('/WEBDRIVER_PORT=(\d+)/', $body, $matches) === 1) {
        $persisted = $matches[1];
      }

      return strlen($body);
    });
  }

  protected function mockChrome(bool $mac_present): void {
    $this->registerMock('is_executable', 'DrevOps\\Eddy\\DevTools', fn(): bool => $mac_present);
  }

  /**
   * @param array<string, string> $map
   *   Command name => resolved path for commands that are present.
   */
  protected function mockCommands(array $map): void {
    $this->registerMock('exec', 'DrevOps\\Eddy\\DevTools', function (string $cmd, &$output = NULL, &$result_code = NULL) use ($map): string {
      $output = [];
      $result_code = 1;
      if (preg_match('/command -v (\S+)/', $cmd, $matches) === 1 && isset($map[$matches[1]])) {
        $output = [$map[$matches[1]]];
        $result_code = 0;
      }

      return '';
    });
  }

  protected function mockVersions(string $chrome, string $driver): void {
    $this->registerMock('shell_exec', 'DrevOps\\Eddy\\DevTools', function (string $cmd) use ($chrome, $driver): string {
      if (str_contains($cmd, 'chromedriver')) {
        return $driver;
      }
      if (str_contains($cmd, 'hrome') || str_contains($cmd, 'hromium')) {
        return $chrome;
      }

      return '';
    });
  }

  /**
   * @param array<int, string> $commands
   *   Populated by reference with every passthru() command line.
   */
  protected function recordPassthru(array &$commands, int $npx_exit = 0, int $docker_run_exit = 0): void {
    $this->registerMock('passthru', 'DrevOps\\Eddy\\DevTools', function (string $cmd, &$result_code = NULL) use (&$commands, $npx_exit, $docker_run_exit): void {
      $commands[] = $cmd;
      if (str_contains($cmd, 'npx')) {
        $result_code = $npx_exit;
      }
      if (str_contains($cmd, 'docker run')) {
        $result_code = $docker_run_exit;
      }
    });
  }

}
