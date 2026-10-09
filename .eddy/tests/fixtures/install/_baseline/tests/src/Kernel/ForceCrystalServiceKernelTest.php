<?php

declare(strict_types=1);

namespace Drupal\Tests\force_crystal\Kernel;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Group;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the ForceCrystalService class.
 *
 * @group force_crystal
 */
#[Group('force_crystal')]
#[RunTestsInSeparateProcesses]
class ForceCrystalServiceKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['force_crystal'];

  /**
   * The ForceCrystalService instance.
   *
   * @var \Drupal\force_crystal\ForceCrystalService
   */
  protected $yourExtensionService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['force_crystal']);

    $this->config('force_crystal.settings')
      ->set('text', '<p>This is <strong>bold</strong> text.</p>')
      ->save();

    $this->yourExtensionService = $this->container->get('force_crystal.service');
  }

  /**
   * Tests the getText method of ForceCrystalService.
   */
  public function testGetText(): void {
    $text = $this->yourExtensionService->getText();

    // Assert that the text is sanitized and contains no HTML tags.
    $this->assertEquals('This is bold text.', $text);
  }

}
