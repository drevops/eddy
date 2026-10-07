<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\DevTools\extension_info;
use function DrevOps\Eddy\DevTools\is_debug;
use DrevOps\Eddy\Tests\Exceptions\QuitErrorException;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[CoversFunction('DrevOps\Eddy\DevTools\extension_info')]
#[CoversFunction('DrevOps\Eddy\DevTools\is_debug')]
#[Group('p0')]
final class HelpersExtensionInfoTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';
  }

  #[DataProvider('dataProviderExtensionInfo')]
  public function testExtensionInfo(string $filename, string $content, string $expected_name, string $expected_type): void {
    $dir = self::$tmp . '/ext_' . uniqid();
    mkdir($dir, 0755, TRUE);
    file_put_contents($dir . '/' . $filename, $content);
    $original = (string) getcwd();
    chdir($dir);
    try {
      $result = extension_info();
      $this->assertSame(['name' => $expected_name, 'type' => $expected_type], $result);
    }
    finally {
      chdir($original);
    }
  }

  public static function dataProviderExtensionInfo(): \Iterator {
    yield 'module' => [
      'filename' => 'my_module.info.yml',
      'content' => "name: My Module\ntype: module\ncore_version_requirement: ^10\n",
      'expected_name' => 'my_module',
      'expected_type' => 'module',
    ];
    yield 'theme' => [
      'filename' => 'my_theme.info.yml',
      'content' => "name: My Theme\ntype: theme\ncore_version_requirement: ^10\n",
      'expected_name' => 'my_theme',
      'expected_type' => 'theme',
    ];
  }

  public function testExtensionInfoMissingFile(): void {
    $dir = self::$tmp . '/ext_empty_' . uniqid();
    mkdir($dir, 0755, TRUE);
    $original = (string) getcwd();
    chdir($dir);
    $this->mockQuit(1);
    ob_start();
    try {
      extension_info();
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(1, $e->getCode());
    }
    finally {
      chdir($original);
      $output = ob_get_clean();
      $this->assertIsString($output);
      $this->assertStringContainsString('No .info.yml file found', $output);
    }
  }

  #[DataProvider('dataProviderIsDebug')]
  public function testIsDebug(string|false $env_value, bool $expected): void {
    if ($env_value === FALSE) {
      $this->envUnset('DEBUG');
    }
    else {
      $this->envSet('DEBUG', $env_value);
    }
    $this->assertSame($expected, is_debug());
  }

  public static function dataProviderIsDebug(): \Iterator {
    yield 'debug enabled' => ['env_value' => '1', 'expected' => TRUE];
    yield 'debug disabled' => ['env_value' => '0', 'expected' => FALSE];
    yield 'debug not set' => ['env_value' => FALSE, 'expected' => FALSE];
    yield 'debug empty string' => ['env_value' => '', 'expected' => FALSE];
    yield 'debug other value' => ['env_value' => 'true', 'expected' => FALSE];
  }

}
