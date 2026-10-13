<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\kill_port;
use function DrevOps\Eddy\Tooling\kill_processes;
use function DrevOps\Eddy\Tooling\port_listener_pids_command;
use function DrevOps\Eddy\Tooling\process_command;
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
#[CoversFunction('DrevOps\Eddy\Tooling\kill_port')]
#[CoversFunction('DrevOps\Eddy\Tooling\process_command')]
#[CoversFunction('DrevOps\Eddy\Tooling\webserver_serves')]
#[CoversFunction('DrevOps\Eddy\Tooling\webserver_listeners')]
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

  #[DataProvider('dataProviderKillPort')]
  public function testKillPort(int|string $port, string $expected): void {
    $this->mockPassthru(['cmd' => $expected]);

    kill_port($port);

    $this->mockPassthruAssertAllMocksConsumed();
  }

  public static function dataProviderKillPort(): \Iterator {
    yield 'string port' => ['8000', "lsof -ti tcp:'8000' -sTCP:LISTEN 2>/dev/null | xargs kill -9 2>/dev/null"];
    yield 'integer port' => [4444, "lsof -ti tcp:'4444' -sTCP:LISTEN 2>/dev/null | xargs kill -9 2>/dev/null"];
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
