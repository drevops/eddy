<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\mkdir_or_fail;
use AlexSkrypnyk\File\File;
use DrevOps\Eddy\Tests\Exceptions\QuitErrorException;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the mkdir_or_fail() helper.
 */
#[CoversFunction('DrevOps\Eddy\Tooling\mkdir_or_fail')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersMkdirOrFailTest extends UnitTestCase {

  public function testCreatesMissingDirectory(): void {
    $dir = self::$tmp . '/parent/child';

    mkdir_or_fail($dir);

    $this->assertDirectoryExists($dir);
  }

  public function testKeepsExistingDirectory(): void {
    $dir = self::$tmp . '/existing';
    File::dump($dir . '/file.txt', 'content');

    mkdir_or_fail($dir);

    $this->assertFileExists($dir . '/file.txt');
  }

  public function testFailsWhenPathIsFile(): void {
    $path = self::$tmp . '/file';
    File::dump($path, 'content');
    $this->mockQuit(1);

    ob_start();
    try {
      mkdir_or_fail($path);
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(1, $e->getCode());
    }
    finally {
      $output = (string) ob_get_clean();
    }

    $this->assertStringContainsString('Unable to create directory ' . $path . '.', $output);
  }

}
