<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Traits;

use AlexSkrypnyk\File\File;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads the matrix jobs of the test workflow so that tests can assert on them.
 */
trait CiJobsTrait {

  /**
   * List the jobs of the given matrices of the 'test.yml' workflow.
   *
   * @param array<int, string> $job_names
   *   The workflow jobs whose matrices to read.
   *
   * @return array<int, array{name: string, php: string, drupal: string}>
   *   The jobs in workflow order, each with its PHP and Drupal versions.
   */
  protected static function ciJobs(array $job_names = ['lint', 'test']): array {
    $workflow = Yaml::parse(File::read(self::rootDir() . '/.github/workflows/test.yml'));
    $jobs = [];

    foreach ($job_names as $job_name) {
      $job = is_array($workflow) && is_array($workflow['jobs'] ?? NULL) ? ($workflow['jobs'][$job_name] ?? NULL) : NULL;
      $strategy = is_array($job) && is_array($job['strategy'] ?? NULL) ? $job['strategy'] : [];
      $matrix = is_array($strategy['matrix'] ?? NULL) ? $strategy['matrix'] : [];
      $include = $matrix['include'] ?? NULL;

      self::assertIsArray($include, sprintf('test.yml has no matrix for the `%s` job.', $job_name));

      foreach ($include as $leg) {
        self::assertIsArray($leg);

        $name = $leg['name'] ?? NULL;
        $php = $leg['php-version'] ?? NULL;
        $drupal = $leg['drupal-version'] ?? NULL;

        self::assertIsScalar($name);
        self::assertIsScalar($php);
        self::assertIsScalar($drupal);

        $jobs[] = ['name' => (string) $name, 'php' => (string) $php, 'drupal' => (string) $drupal];
      }
    }

    return $jobs;
  }

}
