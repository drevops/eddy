<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Traits;

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

}
