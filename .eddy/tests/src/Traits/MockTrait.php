<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Traits;

use DrevOps\Eddy\Tests\Exceptions\QuitErrorException;
use DrevOps\Eddy\Tests\Exceptions\QuitSuccessException;
use phpmock\MockBuilder;
use phpmock\phpunit\PHPMock;

trait MockTrait {

  use PHPMock;

  protected const string TOOLING_NAMESPACE = 'DrevOps\\Eddy\\Tooling';

  /**
   * Unified registry of all function mocks indexed by function name.
   *
   * @var array<string, \phpmock\Mock>
   */
  protected array $mocks = [];

  /**
   * Unified registry of all mock responses indexed by function name.
   *
   * @var array<string, array<int, array<string, mixed>>>
   */
  protected array $mockResponses = [];

  /**
   * Unified registry of response indices indexed by function name.
   *
   * @var array<string, int>
   */
  protected array $mockIndices = [];

  /**
   * Unified registry of checked flags indexed by function name.
   *
   * @var array<string, bool>
   */
  protected array $mockChecked = [];

  /**
   * The time of the fake clock that mockClock() installs, in nanoseconds.
   */
  protected int $mockClockNanoseconds = 0;

  /**
   * The pauses that usleep() made on the fake clock, in microseconds.
   *
   * @var array<int, int>
   */
  protected array $mockClockPauses = [];

  protected function mockTearDown(): void {
    foreach (array_keys($this->mocks) as $function_name) {
      $this->assertMockConsumed($function_name);
    }

    foreach (array_keys($this->mocks) as $function_name) {
      $this->mockReset($function_name);
    }

    $this->mocks = [];
    $this->mockResponses = [];
    $this->mockIndices = [];
    $this->mockChecked = [];
  }

  /**
   * Register a new mock function in the unified registry.
   *
   * @param string $function_name
   *   The function name to mock (e.g., 'passthru', 'mail').
   * @param callable $callback
   *   The callback to execute when the mocked function is called.
   * @param string $namespace
   *   The namespace where the function should be mocked.
   */
  protected function mockFunction(string $function_name, callable $callback, string $namespace = self::TOOLING_NAMESPACE): void {
    // A PHPUnit function mock takes a callback only through expects(), so
    // the function is stubbed through php-mock directly.
    $mock = (new MockBuilder())->setNamespace($namespace)->setName($function_name)->setFunction($callback)->build();
    $mock->enable();
    $this->registerForTearDown($mock);

    $this->mocks[$function_name] = $mock;
  }

  /**
   * Add responses for a mock function.
   *
   * @param string $function_name
   *   The function name (e.g., 'passthru', 'mail').
   * @param array<int, array<string, mixed>> $responses
   *   Array of response configurations.
   */
  protected function mockAddResponses(string $function_name, array $responses): void {
    if (!isset($this->mockResponses[$function_name])) {
      $this->mockResponses[$function_name] = [];
      $this->mockIndices[$function_name] = 0;
    }

    $this->mockResponses[$function_name] = array_merge(
      $this->mockResponses[$function_name],
      $responses
    );

    // Reset checked flag so teardown re-validates consumption.
    $this->mockChecked[$function_name] = FALSE;
  }

  /**
   * Get the next response for a mock function.
   *
   * @param string $function_name
   *   The function name.
   *
   * @return array<string, mixed>
   *   The next response configuration.
   *
   * @throws \RuntimeException
   *   When no more responses are available.
   */
  protected function mockNextResponse(string $function_name): array {
    $total_responses = count($this->mockResponses[$function_name] ?? []);
    $current_index = $this->mockIndices[$function_name] ?? 0;

    if ($current_index >= $total_responses) {
      throw new \RuntimeException(sprintf(
        '%s() called more times than mocked responses. Expected %d call(s), but attempting call #%d.',
        $function_name,
        $total_responses,
        $current_index + 1
      ));
    }

    $response = $this->mockResponses[$function_name][$current_index];
    $this->mockIndices[$function_name]++;

    return $response;
  }

