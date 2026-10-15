<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class BrowserStopTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    self::envUnset('WEBDRIVER_PORT');
    self::envUnset('WEBDRIVER_BACKEND');
    $this->mockFunction('getcwd', fn(): string => self::PROJECT_ROOT);
  }

  #[DataProvider('dataProviderBrowserStop')]
  public function testBrowserStop(array $env, ?string $dotenv, bool $docker, string $expected_port): void {
    self::envSetMultiple($env);

    $this->mockIsFile(['.env' => $dotenv !== NULL]);
    $this->mockFunction('file_get_contents', fn(string $file): string|false => $file === '.env' && $dotenv !== NULL ? $dotenv : FALSE);

    $written = FALSE;
    $this->mockFunction('file_put_contents', function (string $file, string $content) use (&$written): int {
      $written = TRUE;

      return strlen($content);
    });

    $probed = FALSE;
    $this->mockFunction('stream_socket_client', function () use (&$probed): false {
      $probed = TRUE;

      return FALSE;
    });

    $this->mockCommandAvailable('docker', $docker);

    $this->mockPortListeners($expected_port, [1234 => ['/usr/local/bin/chromedriver --port=' . $expected_port, self::PROJECT_ROOT]], $docker ? self::seleniumContainerOutputs($expected_port, self::PROJECT_ROOT) : []);

    $commands = [];
    if ($docker) {
      $commands[] = ['cmd' => self::seleniumRemoveCommand($expected_port)];
    }
    $commands[] = ['cmd' => self::killProcessesCommand(1234)];
    $this->mockPassthruMultiple($commands);

    $this->mockSleep();

    $output = $this->runCommand('eddy-browser-stop');

    $this->assertStringContainsString('STOP BROWSER', $output);
    $this->assertStringContainsString('Browser stopped on port ' . $expected_port . '.', $output);
    $this->assertStringContainsString('BROWSER STOPPED', $output);
    $this->assertFalse($written, 'Stopping must not persist a port to .env.');
    $this->assertFalse($probed, 'Stopping must target the resolved port rather than discover a free one.');
  }

  public static function dataProviderBrowserStop(): \Iterator {
    yield 'default port, docker installed' => [
      'env' => [],
      'dotenv' => NULL,
      'docker' => TRUE,
      'expected_port' => '4444',
    ];
    yield 'default port, docker not installed' => [
      'env' => [],
      'dotenv' => NULL,
      'docker' => FALSE,
      'expected_port' => '4444',
    ];
    yield 'port from .env' => [
      'env' => [],
      'dotenv' => "WEBDRIVER_PORT=4445\n",
      'docker' => TRUE,
      'expected_port' => '4445',
    ];
    yield 'port from env overrides .env' => [
      'env' => ['WEBDRIVER_PORT' => '4450'],
      'dotenv' => "WEBDRIVER_PORT=4445\n",
      'docker' => TRUE,
      'expected_port' => '4450',
    ];
    yield 'chromedriver backend still removes the container' => [
      'env' => ['WEBDRIVER_BACKEND' => 'chromedriver'],
      'dotenv' => "WEBDRIVER_PORT=4445\n",
      'docker' => TRUE,
      'expected_port' => '4445',
    ];
    yield 'selenium backend without docker still frees the port' => [
      'env' => ['WEBDRIVER_BACKEND' => 'selenium'],
      'dotenv' => "WEBDRIVER_PORT=4445\n",
      'docker' => FALSE,
      'expected_port' => '4445',
    ];
    yield 'unknown backend is not validated' => [
      'env' => ['WEBDRIVER_BACKEND' => 'firefox'],
      'dotenv' => "WEBDRIVER_PORT=4445\n",
      'docker' => TRUE,
      'expected_port' => '4445',
    ];
  }

  /**
   * @param string|null $container
   *   The project label of the Selenium container on the port, '' for a
   *   container without the label, or NULL for no container.
   * @param array<int, array{0: string, 1: string}> $listeners
   *   The command line and the working directory of each process listening on
   *   the port, keyed by process ID.
   * @param bool $expected_removed
   *   Whether the container is removed.
   * @param array<int, int> $expected_killed
   *   The process IDs that are killed.
   * @param array<int, string> $expected_notes
   *   Lines the output reports about what was left running.
   * @param bool $running
   *   Whether the container is running.
   */
  #[DataProvider('dataProviderBrowserStopStopsOnlyOwnBackend')]
  public function testBrowserStopStopsOnlyOwnBackend(?string $container, array $listeners, bool $expected_removed, array $expected_killed, array $expected_notes, bool $running = TRUE): void {
    self::envSet('WEBDRIVER_PORT', '4444');
    $this->mockCommandAvailable('docker', TRUE);
    $this->mockPortListeners('4444', $listeners, self::seleniumContainerOutputs('4444', $container, $running));

    $commands = [];
    if ($expected_removed) {
      $commands[] = ['cmd' => self::seleniumRemoveCommand('4444')];
    }
    if ($expected_killed !== []) {
      $commands[] = ['cmd' => self::killProcessesCommand(...$expected_killed)];
    }
    $this->mockPassthruMultiple($commands);

    $this->mockSleep();

    $output = $this->runCommand('eddy-browser-stop');

    $this->assertStringContainsString('Browser stopped on port 4444.', $output);
    $this->assertStringContainsString('BROWSER STOPPED', $output);

    foreach ($expected_notes as $note) {
      $this->assertStringContainsString($note, $output);
    }

    if ($expected_notes === []) {
      $this->assertStringNotContainsString('left running', $output);
    }
  }

  public static function dataProviderBrowserStopStopsOnlyOwnBackend(): \Iterator {
    $own_driver = ['/usr/local/bin/chromedriver --port=4444', self::PROJECT_ROOT];
    $other_driver = ['/usr/local/bin/chromedriver --port=4444', '/www/other'];
    $docker_backend = ['/Applications/Docker.app/Contents/MacOS/com.docker.backend', '/'];

    yield 'nothing runs' => [NULL, [], FALSE, [], []];
    yield 'the chromedriver of the project' => [NULL, [1234 => $own_driver], FALSE, [1234], []];
    yield 'the chromedriver of the project with a child on the port' => [NULL, [1234 => $own_driver, 1235 => $own_driver], FALSE, [1234, 1235], []];
    yield 'a process whose working directory cannot be read' => [NULL, [5678 => ['/usr/local/bin/chromedriver --port=4444', '']], FALSE, [], ['Port 4444 is still in use by another process, which was left running:', '5678 /usr/local/bin/chromedriver --port=4444']];
    yield 'the chromedriver of another project' => [NULL, [5678 => $other_driver], FALSE, [], ['Port 4444 is still in use by another process, which was left running:', '5678 /usr/local/bin/chromedriver --port=4444']];
    yield 'another program started from the project root' => [NULL, [5678 => ['node server.js --port=4444', self::PROJECT_ROOT]], FALSE, [], ['Port 4444 is still in use by another process, which was left running:', '5678 node server.js --port=4444']];
    yield 'the chromedriver of the project and of another project' => [NULL, [1234 => $own_driver, 5678 => $other_driver], FALSE, [1234], ['5678 /usr/local/bin/chromedriver --port=4444']];
    yield 'the container of the project' => [self::PROJECT_ROOT, [], TRUE, [], []];
    yield 'a container without the project label' => ['', [], TRUE, [], []];
    yield 'the container of another project' => ['/www/other', [9999 => $docker_backend], FALSE, [], ['The Selenium container selenium-4444 belongs to /www/other and was left running.', '9999 /Applications/Docker.app/Contents/MacOS/com.docker.backend']];
    yield 'a stopped container of the project' => [self::PROJECT_ROOT, [], TRUE, [], [], FALSE];
    yield 'a stopped container of another project' => ['/www/other', [], FALSE, [], [], FALSE];
  }

  public function testBrowserStopFailsOnInvalidPort(): void {
    self::envSet('WEBDRIVER_PORT', '99999');
    $this->mockIsFile(['.env' => FALSE]);
    $this->mockCommandAvailable('docker', TRUE);
    $this->mockPassthruNever();
    $this->mockQuit(1);

    $output = $this->runCommand('eddy-browser-stop', 1);

    $this->assertStringContainsString('Invalid WEBDRIVER_PORT "99999"', $output);
    $this->assertStringNotContainsString('BROWSER STOPPED', $output);
  }

}
