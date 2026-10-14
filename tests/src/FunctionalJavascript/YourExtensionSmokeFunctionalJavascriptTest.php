<?php

declare(strict_types=1);

namespace Drupal\Tests\your_extension\FunctionalJavascript;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Group;

/**
 * Smoke test validating the WebDriver and screenshot pipeline.
 *
 * @group your_extension
 */
#[Group('your_extension')]
#[RunTestsInSeparateProcesses]
class YourExtensionSmokeFunctionalJavascriptTest extends YourExtensionFunctionalJavascriptTestBase {

  /**
   * Tests WebDriver connectivity and screenshot generation.
   */
  public function testSmokeWebDriver(): void {
    // Verify unauthenticated page renders.
    $this->drupalGet('/user/login');
    $this->createAutoScreenshot();

    // Create user and log in.
    $account = $this->drupalCreateUser(['administer site configuration']);
    $this->assertNotEmpty($account);
    $this->drupalLogin($account);

    // Verify authenticated page renders.
    $this->drupalGet('<front>');
    $this->createAutoScreenshot();

    // Verify the extension's library is attached.
    $this->assertJsCondition('typeof Drupal !== "undefined" && typeof Drupal.behaviors.yourExtension !== "undefined"');
  }

}