  /**
   * Assert all responses were consumed for a mock function.
   *
   * @param string $function_name
   *   The function name.
   *
   * @throws \PHPUnit\Framework\AssertionFailedError
   *   When not all responses were consumed.
   */
  protected function assertMockConsumed(string $function_name): void {
    if (!isset($this->mocks[$function_name])) {
      return;
    }

    if (isset($this->mockChecked[$function_name]) && $this->mockChecked[$function_name]) {
      return;
    }

    $this->mockChecked[$function_name] = TRUE;

    $total_responses = count($this->mockResponses[$function_name] ?? []);
    $consumed_responses = $this->mockIndices[$function_name] ?? 0;

    $this->assertSame($total_responses, $consumed_responses, sprintf(
      'Not all mocked %s responses were consumed. Expected %d call(s), but only %d call(s) were made.',
      $function_name,
      $total_responses,
      $consumed_responses
    ));
  }

  /**
   * Reset a mock function's state.
   *
   * @param string $function_name
   *   The function name.
   */
  protected function mockReset(string $function_name): void {
    unset($this->mocks[$function_name]);
    unset($this->mockResponses[$function_name]);
    unset($this->mockIndices[$function_name]);
    unset($this->mockChecked[$function_name]);
  }

  /**
   * Mock passthru function to return predefined output and exit codes.
   *
   * @param array<int, array{cmd:string, output?: string, result_code?: int, return?: NULL|FALSE}> $responses
   *   Array of responses to return for each passthru call.
   * @param string $namespace
   *   Namespace to mock the functions in.
   *
   * @throws \RuntimeException
   *   When more passthru calls are made than mocked responses available.
   */
  protected function mockPassthruMultiple(array $responses, string $namespace = self::TOOLING_NAMESPACE): void {
    $this->mockAddResponses('passthru', $responses);

    if (isset($this->mocks['passthru'])) {
      return;
    }

    $this->mockFunction('passthru', function ($command, &...$args): null|false {
      $response = $this->mockNextResponse('passthru');

      $response += [
        'output' => '',
        'result_code' => 0,
        'return' => NULL,
      ];

      // @phpstan-ignore-next-line isset.offset
      if (!isset($response['cmd'])) {
        throw new \InvalidArgumentException('Mocked passthru response must include "cmd" key to specify expected command.');
      }

      // @phpstan-ignore-next-line booleanAnd.alwaysFalse
      if ($response['return'] !== FALSE && $response['return'] !== NULL) {
        throw new \InvalidArgumentException(sprintf('Mocked passthru response "return" key must be either NULL or FALSE, but got %s.', gettype($response['return'])));
      }

      /** @var array{cmd: string, output: string, result_code: int, return: NULL|FALSE} $response */

      // Without DEBUG, passthru_or_fail() and drush() wrap the command in a
      // brace group, "{ <cmd>; } 2>&1", to capture stdout and stderr together.
      // Strip the wrapper before matching so expectations use the logical
      // command in both captured and streamed (DEBUG=1) runs;
      // HelpersPassthruCaptureTest asserts the wrapper format.
      $matched = $command;
      if (str_starts_with($matched, '{ ') && str_ends_with($matched, '; } 2>&1')) {
        $matched = substr($matched, 2, -8);
      }

      if ($response['cmd'] !== $matched) {
        throw new \RuntimeException(sprintf('passthru() called with unexpected command. Expected "%s", got "%s".', $response['cmd'], $command));
      }

      echo $response['output'];

      // Set exit code only if it was passed by reference.
      // The spread operator distinguishes between no argument and NULL.
      if (count($args) > 0) {
        $args[0] = $response['result_code'];
      }

      return $response['return'];
    }, $namespace);
  }

  /**
   * Mock single passthru call.
   *
   * @param array{cmd:string, output?: string, result_code?: int, return?: NULL|FALSE} $response
   *   Response with output and result_code.
   * @param string $namespace
   *   Namespace to mock the functions in.
   */
  protected function mockPassthru(array $response, string $namespace = self::TOOLING_NAMESPACE): void {
    $this->mockPassthruMultiple([$response], $namespace);
  }

