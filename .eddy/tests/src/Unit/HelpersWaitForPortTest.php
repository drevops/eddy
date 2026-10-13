<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\wait_for_port;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the wait_for_port() helper.
 *
 * phpcs:disable Drupal.Classes.FullyQualifiedNamespace.UseStatementMissing
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[CoversFunction('DrevOps\Eddy\Tooling\wait_for_port')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersWaitForPortTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';
  }

  /**
   * @param array<int, bool> $accepts
   *   Whether each probe, in order, connects. Its length is the number of
   *   probes expected.
   */
  #[DataProvider('dataProviderWaitForPort')]
  public function testWaitForPort(int $timeout, array $accepts, bool $expected): void {
    $pauses = [];
    $this->registerMock('usleep', 'DrevOps\\Eddy\\Tooling', function (int $microseconds) use (&$pauses): void {
      $pauses[] = $microseconds;
    });

    $connection = fopen('php://memory', 'r');
    $this->assertNotFalse($connection);

    $probes = [];
    $fsockopen = function (string $host, int $port, &$errno = NULL, &$errstr = NULL, ?float $timeout = NULL) use (&$probes, $accepts, $connection) {
      $probes[] = [$host, $port, $timeout];

      return ($accepts[count($probes) - 1] ?? FALSE) ? $connection : FALSE;
    };
    $this->registerMock('fsockopen', 'DrevOps\\Eddy\\Tooling', $fsockopen);

    $closed = [];
    $this->registerMock('fclose', 'DrevOps\\Eddy\\Tooling', function (mixed $stream) use (&$closed): true {
      $closed[] = $stream;

      return TRUE;
    });

    $this->assertSame($expected, wait_for_port('localhost', '8000', $timeout));
    $this->assertSame(array_fill(0, count($accepts), ['localhost', 8000, 0.1]), $probes, 'Each probe connects to the host and port with a 0.1-second timeout.');
    $this->assertSame(array_fill(0, count($accepts), 100000), $pauses, 'Each probe follows a 100-millisecond pause.');
    $this->assertSame($expected ? [$connection] : [], $closed, 'Only the accepted connection is closed.');

    fclose($connection);
  }

  public static function dataProviderWaitForPort(): \Iterator {
    yield 'accepts on the first probe' => [5, [TRUE], TRUE];
    yield 'accepts after 2 refused probes' => [5, [FALSE, FALSE, TRUE], TRUE];
    yield 'accepts on the last probe' => [1, [...array_fill(0, 9, FALSE), TRUE], TRUE];
    yield 'refuses every probe for 1 second' => [1, array_fill(0, 10, FALSE), FALSE];
    yield 'refuses every probe for 3 seconds' => [3, array_fill(0, 30, FALSE), FALSE];
    yield 'zero timeout probes once' => [0, [FALSE], FALSE];
    yield 'negative timeout probes once' => [-1, [FALSE], FALSE];
  }

}
