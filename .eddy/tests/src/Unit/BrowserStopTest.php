<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use DrevOps\Eddy\Tests\Exceptions\QuitErrorException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the 'eddy-browser-stop' command.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class BrowserStopTest extends UnitTestCase {

  protected const string ROOT = '/test/project';

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';
    $this->envUnset('WEBDRIVER_PORT');
    $this->envUnset('WEBDRIVER_BACKEND');
    $this->registerMock('getcwd', 'DrevOps\\Eddy\\Tooling', fn(): string => self::ROOT);
  }

  #[DataProvider('dataProviderBrowserStop')]
  public function testBrowserStop(array $env, ?string $dotenv, bool $docker, string $expected_port): void {
    foreach ($env as $name => $value) {
      $this->envSet($name, $value);
    }

    $this->registerMock('file_exists', 'DrevOps\\Eddy\\Tooling', fn(string $file): bool => $dotenv !== NULL && $file === '.env');
    $this->registerMock('file_get_contents', 'DrevOps\\Eddy\\Tooling', fn(string $file): string|false => $file === '.env' && $dotenv !== NULL ? $dotenv : FALSE);

    $written = FALSE;
    $this->registerMock('file_put_contents', 'DrevOps\\Eddy\\Tooling', function (string $file, string $contents) use (&$written): int {
      $written = TRUE;

      return strlen($contents);
    });

    $probed = FALSE;
    $this->registerMock('stream_socket_client', 'DrevOps\\Eddy\\Tooling', function () use (&$probed): false {
      $probed = TRUE;

      return FALSE;
    });

    $this->mockCommandAvailable('docker', $docker);

    // The container and the chromedriver of this project on the resolved
    // port.
    $this->mockPortListeners($expected_port, [1234 => ['/usr/local/bin/chromedriver --port=' . $expected_port, self::ROOT]], $docker ? [self::seleniumInspectCommand($expected_port) => self::seleniumInspectOutput($expected_port, self::ROOT)] : []);

    $commands = [];
    if ($docker) {
      $commands[] = ['cmd' => sprintf('docker rm -f %s >/dev/null 2>&1', escapeshellarg('selenium-' . $expected_port))];
    }
    $commands[] = ['cmd' => self::killProcessesCommand(1234)];
    $this->mockPassthruMultiple($commands);

    $this->mockSleep();

    ob_start();
    require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-browser-stop';
    $output = (string) ob_get_clean();

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
   */
  #[DataProvider('dataProviderBrowserStopStopsOnlyOwnBackend')]
  public function testBrowserStopStopsOnlyOwnBackend(?string $container, array $listeners, bool $expected_removed, array $expected_killed, array $expected_notes): void {
    $this->envSet('WEBDRIVER_PORT', '4444');
    $this->mockCommandAvailable('docker', TRUE);
    $this->mockPortListeners('4444', $listeners, [self::seleniumInspectCommand('4444') => $container === NULL ? '' : self::seleniumInspectOutput('4444', $container)]);

    $commands = [];
    if ($expected_removed) {
      $commands[] = ['cmd' => "docker rm -f 'selenium-4444' >/dev/null 2>&1"];
    }
    if ($expected_killed !== []) {
      $commands[] = ['cmd' => self::killProcessesCommand(...$expected_killed)];
    }
    $this->mockPassthruMultiple($commands);

    $this->mockSleep();

    ob_start();
    require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-browser-stop';
    $output = (string) ob_get_clean();

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
    $own_driver = ['/usr/local/bin/chromedriver --port=4444', self::ROOT];
    $other_driver = ['/usr/local/bin/chromedriver --port=4444', '/www/other'];
    $docker_backend = ['/Applications/Docker.app/Contents/MacOS/com.docker.backend', '/'];

    yield 'nothing runs' => [NULL, [], FALSE, [], []];
    yield 'the chromedriver of the project' => [NULL, [1234 => $own_driver], FALSE, [1234], []];
    yield 'the chromedriver of the project with a child on the port' => [NULL, [1234 => $own_driver, 1235 => $own_driver], FALSE, [1234, 1235], []];
    yield 'a process whose working directory cannot be read' => [NULL, [1234 => ['/usr/local/bin/chromedriver --port=4444', '']], FALSE, [1234], []];
    yield 'the chromedriver of another project' => [NULL, [5678 => $other_driver], FALSE, [], ['Port 4444 is still in use by another process, which was left running:', '5678 /usr/local/bin/chromedriver --port=4444']];
    yield 'the chromedriver of the project and of another project' => [NULL, [1234 => $own_driver, 5678 => $other_driver], FALSE, [1234], ['5678 /usr/local/bin/chromedriver --port=4444']];
    yield 'the container of the project' => [self::ROOT, [], TRUE, [], []];
    yield 'a container without the project label' => ['', [], TRUE, [], []];
    yield 'the container of another project' => ['/www/other', [9999 => $docker_backend], FALSE, [], ['The Selenium container selenium-4444 belongs to /www/other and was left running.', '9999 /Applications/Docker.app/Contents/MacOS/com.docker.backend']];
  }

  public function testBrowserStopFailsOnInvalidPort(): void {
    $this->envSet('WEBDRIVER_PORT', '99999');
    $this->registerMock('file_exists', 'DrevOps\\Eddy\\Tooling', fn(): false => FALSE);
    $this->mockCommandAvailable('docker', TRUE);
    $this->mockPassthruNever();
    $this->mockQuit(1);

    ob_start();
    try {
      require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-browser-stop';
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(1, $e->getCode());
    }
    finally {
      $output = (string) ob_get_clean();
    }

    $this->assertStringContainsString('Invalid WEBDRIVER_PORT "99999"', $output);
    $this->assertStringNotContainsString('BROWSER STOPPED', $output);
  }

}
