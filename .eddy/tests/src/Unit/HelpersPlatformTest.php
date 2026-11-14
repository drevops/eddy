<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

use function DrevOps\Eddy\Tooling\is_windows;
use function DrevOps\Eddy\Tooling\null_device;
use function DrevOps\Eddy\Tooling\shell_path;
use function DrevOps\Eddy\Tooling\site_db_file;
use function DrevOps\Eddy\Tooling\site_db_url;

#[CoversFunction('DrevOps\Eddy\Tooling\is_windows')]
#[CoversFunction('DrevOps\Eddy\Tooling\null_device')]
#[CoversFunction('DrevOps\Eddy\Tooling\shell_path')]
#[CoversFunction('DrevOps\Eddy\Tooling\site_db_file')]
#[CoversFunction('DrevOps\Eddy\Tooling\site_db_url')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersPlatformTest extends UnitTestCase {

  #[DataProvider('dataProviderIsWindows')]
  public function testIsWindows(string $uname, bool $expected): void {
    $this->mockFunction('php_uname', static fn(string $mode = 'a'): string => $mode === 's' ? $uname : 'unexpected mode');

    $this->assertSame($expected, is_windows());
  }

  public static function dataProviderIsWindows(): \Iterator {
    yield 'windows' => ['Windows NT', TRUE];
    yield 'linux' => ['Linux', FALSE];
    yield 'macos' => ['Darwin', FALSE];
  }

  public function testIsWindowsOnHost(): void {
    $this->assertSame(PHP_OS_FAMILY === 'Windows', is_windows());
  }

  #[DataProvider('dataProviderNullDevice')]
  public function testNullDevice(bool $windows, string $expected): void {
    $this->mockWindows($windows);

    $this->assertSame($expected, null_device());
  }

  public static function dataProviderNullDevice(): \Iterator {
    yield 'windows' => [TRUE, 'NUL'];
    yield 'posix' => [FALSE, '/dev/null'];
  }

  #[DataProvider('dataProviderShellPath')]
  public function testShellPath(bool $windows, string $path, string $expected): void {
    $this->mockWindows($windows);

    $this->assertSame($expected, shell_path($path));
  }

  public static function dataProviderShellPath(): \Iterator {
    yield 'windows' => [TRUE, 'build/vendor/bin/drush', 'build\\vendor\\bin\\drush'];
    yield 'windows without separators' => [TRUE, 'drush', 'drush'];
    yield 'posix' => [FALSE, 'build/vendor/bin/drush', 'build/vendor/bin/drush'];
  }

  #[DataProvider('dataProviderSiteDb')]
  public function testSiteDb(bool $windows, string $expected_file, string $expected_url): void {
    $this->mockWindows($windows);
    chdir(self::$sut);
    $cwd = File::cwd();

    $this->assertSame(str_replace('<cwd>', $cwd, $expected_file), site_db_file('my_ext'));
    $this->assertSame(str_replace('<cwd>', $cwd, $expected_url), site_db_url('my_ext'));
  }

  public static function dataProviderSiteDb(): \Iterator {
    yield 'windows' => [TRUE, '<cwd>/build/site_my_ext.sqlite', 'sqlite://localhost/../site_my_ext.sqlite'];
    yield 'posix' => [FALSE, '/tmp/site_my_ext.sqlite', 'sqlite://localhost//tmp/site_my_ext.sqlite'];
  }

}
