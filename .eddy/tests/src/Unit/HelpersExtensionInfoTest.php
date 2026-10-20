<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\extension_info;
use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[CoversFunction('DrevOps\Eddy\Tooling\extension_info')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersExtensionInfoTest extends UnitTestCase {

  #[DataProvider('dataProviderExtensionInfoNameAndType')]
  public function testExtensionInfoNameAndType(string $filename, string $content, string $expected_name, string $expected_type): void {
    $dir = self::$tmp . '/ext_' . uniqid();
    File::dump($dir . '/' . $filename, $content);
    chdir($dir);

    $this->assertSame(['name' => $expected_name, 'type' => $expected_type], extension_info());
  }

  public static function dataProviderExtensionInfoNameAndType(): \Iterator {
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
    File::mkdir($dir, 0755);
    chdir($dir);
    $this->mockQuit(1);

    $output = $this->capture(fn(): array => extension_info(), 1);

    $this->assertStringContainsString('No .info.yml file found', $output);
  }

  public function testExtensionInfoUnreadableFile(): void {
    $dir = self::$tmp . '/ext_unreadable_' . uniqid();
    File::dump($dir . '/my_theme.info.yml', "name: My Theme\ntype: theme\n");
    chdir($dir);
    $this->mockFunction('file_get_contents', fn(): false => FALSE);
    $this->mockQuit(1);

    $output = $this->capture(fn(): array => extension_info(), 1);

    $this->assertStringContainsString('Unable to read my_theme.info.yml.', $output);
  }

}
