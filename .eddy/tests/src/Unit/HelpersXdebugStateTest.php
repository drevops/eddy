<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\xdebug_state;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[CoversFunction('DrevOps\Eddy\Tooling\xdebug_state')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersXdebugStateTest extends UnitTestCase {

  protected const string DOCROOT = '/www/project/build/web';

  protected const string SERVER_XDEBUG = 'php -d xdebug.mode=debug -d xdebug.start_with_request=yes -S localhost:8000 -t /www/project/build/web /www/project/build/web/.ht.router.php';

  protected const string OTHER_SERVER_XDEBUG = 'php -d xdebug.mode=debug -d xdebug.start_with_request=yes -S localhost:8000 -t /www/other/build/web /www/other/build/web/.ht.router.php';

  /**
   * @param array<int, string> $listeners
   *   The command line of each process listening on the port, keyed by
   *   process ID.
   */
  #[DataProvider('dataProviderXdebugState')]
  public function testXdebugState(array $listeners, string $expected): void {
    $this->mockPortListeners('8000', $listeners);

    $this->assertSame($expected, xdebug_state('8000', self::DOCROOT));
  }

  public static function dataProviderXdebugState(): \Iterator {
    yield 'no server listening' => [[], '-'];
    yield 'project server without XDebug' => [[1234 => self::webserverCommandLine('/www/project')], 'disabled'];
    yield 'project server with XDebug' => [[1234 => self::SERVER_XDEBUG], 'enabled'];
    yield 'project server with XDebug and a worker' => [[1234 => self::SERVER_XDEBUG, 1235 => self::SERVER_XDEBUG], 'enabled'];
    yield 'only the server of another project, with XDebug' => [[5678 => self::OTHER_SERVER_XDEBUG], '-'];
    yield 'project server without XDebug and another project server with it' => [[5678 => self::OTHER_SERVER_XDEBUG, 1234 => self::webserverCommandLine('/www/project')], 'disabled'];
  }

  public function testPortIsShellEscaped(): void {
    // The listener lookup receives the port quoted, or the mock fails.
    $this->mockPortListeners('8000; rm -rf /');

    $this->assertSame('-', xdebug_state('8000; rm -rf /', self::DOCROOT));
  }

}
