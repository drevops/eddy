<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\resolve_env_value;
use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[CoversFunction('DrevOps\Eddy\Tooling\resolve_env_value')]
#[Group('p0')]
final class HelpersResolveEnvValueTest extends UnitTestCase {

  #[DataProvider('dataProviderResolveEnvValue')]
  public function testResolveEnvValue(?string $dotenv, ?string $env, string $default, string $expected_value, string $expected_source): void {
    $file = self::$tmp . '/resolve_env_' . uniqid();

    if ($dotenv !== NULL) {
      File::dump($file, $dotenv);
    }

    if ($env !== NULL) {
      self::envSet('FOO', $env);
    }

    ['value' => $value, 'source' => $source] = resolve_env_value('FOO', $default, $file);

    $this->assertSame($expected_value, $value);
    $this->assertSame($expected_source === 'file' ? $file : $expected_source, $source);
  }

  public static function dataProviderResolveEnvValue(): \Iterator {
    yield 'env wins over dotenv' => ["FOO=from_dotenv\n", 'from_env', 'fallback', 'from_env', 'env'];
    yield 'env without a dotenv file' => [NULL, 'from_env', 'fallback', 'from_env', 'env'];
    yield 'env over an empty dotenv value' => ["FOO=\n", 'from_env', 'fallback', 'from_env', 'env'];
    yield 'env equal to dotenv reads as dotenv' => ["FOO=same\n", 'same', 'fallback', 'same', 'file'];
    yield 'env equal to a quoted dotenv value reads as dotenv' => ["FOO=\"same\"\n", 'same', 'fallback', 'same', 'file'];
    yield 'env equal to an earlier assignment of a repeated key' => ["FOO=first\nFOO=last\n", 'first', 'fallback', 'first', 'env'];
    yield 'env equal to the last assignment of a repeated key' => ["FOO=first\nFOO=last\n", 'last', 'fallback', 'last', 'file'];
    yield 'dotenv when env is unset' => ["FOO=from_dotenv\n", NULL, 'fallback', 'from_dotenv', 'file'];
    yield 'dotenv skipped when its value is empty' => ["FOO=\n", NULL, 'fallback', 'fallback', 'default'];
    yield 'env skipped when its value is empty' => ["FOO=from_dotenv\n", '', 'fallback', 'from_dotenv', 'file'];
    yield 'default when neither has a value' => [NULL, NULL, 'fallback', 'fallback', 'default'];
    yield 'default may be empty' => [NULL, NULL, '', '', 'default'];
  }

  #[DataProvider('dataProviderDotenvFilePathSurfacedAsSource')]
  public function testDotenvFilePathSurfacedAsSource(string $relative_name): void {
    $file = self::$tmp . '/' . $relative_name;
    File::dump($file, "FOO=value\n");

    ['value' => $value, 'source' => $source] = resolve_env_value('FOO', 'fallback', $file);

    $this->assertSame('value', $value);
    $this->assertSame($file, $source);
  }

  public static function dataProviderDotenvFilePathSurfacedAsSource(): \Iterator {
    yield 'standard name' => ['relative_name' => '.env'];
    yield 'environment-specific name' => ['relative_name' => '.env.local'];
  }

}
