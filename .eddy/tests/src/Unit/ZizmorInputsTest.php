<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests the paths the Zizmor step of the scaffold workflow audits.
 *
 * Zizmor collects workflows from every directory below an input. The init
 * fixtures hold workflows with placeholder action refs, so an audit that
 * includes them reports every ref as unpinned.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[Group('p0')]
final class ZizmorInputsTest extends UnitTestCase {

  /**
   * The init fixtures, relative to the repository root.
   */
  protected const string FIXTURES = '.eddy/tests/fixtures';

  public function testFixturesAreNotAudited(): void {
    $overlapping = array_filter(self::inputs(), static fn(string $input): bool => self::covers($input, self::FIXTURES) || self::covers(self::FIXTURES, $input));

    $this->assertSame([], $overlapping, sprintf('These Zizmor inputs include the init fixtures in %s: %s', self::FIXTURES, implode(', ', $overlapping)));
  }

  #[DataProvider('dataProviderWorkflowIsAudited')]
  public function testWorkflowIsAudited(string $path): void {
    $covering = array_filter(self::inputs(), static fn(string $input): bool => self::covers($input, $path));

    $this->assertNotSame([], $covering, sprintf('No Zizmor input includes %s.', $path));
  }

  public static function dataProviderWorkflowIsAudited(): \Iterator {
    $directory = self::rootDir() . '/.github/workflows/';
    $paths = array_merge(glob($directory . '*.yml') ?: [], glob($directory . '*.yaml') ?: []);

    foreach ($paths as $path) {
      yield basename($path) => ['path' => '.github/workflows/' . basename($path)];
    }
  }

  /**
   * Read the inputs of every Zizmor step in the scaffold workflow.
   *
   * @return array<int, string>
   *   The inputs, relative to the repository root.
   */
  protected static function inputs(): array {
    $path = self::rootDir() . '/.github/workflows/scaffold-test.yml';
    $contents = file_get_contents($path);

    if ($contents === FALSE) {
      self::fail(sprintf('Unable to read %s.', $path));
    }

    $parsed = Yaml::parse($contents);
    $jobs = is_array($parsed) && is_array($parsed['jobs'] ?? NULL) ? $parsed['jobs'] : [];

    $found = FALSE;
    $inputs = [];

    foreach ($jobs as $job) {
      $steps = is_array($job) && is_array($job['steps'] ?? NULL) ? $job['steps'] : [];

      foreach ($steps as $step) {
        if (!is_array($step)) {
          continue;
        }

        $uses = is_string($step['uses'] ?? NULL) ? $step['uses'] : '';

        if (!str_starts_with($uses, 'zizmorcore/zizmor-action@')) {
          continue;
        }

        $found = TRUE;
        $with = is_array($step['with'] ?? NULL) ? $step['with'] : [];
        // 'inputs' defaults to the repository root.
        $value = is_string($with['inputs'] ?? NULL) ? $with['inputs'] : '.';

        foreach (preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $input) {
          $inputs[] = self::normalize($input);
        }
      }
    }

    if (!$found) {
      self::fail(sprintf('%s has no step that uses zizmorcore/zizmor-action.', basename($path)));
    }

    return $inputs;
  }

  /**
   * Strip the leading './' and the trailing '/' from a path.
   *
   * @param string $path
   *   A path relative to the repository root, such as './.github/'.
   *
   * @return string
   *   The path, or '.' for the repository root.
   */
  protected static function normalize(string $path): string {
    $path = rtrim($path, '/');

    while (str_starts_with($path, './')) {
      $path = substr($path, 2);
    }

    return $path === '' ? '.' : $path;
  }

  /**
   * Check whether a path is a directory or lies below it.
   *
   * @param string $directory
   *   The directory, relative to the repository root.
   * @param string $path
   *   The path, relative to the repository root.
   *
   * @return bool
   *   TRUE when the path is the directory or lies below it.
   */
  protected static function covers(string $directory, string $path): bool {
    return $directory === '.' || $path === $directory || str_starts_with($path, $directory . '/');
  }

  /**
   * Get the repository root directory.
   *
   * @return string
   *   The absolute path to the repository root.
   */
  protected static function rootDir(): string {
    return dirname(__DIR__, 4);
  }

}
