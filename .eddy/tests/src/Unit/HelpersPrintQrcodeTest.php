<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\print_qrcode;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[CoversFunction('DrevOps\Eddy\Tooling\print_qrcode')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersPrintQrcodeTest extends UnitTestCase {

  public function testEmptyUrlDoesNothing(): void {
    // With qrencode available, only the empty URL can stop the render.
    $this->mockCommandAvailable('qrencode', TRUE);
    $this->mockPassthruNever();

    $output = $this->capture(fn() => print_qrcode(''));

    $this->assertSame('', $output);
  }

  #[DataProvider('dataProviderRendersCode')]
  public function testRendersCode(?string $login_qrcode): void {
    if ($login_qrcode !== NULL) {
      self::envSet('LOGIN_QRCODE', $login_qrcode);
    }

    $this->mockCommandAvailable('qrencode', TRUE);
    $this->mockPassthru([
      'cmd' => "qrencode -t ANSIUTF8 'https://example.com'",
      'output' => '[QR-CODE]',
    ]);

    $output = $this->capture(fn() => print_qrcode('https://example.com'));

    $this->assertStringContainsString('[QR-CODE]', $output);
  }

  public static function dataProviderRendersCode(): \Iterator {
    yield 'default' => [NULL];
    // Whether to draw a code is decided by the caller, so the login opt-in
    // has no bearing on this helper.
    yield 'login opt-in unset' => [''];
  }

  public function testMissingQrencodeFailsWithInstallHint(): void {
    $this->mockCommandAvailable('qrencode', FALSE);
    $this->mockPassthruNever();
    $this->mockQuit(1);

    $output = $this->capture(fn() => print_qrcode('https://example.com'), 1);

    $this->assertStringContainsString("Command 'qrencode' is not available", $output);
    $this->assertStringContainsString('https://fukuchi.org/works/qrencode/', $output);
  }

}
