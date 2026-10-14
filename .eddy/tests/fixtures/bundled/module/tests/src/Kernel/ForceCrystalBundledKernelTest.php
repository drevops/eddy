<?php

declare(strict_types=1);

namespace Drupal\Tests\force_crystal\Kernel;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Group;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the themes that the module bundles.
 *
 * @group force_crystal
 */
#[Group('force_crystal')]
#[RunTestsInSeparateProcesses]
class ForceCrystalBundledKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system']);
  }

  /**
   * Tests that the bundled themes are found and installed.
   */
  public function testBundledThemes(): void {
    \Drupal::service('theme_installer')->install(['force_crystal_admin', 'force_crystal_test_theme']);

    $themes = \Drupal::service('theme_handler')->listInfo();

    $this->assertArrayHasKey('force_crystal_admin', $themes);
    $this->assertArrayHasKey('force_crystal_test_theme', $themes);
  }

}
