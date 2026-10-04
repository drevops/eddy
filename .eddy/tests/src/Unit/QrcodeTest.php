<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use DrevOps\Eddy\Tests\Exceptions\QuitErrorException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the 'eddy-qrcode' command.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class QrcodeTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';
  }

  public function testQrcodeRendersQrcodeForUrlArgument(): void {
    // Invoking the command is an explicit request, so no opt-in is consulted.
    $this->envSet('LOGIN_QRCODE', '');
    $this->mockCommandAvailable('qrencode', TRUE);
    $this->mockPassthru([
      'cmd' => "qrencode -t ANSIUTF8 'https://example.com'",
      'output' => '[QR-CODE]',
    ]);

    $argv = ['qrcode', 'https://example.com'];
    ob_start();
    require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-qrcode';
    $output = ob_get_clean();
    $this->assertIsString($output);

    $this->assertStringContainsString('[QR-CODE]', $output);
  }

  public function testQrcodeDoesNothingWithoutUrlArgument(): void {
    $this->mockCommandAvailable('qrencode', TRUE);
    $this->mockPassthruNever();

    $argv = ['qrcode'];
    ob_start();
    require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-qrcode';
    $output = ob_get_clean();
    $this->assertIsString($output);

    $this->assertSame('', $output);
  }

  public function testQrcodeFailsWhenQrencodeMissing(): void {
    $this->mockCommandAvailable('qrencode', FALSE);
    $this->mockPassthruNever();
    $this->mockQuit(1);

    $argv = ['qrcode', 'https://example.com'];
    ob_start();
    try {
      require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-qrcode';
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(1, $e->getCode());
    }
    finally {
      $output = ob_get_clean();
      $this->assertIsString($output);
    }

    $this->assertStringContainsString("Command 'qrencode' is not available", $output);
  }

}
