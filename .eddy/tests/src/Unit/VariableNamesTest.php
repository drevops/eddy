<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests the names given to environment and CI variables.
 *
 * A name says what the variable configures, not which project owns it, so no
 * name carries a project segment such as 'EDDY_' or 'DREVOPS_'. A
 * segment that spells a package, such as 'EDDY_TOOLING' for 'eddy-tooling',
 * names what the variable configures and is allowed.
 *
 * Every toggle that lets a CI step fail without failing its job is named
 * 'CI_<TOOL>_IGNORE_FAILURE'.
 */
#[Group('p0')]
final class VariableNamesTest extends UnitTestCase {

  /**
   * An uppercase identifier with a project segment at its start or after '_'.
   *
   * 'EDDY_' followed by 'TOOLING' spells the 'eddy-tooling' package, so it is
   * not a project segment.
   */
  protected const string PROJECT_SEGMENT = '/\b(?:[A-Z0-9]+_)*(?:DREVOPS_|VORTEX_|EDDY_(?!TOOLING(?:_|\b)))\w*/';

  #[DataProvider('dataProviderNoProjectSegment')]
  public function testNoProjectSegment(string $path): void {
    $names = self::projectNames(File::read($path));

    $this->assertSame([], $names, sprintf('%s names variables after a project: %s', basename($path), implode(', ', $names)));
  }

  public static function dataProviderNoProjectSegment(): \Iterator {
    $root = self::rootDir();

    $paths = array_merge(
      glob($root . '/*.md') ?: [],
      glob($root . '/.eddy/docs/content/*.mdx') ?: [],
      glob($root . '/.eddy/docs/content/*/*.mdx') ?: [],
      glob($root . '/.github/workflows/*.yml') ?: [],
      glob($root . '/.eddy/tooling/src/*') ?: [],
      glob($root . '/scripts/*') ?: [],
      glob($root . '/tests/src/*/*.php') ?: [],
      glob($root . '/.eddy/tests/src/*/*.php') ?: [],
      [
        $root . '/.ahoy.yml',
        $root . '/Makefile',
        $root . '/install.php',
        $root . '/phpunit.xml',
        $root . '/phpunit.d10.xml',
        $root . '/.eddy/CLAUDE.md',
        $root . '/.eddy/docs/docusaurus.config.js',
        $root . '/.eddy/assets/update-assets.php',
        $root . '/.eddy/skills/update-consumer-eddy/SKILL.md',
        $root . '/.claude/skills/create-eddy-tooling-release-notes/SKILL.md',
        $root . '/.eddy/tooling/README.md',
      ],
    );

    foreach ($paths as $path) {
      // The sample data of this test holds project-prefixed names on purpose.
      if ($path === __FILE__) {
        continue;
      }

      yield substr($path, strlen($root) + 1) => ['path' => $path];
    }
  }

  /**
   * @param array<int, string> $expected
   */
  #[DataProvider('dataProviderProjectNames')]
  public function testProjectNames(string $content, array $expected): void {
    $this->assertSame($expected, self::projectNames($content));
  }

  public static function dataProviderProjectNames(): \Iterator {
    yield 'project prefix' => ['EDDY_DEBUG=1', ['EDDY_DEBUG']];
    yield 'organization prefix' => ["vars.DREVOPS_CI_A == '1'", ['DREVOPS_CI_A']];
    yield 'other project prefix' => ['export VORTEX_A=1', ['VORTEX_A']];
    yield 'project segment after a tool prefix' => ['AHOY_EDDY_READY=1', ['AHOY_EDDY_READY']];
    yield 'repeated name' => ['EDDY_A and EDDY_A', ['EDDY_A']];
    yield 'project name inside a word' => ['TEDDY_A', []];
    yield 'project name without a separator' => ['EDDY', []];
    yield 'package and namespace' => ['drevops/eddy-tooling DrevOps\Eddy\Tooling', []];
    yield 'package name' => ['EDDY_TOOLING_DEPLOY_KEY and AHOY_EDDY_TOOLING_READY', []];
    yield 'package name at the end' => ['EDDY_TOOLING=1', []];
    yield 'longer word after the project name' => ['EDDY_TOOLINGS', ['EDDY_TOOLINGS']];
    yield 'package word after another project name' => ['DREVOPS_TOOLING_A', ['DREVOPS_TOOLING_A']];
    yield 'functional prefixes' => ['CI_PHPCS_IGNORE_FAILURE INSTALL_NAME', []];
  }

  #[DataProvider('dataProviderIgnoreFailureToggles')]
  public function testIgnoreFailureToggles(string $path): void {
    $parsed = Yaml::parse(File::read($path));
    $this->assertIsArray($parsed);

    $names = [];
    foreach (self::continueOnErrorValues($parsed) as $value) {
      preg_match_all('/\bvars\.(\w+)/', $value, $matches);
      $names = array_merge($names, $matches[1]);
    }

    $this->assertNotSame([], $names, sprintf('%s has no ignore-failure toggles.', basename($path)));

    $misnamed = array_values(array_filter($names, static fn(string $name): bool => preg_match('/^CI_[A-Z0-9_]+_IGNORE_FAILURE$/', $name) !== 1));

    $this->assertSame([], $misnamed, sprintf('%s has ignore-failure toggles not named CI_<TOOL>_IGNORE_FAILURE: %s', basename($path), implode(', ', $misnamed)));
  }

  public static function dataProviderIgnoreFailureToggles(): \Iterator {
    foreach (glob(self::rootDir() . '/.github/workflows/*.yml') ?: [] as $path) {
      if (str_contains(File::read($path), 'continue-on-error:')) {
        yield basename($path) => ['path' => $path];
      }
    }
  }

  /**
   * Collect the names with a project segment.
   *
   * @param string $content
   *   The text to search.
   *
   * @return array<int, string>
   *   The distinct names, in order of appearance.
   */
  protected static function projectNames(string $content): array {
    preg_match_all(self::PROJECT_SEGMENT, $content, $matches);

    return array_values(array_unique($matches[0]));
  }

  /**
   * Collect the 'continue-on-error' values of a parsed workflow.
   *
   * @param mixed $node
   *   A node of the parsed workflow.
   *
   * @return array<int, string>
   *   The values that are expressions or strings.
   */
  protected static function continueOnErrorValues(mixed $node): array {
    if (!is_array($node)) {
      return [];
    }

    $values = [];

    foreach ($node as $key => $value) {
      if ($key === 'continue-on-error' && is_string($value)) {
        $values[] = $value;
      }

      $values = array_merge($values, self::continueOnErrorValues($value));
    }

    return $values;
  }

  protected static function rootDir(): string {
    return dirname(__DIR__, 4);
  }

}
