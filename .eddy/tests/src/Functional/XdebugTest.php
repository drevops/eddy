<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Functional;

use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the XDebug step-debugging toggle.
 *
 * The runner PHP for this group has the xdebug extension installed with a
 * baseline `xdebug.mode=off`. The `debug` command overrides it via the
 * `-d xdebug.mode=debug` runtime flag.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[Group('p5')]
final class XdebugTest extends DevtoolsTestCase {

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
    file_put_contents($probe, '<?php echo ini_get("xdebug.mode") ?: "off";');

    $this->assertSame('off', $this->fetchProbe(), 'Baseline: xdebug.mode is off.');

    $this->processRun($tool, ['debug'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('Enabled XDebug');
    $this->assertSame('debug', $this->fetchProbe(), 'After debug: xdebug.mode is debug.');

    $this->processRun($tool, ['debug'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('XDebug is already enabled');
    $this->assertSame('debug', $this->fetchProbe(), 'After repeat debug: still debug.');

    $this->processRun($tool, ['start'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('XDebug    : Disabled');
    $this->assertSame('off', $this->fetchProbe(), 'After start: xdebug.mode is off again.');
  }

  protected function fetchProbe(): string {
    $env = file_exists(self::$sut . '/.env') ? parse_ini_file(self::$sut . '/.env') : [];
    $port = (string) ($env['WEBSERVER_PORT'] ?? '8000');
    $body = @file_get_contents('http://localhost:' . $port . '/_xdebug_check.php');

    return $body === FALSE ? '' : trim($body);
  }

}
