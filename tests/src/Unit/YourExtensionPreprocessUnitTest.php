<?php

declare(strict_types=1);

namespace Drupal\Tests\your_extension\Unit;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the preprocess hooks of the your_extension theme.
 *
 * @group your_extension
 */
#[Group('your_extension')]
class YourExtensionPreprocessUnitTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    require_once dirname(__DIR__, 3) . '/your_extension.theme';
  }

  /**
   * Tests that every page gets the load time element at its bottom.
   */
  public function testPreprocessHtml(): void {
    $variables = ['page_bottom' => []];

    your_extension_preprocess_html($variables);

    $this->assertEquals(['#type' => 'html_tag', '#tag' => 'div', '#attributes' => ['data-your-extension-time' => TRUE]], $variables['page_bottom']['your_extension_time']);
  }

}
