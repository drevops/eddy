<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\is_debug;
use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[CoversFunction('DrevOps\Eddy\Tooling\is_debug')]
#[Group('p0')]
final class HelpersIsDebugTest extends UnitTestCase {

  #[DataProvider('dataProviderIsDebug')]
  public function testIsDebug(string|false $env_value, ?string $dotenv, bool $expected): void {
    chdir(self::$tmp);

    if ($env_value === FALSE) {
      self::envUnset('DEBUG');
    }
    else {
      self::envSet('DEBUG', $env_value);
    }

    if ($dotenv !== NULL) {
      File::dump('.env', 'DEBUG=' . $dotenv . PHP_EOL);
    }

    $this->assertSame($expected, is_debug());
  }

  public static function dataProviderIsDebug(): \Iterator {
    yield 'debug enabled' => ['env_value' => '1', 'dotenv' => NULL, 'expected' => TRUE];
    yield 'debug disabled' => ['env_value' => '0', 'dotenv' => NULL, 'expected' => FALSE];
    yield 'debug not set' => ['env_value' => FALSE, 'dotenv' => NULL, 'expected' => FALSE];
    yield 'debug empty string' => ['env_value' => '', 'dotenv' => NULL, 'expected' => FALSE];
    yield 'debug other value' => ['env_value' => 'true', 'dotenv' => NULL, 'expected' => FALSE];
    yield 'debug enabled in .env' => ['env_value' => FALSE, 'dotenv' => '1', 'expected' => TRUE];
    yield 'environment over .env' => ['env_value' => '0', 'dotenv' => '1', 'expected' => FALSE];
    yield 'empty environment falls back to .env' => ['env_value' => '', 'dotenv' => '1', 'expected' => TRUE];
  }

}
