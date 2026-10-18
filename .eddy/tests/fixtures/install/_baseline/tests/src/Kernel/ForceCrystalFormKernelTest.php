<?php

declare(strict_types=1);

namespace Drupal\Tests\force_crystal\Kernel;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\force_crystal\Form\ForceCrystalForm;

/**
 * Tests the ForceCrystalForm class.
 *
 * @group force_crystal
 */
#[Group('force_crystal')]
#[RunTestsInSeparateProcesses]
class ForceCrystalFormKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['force_crystal'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->config('force_crystal.settings')
      ->set('text', 'Saved text.')
      ->save();
  }

  /**
   * Tests that the form shows the saved text.
   */
  public function testBuildForm(): void {
    $form = $this->container->get('form_builder')->getForm(ForceCrystalForm::class);

    $this->assertEquals('force_crystal_settings_form', $form['#form_id']);
    $this->assertEquals('textarea', $form['text']['#type']);
    $this->assertEquals('Saved text.', $form['text']['#default_value']);
  }

  /**
   * Tests that submitting the form saves the text.
   */
  public function testSubmitForm(): void {
    $form_state = (new FormState())->setValues(['text' => 'Submitted text.']);

    $this->container->get('form_builder')->submitForm(ForceCrystalForm::class, $form_state);

    $this->assertEquals([], $form_state->getErrors());
    $this->assertEquals('Submitted text.', $this->config('force_crystal.settings')->get('text'));
  }

}
