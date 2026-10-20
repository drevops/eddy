<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the tooling constraint allows only releases the template can use.
 *
 * A checkout keeps the tooling release it installed until the constraint in
 * 'composer.dev.json' changes, so it can hold any release that the constraint
 * allows, down to the lowest. Every command and 'eddy-info' field that the
 * template calls has to ship in that lowest release.
 *
 * The other tests install the in-tree package, so they pass while the lowest
 * release lacks a call.
 */
#[Group('p0')]
final class ToolingConstraintTest extends UnitTestCase {

  /**
   * The first tooling release that ships each call the template makes.
   *
   * A call is a command, or 'eddy-info' with the field it prints.
   */
  protected const array RELEASES = [
    'eddy-assemble' => '1.0.0',
    'eddy-browser-start' => '1.0.0',
    'eddy-browser-stop' => '1.0.0',
    'eddy-deploy' => '1.0.0',
    'eddy-info' => '1.0.0',
    'eddy-info site-url' => '1.0.0',
    'eddy-info webdriver-backend' => '1.0.3',
    'eddy-info webdriver-host' => '1.0.3',
    'eddy-info webdriver-port' => '1.0.0',
    'eddy-info webserver-port' => '1.0.0',
    'eddy-info xdebug' => '1.0.0',
    'eddy-login' => '1.0.4',
    'eddy-provision' => '1.0.0',
    'eddy-start' => '1.0.0',
    'eddy-stop' => '1.0.0',
  ];

  /**
   * The files besides the workflows that ship to projects and call the tooling.
   */
  protected const array CALLERS = ['.ahoy.yml', 'Makefile', 'AGENTS.md', 'CONTRIBUTING.dist.md'];

  #[DataProvider('dataProviderLowestReleaseShipsCall')]
  public function testLowestReleaseShipsCall(string $call, array $paths): void {
    $location = sprintf('The template calls `%s` in %s', $call, implode(', ', $paths));

    $this->assertArrayHasKey($call, self::RELEASES, $location . ', but RELEASES does not list the first tooling release that ships it.');

    $release = self::RELEASES[$call];
    $lowest = self::lowestRelease();

    $this->assertTrue(version_compare($release, $lowest, '<='), sprintf('%s, which tooling %s first ships, but the tooling constraint in composer.dev.json allows %s. Raise the constraint to ~%s.', $location, $release, $lowest, $release));
  }

  public static function dataProviderLowestReleaseShipsCall(): \Iterator {
    foreach (self::calls() as $call => $paths) {
      yield $call => ['call' => $call, 'paths' => $paths];
    }
  }

  #[DataProvider('dataProviderRecordedCallIsMade')]
  public function testRecordedCallIsMade(string $call): void {
    $this->assertArrayHasKey($call, self::calls(), sprintf('RELEASES lists `%s`, which the template does not call.', $call));
  }

  public static function dataProviderRecordedCallIsMade(): \Iterator {
    foreach (array_keys(self::RELEASES) as $call) {
      yield $call => ['call' => $call];
    }
  }

  /**
   * Read the lowest release that the tooling constraint allows.
   *
   * @return string
   *   The version that the constraint starts from.
   */
  protected static function lowestRelease(): string {
    $constraint = self::toolingConstraint();

    self::assertMatchesRegularExpression('/^~\d+\.\d+\.\d+$/', $constraint, sprintf('The tooling constraint "%s" in composer.dev.json is not a "~X.Y.Z" constraint, which holds the minor version and starts from release X.Y.Z.', $constraint));

    return substr($constraint, 1);
  }

  /**
   * List the tooling calls of the files that ship to generated projects.
   *
   * @return array<string, array<int, string>>
   *   The files that make each call, keyed by the call and sorted by it.
   */
  protected static function calls(): array {
    $paths = self::CALLERS;
    $workflows = array_merge(glob(self::rootDir() . '/.github/workflows/*.yml') ?: [], glob(self::rootDir() . '/.github/workflows/*.yaml') ?: []);

    foreach ($workflows as $workflow) {
      // install.php removes the maintenance workflows from generated projects.
      if (!str_starts_with(basename($workflow), 'eddy-')) {
        $paths[] = '.github/workflows/' . basename($workflow);
      }
    }

    $calls = [];

    foreach ($paths as $path) {
      preg_match_all('#vendor/bin/(eddy-[a-z]+(?:-[a-z]+)*)(?: ([a-z]+(?:-[a-z]+)*))?#', File::read(self::rootDir() . '/' . $path), $matches, PREG_SET_ORDER);

      foreach ($matches as $match) {
        // Only 'eddy-info' reads its first argument as a field name.
        $call = $match[1] === 'eddy-info' && isset($match[2]) ? $match[1] . ' ' . $match[2] : $match[1];
        $calls[$call][$path] = $path;
      }
    }

    self::assertNotSame([], $calls, 'The template calls no tooling commands.');

    ksort($calls);

    return array_map(array_values(...), $calls);
  }

}
