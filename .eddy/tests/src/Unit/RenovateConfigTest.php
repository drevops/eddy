<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the lock file maintenance that the Renovate configuration enables.
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

  /**
   * Resolve an option of the lock file maintenance update.
   *
   * Renovate merges the `lockFileMaintenance` block onto the repository
   * configuration, so a value in the block wins over a top-level one. The
   * block defaults `enabled` to false.
   */
  protected static function lockFileMaintenanceOption(string $option): mixed {
    $config = json_decode(File::read(self::rootDir() . '/renovate.json'), TRUE);

    if (!is_array($config)) {
      self::fail('renovate.json does not parse to an object.');
    }

    $block = (is_array($config['lockFileMaintenance'] ?? NULL) ? $config['lockFileMaintenance'] : []) + ['enabled' => FALSE];

    return $block[$option] ?? $config[$option] ?? NULL;
  }

}
