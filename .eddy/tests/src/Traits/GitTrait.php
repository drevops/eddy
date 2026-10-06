<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Traits;

use Symfony\Component\Process\Process;

/**
 * Runs git in purpose-built test repositories.
 */
trait GitTrait {

  /**
   * Run a git command in a repository.
   *
   * @param string $repository
   *   Directory of the repository to run in.
   * @param array<int, string> $arguments
   *   Arguments for the command.
   *
   * @return string
   *   The trimmed standard output.
   */
  protected function git(string $repository, array $arguments): string {
    $process = new Process(array_merge(['git'], $arguments), $repository, self::gitEnvironment());
    $process->run();

    if (!$process->isSuccessful()) {
      self::fail(sprintf("git %s failed:\n%s", implode(' ', $arguments), $process->getErrorOutput()));
    }

    return trim($process->getOutput());
  }

  /**
   * Environment that detaches git from the configuration of the host.
   *
   * @return array<string, string>
   *   Environment variables.
   */
  protected static function gitEnvironment(): array {
    return [
      'GIT_CONFIG_GLOBAL' => '/dev/null',
      'GIT_CONFIG_SYSTEM' => '/dev/null',
    ];
  }

}
