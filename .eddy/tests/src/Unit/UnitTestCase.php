<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use AlexSkrypnyk\File\Testing\DirectoryAssertionsTrait;
use AlexSkrypnyk\File\Testing\FileAssertionsTrait;
use AlexSkrypnyk\PhpunitHelpers\Traits\EnvTrait;
use AlexSkrypnyk\PhpunitHelpers\Traits\SerializableClosureTrait;
use AlexSkrypnyk\PhpunitHelpers\UnitTestCase as UpstreamUnitTestCase;
use DrevOps\Eddy\Tests\Traits\MockTrait;

/**
 * UnitTestCase fixture class.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
abstract class UnitTestCase extends UpstreamUnitTestCase {

  use DirectoryAssertionsTrait;
  use EnvTrait;
  use FileAssertionsTrait;
  use MockTrait;
  use SerializableClosureTrait;

  protected function tearDown(): void {
    // Reset before the mock assertions so a failed mock expectation cannot
    // leave environment variables set for the next test.
    self::envReset();
    $this->mockTearDown();
    parent::tearDown();
  }

  /**
   * Build the command that kill_port() runs for a port.
   */
  protected static function killPortCommand(string $port): string {
    return sprintf('lsof -ti tcp:%s -sTCP:LISTEN 2>/dev/null | xargs kill -9 2>/dev/null', escapeshellarg($port));
  }

  /**
   * Build the command line that 'ps' prints for the webserver of a project.
   */
  protected static function webserverCommandLine(string $root, string $port = '8000'): string {
    return sprintf('php -S localhost:%2$s -t %1$s/build/web %1$s/build/web/.ht.router.php', $root, $port);
  }

  /**
   * Build the command that kill_processes() runs for process IDs.
   */
  protected static function killProcessesCommand(int ...$pids): string {
    return sprintf('kill -9 %s 2>/dev/null', implode(' ', $pids));
  }

  /**
   * Mock the lookup of the processes that listen on a port.
   *
   * @param string $port
   *   The TCP port.
   * @param array<int, string> $listeners
   *   The command line of each listening process, keyed by process ID.
   * @param array<string, string|\Closure> $outputs
   *   The output of other shell_exec() commands, keyed by command. A closure
   *   is called for its output. Any command not listed fails the test.
   */
  protected function mockPortListeners(string $port, array $listeners = [], array $outputs = []): void {
    $outputs[sprintf('lsof -ti tcp:%s -sTCP:LISTEN 2>/dev/null', escapeshellarg($port))] = implode(PHP_EOL, array_keys($listeners)) . PHP_EOL;

    foreach ($listeners as $pid => $command) {
      $outputs[sprintf('ps -p %d -o command= 2>/dev/null', $pid)] = $command . PHP_EOL;
    }

    $this->registerMock('shell_exec', 'DrevOps\\Eddy\\Tooling', function (string $command) use ($outputs): ?string {
      if (!array_key_exists($command, $outputs)) {
        throw new \RuntimeException(sprintf('shell_exec() called with unexpected command "%s".', $command));
      }

      $output = $outputs[$command];

      return $output instanceof \Closure ? $output() : $output;
    });
  }

}
