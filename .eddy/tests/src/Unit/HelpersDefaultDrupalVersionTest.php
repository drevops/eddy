<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\default_drupal_version;
use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for default_drupal_version().
 */
#[CoversFunction('DrevOps\Eddy\Tooling\default_drupal_version')]
#[CoversFunction('DrevOps\Eddy\Tooling\json_file')]
#[Group('p0')]
final class HelpersDefaultDrupalVersionTest extends UnitTestCase {

  #[DataProvider('dataProviderDefaultDrupalVersion')]
  public function testDefaultDrupalVersion(?string $content, string $expected): void {
    $file = self::$tmp . '/composer.dev.json';

    if ($content !== NULL) {
      File::dump($file, $content);
    }

    $this->assertSame($expected, default_drupal_version($file));
  }

  public static function dataProviderDefaultDrupalVersion(): \Iterator {
    yield 'missing file' => [NULL, '11'];
    yield 'invalid JSON' => ['{', '11'];
    yield 'no extra' => ['{}', '11'];
    yield 'extra is not a map' => ['{"extra": "eddy"}', '11'];
    yield 'no Eddy settings' => ['{"extra": {"patches": {}}}', '11'];
    yield 'no version' => ['{"extra": {"eddy": {}}}', '11'];
    yield 'empty version' => ['{"extra": {"eddy": {"drupal-version": ""}}}', '11'];
    yield 'version that is not scalar' => ['{"extra": {"eddy": {"drupal-version": ["12"]}}}', '11'];
    yield 'major' => ['{"extra": {"eddy": {"drupal-version": "12"}}}', '12'];
    yield 'major as a number' => ['{"extra": {"eddy": {"drupal-version": 10}}}', '10'];
    yield 'minor' => ['{"extra": {"eddy": {"drupal-version": "11.1.0"}}}', '11.1.0'];
    yield 'stability flag' => ['{"extra": {"eddy": {"drupal-version": "12@beta"}}}', '12@beta'];
  }

}
