<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\drupal_profile;
use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[CoversFunction('DrevOps\Eddy\Tooling\drupal_profile')]
#[Group('p0')]
final class HelpersDrupalProfileTest extends UnitTestCase {

  #[DataProvider('dataProviderDrupalProfile')]
  public function testDrupalProfile(?string $env, ?string $dotenv, string $expected): void {
    chdir(self::$tmp);

    if ($env === NULL) {
      self::envUnset('DRUPAL_PROFILE');
    }
    else {
      self::envSet('DRUPAL_PROFILE', $env);
    }

    if ($dotenv !== NULL) {
      File::dump('.env', 'DRUPAL_PROFILE=' . $dotenv . PHP_EOL);
    }

    $this->assertSame($expected, drupal_profile());
  }

  public static function dataProviderDrupalProfile(): \Iterator {
    yield 'default' => [NULL, NULL, 'standard'];
    yield 'environment' => ['minimal', NULL, 'minimal'];
    yield '.env' => [NULL, 'minimal', 'minimal'];
    yield 'environment over .env' => ['testing', 'minimal', 'testing'];
    yield 'empty environment falls back to .env' => ['', 'minimal', 'minimal'];
  }

}
