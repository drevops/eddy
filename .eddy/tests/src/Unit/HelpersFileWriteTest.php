<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\copy_or_fail;
use function DrevOps\Eddy\Tooling\file_write_or_fail;
use function DrevOps\Eddy\Tooling\json_file;
use function DrevOps\Eddy\Tooling\json_file_write;
use function DrevOps\Eddy\Tooling\symlink_or_fail;
use AlexSkrypnyk\File\File;
use Laravel\SerializableClosure\SerializableClosure;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[CoversFunction('DrevOps\Eddy\Tooling\file_write_or_fail')]
#[CoversFunction('DrevOps\Eddy\Tooling\json_file_write')]
#[CoversFunction('DrevOps\Eddy\Tooling\copy_or_fail')]
#[CoversFunction('DrevOps\Eddy\Tooling\symlink_or_fail')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersFileWriteTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    chdir(self::$tmp);
  }

  #[DataProvider('dataProviderFileWriteOrFail')]
  public function testFileWriteOrFail(?string $existing, int $flags, string $expected): void {
    if ($existing !== NULL) {
      File::dump('target.txt', $existing);
    }

    file_write_or_fail('target.txt', 'new', $flags);

    $this->assertSame($expected, File::read('target.txt'));
  }

  public static function dataProviderFileWriteOrFail(): \Iterator {
    yield 'new file' => [NULL, 0, 'new'];
    yield 'existing file' => ['old', 0, 'new'];
    yield 'appended to an existing file' => ['old', FILE_APPEND, 'oldnew'];
  }

  public function testJsonFileWrite(): void {
    $data = ['name' => 'drupal/test', 'repositories' => [['url' => 'https://packages.drupal.org/8']]];

    json_file_write('composer.json', $data);

    $expected = <<<'JSON'
{
    "name": "drupal/test",
    "repositories": [
        {
            "url": "https://packages.drupal.org/8"
        }
    ]
}

JSON;
    $this->assertSame($expected, File::read('composer.json'));
    $this->assertSame($data, json_file('composer.json'));
  }

  public function testCopyOrFail(): void {
    File::dump('source.txt', 'content');

    copy_or_fail('source.txt', 'target.txt');

    $this->assertSame('content', File::read('target.txt'));
  }

  public function testSymlinkOrFail(): void {
    File::dump('source.txt', 'content');

    symlink_or_fail('source.txt', 'link.txt');

    $this->assertTrue(is_link('link.txt'));
    $this->assertSame('source.txt', readlink('link.txt'));
  }

  #[DataProvider('dataProviderWriteFails')]
  public function testWriteFails(string $function, SerializableClosure $write, string $expected_message): void {
    File::dump('source.txt', 'content');
    $this->mockFunction($function, fn(): false => FALSE);
    $this->mockQuit(1);

    $output = $this->capture(self::cu($write), 1);

    $this->assertStringContainsString($expected_message, $output);
  }

  public static function dataProviderWriteFails(): \Iterator {
    yield 'file' => ['file_put_contents', self::cw(static fn() => file_write_or_fail('target.txt', 'new')), 'Unable to write target.txt.'];
    yield 'JSON file' => ['file_put_contents', self::cw(static fn() => json_file_write('composer.json', [])), 'Unable to write composer.json.'];
    yield 'copy' => ['copy', self::cw(static fn() => copy_or_fail('source.txt', 'target.txt')), 'Unable to copy source.txt to target.txt.'];
    yield 'symlink' => ['symlink', self::cw(static fn() => symlink_or_fail('source.txt', 'link.txt')), 'Unable to create symlink link.txt to source.txt.'];
  }

}
