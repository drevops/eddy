<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Traits;

use function DrevOps\Eddy\Tooling\kill_processes;
use function DrevOps\Eddy\Tooling\webserver_listeners;
use Symfony\Component\Process\Process;

/**
 * Helpers for tests that run the PHP webserver of a project.
 */
trait WebserverTrait {

  /**
   * Create a docroot that the webserver serves without an assembled site.
   *
   * The router returns FALSE for every request, so the webserver serves the
   * files in the docroot as they are.
   *
   * @param string $root
   *   The project root.
   */
  protected static function webserverCreateDocroot(string $root): void {
    mkdir($root . '/build/web', 0755, TRUE);
    file_put_contents($root . '/build/web/.ht.router.php', "<?php return FALSE;\n");
    file_put_contents($root . '/build/web/index.html', "OK\n");
  }

  /**
   * Check whether a TCP port on the local host accepts connections.
   *
   * @param string $port
   *   The TCP port.
   *
   * @return bool
   *   TRUE when a connection succeeds within 1 second.
   */
  protected static function webserverPortAccepts(string $port): bool {
    $connection = @stream_socket_client('tcp://localhost:' . $port, $errno, $errstr, 1);

    if ($connection === FALSE) {
      return FALSE;
    }

    fclose($connection);

    return TRUE;
  }

  /**
   * Find a free TCP port outside the range that the tooling discovers from.
   *
   * A connection to 'localhost' reaches a listener on either IP version, which
   * a bind to '127.0.0.1' alone can miss.
   *
   * @return string
   *   The first port from 9000 to 9099 that accepts no connection and can be
   *   bound.
   */
  protected static function webserverFreePort(): string {
    for ($port = 9000; $port < 9100; $port++) {
      if (self::webserverPortAccepts((string) $port)) {
        continue;
      }

      $socket = @stream_socket_server(sprintf('tcp://127.0.0.1:%d', $port));

      if ($socket !== FALSE) {
        fclose($socket);

        return (string) $port;
      }
    }

    throw new \RuntimeException('No free port from 9000 to 9099.');
  }

  /**
   * Start the webserver of another project the way 'eddy-start' does.
   *
   * @param string $root
   *   The root of the other project. Its docroot is created.
   * @param string $port
   *   The TCP port.
   *
   * @return \Symfony\Component\Process\Process
   *   The webserver process, once the port accepts connections or after 5
   *   seconds.
   */
  protected static function webserverStartOther(string $root, string $port): Process {
    self::webserverCreateDocroot($root);

    $server = new Process(['php', '-S', 'localhost:' . $port, '-t', $root . '/build/web', $root . '/build/web/.ht.router.php']);
    $server->start();

    for ($i = 0; $i < 50 && !self::webserverPortAccepts($port); $i++) {
      usleep(100000);
    }

    return $server;
  }

  /**
   * Stop the webserver of a project, leaving the other processes on its port.
   *
   * The tooling's own ownership check decides which listeners serve the
   * project's docroot.
   *
   * @param string $root
   *   The project root, as a real path.
   * @param string $port
   *   The TCP port.
   */
  protected static function webserverStop(string $root, string $port): void {
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';

    kill_processes(array_keys(webserver_listeners($port, $root . '/build/web')['own']));
  }

}
