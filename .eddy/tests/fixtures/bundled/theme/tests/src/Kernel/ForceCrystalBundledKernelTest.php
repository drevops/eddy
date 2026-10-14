<?php

declare(strict_types=1);

namespace Drupal\Tests\force_crystal\Kernel;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Group;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the modules that the theme bundles.
 *
 * @group force_crystal
 */
#[Group('force_crystal')]
#[RunTestsInSeparateProcesses]
class ForceCrystalBundledKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['force_crystal_companion', 'force_crystal_test'];

  /**
   * Tests that the bundled modules are found and enabled.
   */
  public function testBundledModules(): void {
    $module_handler = \Drupal::moduleHandler();

    $this->assertTrue($module_handler->moduleExists('force_crystal_companion'));
    $this->assertTrue($module_handler->moduleExists('force_crystal_test'));
  }

}
