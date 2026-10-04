<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\DevTools\resolve_env_value;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the resolve_env_value() helper.
 *
 * phpcs:disable Drupal.Classes.FullyQualifiedNamespace.UseStatementMissing
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[CoversFunction('DrevOps\Eddy\DevTools\resolve_env_value')]
#[Group('p0')]
final class HelpersResolveEnvTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';
  }

  public function testEnvWinsOverDotenv(): void {
    $file = self::$tmp . '/resolve_env_' . uniqid();
    file_put_contents($file, "FOO=from_dotenv\n");
    $this->envSet('FOO', 'from_env');

    ['value' => $value, 'source' => $source] = resolve_env_value('FOO', 'fallback', $file);

    $this->assertSame('from_env', $value);
    $this->assertSame('env', $source);
  }

  public function testDotenvUsedWhenEnvUnset(): void {
    $file = self::$tmp . '/resolve_env_' . uniqid();
    file_put_contents($file, "FOO=from_dotenv\n");

    ['value' => $value, 'source' => $source] = resolve_env_value('FOO', 'fallback', $file);

    $this->assertSame('from_dotenv', $value);
    $this->assertSame($file, $source);
  }

  public function testDotenvSkippedWhenValueIsEmpty(): void {
    $file = self::$tmp . '/resolve_env_' . uniqid();
    file_put_contents($file, "FOO=\n");

    ['value' => $value, 'source' => $source] = resolve_env_value('FOO', 'fallback', $file);

    $this->assertSame('fallback', $value);
    $this->assertSame('default', $source);
  }

  public function testEnvSkippedWhenValueIsEmpty(): void {
    $file = self::$tmp . '/resolve_env_' . uniqid();
    file_put_contents($file, "FOO=from_dotenv\n");
    $this->envSet('FOO', '');

    ['value' => $value, 'source' => $source] = resolve_env_value('FOO', 'fallback', $file);

    $this->assertSame('from_dotenv', $value);
    $this->assertSame($file, $source);
  }

  public function testDefaultReturnedWhenNeitherSourceHasValue(): void {
    $file = self::$tmp . '/resolve_env_' . uniqid();

    ['value' => $value, 'source' => $source] = resolve_env_value('FOO', 'fallback', $file);

    $this->assertSame('fallback', $value);
    $this->assertSame('default', $source);
  }

  public function testDefaultMayBeEmptyString(): void {
    $file = self::$tmp . '/resolve_env_' . uniqid();

    ['value' => $value, 'source' => $source] = resolve_env_value('FOO', '', $file);

    $this->assertSame('', $value);
    $this->assertSame('default', $source);
  }

  #[DataProvider('dataProviderDotenvFilePathSurfacedAsSource')]
  public function testDotenvFilePathSurfacedAsSource(string $relative_name): void {
    $file = self::$tmp . '/' . $relative_name;
    file_put_contents($file, "FOO=value\n");

    ['value' => $value, 'source' => $source] = resolve_env_value('FOO', 'fallback', $file);

    $this->assertSame('value', $value);
    $this->assertSame($file, $source);
  }

  public static function dataProviderDotenvFilePathSurfacedAsSource(): \Iterator {
    yield 'standard name' => ['relative_name' => '.env'];
    yield 'environment-specific name' => ['relative_name' => '.env.local'];
  }

}
