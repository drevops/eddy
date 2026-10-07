<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the WebDriver endpoint follows the resolved WEBDRIVER_PORT.
 *
 * The shipped PHPUnit configs carry the WebDriver endpoint as a literal. Both
 * backends bind a port resolved at run time and persisted to '.env', so
 * several projects can run FunctionalJavascript tests at once.
 *
 * 2 rules keep the literal from becoming authoritative:
 *
 * - The FunctionalJavascript base class rewrites the endpoint from
 *   WEBDRIVER_PORT, so every test that extends it reaches the browser
 *   wherever 'eddy-browser' put it.
 * - The env entry is not forced, so a project whose tests do not extend that
 *   base class can still export its own MINK_DRIVER_ARGS_WEBDRIVER.
 *
 * If either rule is removed, the tests connect to 4444 while the browser
 * listens on another port. The tests then fail only on a machine where 4444 is
 * busy.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[Group('p0')]
final class PhpunitWebdriverEndpointTest extends UnitTestCase {

  #[DataProvider('dataProviderEndpointIsOverridableFromTheEnvironment')]
  public function testEndpointIsOverridableFromTheEnvironment(string $path): void {
    $element = self::minkDriverArgsElement($path);

    $this->assertFalse($element->hasAttribute('force'), sprintf('%s forces MINK_DRIVER_ARGS_WEBDRIVER, so an exported value cannot override the hardcoded endpoint.', basename($path)));
  }

  /**
   * @return \Iterator<string, array{path: string}>
   *   Path of each shipped PHPUnit configuration file.
   */
  public static function dataProviderEndpointIsOverridableFromTheEnvironment(): \Iterator {
    yield from self::phpunitConfigPaths();
  }

  #[DataProvider('dataProviderEndpointLiteralIsTheDefaultPort')]
  public function testEndpointLiteralIsTheDefaultPort(string $path): void {
    $args = json_decode(self::minkDriverArgsElement($path)->getAttribute('value'), TRUE);

    $this->assertIsArray($args, sprintf('%s does not hold a JSON array in MINK_DRIVER_ARGS_WEBDRIVER.', basename($path)));
    $this->assertArrayHasKey(2, $args, sprintf('%s omits the WebDriver endpoint from MINK_DRIVER_ARGS_WEBDRIVER.', basename($path)));
    $this->assertSame('http://localhost:4444', $args[2], sprintf('%s must carry the default WebDriver endpoint; anything else desynchronises from resolve_webdriver_port().', basename($path)));
  }

  /**
   * @return \Iterator<string, array{path: string}>
   *   Path of each shipped PHPUnit configuration file.
   */
  public static function dataProviderEndpointLiteralIsTheDefaultPort(): \Iterator {
    yield from self::phpunitConfigPaths();
  }

  public function testBaseClassRewritesEndpointFromResolvedPort(): void {
    $path = dirname(__DIR__, 4) . '/tests/src/FunctionalJavascript/YourExtensionFunctionalJavascriptTestBase.php';
    $contents = file_get_contents($path);

    $this->assertIsString($contents);
    $this->assertStringContainsString('function getMinkDriverArgs()', $contents, 'The base class must override getMinkDriverArgs() to apply the resolved WebDriver port.');
    $this->assertStringContainsString("getenv('WEBDRIVER_PORT')", $contents, 'The base class must read WEBDRIVER_PORT; without it the endpoint stays pinned to the phpunit.xml literal.');
  }

  /**
   * Get the path of each shipped PHPUnit configuration file.
   *
   * @return \Iterator<string, array{path: string}>
   *   Configuration file name mapped to its absolute path.
   */
  protected static function phpunitConfigPaths(): \Iterator {
    $root = dirname(__DIR__, 4);

    foreach (['phpunit.xml', 'phpunit.d10.xml'] as $file) {
      yield $file => ['path' => $root . '/' . $file];
    }
  }

  /**
   * Get the MINK_DRIVER_ARGS_WEBDRIVER env element from a PHPUnit config.
   */
  protected static function minkDriverArgsElement(string $path): \DOMElement {
    $document = new \DOMDocument();
    self::assertTrue($document->load($path), sprintf('Unable to parse %s.', $path));

    $nodes = (new \DOMXPath($document))->query('//php/env[@name="MINK_DRIVER_ARGS_WEBDRIVER"]');
    self::assertInstanceOf(\DOMNodeList::class, $nodes);
    self::assertSame(1, $nodes->length, sprintf('%s must declare exactly one MINK_DRIVER_ARGS_WEBDRIVER entry.', basename($path)));

    $element = $nodes->item(0);
    self::assertInstanceOf(\DOMElement::class, $element);

    return $element;
  }

}
