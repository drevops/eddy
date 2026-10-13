<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\config_platform_php;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for config_platform_php().
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[CoversFunction('DrevOps\Eddy\Tooling\config_platform_php')]
#[Group('p0')]
final class HelpersConfigPlatformPhpTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';
  }

  #[DataProvider('dataProviderConfigPlatformPhp')]
  public function testConfigPlatformPhp(?string $first, ?string $second, ?string $expected): void {
    $manifests = [self::$tmp . '/composer.dev.json', self::$tmp . '/composer.json'];

    foreach ([$first, $second] as $index => $contents) {
      if ($contents !== NULL) {
        file_put_contents($manifests[$index], $contents);
      }
    }

    $this->assertSame($expected, config_platform_php(...$manifests));
  }

  public static function dataProviderConfigPlatformPhp(): \Iterator {
    $platform = static fn(mixed $php): string => (string) json_encode(['config' => ['platform' => ['php' => $php]]]);

    yield 'no manifests' => [NULL, NULL, NULL];
    yield 'no platform' => ['{"config": {"sort-packages": true}}', '{}', NULL];
    yield 'first manifest' => [$platform('8.4.0'), NULL, '8.4.0'];
    yield 'second manifest' => [NULL, $platform('8.3.0'), '8.3.0'];
    yield 'first manifest wins' => [$platform('8.4.0'), $platform('8.3.0'), '8.4.0'];
    yield 'empty value falls through' => [$platform(''), $platform('8.3.0'), '8.3.0'];
    yield 'value that is not a string falls through' => [$platform(['8.4.0']), $platform('8.3.0'), '8.3.0'];
    yield 'invalid JSON falls through' => ['{', $platform('8.3.0'), '8.3.0'];
  }

}
