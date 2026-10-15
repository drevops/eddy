<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\nvm_prefix;
use function DrevOps\Eddy\Tooling\passthru_capture;
use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for nvm_prefix().
 *
 * The prefix runs in a real shell through passthru_capture(), as the tooling
 * runs npm, with a stub 'nvm.sh' in place of nvm.
 */
#[CoversFunction('DrevOps\Eddy\Tooling\nvm_prefix')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersNvmPrefixTest extends UnitTestCase {

  /**
   * The prefix that selects the Node.js version of '.nvmrc' with nvm.
   */
  protected const string PREFIX = '. "$NVM_DIR/nvm.sh"; { nvm use || nvm install; } && ';

  /**
   * The working directory before the test changed into the project.
   */
  protected string $originalDir;

  protected function setUp(): void {
    parent::setUp();

    $this->originalDir = File::cwd();
    chdir(self::$sut);
  }

  protected function tearDown(): void {
    chdir($this->originalDir);
    parent::tearDown();
  }

  #[DataProvider('dataProviderNvmPrefix')]
  public function testNvmPrefix(bool $has_nvmrc, bool $has_nvm, string $expected_prefix, string $expected_note): void {
    if ($has_nvmrc) {
      File::dump('.nvmrc', "22\n");
    }

    $nvm_dir = $this->createNvmDir($has_nvm ? 'nvm() { :; }' : NULL);
    self::envSet('NVM_DIR', $nvm_dir);

    ob_start();
    $prefix = nvm_prefix();
    $output = (string) ob_get_clean();

    $this->assertSame($expected_prefix, $prefix);

    if ($expected_note === '') {
      $this->assertSame('', $output);

      return;
    }

    $this->assertStringContainsString(sprintf($expected_note, $nvm_dir), $output);
  }

  public static function dataProviderNvmPrefix(): \Iterator {
    yield 'no .nvmrc' => [FALSE, TRUE, '', ''];
    yield 'no .nvmrc and no nvm' => [FALSE, FALSE, '', ''];
    yield '.nvmrc without nvm' => [TRUE, FALSE, '', 'nvm is not installed in %s; using the Node.js on PATH, not the version in .nvmrc.'];
    yield '.nvmrc with nvm' => [TRUE, TRUE, self::PREFIX, 'Using the Node.js version in .nvmrc with nvm from %s.'];
  }

  #[DataProvider('dataProviderNvmPrefixRunsInShell')]
  public function testNvmPrefixRunsInShell(string $nvm_script, string $expected_output, int $expected_exit_code): void {
    File::dump('.nvmrc', "22\n");
    self::envSet('NVM_DIR', $this->createNvmDir($nvm_script));

    ob_start();
    $prefix = nvm_prefix();
    ob_end_clean();

    $exit_code = 0;
    $output = passthru_capture($prefix . 'echo "npm ran"', $exit_code);

    $this->assertSame($expected_output, $output);
    $this->assertSame($expected_exit_code, $exit_code);
  }

  public static function dataProviderNvmPrefixRunsInShell(): \Iterator {
    yield 'version installed' => ['nvm() { echo "nvm $*"; }', "nvm use\nnpm ran\n", 0];
    yield 'version fetched' => ['nvm() { echo "nvm $*"; [ "$1" = install ]; }', "nvm use\nnvm install\nnpm ran\n", 0];
    yield 'version unavailable' => ['nvm() { echo "nvm $*"; return 3; }', "nvm use\nnvm install\n", 3];
    yield 'loading nvm.sh fails' => ['nvm() { echo "nvm $*"; }' . "\n" . 'false', "nvm use\nnpm ran\n", 0];
  }

  public function testNvmPrefixFailsWithoutNvmFunction(): void {
    File::dump('.nvmrc', "22\n");
    self::envSet('NVM_DIR', $this->createNvmDir('NVM_LOADED=1'));

    ob_start();
    $prefix = nvm_prefix();
    ob_end_clean();

    $exit_code = 0;
    $output = passthru_capture($prefix . 'echo "npm ran"', $exit_code);

    $this->assertSame(127, $exit_code);
    $this->assertStringContainsString('nvm', $output);
    $this->assertStringNotContainsString('npm ran', $output);
  }

  public function testNvmPrefixExportsDefaultDirectory(): void {
    File::dump('.nvmrc', "22\n");

    $home = self::$tmp . '/home';
    File::dump($home . '/.nvm/nvm.sh', 'nvm() { echo "nvm $* from $NVM_DIR"; }' . PHP_EOL);

    self::envUnset('NVM_DIR');
    self::envUnset('XDG_CONFIG_HOME');
    self::envSet('HOME', $home);

    ob_start();
    $prefix = nvm_prefix();
    $note = (string) ob_get_clean();

    $exit_code = 0;
    $output = passthru_capture($prefix . 'echo "npm ran"', $exit_code);

    $this->assertStringContainsString('Using the Node.js version in .nvmrc with nvm from ' . $home . '/.nvm.', $note);
    $this->assertSame($home . '/.nvm', self::envGet('NVM_DIR'));
    $this->assertSame('nvm use from ' . $home . "/.nvm\nnpm ran\n", $output);
    $this->assertSame(0, $exit_code);
  }

  /**
   * Create an nvm directory.
   *
   * @param string|null $script
   *   The content of its 'nvm.sh', or NULL for a directory without one.
   *
   * @return string
   *   The directory.
   */
  protected function createNvmDir(?string $script): string {
    $dir = self::$tmp . '/nvm';
    File::mkdir($dir);

    if ($script !== NULL) {
      File::dump($dir . '/nvm.sh', $script . PHP_EOL);
    }

    return $dir;
  }

}
