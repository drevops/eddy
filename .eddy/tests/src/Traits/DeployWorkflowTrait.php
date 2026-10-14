<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Traits;

use AlexSkrypnyk\File\File;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads the deploy workflow so that tests can assert on its definition.
 */
trait DeployWorkflowTrait {

  /**
   * Path of the deploy workflow.
   *
   * @return string
   *   The absolute path.
   */
  protected static function deployWorkflowPath(): string {
    return dirname(__DIR__, 4) . '/.github/workflows/deploy.yml';
  }

  /**
   * Read the deploy job out of the workflow.
   *
   * @return array<mixed, mixed>
   *   The job definition.
   */
  protected static function deployJob(): array {
    $path = self::deployWorkflowPath();
    $job = self::child(self::child(Yaml::parse(File::read($path)), 'jobs'), 'deploy');

    if (!is_array($job)) {
      self::fail(sprintf('%s has no "deploy" job.', basename($path)));
    }

    return $job;
  }

  /**
   * Read the steps of the deploy job.
   *
   * @return array<mixed, mixed>
   *   The step definitions.
   */
  protected static function deploySteps(): array {
    $steps = self::child(self::deployJob(), 'steps');

    if (!is_array($steps)) {
      self::fail(sprintf('The deploy job of %s declares no steps.', basename(self::deployWorkflowPath())));
    }

    return $steps;
  }

  /**
   * Read a named step of the deploy job.
   *
   * @param string $name
   *   Name of the step.
   *
   * @return array<mixed, mixed>
   *   The step definition.
   */
  protected static function deployStep(string $name): array {
    foreach (self::deploySteps() as $step) {
      if (!is_array($step)) {
        continue;
      }

      if (self::child($step, 'name') !== $name) {
        continue;
      }

      return $step;
    }

    self::fail(sprintf('%s has no "%s" step.', basename(self::deployWorkflowPath()), $name));
  }

  /**
   * Read a child of a parsed workflow node.
   *
   * @param mixed $node
   *   Node to read from.
   * @param string $key
   *   Key of the child.
   *
   * @return mixed
   *   The child, or NULL when the node does not hold it.
   */
  protected static function child(mixed $node, string $key): mixed {
    return is_array($node) && array_key_exists($key, $node) ? $node[$key] : NULL;
  }

}
