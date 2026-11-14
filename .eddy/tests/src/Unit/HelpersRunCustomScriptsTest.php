<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\run_custom_scripts;
use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[CoversFunction('DrevOps\Eddy\Tooling\run_custom_scripts')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersRunCustomScriptsTest extends UnitTestCase {

  /**
   * @param array<string, string>|null $files
   *   The files of the scripts directory, or NULL for a missing directory.
   */
  #[DataProvider('dataProviderDoesNothingWithoutMatchingScripts')]
  public function testDoesNothingWithoutMatchingScripts(?array $files): void {
    $dir = $files === NULL ? self::$tmp . '/nonexistent_' . uniqid() : self::createScriptsDir($files);
    run_custom_scripts($dir, 'assemble-');
    $this->expectNotToPerformAssertions();
  }

  public static function dataProviderDoesNothingWithoutMatchingScripts(): \Iterator {
    yield 'missing directory' => [NULL];
    yield 'empty directory' => [[]];
    yield 'no prefix matches' => [['unrelated.sh' => 'echo nope', 'README.md' => '']];
  }

  public function testExecutesMatchingScriptsInOrder(): void {
    $dir = self::createScriptsDir([
      'assemble-zeta.sh' => 'echo zeta',
      'assemble-alpha.sh' => 'echo alpha',
      'assemble-beta.sh' => 'echo beta',
    ]);

    $this->mockPassthruMultiple([
      ['cmd' => escapeshellarg($dir . '/assemble-alpha.sh'), 'result_code' => 0],
      ['cmd' => escapeshellarg($dir . '/assemble-beta.sh'), 'result_code' => 0],
      ['cmd' => escapeshellarg($dir . '/assemble-zeta.sh'), 'result_code' => 0],
    ]);

    $output = $this->capture(fn() => run_custom_scripts($dir, 'assemble-'));

    $this->assertStringContainsString('assemble-alpha.sh', $output);
    $this->assertStringContainsString('assemble-beta.sh', $output);
    $this->assertStringContainsString('assemble-zeta.sh', $output);
  }

  /**
   * @param array<string, string> $files
   *   The contents of each file in the scripts directory, keyed by file name.
   * @param array<int, string> $expected_skipped
   *   The names of the files that must not run.
   */
  #[DataProvider('dataProviderRunsOnlyMatchingScripts')]
  public function testRunsOnlyMatchingScripts(array $files, string $expected_run, array $expected_skipped): void {
    $dir = self::createScriptsDir($files);

    $this->mockPassthru(['cmd' => escapeshellarg($dir . '/' . $expected_run), 'result_code' => 0]);

    $output = $this->capture(fn() => run_custom_scripts($dir, 'assemble-'));

    $this->assertStringContainsString($expected_run, $output);

    foreach ($expected_skipped as $skipped) {
      $this->assertStringNotContainsString($skipped, $output);
    }
  }

  public static function dataProviderRunsOnlyMatchingScripts(): \Iterator {
    yield 'different prefix' => [['assemble-run.sh' => 'echo a', 'provision-skip.sh' => 'echo p'], 'assemble-run.sh', ['provision-skip.sh']];
    yield 'non-shell extension' => [
      ['assemble-real.sh' => 'echo run', 'assemble-readme.md' => '# notes', 'assemble-script.txt' => 'echo nope'],
      'assemble-real.sh',
      ['assemble-readme.md', 'assemble-script.txt'],
    ];
  }

  public function testSkipsDirectoriesMatchingPrefix(): void {
    $dir = self::$tmp . '/scripts_' . uniqid();
    File::mkdir($dir . '/assemble-rogue.sh', 0755);
    File::dump($dir . '/assemble-real.sh', 'echo real');

    $this->mockPassthru(['cmd' => escapeshellarg($dir . '/assemble-real.sh'), 'result_code' => 0]);

    $output = $this->capture(fn() => run_custom_scripts($dir, 'assemble-'));

    $this->assertStringContainsString('assemble-real.sh', $output);
    $this->assertStringNotContainsString('assemble-rogue.sh', $output);
  }

  public function testNonZeroExitAbortsParent(): void {
    $dir = self::createScriptsDir([
      'provision-first.sh' => 'echo first',
      'provision-second.sh' => 'echo second',
    ]);

    $this->mockPassthru(['cmd' => escapeshellarg($dir . '/provision-first.sh'), 'result_code' => 7]);
    $this->mockQuit(7);

    $output = $this->capture(fn() => run_custom_scripts($dir, 'provision-'), 7);

    $this->assertStringContainsString('provision-first.sh', $output);
    $this->assertStringContainsString('Custom script', $output);
    $this->assertStringContainsString('failed', $output);
    $this->assertStringNotContainsString('provision-second.sh', $output);
  }

  protected static function createScriptsDir(array $files = []): string {
    $dir = self::$tmp . '/scripts_' . uniqid();
    File::mkdir($dir, 0755);
    foreach ($files as $name => $contents) {
      File::dump($dir . '/' . $name, $contents);
    }

    return $dir;
  }

}
