<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the update policy that the Renovate configuration sets.
 *
 * Without lock file maintenance, Renovate never updates the transitive npm
 * dependencies in `package-lock.json` that the direct ones still accept.
 *
 * The install snapshot pins `renovate.json`, but a snapshot update accepts
 * any change to it.
 */
#[Group('p0')]
final class RenovateConfigTest extends UnitTestCase {

  #[DataProvider('dataProviderLockFileMaintenanceOption')]
  public function testLockFileMaintenanceOption(string $option, bool $expected): void {
    $this->assertSame($expected, self::lockFileMaintenanceOption($option), sprintf('The lock file maintenance update in renovate.json must resolve "%s" to %s.', $option, var_export($expected, TRUE)));
  }

  public static function dataProviderLockFileMaintenanceOption(): \Iterator {
    yield 'enabled' => ['option' => 'enabled', 'expected' => TRUE];
    yield 'automerge' => ['option' => 'automerge', 'expected' => TRUE];
  }

  public function testMinimumReleaseAge(): void {
    $config = self::config();

    $this->assertSame('7 days', $config['minimumReleaseAge'] ?? NULL, 'renovate.json must hold every new version back for 7 days.');
    $this->assertTrue($config['minimumReleaseAgePackageManagerSync'] ?? NULL, 'renovate.json must pass the release age to npm, so lock file maintenance waits too.');
    $this->assertSame('strict', $config['internalChecksFilter'] ?? NULL, 'renovate.json must hold back an update until the release age passes.');

    foreach (self::packageRules() as $rule) {
      $this->assertArrayNotHasKey('minimumReleaseAge', $rule, 'No package rule may shorten the release age.');
    }
  }

  /**
   * @param array<string, string> $update
   * @param array<string, mixed> $expected
   */
  #[DataProvider('dataProviderUpdatePolicy')]
  public function testUpdatePolicy(array $update, array $expected): void {
    $resolved = self::resolve($update);

    $this->assertSame($expected, array_intersect_key($resolved, $expected), 'renovate.json resolves an unexpected policy for ' . json_encode($update) . '.');
  }

  public static function dataProviderUpdatePolicy(): \Iterator {
    yield 'npm runtime dependency' => [
      ['manager' => 'npm', 'depType' => 'dependencies', 'depName' => 'is-positive', 'updateType' => 'minor'],
      ['enabled' => TRUE, 'automerge' => FALSE, 'groupSlug' => 'runtime'],
    ];

    yield 'npm development dependency' => [
      ['manager' => 'npm', 'depType' => 'devDependencies', 'depName' => 'eslint', 'updateType' => 'minor'],
      ['enabled' => TRUE, 'automerge' => TRUE, 'groupSlug' => 'all'],
    ];

    yield 'GitHub Action' => [
      ['manager' => 'github-actions', 'depType' => 'action', 'depName' => 'actions/checkout', 'updateType' => 'digest'],
      ['enabled' => TRUE, 'automerge' => TRUE, 'groupSlug' => 'all'],
    ];

    yield 'npm major update' => [
      ['manager' => 'npm', 'depType' => 'dependencies', 'depName' => 'is-positive', 'updateType' => 'major'],
      ['enabled' => FALSE],
    ];

    yield 'Node.js engine' => [
      ['manager' => 'npm', 'depType' => 'engines', 'depName' => 'node', 'updateType' => 'minor'],
      ['enabled' => FALSE],
    ];
  }

  /**
   * Resolve an option of the lock file maintenance update.
   *
   * Renovate merges the `lockFileMaintenance` block onto the repository
   * configuration, so a value in the block wins over a top-level one. The
   * block defaults `enabled` to false.
   */
  protected static function lockFileMaintenanceOption(string $option): mixed {
    $config = self::config();
    $block = (is_array($config['lockFileMaintenance'] ?? NULL) ? $config['lockFileMaintenance'] : []) + ['enabled' => FALSE];

    return $block[$option] ?? $config[$option] ?? NULL;
  }

  /**
   * Resolve the options of an update the way Renovate applies package rules.
   *
   * Every rule whose matchers all match is merged in order, so a later rule
   * wins. Only the matchers the template uses are supported, so a new one
   * fails the test until it is added here.
   *
   * @param array<string, string> $update
   *   The 'manager', 'depType', 'depName' and 'updateType' of the update.
   *
   * @return array<string, mixed>
   *   The resolved 'enabled', 'automerge' and 'groupSlug' options.
   */
  protected static function resolve(array $update): array {
    $config = self::config();
    $resolved = ['enabled' => TRUE, 'automerge' => $config['automerge'] ?? FALSE, 'groupSlug' => NULL];
    $matchers = ['matchManagers' => 'manager', 'matchDepTypes' => 'depType', 'matchDepNames' => 'depName', 'matchPackageNames' => 'depName', 'matchUpdateTypes' => 'updateType'];

    foreach (self::packageRules() as $rule) {
      foreach (array_filter(array_keys($rule), static fn(int|string $key): bool => str_starts_with((string) $key, 'match')) as $key) {
        if (!isset($matchers[$key])) {
          self::fail(sprintf('RenovateConfigTest does not support the "%s" matcher.', $key));
        }

        $values = (array) $rule[$key];

        if (!in_array('*', $values, TRUE) && !in_array($update[$matchers[$key]], $values, TRUE)) {
          continue 2;
        }
      }

      $resolved = array_replace($resolved, array_intersect_key($rule, $resolved));
    }

    return $resolved;
  }

  /**
   * Read the template's Renovate configuration.
   *
   * @return array<mixed>
   *   The configuration.
   */
  protected static function config(): array {
    $config = json_decode(File::read(self::rootDir() . '/renovate.json'), TRUE);

    if (!is_array($config)) {
      self::fail('renovate.json does not parse to an object.');
    }

    return $config;
  }

  /**
   * Read the package rules of the template's Renovate configuration.
   *
   * @return list<array<mixed>>
   *   The package rules, in order.
   */
  protected static function packageRules(): array {
    $rules = self::config()['packageRules'] ?? [];

    if (!is_array($rules)) {
      self::fail('The packageRules of renovate.json is not a list.');
    }

    $list = [];

    foreach ($rules as $rule) {
      if (!is_array($rule)) {
        self::fail('A package rule in renovate.json is not an object.');
      }

      $list[] = $rule;
    }

    return $list;
  }

}
