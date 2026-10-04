<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\DevTools\print_qrcode;
use DrevOps\Eddy\Tests\Exceptions\QuitErrorException;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the print_qrcode() helper.
 *
 * Runs in isolated processes so the exec() mock for the `qrencode` probe is
 * not polluted by sibling tests. Those tests invoke the real command_path()
 * in the shared process.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[RunTestsInSeparateProcesses]
#[CoversFunction('DrevOps\Eddy\DevTools\print_qrcode')]
#[Group('p0')]
final class HelpersPrintQrcodeTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';
  }

  public function testEmptyUrlDoesNothing(): void {
    // With qrencode available, only the empty URL can stop the render.
    $this->mockCommandAvailable('qrencode', TRUE);
    $this->mockPassthruNever();

    ob_start();
    print_qrcode('');
    $output = ob_get_clean();
    $this->assertIsString($output);

    $this->assertSame('', $output);
  }

  public function testRendersCode(): void {
    $this->mockCommandAvailable('qrencode', TRUE);
    $this->mockPassthru([
      'cmd' => "qrencode -t ANSIUTF8 'https://example.com'",
      'output' => '[QR-CODE]',
    ]);

    ob_start();
    print_qrcode('https://example.com');
    $output = ob_get_clean();
    $this->assertIsString($output);

    $this->assertStringContainsString('[QR-CODE]', $output);
  }

  public function testRendersCodeRegardlessOfLoginOptIn(): void {
    // Whether to draw a code is decided by the caller, so the login opt-in
    // has no bearing on this helper.
    $this->envSet('LOGIN_QRCODE', '');
    $this->mockCommandAvailable('qrencode', TRUE);
    $this->mockPassthru([
      'cmd' => "qrencode -t ANSIUTF8 'https://example.com'",
      'output' => '[QR-CODE]',
    ]);

    ob_start();
    print_qrcode('https://example.com');
    $output = ob_get_clean();
    $this->assertIsString($output);

    $this->assertStringContainsString('[QR-CODE]', $output);
  }

  public function testMissingQrencodeFailsWithInstallHint(): void {
    $this->mockCommandAvailable('qrencode', FALSE);
    $this->mockPassthruNever();
    $this->mockQuit(1);

    ob_start();
    try {
      print_qrcode('https://example.com');
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
    $this->assertStringContainsString('https://fukuchi.org/works/qrencode/', $output);
  }

}