  /**
   * Mock passthru() with no allowed calls, so any call raises.
   *
   * @param string $namespace
   *   Namespace to mock the functions in.
   */
  protected function mockPassthruNever(string $namespace = self::TOOLING_NAMESPACE): void {
    $this->mockPassthruMultiple([], $namespace);
  }

  /**
   * Mock quit() to throw an exception instead of terminating.
   *
   * A non-zero code throws QuitErrorException, and 0 throws
   * QuitSuccessException.
   *
   * @param int $code
   *   Exit code to expect (0 for success, non-zero for error).
   * @param string $namespace
   *   Namespace to mock the function in.
   */
  protected function mockQuit(int $code = 0, string $namespace = self::TOOLING_NAMESPACE): void {
    $this->mockFunction('quit', function (int $exit_code = 0) use ($code): void {
      if ($code !== $exit_code) {
        throw new \RuntimeException(sprintf('quit() called with unexpected exit code. Expected %d, got %d.', $code, $exit_code));
      }
      if ($code !== 0) {
        throw new QuitErrorException($code);
      }

      throw new QuitSuccessException($code);
    }, $namespace);
  }

  /**
   * Mock posix_isatty function to control terminal color detection.
   *
   * @param bool $return_value
   *   The value to return (TRUE for TTY/color support, FALSE for no TTY).
   * @param string $namespace
   *   Namespace to mock the function in.
   */
  protected function mockPosixIsatty(bool $return_value, string $namespace = self::TOOLING_NAMESPACE): void {
    $this->mockAddResponses('posix_isatty', [['value' => $return_value]]);

    if (!isset($this->mocks['posix_isatty'])) {
      $this->mockFunction('posix_isatty', function () {
        $response = $this->mockNextResponse('posix_isatty');
        return $response['value'];
      }, $namespace);
    }
  }

  /**
   * Mock command_path() resolution through the exec() probe behind it.
   *
   * Any command other than the named one always resolves as missing, so a
   * test that expects one probe cannot be satisfied by another.
   *
   * @param string $command
   *   Command name to resolve.
   * @param bool $available
   *   TRUE to resolve the command to a stub path, FALSE to report it as
   *   not installed.
   * @param string $namespace
   *   Namespace to mock the function in.
   */
  protected function mockCommandAvailable(string $command, bool $available, string $namespace = self::TOOLING_NAMESPACE): void {
    $this->mockFunction('exec', function (string $cmd, ?array &$output = NULL, ?int &$code = NULL) use ($command, $available): bool {
      $output ??= [];

      if ($available && $cmd === sprintf('command -v %s 2>/dev/null', $command)) {
        $output[] = '/usr/bin/' . $command;
        $code = 0;

        return TRUE;
      }

      $code = 1;

      return FALSE;
    }, $namespace);
  }

  /**
   * Mock sleep() function as a no-op.
   *
   * @param string $namespace
   *   Namespace to mock the function in.
   */
  protected function mockSleep(string $namespace = self::TOOLING_NAMESPACE): void {
    if (isset($this->mocks['sleep'])) {
      return;
    }

    $this->mockFunction('sleep', fn(): int => 0, $namespace);
  }

  /**
   * Mock hrtime() and usleep() with a fake clock.
   *
   * hrtime() reads the clock in nanoseconds and usleep() advances it, so a
   * wait loop runs to its deadline without sleeping. A test advances
   * $mockClockNanoseconds itself to simulate a call that takes time.
   *
   * @param string $namespace
   *   Namespace to mock the functions in.
   */
  protected function mockClock(string $namespace = self::TOOLING_NAMESPACE): void {
    // cspell:ignore hrtime
    $this->mockFunction('hrtime', fn(): int => $this->mockClockNanoseconds, $namespace);

    $this->mockFunction('usleep', function (int $microseconds): void {
      $this->mockClockPauses[] = $microseconds;
      $this->mockClockNanoseconds += $microseconds * 1000;
    }, $namespace);
  }

}
