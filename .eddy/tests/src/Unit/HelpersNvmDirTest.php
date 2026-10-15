<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\nvm_dir;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[CoversFunction('DrevOps\Eddy\Tooling\nvm_dir')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersNvmDirTest extends UnitTestCase {

  /**
   * @param array<string, string|null> $env
   *   The environment, with NULL for a variable that is not set.
   */
  #[DataProvider('dataProviderNvmDir')]
  public function testNvmDir(array $env, string $expected): void {
    foreach ($env as $name => $value) {
      if ($value === NULL) {
        self::envUnset($name);
      }
      else {
        self::envSet($name, $value);
      }
    }

    $this->assertSame($expected, nvm_dir());
  }

  public static function dataProviderNvmDir(): \Iterator {
    yield 'NVM_DIR' => [['NVM_DIR' => '/opt/nvm', 'XDG_CONFIG_HOME' => NULL, 'HOME' => '/home/user'], '/opt/nvm'];
    yield 'NVM_DIR over XDG_CONFIG_HOME' => [['NVM_DIR' => '/opt/nvm', 'XDG_CONFIG_HOME' => '/home/user/.config', 'HOME' => '/home/user'], '/opt/nvm'];
    yield 'XDG_CONFIG_HOME' => [['NVM_DIR' => NULL, 'XDG_CONFIG_HOME' => '/home/user/.config', 'HOME' => '/home/user'], '/home/user/.config/nvm'];
    yield 'empty NVM_DIR' => [['NVM_DIR' => '', 'XDG_CONFIG_HOME' => '/home/user/.config', 'HOME' => '/home/user'], '/home/user/.config/nvm'];
    yield 'HOME' => [['NVM_DIR' => NULL, 'XDG_CONFIG_HOME' => NULL, 'HOME' => '/home/user'], '/home/user/.nvm'];
    yield 'empty XDG_CONFIG_HOME' => [['NVM_DIR' => NULL, 'XDG_CONFIG_HOME' => '', 'HOME' => '/home/user'], '/home/user/.nvm'];
    yield 'no HOME' => [['NVM_DIR' => NULL, 'XDG_CONFIG_HOME' => NULL, 'HOME' => NULL], '/.nvm'];
  }

}
