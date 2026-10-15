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
 */
abstract class UnitTestCase extends UpstreamUnitTestCase {

  use DirectoryAssertionsTrait;
  use EnvTrait;
  use FileAssertionsTrait;
  use MockTrait;
  use SerializableClosureTrait;

  /**
   * The output of each command that mockShellExecOutputs() answers.
   *
   * @var array<string, string|\Closure>
   */
  protected array $mockShellExecOutputs = [];

  protected function setUp(): void {
    parent::setUp();
    require_once self::toolingDir() . '/src/helpers.php';
  }

  protected function tearDown(): void {
    // Reset before the mock assertions so a failed mock expectation cannot
    // leave environment variables set for the next test.
    self::envReset();
    $this->mockTearDown();
    parent::tearDown();
  }

  protected static function rootDir(): string {
    return dirname(__DIR__, 4);
  }

  protected static function toolingDir(): string {
    return self::rootDir() . '/.eddy/tooling';
  }

  protected static function fixturesDir(): string {
    return dirname(__DIR__, 2) . '/fixtures';
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
   * Build the command that reads the project label of a Selenium container.
   */
  protected static function seleniumInspectCommand(string $port): string {
    return sprintf('docker inspect --format %s %s 2>/dev/null', escapeshellarg('{{.Name}}|{{index .Config.Labels "eddy.project"}}'), escapeshellarg('selenium-' . $port));
  }

  /**
   * Build the command that reads whether a Selenium container is running.
   */
  protected static function seleniumRunningCommand(string $port): string {
    return sprintf('docker inspect --format %s %s 2>/dev/null', escapeshellarg('{{.State.Running}}'), escapeshellarg('selenium-' . $port));
  }

  /**
   * Build the outputs of the commands that inspect a Selenium container.
   *
   * @param string $port
   *   The WebDriver port the container publishes.
   * @param string|null $project
   *   The project root in the container's label, '' for a container without
   *   the label, or NULL for no container.
   * @param bool $running
   *   Whether the container is running.
   *
   * @return array<string, string>
   *   The output keyed by command, as for mockShellExecOutputs().
   */
  protected static function seleniumContainerOutputs(string $port, ?string $project, bool $running = TRUE): array {
    if ($project === NULL) {
      return [self::seleniumInspectCommand($port) => '', self::seleniumRunningCommand($port) => ''];
    }

    return [self::seleniumInspectCommand($port) => sprintf("/selenium-%s|%s\n", $port, $project), self::seleniumRunningCommand($port) => $running ? "true\n" : "false\n"];
  }

  /**
   * Mock the lookup of the processes that listen on a port.
   *
   * @param string $port
   *   The TCP port.
   * @param array<int, string|array{0: string, 1: string}> $listeners
   *   The command line of each listening process, or its command line and
   *   working directory, keyed by process ID.
   * @param array<string, string|\Closure> $outputs
   *   The output of other shell_exec() commands, as for
   *   mockShellExecOutputs().
   */
  protected function mockPortListeners(string $port, array $listeners = [], array $outputs = []): void {
    $outputs[sprintf('lsof -ti tcp:%s -sTCP:LISTEN 2>/dev/null', escapeshellarg($port))] = implode(PHP_EOL, array_keys($listeners)) . PHP_EOL;

    foreach ($listeners as $pid => $listener) {
      [$command, $cwd] = is_array($listener) ? $listener : [$listener, NULL];
      $outputs[sprintf('ps -p %d -o command= 2>/dev/null', $pid)] = $command . PHP_EOL;

      if ($cwd !== NULL) {
        $outputs[sprintf('lsof -a -p %d -d cwd -Fn 2>/dev/null', $pid)] = sprintf("p%d\nfcwd\nn%s\n", $pid, $cwd);
      }
    }

    $this->mockShellExecOutputs($outputs);
  }

  /**
   * Mock shell_exec() with the output of each expected command.
   *
   * A later call adds its outputs to those of an earlier one, and replaces the
   * output of a command that both list.
   *
   * @param array<string, string|\Closure> $outputs
   *   The output keyed by command. A closure is called for its output. Any
   *   command not listed fails the test.
   */
  protected function mockShellExecOutputs(array $outputs): void {
    $this->mockShellExecOutputs = $outputs + $this->mockShellExecOutputs;

    if (isset($this->mocks['shell_exec'])) {
      return;
    }

    $this->registerMock('shell_exec', 'DrevOps\\Eddy\\Tooling', function (string $command): ?string {
      if (!array_key_exists($command, $this->mockShellExecOutputs)) {
        throw new \RuntimeException(sprintf('shell_exec() called with unexpected command "%s".', $command));
      }

      $output = $this->mockShellExecOutputs[$command];

      return $output instanceof \Closure ? $output() : $output;
    });
  }

}
