<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\DevTools\find_free_port;
use DrevOps\Eddy\Tests\Exceptions\QuitErrorException;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for find_free_port() helper.
 *
 * phpcs:disable Drupal.Classes.FullyQualifiedNamespace.UseStatementMissing
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[CoversFunction('DrevOps\Eddy\DevTools\find_free_port')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersFindFreePortTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';
  }

  public function testFirstPortIsFree(): void {
    $this->mockStreamSocketClient([
      ['port' => 8000, 'listening' => FALSE],
    ]);

    $port = find_free_port(8000, 100);
    $this->assertSame(8000, $port);
  }

  public function testFirstFewPortsBusy(): void {
    $this->mockStreamSocketClient([
      ['port' => 8000, 'listening' => TRUE],
      ['port' => 8001, 'listening' => TRUE],
      ['port' => 8002, 'listening' => TRUE],
      ['port' => 8003, 'listening' => FALSE],
    ]);

    $port = find_free_port(8000, 100);
    $this->assertSame(8003, $port);
  }

  public function testCustomStartingPort(): void {
    $this->mockStreamSocketClient([
      ['port' => 9000, 'listening' => TRUE],
      ['port' => 9001, 'listening' => FALSE],
    ]);

    $port = find_free_port(9000, 100);
    $this->assertSame(9001, $port);
  }

  public function testInvalidStartPortBelowRangeFails(): void {
    $this->mockQuit(1);

    ob_start();
    try {
      find_free_port(0, 100);
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(1, $e->getCode());
    }
    finally {
      $output = ob_get_clean();
      $this->assertIsString($output);
      $this->assertStringContainsString('Start port must be between 1 and 65535', $output);
    }
  }

  public function testInvalidStartPortAboveRangeFails(): void {
    $this->mockQuit(1);

    ob_start();
    try {
      find_free_port(70000, 100);
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(1, $e->getCode());
    }
    finally {
      $output = ob_get_clean();
      $this->assertIsString($output);
      $this->assertStringContainsString('Start port must be between 1 and 65535', $output);
    }
  }

  public function testInvalidMaxAttemptsFails(): void {
    $this->mockQuit(1);

    ob_start();
    try {
      find_free_port(8000, 0);
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(1, $e->getCode());
    }
    finally {
      $output = ob_get_clean();
      $this->assertIsString($output);
      $this->assertStringContainsString('Max attempts must be a positive integer', $output);
    }
  }

  public function testAllPortsBusyCallsFail(): void {
    $responses = [];
    for ($p = 8000; $p < 8005; $p++) {
      $responses[] = ['port' => $p, 'listening' => TRUE];
    }
    $this->mockStreamSocketClient($responses);
    $this->mockQuit(1);

    ob_start();
    try {
      find_free_port(8000, 5);
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(1, $e->getCode());
    }
    finally {
      $output = ob_get_clean();
      $this->assertIsString($output);
      $this->assertStringContainsString('Unable to find a free port in range 8000-8004', $output);
    }
  }

  /**
   * Mock stream_socket_client() to simulate per-port connect outcomes.
   *
   * @param array<int, array{port: int, listening: bool}> $responses
   *   Ordered list of expected calls. listening=TRUE returns a resource
   *   (connect succeeded => port in use); listening=FALSE returns FALSE
   *   (connect refused => port free).
   */
  protected function mockStreamSocketClient(array $responses): void {
    $this->addMockResponses('stream_socket_client', $responses);

    if (isset($this->mocks['stream_socket_client'])) {
      return;
    }

    $this->registerMock('stream_socket_client', 'DrevOps\\Eddy\\DevTools', function (string $address, &$errno = NULL, &$errstr = NULL, ?float $timeout = NULL) {
      $response = $this->getNextMockResponse('stream_socket_client');
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
