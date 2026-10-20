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

  #[DataProvider('dataProviderParseCalls')]
  public function testParseCalls(string $content, array $expected): void {
    $this->assertSame($expected, self::parseCalls($content));
  }

  public static function dataProviderParseCalls(): \Iterator {
    yield 'command' => ['content' => 'vendor/bin/eddy-login', 'expected' => ['eddy-login']];
    yield 'command with a redirection' => ['content' => 'vendor/bin/eddy-stop > /dev/null 2>&1 || true', 'expected' => ['eddy-stop']];
    yield 'argument of another command' => ['content' => 'vendor/bin/eddy-start site-url', 'expected' => ['eddy-start']];
    yield 'summary' => ['content' => 'cmd: vendor/bin/eddy-info', 'expected' => ['eddy-info']];
    yield 'field' => ['content' => '"$(vendor/bin/eddy-info site-url)"', 'expected' => ['eddy-info site-url']];
    yield 'field in single quotes' => ['content' => "vendor/bin/eddy-info 'webdriver-host'", 'expected' => ['eddy-info webdriver-host']];
    yield 'field in double quotes' => ['content' => 'vendor/bin/eddy-info "webdriver-host"', 'expected' => ['eddy-info webdriver-host']];
    yield 'field after spaces and a tab' => ['content' => "vendor/bin/eddy-info  \twebdriver-port", 'expected' => ['eddy-info webdriver-port']];
    yield 'field with an unclosed quote' => ['content' => "vendor/bin/eddy-info 'site-url", 'expected' => ['eddy-info']];
    yield 'word on the next line' => ['content' => "cmd: vendor/bin/eddy-info\n\n  debug:", 'expected' => ['eddy-info']];
    yield 'placeholder field' => ['content' => 'vendor/bin/eddy-info FIELD', 'expected' => ['eddy-info']];
    yield 'placeholder commands' => ['content' => 'vendor/bin/eddy-<command> and vendor/bin/eddy-*', 'expected' => []];
    yield 'repeated calls' => ['content' => "vendor/bin/eddy-info xdebug\nvendor/bin/eddy-start\nvendor/bin/eddy-info xdebug", 'expected' => ['eddy-info xdebug', 'eddy-start']];
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
      foreach (self::parseCalls(File::read(self::rootDir() . '/' . $path)) as $call) {
        $calls[$call][] = $path;
      }
    }

    self::assertNotSame([], $calls, 'The template calls no tooling commands.');

    ksort($calls);

    return $calls;
  }

  /**
   * Find the tooling calls in a text.
   *
   * @param string $content
   *   The text to search.
   *
   * @return array<int, string>
   *   The calls, each listed once, in the order they first appear.
   */
  protected static function parseCalls(string $content): array {
    // A field follows on the same line, quoted or not.
    preg_match_all('#vendor/bin/(eddy-[a-z]+(?:-[a-z]+)*)(?:[ \t]+([\'"]?)([a-z]+(?:-[a-z]+)*)\2)?#', $content, $matches, PREG_SET_ORDER);

    $calls = [];

    foreach ($matches as $match) {
      // Only 'eddy-info' reads its first argument as a field name.
      $calls[] = $match[1] === 'eddy-info' && isset($match[3]) ? $match[1] . ' ' . $match[3] : $match[1];
    }

    return array_values(array_unique($calls));
  }

}
