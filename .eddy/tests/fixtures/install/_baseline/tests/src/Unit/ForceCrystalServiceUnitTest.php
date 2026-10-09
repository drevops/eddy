<?php

declare(strict_types=1);

namespace Drupal\Tests\force_crystal\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use Drupal\Tests\UnitTestCase;
use Drupal\force_crystal\ForceCrystalService;

/**
 * Tests the ForceCrystalService class.
 *
 * @group force_crystal
 */
#[Group('force_crystal')]
class ForceCrystalServiceUnitTest extends UnitTestCase {

  /**
   * Tests the sanitize method of ForceCrystalService.
   *
   * @covers \Drupal\force_crystal\ForceCrystalService::sanitize
   *
   * @dataProvider dataProviderSanitize
   */
  #[DataProvider('dataProviderSanitize')]
  public function testSanitize(string $input, string $expected): void {
    $this->assertEquals($expected, ForceCrystalService::sanitize($input));
  }

  /**
   * Provides data for testing the sanitize method.
   */
  public static function dataProviderSanitize(): array {
    return [
      ['', ''],
      ['<p>This is <strong>bold</strong> text.</p>', 'This is bold text.'],
      ['<div><span>This is some <em>italic</em> text.</span></div>', 'This is some italic text.'],
      ['<script>alert("Hello!");</script>', 'alert("Hello!");'],
    ];
  }

}
