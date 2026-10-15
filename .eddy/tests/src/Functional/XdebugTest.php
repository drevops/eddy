<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Functional;

use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the XDebug step-debugging toggle.
 */
#[Group('p5')]
final class XdebugTest extends ToolingTestCase {

  public function testAhoy(): void {
    $this->assertToggle('ahoy');
  }

  public function testMake(): void {
    $this->assertToggle('make');
  }

  protected function assertToggle(string $tool): void {
    $this->assertTrue(extension_loaded('xdebug'), 'Xdebug PHP extension must be installed in the runner PHP for this test group.');

    $this->processRun($tool, ['assemble'], [], [], $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('ASSEMBLE COMPLETE');

    $this->processRun($tool, ['start'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('XDebug    : Disabled');

    // The Drupal `.ht.router.php` serves any existing file, so a PHP file in
    // the docroot runs in the webserver whose xdebug configuration the test
    // toggles.
    $probe = self::$sut . '/build/web/_xdebug_check.php';
    File::dump($probe, '<?php echo ini_get("xdebug.mode") ?: "off";');

    $this->assertSame('off', self::fetchProbe(), 'Baseline: xdebug.mode is off.');

    $this->processRun($tool, ['debug'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('Enabled XDebug');
    $this->assertSame('debug', self::fetchProbe(), 'After debug: xdebug.mode is debug.');

    $this->processRun($tool, ['debug'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('XDebug is already enabled');
    $this->assertSame('debug', self::fetchProbe(), 'After repeat debug: still debug.');

    $this->processRun($tool, ['start'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('XDebug    : Disabled');
    $this->assertSame('off', self::fetchProbe(), 'After start: xdebug.mode is off again.');
  }

  protected static function fetchProbe(): string {
    $body = @file_get_contents('http://localhost:' . self::webserverPort() . '/_xdebug_check.php');

    return $body === FALSE ? '' : trim($body);
  }

}
