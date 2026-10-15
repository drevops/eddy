<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class QrcodeTest extends UnitTestCase {

  public function testQrcodeRendersQrcodeForUrlArgument(): void {
    // Invoking the command is an explicit request, so no opt-in is consulted.
    self::envSet('LOGIN_QRCODE', '');
    $this->mockCommandAvailable('qrencode', TRUE);
    $this->mockPassthru([
      'cmd' => "qrencode -t ANSIUTF8 'https://example.com'",
      'output' => '[QR-CODE]',
    ]);

    $output = $this->runCommand('eddy-qrcode', NULL, ['qrcode', 'https://example.com']);

    $this->assertStringContainsString('[QR-CODE]', $output);
  }

  public function testQrcodeDoesNothingWithoutUrlArgument(): void {
    $this->mockCommandAvailable('qrencode', TRUE);
    $this->mockPassthruNever();

    $output = $this->runCommand('eddy-qrcode', NULL, ['qrcode']);

    $this->assertSame('', $output);
  }

  public function testQrcodeFailsWhenQrencodeMissing(): void {
    $this->mockCommandAvailable('qrencode', FALSE);
    $this->mockPassthruNever();
    $this->mockQuit(1);

    $output = $this->runCommand('eddy-qrcode', 1, ['qrcode', 'https://example.com']);

    $this->assertStringContainsString("Command 'qrencode' is not available", $output);
  }

}
