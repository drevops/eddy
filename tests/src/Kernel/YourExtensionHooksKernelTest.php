<?php

declare(strict_types=1);

namespace Drupal\Tests\your_extension\Kernel;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the hooks of the your_extension module.
 *
 * @group your_extension
 */
#[Group('your_extension')]
#[RunTestsInSeparateProcesses]
class YourExtensionHooksKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['config', 'system', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // The user module deletes the data of every module that is uninstalled.
    $this->installSchema('user', ['users_data']);

    // The module installer runs hook_install(), which enabling the module
    // through $modules skips.
    $this->container->get('module_installer')->install(['your_extension']);
  }

  /**
   * Tests that installing the module saves its settings and its route.
   */
  public function testInstall(): void {
    $this->assertEquals('Default content to be displayed if JS is disabled.', $this->config('your_extension.settings')->get('text'));

    $route = $this->container->get('router.route_provider')->getRouteByName('your_extension.settings_form');
    $this->assertEquals('/admin/config/development/your-extension', $route->getPath());
  }

  /**
   * Tests that uninstalling the module removes its settings.
   */
  public function testUninstall(): void {
    $this->container->get('module_installer')->uninstall(['your_extension']);

    $this->assertTrue($this->config('your_extension.settings')->isNew());
  }

  /**
   * Tests that every page gets the global library.
   */
  public function testPageAttachments(): void {
    $attachments = [];

    your_extension_page_attachments($attachments);

    $this->assertEquals(['your_extension/global'], $attachments['#attached']['library']);
  }

  /**
   * Tests the elements that every page gets at its bottom.
   *
   * @dataProvider dataProviderPageBottom
   */
  #[DataProvider('dataProviderPageBottom')]
  public function testPageBottom(string $text, ?string $expected_noscript): void {
    $this->config('your_extension.settings')->set('text', $text)->save();
    $page_bottom = [];

    your_extension_page_bottom($page_bottom);

    if ($expected_noscript === NULL) {
      $this->assertArrayNotHasKey('your_extension', $page_bottom);
    }
    else {
      $this->assertEquals(['#type' => 'html_tag', '#tag' => 'noscript', '#value' => $expected_noscript], $page_bottom['your_extension']);
    }

    $this->assertEquals(['#type' => 'html_tag', '#tag' => 'div', '#attributes' => ['data-your-extension-time' => TRUE]], $page_bottom['your_extension_time']);
  }

  /**
   * Provides data for testing the page bottom elements.
   */
  public static function dataProviderPageBottom(): array {
    return [
      ['<p>This is <strong>bold</strong> text.</p>', 'This is bold text.'],
      ['', NULL],
    ];
  }

}
