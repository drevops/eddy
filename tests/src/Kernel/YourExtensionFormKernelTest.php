<?php

declare(strict_types=1);

namespace Drupal\Tests\your_extension\Kernel;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\your_extension\Form\YourExtensionForm;

/**
 * Tests the YourExtensionForm class.
 *
 * @group your_extension
 */
#[Group('your_extension')]
#[RunTestsInSeparateProcesses]
class YourExtensionFormKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['your_extension'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->config('your_extension.settings')
      ->set('text', 'Saved text.')
      ->save();
  }

  /**
   * Tests that the form shows the saved text.
   */
  public function testBuildForm(): void {
    $form = $this->container->get('form_builder')->getForm(YourExtensionForm::class);

    $this->assertEquals('your_extension_settings_form', $form['#form_id']);
    $this->assertEquals('textarea', $form['text']['#type']);
    $this->assertEquals('Saved text.', $form['text']['#default_value']);
  }

  /**
   * Tests that submitting the form saves the text.
   */
  public function testSubmitForm(): void {
    $form_state = (new FormState())->setValues(['text' => 'Submitted text.']);

    $this->container->get('form_builder')->submitForm(YourExtensionForm::class, $form_state);

    $this->assertEquals([], $form_state->getErrors());
    $this->assertEquals('Submitted text.', $this->config('your_extension.settings')->get('text'));
  }

}
