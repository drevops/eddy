<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\nvm_dir;
use AlexSkrypnyk\File\File;
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
   * @param string|null $dotenv
   *   The content of '.env', or NULL for no file.
   */
  #[DataProvider('dataProviderNvmDir')]
  public function testNvmDir(array $env, ?string $dotenv, string $expected): void {
    chdir(self::$tmp);

    foreach ($env as $name => $value) {
      if ($value === NULL) {
        self::envUnset($name);
      }
      else {
        self::envSet($name, $value);
      }
    }

    if ($dotenv !== NULL) {
      File::dump('.env', $dotenv);
    }

    $this->assertSame($expected, nvm_dir());
  }

  public static function dataProviderNvmDir(): \Iterator {
    yield 'NVM_DIR' => [['NVM_DIR' => '/opt/nvm', 'XDG_CONFIG_HOME' => NULL, 'HOME' => '/home/user'], NULL, '/opt/nvm'];
    yield 'NVM_DIR over XDG_CONFIG_HOME' => [['NVM_DIR' => '/opt/nvm', 'XDG_CONFIG_HOME' => '/home/user/.config', 'HOME' => '/home/user'], NULL, '/opt/nvm'];
    yield 'XDG_CONFIG_HOME' => [['NVM_DIR' => NULL, 'XDG_CONFIG_HOME' => '/home/user/.config', 'HOME' => '/home/user'], NULL, '/home/user/.config/nvm'];
    yield 'empty NVM_DIR' => [['NVM_DIR' => '', 'XDG_CONFIG_HOME' => '/home/user/.config', 'HOME' => '/home/user'], NULL, '/home/user/.config/nvm'];
    yield 'HOME' => [['NVM_DIR' => NULL, 'XDG_CONFIG_HOME' => NULL, 'HOME' => '/home/user'], NULL, '/home/user/.nvm'];
    yield 'empty XDG_CONFIG_HOME' => [['NVM_DIR' => NULL, 'XDG_CONFIG_HOME' => '', 'HOME' => '/home/user'], NULL, '/home/user/.nvm'];
    yield 'no HOME' => [['NVM_DIR' => NULL, 'XDG_CONFIG_HOME' => NULL, 'HOME' => NULL], NULL, '/.nvm'];
    yield 'NVM_DIR from .env' => [['NVM_DIR' => NULL, 'XDG_CONFIG_HOME' => '/home/user/.config', 'HOME' => '/home/user'], "NVM_DIR=/srv/nvm\n", '/srv/nvm'];
    yield 'NVM_DIR from the environment over .env' => [['NVM_DIR' => '/opt/nvm', 'XDG_CONFIG_HOME' => NULL, 'HOME' => '/home/user'], "NVM_DIR=/srv/nvm\n", '/opt/nvm'];
  }

}
