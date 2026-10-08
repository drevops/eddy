<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\DevTools\mkdir_or_fail;
use DrevOps\Eddy\Tests\Exceptions\QuitErrorException;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the mkdir_or_fail() helper.
 *
 * phpcs:disable Drupal.Classes.FullyQualifiedNamespace.UseStatementMissing
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[CoversFunction('DrevOps\Eddy\DevTools\mkdir_or_fail')]
#[Group('p0')]
final class HelpersMkdirOrFailTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';
  }

  public function testCreatesMissingDirectory(): void {
    $dir = self::$tmp . '/parent/child';

    mkdir_or_fail($dir);

    $this->assertDirectoryExists($dir);
  }

  public function testKeepsExistingDirectory(): void {
    $dir = self::$tmp . '/existing';
    mkdir($dir);
    file_put_contents($dir . '/file.txt', 'content');

    mkdir_or_fail($dir);

    $this->assertFileExists($dir . '/file.txt');
  }

  public function testFailsWhenPathIsFile(): void {
    $path = self::$tmp . '/file';
    file_put_contents($path, 'content');
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
