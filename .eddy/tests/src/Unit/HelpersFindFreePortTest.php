<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\find_free_port;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[CoversFunction('DrevOps\Eddy\Tooling\find_free_port')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersFindFreePortTest extends UnitTestCase {

  /**
   * @param array<int, array{port: int, listening: bool}> $responses
   *   The connect outcome of each port, in the order the helper probes them.
   */
  #[DataProvider('dataProviderFindFreePort')]
  public function testFindFreePort(array $responses, int $start, int $expected): void {
    $this->mockStreamSocketClient($responses);

    $port = find_free_port($start, 100);
    $this->assertSame($expected, $port);
  }

  public static function dataProviderFindFreePort(): \Iterator {
    yield 'first port free' => [[['port' => 8000, 'listening' => FALSE]], 8000, 8000];
    yield 'first few ports busy' => [
      [
        ['port' => 8000, 'listening' => TRUE],
        ['port' => 8001, 'listening' => TRUE],
        ['port' => 8002, 'listening' => TRUE],
        ['port' => 8003, 'listening' => FALSE],
      ],
      8000,
      8003,
    ];
    yield 'custom starting port' => [[['port' => 9000, 'listening' => TRUE], ['port' => 9001, 'listening' => FALSE]], 9000, 9001];
  }

  #[DataProvider('dataProviderFindFreePortFailsOnInvalidArguments')]
  public function testFindFreePortFailsOnInvalidArguments(int $start, int $max_attempts, string $expected_message): void {
    $this->mockQuit(1);

    $output = $this->capture(fn(): int => find_free_port($start, $max_attempts), 1);

    $this->assertStringContainsString($expected_message, $output);
  }

  public static function dataProviderFindFreePortFailsOnInvalidArguments(): \Iterator {
    yield 'start port below range' => [0, 100, 'Start port must be between 1 and 65535'];
    yield 'start port above range' => [70000, 100, 'Start port must be between 1 and 65535'];
    yield 'invalid max attempts' => [8000, 0, 'Max attempts must be a positive integer'];
  }

  public function testAllPortsBusyCallsFail(): void {
    $responses = [];
    for ($p = 8000; $p < 8005; $p++) {
      $responses[] = ['port' => $p, 'listening' => TRUE];
    }
    $this->mockStreamSocketClient($responses);
    $this->mockQuit(1);

    $output = $this->capture(fn(): int => find_free_port(8000, 5), 1);

    $this->assertStringContainsString('Unable to find a free port in range 8000-8004', $output);
  }

  /**
   * @param array<int, array{port: int, listening: bool}> $responses
   *   Ordered list of expected calls. listening=TRUE returns a resource
   *   (connect succeeded => port in use); listening=FALSE returns FALSE
   *   (connect refused => port free).
   */
  protected function mockStreamSocketClient(array $responses): void {
    $this->mockAddResponses('stream_socket_client', $responses);

    if (isset($this->mocks['stream_socket_client'])) {
      return;
    }

    $this->mockFunction('stream_socket_client', function (string $address, &$errno = NULL, &$errstr = NULL, ?float $timeout = NULL) {
      $response = $this->mockNextResponse('stream_socket_client');
      if (!is_int($response['port']) || !is_bool($response['listening'])) {
        throw new \RuntimeException('Mocked stream_socket_client response must have int "port" and bool "listening".');
      }
      $expected = sprintf('tcp://localhost:%d', $response['port']);
      if ($address !== $expected) {
        throw new \RuntimeException(sprintf('stream_socket_client() called with unexpected address. Expected "%s", got "%s".', $expected, $address));
      }

      if (!$response['listening']) {
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

}
