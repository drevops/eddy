<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests that workflows define every environment variable they read.
 *
 * The `env` context resolves an undefined name to an empty string. A typo or a
 * self-reference then silently disables the value instead of failing the run.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[Group('p0')]
final class WorkflowsEnvContextTest extends UnitTestCase {

  #[DataProvider('dataProviderEnvReferencesAreDefined')]
  public function testEnvReferencesAreDefined(string $path): void {
    $content = File::read($path);

    $parsed = Yaml::parse($content);
    $this->assertIsArray($parsed);

    $defined = [];
    self::collectDefined($parsed, $defined);

    $undefined = array_values(array_unique(array_diff(self::collectReferenced($content), $defined)));

    $this->assertSame([], $undefined, sprintf('%s reads environment variables that it never defines: %s', basename($path), implode(', ', $undefined)));
  }

  public static function dataProviderEnvReferencesAreDefined(): \Iterator {
    $directory = dirname(__DIR__, 4) . '/.github/workflows/';
    $paths = array_merge(glob($directory . '*.yml') ?: [], glob($directory . '*.yaml') ?: []);

    foreach ($paths as $path) {
      yield basename($path) => ['path' => $path];
    }
  }

  /**
   * Collect the names a workflow defines, into the passed array.
   *
   * @param mixed $node
   *   A node of the parsed workflow.
   * @param array<int, string> $defined
   *   Collected names.
   */
  protected static function collectDefined(mixed $node, array &$defined): void {
    if (!is_array($node)) {
      return;
    }

    foreach ($node as $key => $value) {
      if ($key === 'env' && is_array($value)) {
        foreach ($value as $name => $expression) {
          // A name assigned from its own `env` entry defines nothing: the
          // context does not yet hold the block being evaluated.
          if (is_string($name) && !self::isSelfReference($name, $expression)) {
            $defined[] = $name;
          }
        }
      }

      if ($key === 'run' && is_string($value) && str_contains($value, 'GITHUB_ENV')) {
        preg_match_all('/\b([A-Z_][A-Z0-9_]*)=/', $value, $matches);
        $defined = array_merge($defined, $matches[1]);
      }

      self::collectDefined($value, $defined);
    }
  }

  /**
   * Collect the names a workflow reads through the `env` context.
   *
   * @param string $content
   *   The workflow contents.
   *
   * @return array<int, string>
   *   Collected names.
   */
  protected static function collectReferenced(string $content): array {
    $referenced = [];

    preg_match_all('/\$\{\{(.*?)\}\}/s', $content, $expressions);

    foreach ($expressions[1] as $expression) {
      preg_match_all('/\benv\.([A-Za-z_]\w*)/', $expression, $matches);
      $referenced = array_merge($referenced, $matches[1]);
    }

    return $referenced;
  }

  protected static function isSelfReference(string $name, mixed $expression): bool {
    if (!is_string($expression)) {
      return FALSE;
    }

    return preg_match('/^\s*\$\{\{\s*env\.' . preg_quote($name, '/') . '\s*\}\}\s*$/', $expression) === 1;
  }

}
