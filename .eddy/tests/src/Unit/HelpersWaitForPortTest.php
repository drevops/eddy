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
   * @param array<int, true|float> $probes
   *   Each probe's outcome, in order: TRUE when it connects, otherwise the
   *   seconds its connection attempt lasts before it fails, up to the
   *   attempt's timeout. Its length is the number of probes expected.
   * @param array<int, int> $expected_pauses
   *   The pause before each probe, in microseconds.
   * @param array<int, float> $expected_timeouts
   *   The timeout of each connection attempt, in seconds.
   */
  #[DataProvider('dataProviderWaitForPort')]
  public function testWaitForPort(int $timeout, array $probes, bool $expected, array $expected_pauses, array $expected_timeouts, float $expected_elapsed): void {
    $this->mockClock();

    $connection = fopen('php://memory', 'r');
    $this->assertNotFalse($connection);

    $attempts = [];
    $fsockopen = function (string $host, int $port, &$errno = NULL, &$errstr = NULL, ?float $timeout = NULL) use (&$attempts, $probes, $connection) {
      $probe = $probes[count($attempts)] ?? 0.0;
      $attempts[] = [$host, $port, round((float) $timeout, 6)];

      if ($probe === TRUE) {
        return $connection;
      }

      $this->mockClockNanoseconds += (int) round(min($probe, (float) $timeout) * 1000000000);

      return FALSE;
    };
    $this->registerMock('fsockopen', 'DrevOps\\Eddy\\Tooling', $fsockopen);

    $closed = [];
    $this->registerMock('fclose', 'DrevOps\\Eddy\\Tooling', function (mixed $stream) use (&$closed): true {
      $closed[] = $stream;

      return TRUE;
    });

    $this->assertSame($expected, wait_for_port('localhost', '8000', $timeout));
    $this->assertSame(array_map(static fn(float $attempt_timeout): array => ['localhost', 8000, $attempt_timeout], $expected_timeouts), $attempts, 'Each probe connects to the host and port with a timeout capped at the time left.');
    $this->assertSame($expected_pauses, $this->mockClockPauses, 'Each probe follows a pause capped at the time left.');
    $this->assertSame((int) round($expected_elapsed * 1000000000), $this->mockClockNanoseconds, 'The wait ends by the timeout.');
    $this->assertSame($expected ? [$connection] : [], $closed, 'Only the accepted connection is closed.');

    fclose($connection);
  }

  public static function dataProviderWaitForPort(): \Iterator {
    // A refused connection attempt fails at once, and a dropped one lasts its
    // whole timeout.
    $refused = 0.0;
    $dropped = INF;

    yield 'accepts on the first probe' => [5, [TRUE], TRUE, [100000], [0.1], 0.1];
    yield 'accepts after 2 refused probes' => [5, [$refused, $refused, TRUE], TRUE, array_fill(0, 3, 100000), array_fill(0, 3, 0.1), 0.3];
    yield 'accepts on the last probe' => [1, [...array_fill(0, 9, $refused), TRUE], TRUE, array_fill(0, 10, 100000), [...array_fill(0, 9, 0.1), 0.0], 1.0];
    yield 'refuses every probe for 1 second' => [1, array_fill(0, 10, $refused), FALSE, array_fill(0, 10, 100000), [...array_fill(0, 9, 0.1), 0.0], 1.0];
    yield 'refuses every probe for 3 seconds' => [3, array_fill(0, 30, $refused), FALSE, array_fill(0, 30, 100000), [...array_fill(0, 29, 0.1), 0.0], 3.0];
    yield 'drops every probe for 1 second' => [1, array_fill(0, 5, $dropped), FALSE, array_fill(0, 5, 100000), array_fill(0, 5, 0.1), 1.0];
    yield 'drops every probe for 3 seconds' => [3, array_fill(0, 15, $dropped), FALSE, array_fill(0, 15, 100000), array_fill(0, 15, 0.1), 3.0];
    yield 'caps the last pause at the time left' => [1, array_fill(0, 8, 0.03), FALSE, [...array_fill(0, 7, 100000), 90000], [...array_fill(0, 7, 0.1), 0.0], 1.0];
    yield 'caps the last attempt at the time left' => [1, array_fill(0, 6, 0.07), FALSE, array_fill(0, 6, 100000), [...array_fill(0, 5, 0.1), 0.05], 1.0];
    yield 'zero timeout probes once without a pause' => [0, [$refused], FALSE, [0], [0.0], 0.0];
    yield 'zero timeout accepts on the only probe' => [0, [TRUE], TRUE, [0], [0.0], 0.0];
    yield 'negative timeout probes once without a pause' => [-1, [$refused], FALSE, [0], [0.0], 0.0];
  }

}
