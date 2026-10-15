<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the 'eddy-browser-start' command.
 */
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class BrowserStartTest extends UnitTestCase {

  /**
   * The '/status' response body of each WebDriver server, keyed by server.
   *
   * Modelled on the responses of ChromeDriver 155, Selenium 4.44 and
   * geckodriver, which stands for a server of neither backend.
   */
  protected const array STATUS_BODIES = [
    'chromedriver' => '{"value":{"build":{"version":"150.0.7871.129"},"message":"ChromeDriver ready for new sessions.","os":{"arch":"arm64","name":"Mac OS X","version":"26.6.2"},"ready":true}}',
    'selenium' => '{"value": {"nodes": [{"availability": "UP", "version": "4.44.0 (revision da2039bd14*)"}], "ready": true, "message": "Selenium Grid ready."}}',
    'selenium booting' => '{"value": {"nodes": [], "ready": false, "message": "Selenium Grid not ready."}}',
    'geckodriver' => '{"value":{"message":"","ready":true}}',
  ];

  protected string $envFileContent = "WEBDRIVER_PORT=4444\n";

  protected string $installLogContent = "chromedriver@150.0.7871.129 /cache/chromedriver\n";

  /**
   * The '/status' response body of each probe, or FALSE for no answer.
   *
   * @var array<int, string|false>
   */
  protected array $statusBodies = [FALSE];

  protected int $statusIndex = 0;

  /**
   * @var array<int, string>
   */
  protected array $createdDirs = [];

  /**
   * @var array<int, array<int, string>>
   */
  protected array $probes = [];

  protected bool $probeHangs = FALSE;

  protected function setUp(): void {
    parent::setUp();
    self::envUnset('WEBDRIVER_PORT');
    self::envUnset('WEBDRIVER_BACKEND');

    $this->mockFunction('getcwd', fn(): string => self::PROJECT_ROOT);

    // Nothing listens on either port the tests resolve, and neither has a
    // Selenium container. A test replaces these outputs to add either.
    $this->mockPortListeners('4444', [], self::seleniumContainerOutputs('4444', NULL));
    $this->mockPortListeners('4445', [], self::seleniumContainerOutputs('4445', NULL));

    // The mocked '.env' supplies port 4444, so the port resolves without the
    // free-port probe.
    $this->mockFunction('file_exists', fn(string $file): bool => $file === '.env');
    $this->mockFunction('file_get_contents', function (string $file): string|false {
      if ($file === '.env') {
        return $this->envFileContent;
      }

      if (str_contains($file, 'install.log')) {
        return $this->installLogContent === '' ? FALSE : $this->installLogContent;
      }

      if (str_starts_with($file, 'http://localhost:')) {
        $body = $this->statusBodies[$this->statusIndex] ?? end($this->statusBodies);
        $this->statusIndex++;

        return $body;
      }

      return FALSE;
    });
    $this->mockFunction('mkdir', function (string $dir): true {
      $this->createdDirs[] = $dir;

      return TRUE;
    });
    $this->mockSleep();
  }

  #[DataProvider('dataProviderBrowserStartFailsOnInvalidEnvironment')]
  public function testBrowserStartFailsOnInvalidEnvironment(string $name, string $value, string $expected_message, string $invalid_input): void {
    self::envSet($name, $value);
    $commands = [];
    $this->recordPassthru($commands);

    $output = $this->runBrowserStart(1);

    $this->assertStringContainsString($expected_message, $output);
    $this->assertSame([], $commands, $invalid_input . ' must fail before any process is started or removed.');
  }

  public static function dataProviderBrowserStartFailsOnInvalidEnvironment(): \Iterator {
    yield 'unknown backend' => ['WEBDRIVER_BACKEND', 'firefox', "Unknown WEBDRIVER_BACKEND 'firefox'", 'An unknown backend'];
    yield 'invalid port' => ['WEBDRIVER_PORT', '99999', 'Invalid WEBDRIVER_PORT "99999"', 'An invalid port'];
  }

  /**
   * @param array<string, string> $commands_available
   *   Command name => resolved path for the commands that are present.
   */
  #[DataProvider('dataProviderBrowserStartAlreadyRunning')]
  public function testBrowserStartAlreadyRunning(?string $backend, array $commands_available, string $expected_message): void {
    if ($backend !== NULL) {
      self::envSet('WEBDRIVER_BACKEND', $backend);
    }

    $this->mockCommandPaths($commands_available);
    $this->mockStatus([$backend ?? 'chromedriver']);

    $output = $this->runBrowserStart();

    $this->assertStringContainsString('START BROWSER', $output);
    $this->assertStringContainsString($expected_message, $output);
  }

  public static function dataProviderBrowserStartAlreadyRunning(): \Iterator {
    yield 'chromedriver' => [NULL, [], 'chromedriver is already running on port 4444'];
    yield 'selenium' => ['selenium', ['docker' => '/usr/bin/docker'], 'Selenium is already running on port 4444'];
  }

  /**
   * Only a server whose '/status' body names the selected backend and reports
   * that it accepts sessions is reused.
   *
   * A server that is not reused does not pass for the backend launched in its
   * place either, so the launch times out.
   */
  #[DataProvider('dataProviderBrowserStartReusesOnlyServerOfBackend')]
  public function testBrowserStartReusesOnlyServerOfBackend(string $backend, string|false $body, bool $expected_reused): void {
    self::envSet('WEBDRIVER_BACKEND', $backend);
    $this->mockChrome(FALSE);
    $this->mockCommandPaths(['docker' => '/usr/bin/docker', 'google-chrome' => '/usr/bin/google-chrome', 'chromedriver' => '/usr/local/bin/chromedriver']);
    $this->mockVersions('Google Chrome 150.0.7871.129', 'ChromeDriver 150.0.7871.129 (abc)');
    $this->mockStatusBodies([$body]);
    $commands = [];
    $this->recordPassthru($commands);

    $output = $this->runBrowserStart($expected_reused ? NULL : 1);

    if ($expected_reused) {
      $this->assertStringContainsString('already running on port 4444', $output);
      $this->assertSame([], $commands, 'A reused server must not be stopped, removed or replaced.');

      return;
    }

    $this->assertStringContainsString('failed to become ready on port 4444 after 30 seconds', $output);
    $this->assertNotSame([], $commands, 'The selected backend must be launched in place of a server that is not reused.');
  }

  public static function dataProviderBrowserStartReusesOnlyServerOfBackend(): \Iterator {
    yield 'chromedriver backend, ChromeDriver' => ['chromedriver', self::STATUS_BODIES['chromedriver'], TRUE];
    yield 'chromedriver backend, Selenium' => ['chromedriver', self::STATUS_BODIES['selenium'], FALSE];
    yield 'chromedriver backend, booting Selenium' => ['chromedriver', self::STATUS_BODIES['selenium booting'], FALSE];
    yield 'chromedriver backend, another WebDriver server' => ['chromedriver', self::STATUS_BODIES['geckodriver'], FALSE];
    yield 'selenium backend, Selenium' => ['selenium', self::STATUS_BODIES['selenium'], TRUE];
    yield 'selenium backend, ChromeDriver' => ['selenium', self::STATUS_BODIES['chromedriver'], FALSE];
    yield 'selenium backend, booting Selenium' => ['selenium', self::STATUS_BODIES['selenium booting'], FALSE];
    yield 'selenium backend, another WebDriver server' => ['selenium', self::STATUS_BODIES['geckodriver'], FALSE];
    yield 'ChromeDriver that is not ready' => ['chromedriver', '{"value":{"message":"ChromeDriver ready for new sessions.","ready":false}}', FALSE];
    yield 'readiness that is not a boolean' => ['chromedriver', '{"value":{"message":"ChromeDriver ready for new sessions.","ready":"true"}}', FALSE];
    yield 'body without a message' => ['chromedriver', '{"value":{"ready":true}}', FALSE];
    yield 'message that is not a string' => ['chromedriver', '{"value":{"message":["ChromeDriver"],"ready":true}}', FALSE];
    yield 'message naming the backend later on' => ['selenium', '{"value":{"message":"Not a Selenium Grid","ready":true}}', FALSE];
    yield 'body without a value' => ['chromedriver', '{"message":"ChromeDriver ready for new sessions.","ready":true}', FALSE];
    yield 'value that is not an object' => ['selenium', '{"value":"Selenium Grid ready."}', FALSE];
    yield 'body that is not JSON' => ['chromedriver', 'ChromeDriver "ready": true', FALSE];
    yield 'empty body' => ['selenium', '', FALSE];
    yield 'chromedriver backend, no answer' => ['chromedriver', FALSE, FALSE];
    yield 'selenium backend, no answer' => ['selenium', FALSE, FALSE];
  }

  public function testBrowserStartReusesOwnChromedriver(): void {
    $this->mockCommandPaths([]);
    $this->mockPortListeners('4444', [1234 => ['/usr/local/bin/chromedriver --port=4444', self::PROJECT_ROOT]]);
    $this->mockStatus(['chromedriver']);
    $commands = [];
    $this->recordPassthru($commands);

    $output = $this->runBrowserStart();

    $this->assertStringContainsString('chromedriver is already running on port 4444', $output);
    $this->assertSame([], $commands, 'A running chromedriver of the project must be reused.');
  }

  public function testBrowserStartRestartsOwnChromedriverThatDoesNotAnswer(): void {
    $this->mockChrome(FALSE);
    $this->mockCommandPaths(['google-chrome' => '/usr/bin/google-chrome', 'chromedriver' => '/usr/local/bin/chromedriver']);
    $this->mockVersions('Google Chrome 150.0.7871.129', 'ChromeDriver 150.0.7871.129 (abc)');
    $this->mockPortListeners('4444', [1234 => ['/usr/local/bin/chromedriver --port=4444', self::PROJECT_ROOT]]);
    $this->mockStatus([NULL, 'chromedriver']);
    $commands = [];
    $this->recordPassthru($commands);

    $output = $this->runBrowserStart();

    $this->assertStringContainsString('Stopping chromedriver on port 4444.', $output);
    $this->assertStringContainsString('chromedriver is ready on port 4444', $output);
    $this->assertSame(self::killProcessesCommand(1234), $commands[0] ?? NULL, 'A chromedriver of the project that does not answer must be stopped first.');
    $this->assertStringContainsString('nohup', $commands[1] ?? '', 'A new chromedriver must be launched after the old one is stopped.');
  }

  /**
   * The running container of the project publishes the port whether or not it
   * accepts sessions, so the chromedriver backend removes it first.
   */
  #[DataProvider('dataProviderBrowserStartChromedriverReplacesOwnContainer')]
  public function testBrowserStartChromedriverReplacesOwnContainer(string $project, ?string $status): void {
    $this->mockChrome(FALSE);
    $this->mockCommandPaths(['docker' => '/usr/bin/docker', 'google-chrome' => '/usr/bin/google-chrome', 'chromedriver' => '/usr/local/bin/chromedriver']);
    $this->mockVersions('Google Chrome 150.0.7871.129', 'ChromeDriver 150.0.7871.129 (abc)');
    $this->mockPortListeners('4444', [9999 => ['/Applications/Docker.app/Contents/MacOS/com.docker.backend', '/']], self::seleniumContainerOutputs('4444', $project));
    $this->mockStatus([$status, 'chromedriver']);
    $commands = [];
    $this->recordPassthru($commands);

    $output = $this->runBrowserStart();

    $this->assertStringContainsString('Removing the Selenium container selenium-4444.', $output);
    $this->assertStringContainsString('chromedriver is ready on port 4444', $output);
    $this->assertCount(2, $commands, 'Only the container may be removed before chromedriver is launched.');
    $this->assertStringContainsString("docker rm -f 'selenium-4444'", $commands[0], 'The container of the project must be removed first.');
    $this->assertStringContainsString('nohup', $commands[1], 'chromedriver must be launched once the container is removed.');
  }

  public static function dataProviderBrowserStartChromedriverReplacesOwnContainer(): \Iterator {
    yield 'answering container of the project' => [self::PROJECT_ROOT, 'selenium'];
    yield 'answering container without the label' => ['', 'selenium'];
    yield 'booting container of the project' => [self::PROJECT_ROOT, 'selenium booting'];
    yield 'container of the project that does not answer' => [self::PROJECT_ROOT, NULL];
    yield 'container without the label that does not answer' => ['', NULL];
  }

  public function testBrowserStartChromedriverFailsWhenOwnContainerIsNotRemoved(): void {
    $this->mockChrome(FALSE);
    $this->mockCommandPaths(['docker' => '/usr/bin/docker', 'google-chrome' => '/usr/bin/google-chrome', 'chromedriver' => '/usr/local/bin/chromedriver']);
    $this->mockVersions('Google Chrome 150.0.7871.129', 'ChromeDriver 150.0.7871.129 (abc)');
    $this->mockPortListeners('4444', [9999 => ['/Applications/Docker.app/Contents/MacOS/com.docker.backend', '/']], self::seleniumContainerOutputs('4444', self::PROJECT_ROOT));
    $this->mockStatus(['selenium']);
    $commands = [];
    $this->recordPassthru($commands, docker_rm_exit: 1);

    $output = $this->runBrowserStart(1);

    $this->assertStringContainsString('Failed to remove the Selenium container selenium-4444.', $output);
    $this->assertCount(1, $commands, 'chromedriver must not be launched while the container holds the port.');
    $this->assertStringContainsString("docker rm -f 'selenium-4444'", $commands[0]);
  }

  /**
   * The project's own backend is stopped only once chromedriver can launch, so
   * a missing prerequisite leaves it running.
   *
   * @param array<string, string> $available
   *   Command name => resolved path for the commands that are present besides
   *   docker.
   */
  #[DataProvider('dataProviderBrowserStartChromedriverKeepsOwnBackendWhenItCannotLaunch')]
  public function testBrowserStartChromedriverKeepsOwnBackendWhenItCannotLaunch(bool $container, array $available, string $driver_version, int $npx_exit, string $expected): void {
    $this->mockChrome(FALSE);
    $this->mockCommandPaths(['docker' => '/usr/bin/docker'] + $available);
    $this->mockVersions('Google Chrome 150.0.7871.129', $driver_version);
    $listeners = $container ? [9999 => ['/Applications/Docker.app/Contents/MacOS/com.docker.backend', '/']] : [1234 => ['/usr/local/bin/chromedriver --port=4444', self::PROJECT_ROOT]];
    $this->mockPortListeners('4444', $listeners, self::seleniumContainerOutputs('4444', $container ? self::PROJECT_ROOT : NULL));
    $this->mockStatus([$container ? 'selenium' : NULL]);
    $commands = [];
    $this->recordPassthru($commands, npx_exit: $npx_exit);

    $output = $this->runBrowserStart(1);

    $this->assertStringContainsString($expected, $output);
    $this->assertStringNotContainsString('Removing the Selenium container', $output);
    $this->assertStringNotContainsString('Stopping chromedriver', $output);
    foreach ($commands as $command) {
      $this->assertStringNotContainsString('docker rm', $command, 'The container of the project must keep running.');
      $this->assertStringNotContainsString('kill', $command, 'The chromedriver of the project must keep running.');
    }
  }

  public static function dataProviderBrowserStartChromedriverKeepsOwnBackendWhenItCannotLaunch(): \Iterator {
    $chrome = ['google-chrome' => '/usr/bin/google-chrome'];
    $mismatch = ['chromedriver' => '/usr/local/bin/chromedriver'];
    $npx = ['npx' => '/usr/bin/npx'];

    yield 'container of the project, Chrome is missing' => [TRUE, [], '', 0, 'Google Chrome or Chromium was not found'];
    yield 'container of the project, no matching chromedriver and no npx' => [TRUE, $chrome + $mismatch, 'ChromeDriver 151.0.7922.34 (abc)', 0, 'npx is unavailable'];
    yield 'container of the project, chromedriver download fails' => [TRUE, $chrome + $npx, '', 1, 'Failed to install chromedriver'];
    yield 'chromedriver of the project, Chrome is missing' => [FALSE, [], '', 0, 'Google Chrome or Chromium was not found'];
    yield 'chromedriver of the project, no matching chromedriver and no npx' => [FALSE, $chrome + $mismatch, 'ChromeDriver 151.0.7922.34 (abc)', 0, 'npx is unavailable'];
    yield 'chromedriver of the project, chromedriver download fails' => [FALSE, $chrome + $npx, '', 1, 'Failed to install chromedriver'];
  }

  /**
   * A WebDriver session belongs to whoever opens it, so a backend of another
   * project that answers on the port serves this project too.
   *
   * @param array<int, array{0: string, 1: string}> $listeners
   *   The command line and the working directory of each process listening on
   *   the port, keyed by process ID.
   */
  #[DataProvider('dataProviderBrowserStartReusesRunningBackendOfAnotherProject')]
  public function testBrowserStartReusesRunningBackendOfAnotherProject(string $backend, array $listeners, ?string $container, string $expected): void {
    self::envSet('WEBDRIVER_BACKEND', $backend);
    $this->mockCommandPaths(['docker' => '/usr/bin/docker']);
    $this->mockPortListeners('4444', $listeners, self::seleniumContainerOutputs('4444', $container));
    $this->mockStatus([$backend]);
    $commands = [];
    $this->recordPassthru($commands);

    $output = $this->runBrowserStart();

    $this->assertStringContainsString($expected, $output);
    $this->assertSame([], $commands, 'A running backend of another project must be reused, not stopped, removed or replaced.');
  }

  public static function dataProviderBrowserStartReusesRunningBackendOfAnotherProject(): \Iterator {
    $driver = [5678 => ['/usr/bin/chromedriver --port=4444', '/www/other']];
    $docker = [9999 => ['/Applications/Docker.app/Contents/MacOS/com.docker.backend', '/']];

    yield 'chromedriver of another project' => ['chromedriver', $driver, NULL, 'chromedriver is already running on port 4444'];
    yield 'container of another project' => ['selenium', $docker, '/www/other', 'Selenium is already running on port 4444'];
    yield 'container started under another name' => ['selenium', $docker, NULL, 'Selenium is already running on port 4444'];
  }

  /**
   * A server that answers on the port but is not of the selected backend is
   * not reused, and it is named rather than stopped when it is not the
   * project's own.
   *
   * @param array<int, array{0: string, 1: string}> $listeners
   *   The command line and the working directory of each process listening on
   *   the port, keyed by process ID.
   * @param array<int, string> $expected
   *   Lines the output names the holder with.
   */
  #[DataProvider('dataProviderBrowserStartFailsWhenOtherServerAnswersOnPort')]
  public function testBrowserStartFailsWhenOtherServerAnswersOnPort(string $backend, string $status, array $listeners, ?string $container, array $expected): void {
    self::envSet('WEBDRIVER_BACKEND', $backend);
    $this->mockCommandPaths(['docker' => '/usr/bin/docker']);
    $this->mockPortListeners('4444', $listeners, self::seleniumContainerOutputs('4444', $container));
    $this->mockStatus([$status]);
    $commands = [];
    $this->recordPassthru($commands);

    $output = $this->runBrowserStart(1);

    foreach ($expected as $line) {
      $this->assertStringContainsString($line, $output);
    }
    $this->assertStringNotContainsString('already running', $output);
    $this->assertSame([], $commands, 'A server of another project or another process must not be stopped, removed or replaced.');
  }

  public static function dataProviderBrowserStartFailsWhenOtherServerAnswersOnPort(): \Iterator {
    $driver = [5678 => ['/usr/bin/chromedriver --port=4444', '/www/other']];
    $gecko = [5678 => ['/usr/local/bin/geckodriver --port 4444', '/www/other']];
    $docker = [9999 => ['/Applications/Docker.app/Contents/MacOS/com.docker.backend', '/']];
    $container_message = 'Port 4444 is in use by the Selenium container of another project. Stop its browser, or set WEBDRIVER_PORT in .env to a free port.';
    $process_message = 'Port 4444 is in use by another process. Stop it, or set WEBDRIVER_PORT in .env to a free port.';

    yield 'chromedriver backend, container of another project' => ['chromedriver', 'selenium', $docker, '/www/other', [$container_message, 'selenium-4444 /www/other']];
    yield 'chromedriver backend, container started under another name' => ['chromedriver', 'selenium', $docker, NULL, [$process_message, '9999 /Applications/Docker.app/Contents/MacOS/com.docker.backend']];
    yield 'selenium backend, chromedriver of another project' => ['selenium', 'chromedriver', $driver, NULL, [$process_message, '5678 /usr/bin/chromedriver --port=4444']];
    yield 'chromedriver backend, another WebDriver server' => ['chromedriver', 'geckodriver', $gecko, NULL, [$process_message, '5678 /usr/local/bin/geckodriver --port 4444']];
    yield 'selenium backend, another WebDriver server' => ['selenium', 'geckodriver', $gecko, NULL, [$process_message, '5678 /usr/local/bin/geckodriver --port 4444']];
  }

  #[DataProvider('dataProviderBrowserStartFailsWhenAnotherProcessHoldsPort')]
  public function testBrowserStartFailsWhenAnotherProcessHoldsPort(string $backend, string $cwd): void {
    self::envSet('WEBDRIVER_BACKEND', $backend);
    $this->mockCommandPaths(['docker' => '/usr/bin/docker']);
    $this->mockPortListeners('4444', [5678 => ['node server.js --port=4444', $cwd]]);
    $this->mockStatus([NULL]);
    $commands = [];
    $this->recordPassthru($commands);

    $output = $this->runBrowserStart(1);

    $this->assertStringContainsString('Port 4444 is in use by another process. Stop it, or set WEBDRIVER_PORT in .env to a free port.', $output);
    $this->assertStringContainsString('5678 node server.js --port=4444', $output);
    $this->assertSame([], $commands, 'Nothing may be stopped, removed or started while another process holds the port.');
  }

  public static function dataProviderBrowserStartFailsWhenAnotherProcessHoldsPort(): \Iterator {
    yield 'chromedriver backend, a program of another project' => ['chromedriver', '/www/other'];
    yield 'selenium backend, a program of another project' => ['selenium', '/www/other'];
    yield 'chromedriver backend, another program started from the project root' => ['chromedriver', self::PROJECT_ROOT];
    yield 'selenium backend, another program started from the project root' => ['selenium', self::PROJECT_ROOT];
  }

  #[DataProvider('dataProviderBrowserStartFailsWhenAnotherProjectContainerHoldsPort')]
  public function testBrowserStartFailsWhenAnotherProjectContainerHoldsPort(string $backend): void {
    self::envSet('WEBDRIVER_BACKEND', $backend);
    $this->mockCommandPaths(['docker' => '/usr/bin/docker']);
    $this->mockPortListeners('4444', [9999 => ['/Applications/Docker.app/Contents/MacOS/com.docker.backend', '/']], self::seleniumContainerOutputs('4444', '/www/other'));
    $this->mockStatus([NULL]);
    $commands = [];
    $this->recordPassthru($commands);

    $output = $this->runBrowserStart(1);

    $this->assertStringContainsString('Port 4444 is in use by the Selenium container of another project. Stop its browser, or set WEBDRIVER_PORT in .env to a free port.', $output);
    $this->assertStringContainsString('selenium-4444 /www/other', $output);
    $this->assertSame([], $commands, "Another project's container that does not answer must not be removed or replaced.");
  }

  public static function dataProviderBrowserStartFailsWhenAnotherProjectContainerHoldsPort(): \Iterator {
    yield 'chromedriver' => ['chromedriver'];
    yield 'selenium' => ['selenium'];
  }

  /**
   * A stopped container publishes no port, so the process that holds the port
   * is named whichever project the container belongs to.
   */
  #[DataProvider('dataProviderBrowserStartFailsWhenAnotherProcessHoldsPortBesideStoppedContainer')]
  public function testBrowserStartFailsWhenAnotherProcessHoldsPortBesideStoppedContainer(string $backend, string $container): void {
    self::envSet('WEBDRIVER_BACKEND', $backend);
    $this->mockCommandPaths(['docker' => '/usr/bin/docker']);
    $this->mockPortListeners('4444', [5678 => ['node /www/other/server.js --port=4444', '/www/other']], self::seleniumContainerOutputs('4444', $container, FALSE));
    $this->mockStatus([NULL]);
    $commands = [];
    $this->recordPassthru($commands);

    $output = $this->runBrowserStart(1);

    $this->assertStringContainsString('Port 4444 is in use by another process. Stop it, or set WEBDRIVER_PORT in .env to a free port.', $output);
    $this->assertStringContainsString('5678 node /www/other/server.js --port=4444', $output);
    $this->assertSame([], $commands, 'Nothing may be stopped, removed or started while another process holds the port.');
  }

  public static function dataProviderBrowserStartFailsWhenAnotherProcessHoldsPortBesideStoppedContainer(): \Iterator {
    yield 'container of the project, chromedriver backend' => ['chromedriver', self::PROJECT_ROOT];
    yield 'container of the project, selenium backend' => ['selenium', self::PROJECT_ROOT];
    yield 'container without the label, chromedriver backend' => ['chromedriver', ''];
    yield 'container without the label, selenium backend' => ['selenium', ''];
    yield 'container of another project, chromedriver backend' => ['chromedriver', '/www/other'];
    yield 'container of another project, selenium backend' => ['selenium', '/www/other'];
  }

  #[DataProvider('dataProviderBrowserStartStartsChromedriverBesideStoppedContainer')]
  public function testBrowserStartStartsChromedriverBesideStoppedContainer(string $container): void {
    $this->mockChrome(FALSE);
    $this->mockCommandPaths(['docker' => '/usr/bin/docker', 'google-chrome' => '/usr/bin/google-chrome', 'chromedriver' => '/usr/local/bin/chromedriver']);
    $this->mockVersions('Google Chrome 150.0.7871.129', 'ChromeDriver 150.0.7871.129 (abc)');
    $this->mockPortListeners('4444', [], self::seleniumContainerOutputs('4444', $container, FALSE));
    $this->mockStatus([NULL, 'chromedriver']);
    $commands = [];
    $this->recordPassthru($commands);

    $output = $this->runBrowserStart();

    $this->assertStringContainsString('chromedriver is ready on port 4444', $output);
    $this->assertCount(1, $commands, 'Only chromedriver may be launched: the stopped container holds no port.');
    $this->assertStringContainsString('nohup', $commands[0] ?? '');
  }

  public static function dataProviderBrowserStartStartsChromedriverBesideStoppedContainer(): \Iterator {
    yield 'container of the project' => [self::PROJECT_ROOT];
    yield 'container of another project' => ['/www/other'];
  }

  public function testBrowserStartUsesMatchingInstalledDriver(): void {
    $this->mockChrome(FALSE);
    $this->mockCommandPaths(['google-chrome' => '/usr/bin/google-chrome', 'chromedriver' => '/usr/local/bin/chromedriver']);
    $this->mockVersions('Google Chrome 150.0.7871.129', 'ChromeDriver 150.0.7871.129 (abc)');
    $this->mockStatus([NULL, 'chromedriver']);
    $commands = [];
    $this->recordPassthru($commands);

    $output = $this->runBrowserStart();

    $this->assertStringContainsString('Using installed chromedriver at /usr/local/bin/chromedriver', $output);
    $this->assertStringContainsString('chromedriver is ready on port 4444', $output);
    $this->assertSame([['/usr/bin/google-chrome', '--version'], ['/usr/local/bin/chromedriver', '--version']], $this->probes, 'Each binary must be probed by its resolved path, without a shell.');
    $this->assertNotSame([], $commands);
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
    $this->mockCommandPaths(['google-chrome' => '/usr/bin/google-chrome', 'chromedriver' => '/usr/local/bin/chromedriver', 'npx' => '/usr/bin/npx']);
    $this->mockVersions('Google Chrome 150.0.7871.129', 'ChromeDriver 151.0.7922.34 (abc)');
    $this->mockStatus([NULL, 'chromedriver']);
    $commands = [];
    $this->recordPassthru($commands);

    $output = $this->runBrowserStart();

    $this->assertStringContainsString('does not match Chrome 150.0.7871.129', $output);
    $this->assertStringContainsString('chromedriver is ready on port 4444', $output);
    $this->assertNotSame([], array_filter($commands, fn(string $c): bool => str_contains($c, 'npx')), 'A version mismatch must trigger an npx download.');
    $this->assertNotSame([], array_filter($commands, fn(string $c): bool => str_contains($c, 'nohup') && str_contains($c, '/cache/chromedriver')), 'The freshly fetched binary must be launched.');
  }

  public function testBrowserStartFallsBackToNpxWhenDriverProbeTimesOut(): void {
    $this->mockChrome(FALSE);
    $this->mockCommandPaths(['google-chrome' => '/usr/bin/google-chrome', 'chromedriver' => '/usr/local/bin/chromedriver', 'npx' => '/usr/bin/npx']);
    $this->mockVersions('Google Chrome 150.0.7871.129', 'ChromeDriver 150.0.7871.129 (abc)', hanging: ['/usr/local/bin/chromedriver']);
    $this->mockStatus([NULL, 'chromedriver']);
    $commands = [];
    $this->recordPassthru($commands);

    $output = $this->runBrowserStart();

    $this->assertStringContainsString('Installed chromedriver at /usr/local/bin/chromedriver did not report its version within 10 seconds; fetching a matching build.', $output);
    // cspell:ignore xattr
    $this->assertStringContainsString("If macOS has quarantined it, clear the flag with: xattr -d com.apple.quarantine '/usr/local/bin/chromedriver'", $output);
    $this->assertStringNotContainsString('Using installed chromedriver', $output);
    $this->assertStringContainsString('chromedriver is ready on port 4444', $output);
    $this->assertNotSame([], array_filter($commands, fn(string $c): bool => str_contains($c, 'npx')), 'A chromedriver that does not answer must trigger an npx download.');
    $this->assertNotSame([], array_filter($commands, fn(string $c): bool => str_contains($c, 'nohup') && str_contains($c, '/cache/chromedriver')), 'The freshly fetched binary must be launched, not the one that did not answer.');
  }

  public function testBrowserStartFailsWhenChromeProbeTimesOut(): void {
    $this->mockChrome(FALSE);
    $this->mockCommandPaths(['google-chrome' => '/usr/bin/google-chrome', 'chromedriver' => '/usr/local/bin/chromedriver', 'npx' => '/usr/bin/npx']);
    $this->mockVersions('Google Chrome 150.0.7871.129', 'ChromeDriver 150.0.7871.129 (abc)', hanging: ['/usr/bin/google-chrome']);
    $this->mockStatus([NULL]);
    $commands = [];
    $this->recordPassthru($commands);

    $output = $this->runBrowserStart(1);

    $this->assertStringContainsString('The browser at /usr/bin/google-chrome did not report its version within 10 seconds. Run it with --version to see why, or set WEBDRIVER_BACKEND=selenium to use Docker.', $output);
    $this->assertSame([['/usr/bin/google-chrome', '--version']], $this->probes, 'chromedriver must not be probed without the Chrome version.');
    $this->assertSame([], $commands, 'Nothing may be fetched or started without the Chrome version.');
  }

  public function testBrowserStartUsesMacChromeWhenPresent(): void {
    $this->mockChrome(TRUE);
    $this->mockCommandPaths(['chromedriver' => '/usr/local/bin/chromedriver']);
    $this->mockVersions('Google Chrome 150.0.7871.129', 'ChromeDriver 150.0.7871.129 (abc)');
    $this->mockStatus([NULL, 'chromedriver']);
    $commands = [];
    $this->recordPassthru($commands);

    $output = $this->runBrowserStart();

    $this->assertStringContainsString('chromedriver is ready on port 4444', $output);
    $this->assertSame([['/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', '--version'], ['/usr/local/bin/chromedriver', '--version']], $this->probes);
  }

  #[DataProvider('dataProviderBrowserStartProbesFirstAvailableChrome')]
  public function testBrowserStartProbesFirstAvailableChrome(array $available, string $expected_binary): void {
    $this->mockChrome(FALSE);
    $this->mockCommandPaths($available + ['chromedriver' => '/usr/local/bin/chromedriver']);
    $this->mockVersions('Chromium 150.0.7871.129', 'ChromeDriver 150.0.7871.129 (abc)');
    $this->mockStatus([NULL, 'chromedriver']);
    $commands = [];
    $this->recordPassthru($commands);

    $output = $this->runBrowserStart();

    $this->assertStringContainsString('Using installed chromedriver at /usr/local/bin/chromedriver', $output);
    $this->assertSame([[$expected_binary, '--version'], ['/usr/local/bin/chromedriver', '--version']], $this->probes);
  }

  public static function dataProviderBrowserStartProbesFirstAvailableChrome(): \Iterator {
    yield 'google-chrome' => [
      'available' => ['google-chrome' => '/usr/bin/google-chrome', 'chromium' => '/usr/bin/chromium', 'chromium-browser' => '/usr/bin/chromium-browser'],
      'expected_binary' => '/usr/bin/google-chrome',
    ];
    yield 'chromium' => [
      'available' => ['chromium' => '/usr/bin/chromium', 'chromium-browser' => '/usr/bin/chromium-browser'],
      'expected_binary' => '/usr/bin/chromium',
    ];
    yield 'chromium-browser' => [
      'available' => ['chromium-browser' => '/snap/bin/chromium-browser'],
      'expected_binary' => '/snap/bin/chromium-browser',
    ];
  }

  /**
   * @param array<string, string> $commands_available
   *   Command name => resolved path for the commands that are present.
   */
  #[DataProvider('dataProviderBrowserStartChromedriverFails')]
  public function testBrowserStartChromedriverFails(array $commands_available, string $chrome_version, string $driver_version, int $npx_exit, string $expected_message): void {
    $this->mockChrome(FALSE);
    $this->mockCommandPaths($commands_available);
    $this->mockVersions($chrome_version, $driver_version);
    $this->mockStatus([NULL]);
    $commands = [];
    $this->recordPassthru($commands, npx_exit: $npx_exit);

    $output = $this->runBrowserStart(1);

    $this->assertStringContainsString($expected_message, $output);
  }

  public static function dataProviderBrowserStartChromedriverFails(): \Iterator {
    $chrome = ['google-chrome' => '/usr/bin/google-chrome'];
    $driver = ['chromedriver' => '/usr/local/bin/chromedriver'];
    $npx = ['npx' => '/usr/bin/npx'];

    yield 'Chrome is missing' => [[], '', '', 0, 'Google Chrome or Chromium was not found'];
    yield 'no matching chromedriver and no npx' => [
      $chrome + $driver,
      'Google Chrome 150.0.7871.129',
      'ChromeDriver 151.0.7922.34 (abc)',
      0,
      'npx is unavailable',
    ];
    yield 'chromedriver download fails' => [$chrome + $npx, 'Google Chrome 150.0.7871.129', '', 1, 'Failed to install chromedriver'];
    yield 'never ready' => [
      $chrome + $driver,
      'Google Chrome 150.0.7871.129',
      'ChromeDriver 150.0.7871.129 (abc)',
      0,
      'failed to become ready on port 4444 after 30 seconds; see .logs/chromedriver.log.',
    ];
  }

  /**
   * @param string $install_log
   *   The install log of the fetched chromedriver, or '' when it is unreadable.
   */
  #[DataProvider('dataProviderBrowserStartFailsWhenBinaryPathUnresolved')]
  public function testBrowserStartFailsWhenBinaryPathUnresolved(string $install_log): void {
    $this->installLogContent = $install_log;
    $this->mockChrome(FALSE);
    $this->mockCommandPaths(['google-chrome' => '/usr/bin/google-chrome', 'npx' => '/usr/bin/npx']);
    $this->mockVersions('Google Chrome 150.0.7871.129', '');
    $this->mockStatus([NULL]);
    $commands = [];
    $this->recordPassthru($commands);

    $output = $this->runBrowserStart(1);

    $this->assertStringContainsString('Could not determine the chromedriver binary path', $output);
  }

  public static function dataProviderBrowserStartFailsWhenBinaryPathUnresolved(): \Iterator {
    yield 'unreadable log' => [''];
    yield 'log of blank lines' => ["\n\n"];
    yield 'last line without a path' => ["chromedriver@150.0.7871.129\n"];
  }

  public function testBrowserStartAutoDiscoversPort(): void {
    $this->envFileContent = "WEBSERVER_PORT=8000\n";
    $this->mockChrome(FALSE);
    $this->mockCommandPaths(['google-chrome' => '/usr/bin/google-chrome', 'chromedriver' => '/usr/local/bin/chromedriver']);
    $this->mockVersions('Google Chrome 150.0.7871.129', 'ChromeDriver 150.0.7871.129 (abc)');
    $this->mockPortsInUse([4444]);
    $persisted = NULL;
    $this->recordDotenvWrites($persisted);
    $this->mockStatus([NULL, 'chromedriver']);
    $commands = [];
    $this->recordPassthru($commands);

    $output = $this->runBrowserStart();

    $this->assertStringContainsString('chromedriver is ready on port 4445', $output);
    $this->assertSame('4445', $persisted, 'The discovered port must be persisted to .env.');
    $this->assertNotSame([], $commands);
    $this->assertStringContainsString("--port='4445'", $commands[0], 'chromedriver must bind the discovered port, not the occupied default.');
  }

  #[DataProvider('dataProviderBrowserStartSeleniumReusesOwnContainer')]
  public function testBrowserStartSeleniumReusesOwnContainer(string $project): void {
    self::envSet('WEBDRIVER_BACKEND', 'selenium');
    $this->mockCommandPaths(['docker' => '/usr/bin/docker']);
    $this->mockPortListeners('4444', [9999 => ['/Applications/Docker.app/Contents/MacOS/com.docker.backend', '/']], self::seleniumContainerOutputs('4444', $project));
    $this->mockStatus(['selenium']);
    $commands = [];
    $this->recordPassthru($commands);

    $output = $this->runBrowserStart();

    $this->assertStringContainsString('Selenium is already running on port 4444', $output);
    $this->assertSame([], $commands);
  }

  public static function dataProviderBrowserStartSeleniumReusesOwnContainer(): \Iterator {
    yield 'labelled with the project root' => [self::PROJECT_ROOT];
    yield 'without the label' => [''];
  }

  /**
   * The listener that publishes the port of the project's own container does
   * not count as another process.
   */
  #[DataProvider('dataProviderBrowserStartSeleniumReplacesOwnContainerThatDoesNotAnswer')]
  public function testBrowserStartSeleniumReplacesOwnContainerThatDoesNotAnswer(string $project): void {
    self::envSet('WEBDRIVER_BACKEND', 'selenium');
    $this->mockCommandPaths(['docker' => '/usr/bin/docker']);
    $this->mockPortListeners('4444', [9999 => ['/Applications/Docker.app/Contents/MacOS/com.docker.backend', '/']], self::seleniumContainerOutputs('4444', $project));
    $this->mockStatus([NULL, 'selenium']);
    $commands = [];
    $this->recordPassthru($commands);

    $output = $this->runBrowserStart();

    $this->assertStringContainsString('Selenium is ready on port 4444', $output);
    $this->assertCount(2, $commands, 'Only the container may be replaced, not the listener that publishes its port.');
    $this->assertSame(self::seleniumRemoveCommand('4444'), $commands[0], 'A container of the project that does not answer must be removed first.');
    $this->assertStringContainsString("--label 'eddy.project=/test/project'", $commands[1], 'The new container must carry the project label.');
  }

  public static function dataProviderBrowserStartSeleniumReplacesOwnContainerThatDoesNotAnswer(): \Iterator {
    yield 'labelled with the project root' => [self::PROJECT_ROOT];
    yield 'without the label' => [''];
  }

  /**
   * The chromedriver of the project holds the port whether or not it accepts
   * sessions, so the selenium backend stops it first.
   */
  #[DataProvider('dataProviderBrowserStartSeleniumReplacesOwnChromedriver')]
  public function testBrowserStartSeleniumReplacesOwnChromedriver(?string $status, ?string $container): void {
    self::envSet('WEBDRIVER_BACKEND', 'selenium');
    $this->mockCommandPaths(['docker' => '/usr/bin/docker']);
    $this->mockPortListeners('4444', [1234 => ['/usr/local/bin/chromedriver --port=4444', self::PROJECT_ROOT]], self::seleniumContainerOutputs('4444', $container));
    $this->mockStatus([$status, 'selenium']);
    $commands = [];
    $this->recordPassthru($commands);

    $output = $this->runBrowserStart();

    $this->assertStringContainsString('Stopping chromedriver on port 4444.', $output);
    $this->assertStringContainsString('Selenium is ready on port 4444', $output);
    $this->assertCount(3, $commands, 'Only chromedriver and the container name may be freed before the container is started.');
    $this->assertSame(self::killProcessesCommand(1234), $commands[0], 'The chromedriver of the project must be stopped first.');
    $this->assertSame(self::seleniumRemoveCommand('4444'), $commands[1]);
    $this->assertStringContainsString('docker run', $commands[2]);
  }

  public static function dataProviderBrowserStartSeleniumReplacesOwnChromedriver(): \Iterator {
    yield 'answering chromedriver' => ['chromedriver', NULL];
    yield 'chromedriver that does not answer' => [NULL, NULL];
    yield 'answering chromedriver beside the container of the project' => ['chromedriver', self::PROJECT_ROOT];
  }

  /**
   * A stopped container holds the container name but not the port, so it is
   * replaced whichever project it belongs to.
   */
  #[DataProvider('dataProviderBrowserStartSeleniumReplacesStaleContainer')]
  public function testBrowserStartSeleniumReplacesStaleContainer(string $project, bool $running): void {
    self::envSet('WEBDRIVER_BACKEND', 'selenium');
    $this->mockCommandPaths(['docker' => '/usr/bin/docker']);
    $this->mockPortListeners('4444', [], self::seleniumContainerOutputs('4444', $project, $running));
    $this->mockStatus([NULL, 'selenium']);
    $commands = [];
    $this->recordPassthru($commands);

    $output = $this->runBrowserStart();

    $this->assertStringContainsString('Selenium is ready on port 4444', $output);
    $this->assertSame(self::seleniumRemoveCommand('4444'), $commands[0] ?? NULL, 'The stale container must be removed first.');
    $this->assertStringContainsString("--label 'eddy.project=/test/project'", $commands[1] ?? '', 'The new container must carry the project label.');
  }

  public static function dataProviderBrowserStartSeleniumReplacesStaleContainer(): \Iterator {
    yield 'running container of the project' => [self::PROJECT_ROOT, TRUE];
    yield 'running container without the label' => ['', TRUE];
    yield 'stopped container of the project' => [self::PROJECT_ROOT, FALSE];
    yield 'stopped container of another project' => ['/www/other', FALSE];
  }

  public function testBrowserStartSeleniumStartsContainer(): void {
    self::envSet('WEBDRIVER_BACKEND', 'selenium');
    $this->mockCommandPaths(['docker' => '/usr/bin/docker']);
    $this->mockStatus([NULL, 'selenium']);
    $commands = [];
    $this->recordPassthru($commands);

    $output = $this->runBrowserStart();

    $this->assertStringContainsString('Selenium is ready on port 4444', $output);
    $this->assertNotSame([], array_filter($commands, fn(string $c): bool => str_contains($c, 'docker rm -f')), 'A stale container must be removed before starting a new one.');
    $run_commands = array_filter($commands, fn(string $c): bool => str_contains($c, 'docker run'));
    $this->assertNotSame([], $run_commands, 'The Selenium container must be started.');
    $run_command = (string) reset($run_commands);
    $this->assertStringContainsString("'4444':4444", $run_command, 'The container must publish the resolved WebDriver port.');
    $this->assertStringContainsString('--shm-size=2g', $run_command, 'The container must get the shared memory size Chromium needs.');
    $this->assertStringContainsString("--label 'eddy.project=/test/project'", $run_command, 'The container must name the project that started it.');
    $this->assertStringContainsString('standalone-chromium', $run_command);
  }

  public function testBrowserStartSeleniumAutoDiscoversPort(): void {
    $this->envFileContent = "WEBSERVER_PORT=8000\n";
    self::envSet('WEBDRIVER_BACKEND', 'selenium');
    $this->mockCommandPaths(['docker' => '/usr/bin/docker']);
    $this->mockPortsInUse([4444]);
    $persisted = NULL;
    $this->recordDotenvWrites($persisted);
    $this->mockStatus([NULL, 'selenium']);
    $commands = [];
    $this->recordPassthru($commands);

    $output = $this->runBrowserStart();

    $this->assertStringContainsString('Selenium is ready on port 4445', $output);
    $this->assertSame('4445', $persisted, 'The discovered port must be persisted to .env.');
    $run_commands = array_filter($commands, fn(string $c): bool => str_contains($c, 'docker run'));
    $this->assertNotSame([], $run_commands, 'The Selenium container must be started.');
    $run_command = (string) reset($run_commands);
    $this->assertStringContainsString("'4445':4444", $run_command, 'The container must publish the discovered port, not the occupied default.');
    $this->assertStringContainsString("--name 'selenium-4445'", $run_command, "The container name must be scoped to the endpoint port, or a second project removes the first project's container.");
    $this->assertNotSame([], array_filter($commands, fn(string $c): bool => str_contains($c, "docker rm -f 'selenium-4445'")), "Only this project's stale container may be removed before starting a new one.");
  }

  /**
   * @param array<string, string> $commands_available
   *   Command name => resolved path for the commands that are present.
   */
  #[DataProvider('dataProviderBrowserStartSeleniumFails')]
  public function testBrowserStartSeleniumFails(array $commands_available, int $docker_run_exit, string $expected_message): void {
    self::envSet('WEBDRIVER_BACKEND', 'selenium');
    $this->mockCommandPaths($commands_available);
    $this->mockStatus([NULL]);
    $commands = [];
    $this->recordPassthru($commands, docker_run_exit: $docker_run_exit);

    $output = $this->runBrowserStart(1);

    $this->assertStringContainsString($expected_message, $output);
  }

  public static function dataProviderBrowserStartSeleniumFails(): \Iterator {
    $docker = ['docker' => '/usr/bin/docker'];

    yield 'docker is missing' => [[], 0, 'docker was not found'];
    yield 'docker run fails' => [$docker, 1, 'Failed to start the Selenium container'];
    yield 'never ready' => [$docker, 0, 'Selenium failed to become ready on port 4444'];
  }

  /**
   * Run the command and return its output.
   *
   * The completion banner is asserted here for every run: it is printed when
   * the backend is ready and never on a failure.
   */
  protected function runBrowserStart(?int $expected_exit_code = NULL): string {
    if ($expected_exit_code !== NULL) {
      $this->mockQuit($expected_exit_code);
    }

    $output = $this->runCommand('eddy-browser-start', $expected_exit_code);

    if ($expected_exit_code === NULL) {
      $this->assertStringContainsString('BROWSER READY', $output);
    }
    else {
      $this->assertStringNotContainsString('BROWSER READY', $output);
    }

    return $output;
  }

  /**
   * @param array<int, string|null> $servers
   *   The key in STATUS_BODIES of the server that answers each probe, or NULL
   *   when nothing answers. The last entry repeats for further probes.
   */
  protected function mockStatus(array $servers): void {
    $this->mockStatusBodies(array_map(static fn(?string $server): string|false => $server === NULL ? FALSE : self::STATUS_BODIES[$server], $servers));
  }

  /**
   * @param array<int, string|false> $bodies
   *   The '/status' response body of each probe, or FALSE when nothing
   *   answers. The last entry repeats for further probes.
   */
  protected function mockStatusBodies(array $bodies): void {
    $this->statusBodies = $bodies;
    $this->statusIndex = 0;
  }

  /**
   * @param array<int, int> $ports
   *   Ports that answer a connect probe; every other port reads as free.
   */
  protected function mockPortsInUse(array $ports): void {
    $this->mockFunction('stream_socket_client', function (string $address, &$errno = NULL, &$errstr = NULL, ?float $timeout = NULL) use ($ports) {
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
    $this->mockFunction('file_put_contents', function (string $file, string $content) use (&$persisted): int {
      if (preg_match('/WEBDRIVER_PORT=(\d+)/', $content, $matches) === 1) {
        $persisted = $matches[1];
      }

      return strlen($content);
    });
  }

  protected function mockChrome(bool $mac_present): void {
    $this->mockFunction('is_executable', fn(): bool => $mac_present);
  }

  /**
   * Answer the '--version' probes with a real process standing in for each.
   *
   * The probed command lines are recorded in $probes.
   *
   * @param array<int, string> $hanging
   *   Paths of binaries whose probe never answers.
   */
  protected function mockVersions(string $chrome, string $driver, array $hanging = []): void {
    $this->mockFunction('proc_open', function (array $command, array $descriptors, &$pipes) use ($chrome, $driver, $hanging) {
      $this->probes[] = $command;
      $this->probeHangs = in_array($command[0], $hanging, TRUE);
      $version = str_contains($command[0], 'chromedriver') ? $driver : $chrome;

      return \proc_open($this->probeHangs ? ['sleep', '30'] : ['printf', '%s', $version], $descriptors, $pipes);
    });

    // Skip the poll interval for a hanging probe, so its time limit expires at
    // once. An answering probe sleeps for real, so its process can exit.
    $this->mockFunction('usleep', function (int $microseconds): void {
      if (!$this->probeHangs) {
        \usleep($microseconds);
      }
    });
  }

  /**
   * @param array<int, string> $commands
   *   Populated by reference with every passthru() command line.
   */
  protected function recordPassthru(array &$commands, int $npx_exit = 0, int $docker_run_exit = 0, int $docker_rm_exit = 0): void {
    $this->mockFunction('passthru', function (string $command, &$result_code = NULL) use (&$commands, $npx_exit, $docker_run_exit, $docker_rm_exit): void {
      $commands[] = $command;
      if (str_contains($command, 'npx')) {
        $result_code = $npx_exit;
      }
      if (str_contains($command, 'docker run')) {
        $result_code = $docker_run_exit;
      }
      if (str_contains($command, 'docker rm')) {
        $result_code = $docker_rm_exit;
      }
    });
  }

}
