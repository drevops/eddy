<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use AlexSkrypnyk\File\File;
use function DrevOps\Eddy\Tooling\package_patches;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[CoversFunction('DrevOps\Eddy\Tooling\package_patches')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersPackagePatchesTest extends UnitTestCase {

  /**
   * @param array<int, string> $files
   * @param array<string, list<string>> $expected
   */
  #[DataProvider('dataProviderListsPatches')]
  public function testListsPatches(array $files, array $expected): void {
    $patches_dir = self::$tmp . '/patches';

    foreach ($files as $file) {
      File::dump($patches_dir . '/' . $file);
    }

    $expected = array_map(static fn(array $names): array => array_map(static fn(string $name): string => $patches_dir . '/' . $name, $names), $expected);

    $this->assertSame($expected, package_patches($patches_dir));
  }

  public static function dataProviderListsPatches(): \Iterator {
    yield 'missing directory' => [[], []];
    yield 'patches grouped by package and sorted' => [
      ['other/lib/fix.patch', 'acme/widget/b.patch', 'acme/widget/a.patch'],
      ['acme/widget' => ['acme/widget/a.patch', 'acme/widget/b.patch'], 'other/lib' => ['other/lib/fix.patch']],
    ];
    yield 'files outside the package layout are ignored' => [
      ['acme/widget/fix.patch', 'acme/widget/notes.txt', 'top.patch', 'acme/top.patch', 'acme/widget/nested/deep.patch'],
      ['acme/widget' => ['acme/widget/fix.patch']],
    ];
  }

  public function testShippedPatchesFollowPackageLayout(): void {
    $patches_dir = self::toolingDir() . '/patches';
    $paths = array_map(static fn(string $path): string => substr($path, strlen($patches_dir) + 1), File::scandir($patches_dir));

    $this->assertNotSame([], $paths);

    foreach ($paths as $path) {
      $this->assertMatchesRegularExpression('#^[^/]+/[^/]+/[^/]+\.patch$#', $path, sprintf('%s must sit at patches/<vendor>/<package>/<name>.patch, or assemble never applies it.', $path));
    }
  }

}
