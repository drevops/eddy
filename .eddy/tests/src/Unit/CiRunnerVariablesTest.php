<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests the runner role variables that determine which tools run where.
 *
 * Each tool reads a `CI_IS_<TOOL>_RUNNER` flag declared once at the top of the
 * job. A flag that no step reads leaves the tool running everywhere, and a flag
 * whose declaration outlives its `#;< DEV_<TOOL>` block leaves a name nothing
 * consumes. Both stay valid YAML, so no linter reports either.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[Group('p0')]
final class CiRunnerVariablesTest extends UnitTestCase {

  /**
   * The names that anchor the declaration when every tool is removed.
   */
  protected const array ANCHORS = ['CI_RUNNER_INDEX', 'CI_RUNNER_TOTAL'];

  public function testGithubActionsFlagsAreReadByStep(): void {
    $path = self::rootDir() . '/.github/workflows/test.yml';
    $contents = file_get_contents($path);
    $this->assertIsString($contents);

    $parsed = Yaml::parse($contents);
    $this->assertIsArray($parsed);
    $this->assertArrayHasKey('jobs', $parsed);
    $this->assertIsArray($parsed['jobs']);

    $flags = [];
    $unread = [];

    foreach ($parsed['jobs'] as $job_name => $job) {
      if (!is_array($job)) {
        continue;
      }

      if (!is_array($job['env'] ?? NULL)) {
        continue;
      }

      $conditions = '';
      foreach (is_array($job['steps'] ?? NULL) ? $job['steps'] : [] as $step) {
        $conditions .= is_array($step) && is_string($step['if'] ?? NULL) ? $step['if'] : '';
      }

      foreach (array_keys($job['env']) as $name) {
        if (!is_string($name)) {
          continue;
        }

        if (preg_match('/^CI_IS_[A-Z0-9]+_RUNNER$/', $name) !== 1) {
          continue;
        }

        $flags[] = $name;

        if (!str_contains($conditions, 'env.' . $name)) {
          $unread[] = sprintf('%s: %s', $job_name, $name);
        }
      }
    }

    $this->assertNotSame([], $flags, 'test.yml declares no runner flags.');
    $this->assertSame([], $unread, sprintf('test.yml declares runner flags that no step reads: %s', implode(', ', $unread)));
  }

  #[DataProvider('dataProviderAnchorsAreDeclaredOutsideToolBlocks')]
  public function testAnchorsAreDeclaredOutsideToolBlocks(string $path): void {
    $declared = self::declaredVariables($path);
    $blocks = self::openBlocksByLine($path);

    foreach (self::ANCHORS as $name) {
      $this->assertArrayHasKey($name, $declared, sprintf('%s does not declare %s.', basename($path), $name));

      $open = $blocks[$declared[$name]];

      $this->assertSame([], $open, sprintf('%s declares %s inside the %s block, so removing that tool takes the anchor with it.', basename($path), $name, implode(' and ', $open)));
    }
  }

  #[DataProvider('dataProviderFlagsAreDeclaredInsideTheirToolBlocks')]
  public function testFlagsAreDeclaredInsideTheirToolBlocks(string $path): void {
    $declared = self::declaredVariables($path);
    $blocks = self::openBlocksByLine($path);

    $flags = [];
    $misplaced = [];

    foreach ($declared as $name => $line) {
      if (preg_match('/^CI_IS_([A-Z0-9]+)_RUNNER$/', $name, $matches) !== 1) {
        continue;
      }

      $flags[] = $name;
      $token = 'DEV_' . $matches[1];

      if (!in_array($token, $blocks[$line], TRUE)) {
        $misplaced[] = sprintf('%s (expected %s)', $name, $token);
      }
    }

    $this->assertNotSame([], $flags, sprintf('%s declares no runner flags.', basename($path)));
    $this->assertSame([], $misplaced, sprintf('%s declares runner flags outside the block that removes their tool: %s', basename($path), implode(', ', $misplaced)));
  }

  public static function dataProviderAnchorsAreDeclaredOutsideToolBlocks(): \Iterator {
    yield from self::configurationPaths();
  }

  public static function dataProviderFlagsAreDeclaredInsideTheirToolBlocks(): \Iterator {
    yield from self::configurationPaths();
  }

  /**
   * @return \Iterator<string, array{path: string}>
   */
  protected static function configurationPaths(): \Iterator {
    yield 'test.yml' => ['path' => self::rootDir() . '/.github/workflows/test.yml'];
  }

  /**
   * Find the runner variables a configuration declares.
   *
   * Covers both spellings: a GitHub Actions `env` entry and a CircleCI shell
   * export.
   *
   * @param string $path
   *   The configuration to read.
   *
   * @return array<string, int>
   *   The line each name is declared on, keyed by name.
   */
  protected static function declaredVariables(string $path): array {
    $declared = [];

    foreach (self::lines($path) as $number => $line) {
      if (preg_match('/^\s*(?:echo "export )?(CI_IS_[A-Z0-9]+_RUNNER|CI_RUNNER_[A-Z]+)\s*[:=]/', $line, $matches) === 1) {
        $declared[$matches[1]] = $number;
      }
    }

    return $declared;
  }

  /**
   * Map each line of a configuration to the tool blocks open at that line.
   *
   * @param string $path
   *   The configuration to read.
   *
   * @return array<int, array<int, string>>
   *   The tokens of the open blocks, keyed by line.
   */
  protected static function openBlocksByLine(string $path): array {
    $blocks = [];
    $stack = [];

    foreach (self::lines($path) as $number => $line) {
      if (preg_match('/#;< (\S+)/', $line, $matches) === 1) {
        $stack[] = $matches[1];
      }
      elseif (preg_match('/#;> (\S+)/', $line) === 1) {
        array_pop($stack);
      }

      $blocks[$number] = $stack;
    }

    return $blocks;
  }

  /**
   * Read a configuration into lines.
   *
   * @param string $path
   *   The configuration to read.
   *
   * @return array<int, string>
   *   The lines.
   */
  protected static function lines(string $path): array {
    $contents = file_get_contents($path);
    self::assertIsString($contents);

    return explode("\n", $contents);
  }

  protected static function rootDir(): string {
    return dirname(__DIR__, 4);
  }

}
