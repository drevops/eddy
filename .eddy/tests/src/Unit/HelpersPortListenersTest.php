<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\kill_processes;
use function DrevOps\Eddy\Tooling\port_listener_pids_command;
use function DrevOps\Eddy\Tooling\process_command;
use function DrevOps\Eddy\Tooling\process_cwd;
use function DrevOps\Eddy\Tooling\webdriver_listeners;
use function DrevOps\Eddy\Tooling\webserver_docroot_argument;
use function DrevOps\Eddy\Tooling\webserver_listeners;
use function DrevOps\Eddy\Tooling\webserver_serves;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the helpers that find and stop the processes listening on a port.
 *
 * phpcs:disable Drupal.Classes.FullyQualifiedNamespace.UseStatementMissing
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[CoversFunction('DrevOps\Eddy\Tooling\port_listener_pids_command')]
#[CoversFunction('DrevOps\Eddy\Tooling\process_command')]
#[CoversFunction('DrevOps\Eddy\Tooling\process_cwd')]
#[CoversFunction('DrevOps\Eddy\Tooling\webserver_docroot_argument')]
#[CoversFunction('DrevOps\Eddy\Tooling\webserver_serves')]
#[CoversFunction('DrevOps\Eddy\Tooling\webserver_listeners')]
#[CoversFunction('DrevOps\Eddy\Tooling\port_listeners')]
#[CoversFunction('DrevOps\Eddy\Tooling\webdriver_listeners')]
#[CoversFunction('DrevOps\Eddy\Tooling\kill_processes')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersPortListenersTest extends UnitTestCase {

  protected const string DOCROOT = '/www/project/build/web';

  protected const string SERVER = 'php -S localhost:8000 -t /www/project/build/web /www/project/build/web/.ht.router.php';

  protected const string OTHER_SERVER = 'php -S localhost:8000 -t /www/other/build/web /www/other/build/web/.ht.router.php';

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';
  }

  #[DataProvider('dataProviderPortListenerPidsCommand')]
  public function testPortListenerPidsCommand(int|string $port, string $expected): void {
    $this->assertSame($expected, port_listener_pids_command($port));
  }

  public static function dataProviderPortListenerPidsCommand(): \Iterator {
    yield 'string port' => ['8000', "lsof -ti tcp:'8000' -sTCP:LISTEN 2>/dev/null"];
    yield 'integer port' => [4444, "lsof -ti tcp:'4444' -sTCP:LISTEN 2>/dev/null"];
    yield 'shell metacharacters stay quoted' => ['8000; rm -rf /', "lsof -ti tcp:'8000; rm -rf /' -sTCP:LISTEN 2>/dev/null"];
  }

  #[DataProvider('dataProviderProcessCommand')]
  public function testProcessCommand(?string $ps_output, string $expected): void {
    $this->registerMock('shell_exec', 'DrevOps\\Eddy\\Tooling', fn(string $command): ?string => $command === 'ps -p 1234 -o command= 2>/dev/null' ? $ps_output : throw new \RuntimeException(sprintf('shell_exec() called with unexpected command "%s".', $command)));

    $this->assertSame($expected, process_command(1234));
  }

  public static function dataProviderProcessCommand(): \Iterator {
    yield 'running process' => ["php -S localhost:8000 -t /www/project/build/web\n", 'php -S localhost:8000 -t /www/project/build/web'];
    yield 'no such process' => ['', ''];
    yield 'no output at all' => [NULL, ''];
  }

  #[DataProvider('dataProviderProcessCwd')]
  public function testProcessCwd(?string $lsof_output, string $expected): void {
    $this->registerMock('shell_exec', 'DrevOps\\Eddy\\Tooling', fn(string $command): ?string => $command === 'lsof -a -p 1234 -d cwd -Fn 2>/dev/null' ? $lsof_output : throw new \RuntimeException(sprintf('shell_exec() called with unexpected command "%s".', $command)));

    $this->assertSame($expected, process_cwd(1234));
  }

  public static function dataProviderProcessCwd(): \Iterator {
    yield 'working directory' => ["p1234\nfcwd\nn/www/project\n", '/www/project'];
    yield 'working directory with spaces' => ["p1234\nfcwd\nn/www/my project\n", '/www/my project'];
    yield 'no such process' => ['', ''];
    yield 'no output at all' => [NULL, ''];
  }

  #[DataProvider('dataProviderWebserverDocrootArgument')]
  public function testWebserverDocrootArgument(string $command, ?string $expected): void {
    $this->assertSame($expected, webserver_docroot_argument($command));
  }

  public static function dataProviderWebserverDocrootArgument(): \Iterator {
    yield 'separate value' => [self::SERVER, '/www/project/build/web'];
    yield 'relative value' => ['php -S localhost:8000 -t build/web', 'build/web'];
    yield 'attached value' => ['php -S localhost:8000 -tbuild/web', 'build/web'];
    yield 'after the XDebug flags' => ['php -d xdebug.mode=debug -S localhost:8000 -t /www/project/build/web', '/www/project/build/web'];
    yield 'before the address' => ['php -t build/web -S localhost:8000', 'build/web'];
    yield 'value with spaces is cut at the first space' => ['php -S localhost:8000 -t /www/my project/build/web', '/www/my'];
    yield 'no value' => ['php -S localhost:8000 -t', NULL];
    yield 'no option' => ['node server.js --port 8000', NULL];
    yield 'empty command line' => ['', NULL];
  }

  /**
   * @param string $command
   *   The command line, where '{project}', '{link}' and '{other}' stand for the
   *   project root, a symlink to it and the root of another project.
   * @param string|null $cwd
   *   The working directory that 'lsof' reports for process 1234, with the same
   *   placeholders, or NULL when it reports none.
   */
  #[DataProvider('dataProviderWebserverServesResolvesPath')]
  public function testWebserverServesResolvesPath(string $command, ?string $cwd, int $pid, bool $expected): void {
    $project = self::$tmp . '/project';
    $other = self::$tmp . '/other';
    mkdir($project . '/build/web', 0755, TRUE);
    mkdir($other . '/build/web', 0755, TRUE);
    symlink($project, self::$tmp . '/link');
    $replacements = ['{project}' => $project, '{link}' => self::$tmp . '/link', '{other}' => $other];

    $lsof_output = $cwd === NULL ? '' : sprintf("p1234\nfcwd\nn%s\n", strtr($cwd, $replacements));
    $this->registerMock('shell_exec', 'DrevOps\\Eddy\\Tooling', fn(string $command): string => $command === 'lsof -a -p 1234 -d cwd -Fn 2>/dev/null' ? $lsof_output : throw new \RuntimeException(sprintf('shell_exec() called with unexpected command "%s".', $command)));

    $this->assertSame($expected, webserver_serves(strtr($command, $replacements), $project . '/build/web', $pid));
  }

  public static function dataProviderWebserverServesResolvesPath(): \Iterator {
    yield 'relative to the project root' => ['php -S localhost:8000 -t build/web build/web/.ht.router.php', '{project}', 1234, TRUE];
    yield 'relative with a dot and a trailing slash' => ['php -S localhost:8000 -t ./build/web/', '{project}', 1234, TRUE];
    yield 'relative to the build directory' => ['php -S localhost:8000 -t web', '{project}/build', 1234, TRUE];
    yield 'the working directory itself' => ['php -S localhost:8000 -t .', '{project}/build/web', 1234, TRUE];
    yield 'attached to the option' => ['php -S localhost:8000 -tbuild/web', '{project}', 1234, TRUE];
    yield 'relative to another project root' => ['php -S localhost:8000 -t build/web', '{other}', 1234, FALSE];
    yield 'relative to a working directory that cannot be read' => ['php -S localhost:8000 -t build/web', NULL, 1234, FALSE];
    yield 'relative without a process ID' => ['php -S localhost:8000 -t build/web', '{project}', 0, FALSE];
    yield 'relative to a path that does not exist' => ['php -S localhost:8000 -t missing/web', '{project}', 1234, FALSE];
    yield 'absolute through a symlink' => ['php -S localhost:8000 -t {link}/build/web {link}/build/web/.ht.router.php', NULL, 1234, TRUE];
    yield 'absolute to another project' => ['php -S localhost:8000 -t {other}/build/web', NULL, 1234, FALSE];
    yield 'no value after the option' => ['php -S localhost:8000 -t', '{project}', 1234, FALSE];
  }

  #[DataProvider('dataProviderWebserverServes')]
  public function testWebserverServes(string $command, string $docroot, bool $expected): void {
    $this->assertSame($expected, webserver_serves($command, $docroot));
  }

  public static function dataProviderWebserverServes(): \Iterator {
    yield 'serves the docroot' => [self::SERVER, self::DOCROOT, TRUE];
    yield 'serves the docroot with XDebug' => ['php -d xdebug.mode=debug -d xdebug.start_with_request=yes -S localhost:8000 -t /www/project/build/web /www/project/build/web/.ht.router.php', self::DOCROOT, TRUE];
    yield 'docroot ends the command line' => ['php -S localhost:8000 -t /www/project/build/web', self::DOCROOT, TRUE];
    yield 'serves another docroot' => [self::OTHER_SERVER, self::DOCROOT, FALSE];
    yield 'serves a directory below the docroot' => ['php -S localhost:8000 -t /www/project/build/web/sites', self::DOCROOT, FALSE];
    yield 'serves a docroot whose name extends this one' => ['php -S localhost:8000 -t /www/project/build/web-old /www/project/build/web-old/.ht.router.php', self::DOCROOT, FALSE];
    yield 'names the docroot only in the router path' => ['php -S localhost:8000 -t /www/other/build/web /www/project/build/web/.ht.router.php', self::DOCROOT, FALSE];
    yield 'docroot with spaces' => ['php -S localhost:8000 -t /www/my project/build/web /www/my project/build/web/.ht.router.php', '/www/my project/build/web', TRUE];
    yield 'docroot with regular expression characters' => ['php -S localhost:8000 -t /www/a+b (1)/build/web /www/a+b (1)/build/web/.ht.router.php', '/www/a+b (1)/build/web', TRUE];
    yield 'regular expression characters match only themselves' => ['php -S localhost:8000 -t /www/aab 1/build/web /www/aab 1/build/web/.ht.router.php', '/www/a+b (1)/build/web', FALSE];
    yield 'another program' => ['node server.js --port 8000', self::DOCROOT, FALSE];
    yield 'empty command line' => ['', self::DOCROOT, FALSE];
  }

  /**
   * @param array<int, string> $listeners
   *   The command line of each listening process, keyed by process ID.
   * @param array{own: array<int, string>, other: array<int, string>} $expected
   *   The expected listeners, split by owner.
   */
  #[DataProvider('dataProviderWebserverListeners')]
  public function testWebserverListeners(array $listeners, array $expected): void {
    $this->mockPortListeners('8000', $listeners);

    $this->assertSame($expected, webserver_listeners('8000', self::DOCROOT));
  }

  public static function dataProviderWebserverListeners(): \Iterator {
    yield 'nothing listens' => [[], ['own' => [], 'other' => []]];
    yield 'the project server' => [[1234 => self::SERVER], ['own' => [1234 => self::SERVER], 'other' => []]];
    yield 'the project server with workers' => [[1234 => self::SERVER, 1235 => self::SERVER, 1236 => self::SERVER], ['own' => [1234 => self::SERVER, 1235 => self::SERVER, 1236 => self::SERVER], 'other' => []]];
    yield 'the server of another project' => [[5678 => self::OTHER_SERVER], ['own' => [], 'other' => [5678 => self::OTHER_SERVER]]];
    yield 'another program' => [[5678 => 'node server.js --port 8000'], ['own' => [], 'other' => [5678 => 'node server.js --port 8000']]];
    yield 'the project server and another' => [[1234 => self::SERVER, 5678 => self::OTHER_SERVER], ['own' => [1234 => self::SERVER], 'other' => [5678 => self::OTHER_SERVER]]];
    yield 'a command line that cannot be read' => [[1234 => ''], ['own' => [1234 => ''], 'other' => []]];
  }

  /**
   * @param array<int, array{0: string, 1: string}> $listeners
   *   The command line and the working directory of each listening process,
   *   keyed by process ID.
   * @param array{own: array<int, string>, other: array<int, string>} $expected
   *   The expected listeners, split by owner.
   */
  #[DataProvider('dataProviderWebdriverListeners')]
  public function testWebdriverListeners(array $listeners, array $expected): void {
    $this->mockPortListeners('4444', $listeners);

    $this->assertSame($expected, webdriver_listeners('4444', '/www/project'));
  }

  public static function dataProviderWebdriverListeners(): \Iterator {
    $driver = '/usr/local/bin/chromedriver --port=4444';
    $fetched = '.chromedriver/chromedriver/mac_arm-150.0.7871.129/chromedriver-mac-arm64/chromedriver --port=4444';

    yield 'nothing listens' => [[], ['own' => [], 'other' => []]];
    yield 'started from the project root' => [[1234 => [$driver, '/www/project']], ['own' => [1234 => $driver], 'other' => []]];
    yield 'fetched into the project, started from the project root' => [[1234 => [$fetched, '/www/project']], ['own' => [1234 => $fetched], 'other' => []]];
    yield 'found on PATH, started from the project root' => [[1234 => ['chromedriver --port=4444', '/www/project']], ['own' => [1234 => 'chromedriver --port=4444'], 'other' => []]];
    yield 'started from the root of another project' => [[5678 => [$driver, '/www/other']], ['own' => [], 'other' => [5678 => $driver]]];
    yield 'started from a directory below the project root' => [[5678 => [$driver, '/www/project/build']], ['own' => [], 'other' => [5678 => $driver]]];
    yield 'another program started from the project root' => [[5678 => ['node server.js --port=4444', '/www/project']], ['own' => [], 'other' => [5678 => 'node server.js --port=4444']]];
    yield 'a program in a directory named after chromedriver, started from the project root' => [[5678 => ['node chromedriver-proxy/server.js', '/www/project']], ['own' => [], 'other' => [5678 => 'node chromedriver-proxy/server.js']]];
    yield 'a program given a chromedriver path as an argument, started from the project root' => [[5678 => ['node /tmp/chromedriver --port=4444', '/www/project']], ['own' => [], 'other' => [5678 => 'node /tmp/chromedriver --port=4444']]];
    yield 'an executable path with spaces, started from the project root' => [[5678 => ['/www/my project/bin/chromedriver --port=4444', '/www/project']], ['own' => [], 'other' => [5678 => '/www/my project/bin/chromedriver --port=4444']]];
    yield 'a working directory that cannot be read' => [[5678 => [$driver, '']], ['own' => [], 'other' => [5678 => $driver]]];
    yield 'a command line that cannot be read, started from the project root' => [[5678 => ['', '/www/project']], ['own' => [], 'other' => [5678 => '']]];
    yield 'the project chromedriver and another' => [[1234 => [$driver, '/www/project'], 5678 => [$driver, '/www/other']], ['own' => [1234 => $driver], 'other' => [5678 => $driver]]];
  }

  /**
   * @param string $root
   *   The project root, where '{project}' and '{link}' stand for the real
   *   directory and a symlink to it.
   * @param string $cwd
   *   The working directory that 'lsof' reports for process 1234, with the same
   *   placeholders and '{other}' for the root of another project.
   */
  #[DataProvider('dataProviderWebdriverListenersResolvesPaths')]
  public function testWebdriverListenersResolvesPaths(string $root, string $cwd, bool $expected_own): void {
    $project = self::$tmp . '/project';
    $other = self::$tmp . '/other';
    mkdir($project, 0755, TRUE);
    mkdir($other, 0755, TRUE);
    symlink($project, self::$tmp . '/link');
    $replacements = ['{project}' => $project, '{link}' => self::$tmp . '/link', '{other}' => $other];
    $driver = '/usr/local/bin/chromedriver --port=4444';

    $this->mockPortListeners('4444', [1234 => [$driver, strtr($cwd, $replacements)]]);

    $expected = $expected_own ? ['own' => [1234 => $driver], 'other' => []] : ['own' => [], 'other' => [1234 => $driver]];
    $this->assertSame($expected, webdriver_listeners('4444', strtr($root, $replacements)));
  }

  public static function dataProviderWebdriverListenersResolvesPaths(): \Iterator {
    yield 'root through a symlink' => ['{link}', '{project}', TRUE];
    yield 'working directory through a symlink' => ['{project}', '{link}', TRUE];
    yield 'root through a symlink, started from the root of another project' => ['{link}', '{other}', FALSE];
  }

  public function testWebserverListenersResolvesRelativeDocroot(): void {
    $project = self::$tmp . '/project';
    mkdir($project . '/build/web', 0755, TRUE);
    $server = 'php -S localhost:8000 -t build/web';

    // Both servers were started by hand: 1234 from the project root and 5678
    // from another directory.
    $this->mockPortListeners('8000', [1234 => $server, 5678 => $server], [
      'lsof -a -p 1234 -d cwd -Fn 2>/dev/null' => "p1234\nfcwd\nn" . $project . "\n",
      'lsof -a -p 5678 -d cwd -Fn 2>/dev/null' => "p5678\nfcwd\nn/www/other\n",
    ]);

    $this->assertSame(['own' => [1234 => $server], 'other' => [5678 => $server]], webserver_listeners('8000', $project . '/build/web'));
  }

  #[DataProvider('dataProviderWebserverListenersIgnoresOutput')]
  public function testWebserverListenersIgnoresOutput(?string $lsof_output): void {
    // Only the process ID 1234 reaches 'ps'.
    $this->registerMock('shell_exec', 'DrevOps\\Eddy\\Tooling', fn(string $command): ?string => match ($command) {
      "lsof -ti tcp:'8000' -sTCP:LISTEN 2>/dev/null" => $lsof_output,
      'ps -p 1234 -o command= 2>/dev/null' => self::SERVER . "\n",
      default => throw new \RuntimeException(sprintf('shell_exec() called with unexpected command "%s".', $command)),
    });

    $expected = $lsof_output !== NULL && str_contains($lsof_output, '1234') ? [1234 => self::SERVER] : [];
    $this->assertSame(['own' => $expected, 'other' => []], webserver_listeners('8000', self::DOCROOT));
  }

  public static function dataProviderWebserverListenersIgnoresOutput(): \Iterator {
    yield 'lsof is missing' => [NULL];
    yield 'blank output' => ["  \n\n"];
    yield 'process IDs below 1' => ["0\n-1\n1234\n"];
    yield 'text that is not a process ID' => ["lsof: WARNING\n12ab\n1234\n"];
  }

  /**
   * @param array<int, int> $pids
   *   The process IDs to kill.
   */
  #[DataProvider('dataProviderKillProcesses')]
  public function testKillProcesses(array $pids, ?string $expected): void {
    if ($expected === NULL) {
      $this->mockPassthruNever();
    }
    else {
      $this->mockPassthru(['cmd' => $expected]);
    }

    kill_processes($pids);

    $this->mockPassthruAssertAllMocksConsumed();
  }

  public static function dataProviderKillProcesses(): \Iterator {
    yield 'no processes' => [[], NULL];
    yield '1 process' => [[1234], 'kill -9 1234 2>/dev/null'];
    yield 'several processes' => [[1234, 1235, 1236], 'kill -9 1234 1235 1236 2>/dev/null'];
    yield 'process IDs below 1 are ignored' => [[0, -1, 1234], 'kill -9 1234 2>/dev/null'];
    yield 'only process IDs below 1' => [[0, -5], NULL];
  }

}
