<?php

declare(strict_types=1);

namespace Drupal\Tests\force_crystal\Functional;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Group;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the functionality of ForceCrystalForm.
 *
 * @coversDefaultClass \Drupal\force_crystal\Form\ForceCrystalForm
 *
 * @group force_crystal
 */
#[Group('force_crystal')]
#[RunTestsInSeparateProcesses]
class ForceCrystalFunctionalTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['force_crystal'];

  /**
   * Tests that text saved through the settings form renders in <noscript>.
   */
  public function testGetText(): void {
    $account = $this->drupalCreateUser(['administer site configuration']);
    $this->assertNotEmpty($account);
    $this->drupalLogin($account);

    $this->drupalGet('admin/config/development/force_crystal');

    $edit = [
      'text' => '<p>This is test content.</p>',
    ];
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->statusMessageContains('The configuration options have been saved.');

    $this->drupalGet('<front>');
    $this->assertSession()->responseContains('<noscript>This is test content.</noscript>');
  }

}
