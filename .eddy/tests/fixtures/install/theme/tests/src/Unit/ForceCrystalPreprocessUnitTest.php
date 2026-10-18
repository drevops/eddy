<?php

declare(strict_types=1);

namespace Drupal\Tests\force_crystal\Unit;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the preprocess hooks of the force_crystal theme.
 *
 * @group force_crystal
 */
#[Group('force_crystal')]
class ForceCrystalPreprocessUnitTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    require_once dirname(__DIR__, 3) . '/force_crystal.theme';
  }

  /**
   * Tests that every page gets the load time element at its bottom.
   */
  public function testPreprocessHtml(): void {
    $variables = ['page_bottom' => []];

    force_crystal_preprocess_html($variables);

    $this->assertEquals(['#type' => 'html_tag', '#tag' => 'div', '#attributes' => ['data-force_crystal-time' => TRUE]], $variables['page_bottom']['force_crystal_time']);
  }

}
