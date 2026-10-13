<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Functional;

use function DrevOps\Eddy\Tooling\wait_for_port;
use DrevOps\Eddy\Tests\Unit\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Functional tests for wait_for_port() against a real socket.
 *
 * phpcs:disable Drupal.Classes.FullyQualifiedNamespace.UseStatementMissing
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[Group('p3')]
final class WaitForPortTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';
  }

  public function testWaitEndsAtTimeoutWhenConnectionAttemptsAreDropped(): void {
    // A backlog of 1 lets the kernel queue a connection or 2 that is never
    // accepted. Once the queue is full, the kernel drops new connection
    // attempts instead of refusing them.
    $server = stream_socket_server('tcp://127.0.0.1:0', flags: STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, context: stream_context_create(['socket' => ['backlog' => 1]]));
    $this->assertNotFalse($server, 'The listener starts on a free port.');

    $address = (string) stream_socket_get_name($server, FALSE);
    $port = substr($address, (int) strrpos($address, ':') + 1);

    $clients = [];

    for ($i = 0; $i < 20; $i++) {
      $client = @stream_socket_client('tcp://' . $address, $client_errno, $client_errstr, 0.5);

      if ($client === FALSE) {
        break;
      }

      $clients[] = $client;
    }

    try {
      $start = microtime(TRUE);
      $probe = @stream_socket_client('tcp://' . $address, $probe_errno, $probe_errstr, 0.5);
      $this->assertFalse($probe, 'A connection attempt fails once the queue is full.');
      $this->assertGreaterThanOrEqual(0.4, microtime(TRUE) - $start, 'The kernel drops the connection attempt rather than refusing it.');

      $start = microtime(TRUE);
      $accepted = wait_for_port('127.0.0.1', $port, 2);
      $elapsed = microtime(TRUE) - $start;

      $this->assertFalse($accepted);
      $this->assertGreaterThanOrEqual(1.9, $elapsed, 'The wait lasts the whole timeout.');
      $this->assertLessThan(2.5, $elapsed, 'The wait ends at the timeout even though every connection attempt lasts its own timeout.');
    }
    finally {
      foreach ($clients as $client) {
        fclose($client);
      }

      fclose($server);
    }
  }

}
