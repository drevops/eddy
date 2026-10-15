<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\is_debug;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[CoversFunction('DrevOps\Eddy\Tooling\is_debug')]
#[Group('p0')]
final class HelpersIsDebugTest extends UnitTestCase {

  #[DataProvider('dataProviderIsDebug')]
  public function testIsDebug(string|false $env_value, bool $expected): void {
    if ($env_value === FALSE) {
      self::envUnset('DEBUG');
    }
    else {
      self::envSet('DEBUG', $env_value);
    }
    $this->assertSame($expected, is_debug());
  }

  public static function dataProviderIsDebug(): \Iterator {
    yield 'debug enabled' => ['env_value' => '1', 'expected' => TRUE];
    yield 'debug disabled' => ['env_value' => '0', 'expected' => FALSE];
    yield 'debug not set' => ['env_value' => FALSE, 'expected' => FALSE];
    yield 'debug empty string' => ['env_value' => '', 'expected' => FALSE];
    yield 'debug other value' => ['env_value' => 'true', 'expected' => FALSE];
  }

}
