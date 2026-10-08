<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

use function drupal_version_options;
use function tool_specs;

/**
 * Tests the marker blocks that 'init.php' strips from the template.
 *
 * A malformed block leaves every file valid, so no linter reports it. The
 * snapshot fixtures are regenerated from the same output, so they miss it too.
 *
 * '.eddy/CLAUDE.md' documents the rules checked here.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[Group('p0')]
final class TemplateMarkersTest extends UnitTestCase {

  /**
   * The tokens 'init.php' strips by name.
   *
   * The 'DRUPAL_<major>' tokens follow 'drupal_version_options()' and the tool
   * tokens follow 'tool_specs()'.
   */
  protected const array NAMED_TOKENS = ['DEV_AHOY', 'DEV_MAKEFILE', 'DEV_COMMAND_WRAPPER', 'DEV_NO_COMMAND_WRAPPER', 'DEV_NODEJS_LINT', 'META'];

  public static function setUpBeforeClass(): void {
    putenv('SCRIPT_RUN_SKIP=1');
    require_once self::rootDir() . '/init.php';
    parent::setUpBeforeClass();
  }

  #[DataProvider('dataProviderMarkersHaveTheirOwnLine')]
  public function testMarkersHaveTheirOwnLine(string $path): void {
    $markers = self::markers(self::read($path));
    $shared = array_keys(array_filter($markers, static fn(?array $marker): bool => $marker === NULL));

    $this->assertSame([], $shared, sprintf('%s holds "#;" beside other text on these lines, which init.php deletes: %s.', basename($path), implode(', ', $shared)));
  }

  public static function dataProviderMarkersHaveTheirOwnLine(): \Iterator {
    yield from self::markedFiles();
  }

  #[DataProvider('dataProviderBlocksAreClosed')]
  public function testBlocksAreClosed(string $path): void {
    $unclosed = self::unclosedBlocks(self::markers(self::read($path)));

    $this->assertSame([], $unclosed, sprintf('%s has markers out of order: %s.', basename($path), implode('; ', $unclosed)));
  }

  public static function dataProviderBlocksAreClosed(): \Iterator {
    yield from self::markedFiles();
  }

  #[DataProvider('dataProviderTokensAreStripped')]
  public function testTokensAreStripped(string $path): void {
    $stripped = self::strippedTokens();
    $unknown = [];

    foreach (self::markers(self::read($path)) as $line => $marker) {
      if ($marker !== NULL && !in_array($marker['token'], $stripped, TRUE)) {
        $unknown[] = sprintf('%s on line %d', $marker['token'], $line);
      }
    }

    $this->assertSame([], $unknown, sprintf('%s uses tokens init.php never strips: %s.', basename($path), implode(', ', $unknown)));
  }

  public static function dataProviderTokensAreStripped(): \Iterator {
    yield from self::markedFiles();
  }

  public function testTokensDoNotStartWithAnother(): void {
    $prefixed = self::prefixedTokens(self::strippedTokens());

    $this->assertSame([], $prefixed, sprintf('Markers match as substrings, so %s.', implode('; ', $prefixed)));
  }

  #[DataProvider('dataProviderDrupalBlocksCoverEveryMajor')]
  public function testDrupalBlocksCoverEveryMajor(string $path): void {
    $supported = array_map(strval(...), array_keys(drupal_version_options()));
    sort($supported, SORT_NUMERIC);

    $this->assertSame($supported, self::drupalMajors(self::markers(self::read($path))), sprintf('%s has DRUPAL_<major> blocks for only some majors, so a selected major can miss its block.', basename($path)));
  }

  public static function dataProviderDrupalBlocksCoverEveryMajor(): \Iterator {
    $paths = [];

    foreach (self::markedFiles() as $name => $data) {
      if (self::drupalMajors(self::markers(self::read($data['path']))) !== []) {
        $paths[$name] = $data;
      }
    }

    self::assertNotSame([], $paths, 'No template file holds a DRUPAL_<major> block.');

    yield from $paths;
  }

  /**
   * @param array<int, array{direction: string, token: string}|null> $expected
   */
  #[DataProvider('dataProviderMarkers')]
  public function testMarkers(string $contents, array $expected): void {
    $this->assertSame($expected, self::markers($contents));
  }

  public static function dataProviderMarkers(): \Iterator {
    $open = ['direction' => '<', 'token' => 'META'];
    $close = ['direction' => '>', 'token' => 'META'];

    yield 'no markers' => ["text\ntext", []];
    yield 'bare' => ["#;< META\ntext\n#;> META", [1 => $open, 3 => $close]];
    yield 'indented' => ['    #;< META', [1 => $open]];
    yield 'Markdown or XML comment' => ['<!-- #;< META -->', [1 => $open]];
    yield 'PHP comment' => ['// #;< META', [1 => $open]];
    yield 'Makefile recipe' => ["\t@#;< META", [1 => $open]];
    yield 'text before the marker' => ['key: value #;< META', [1 => NULL]];
    yield 'text after the marker' => ['#;< META value', [1 => NULL]];
    yield 'unclosed Markdown comment' => ['<!-- #;< META', [1 => NULL]];
    yield 'no token' => ['#;<', [1 => NULL]];
    yield 'marker characters in text' => ['echo "#;"', [1 => NULL]];
  }

  /**
   * @param array<int, string> $expected
   */
  #[DataProvider('dataProviderUnclosedBlocks')]
  public function testUnclosedBlocks(string $contents, array $expected): void {
    $this->assertSame($expected, self::unclosedBlocks(self::markers($contents)));
  }

  public static function dataProviderUnclosedBlocks(): \Iterator {
    yield 'closed' => ["#;< A\ntext\n#;> A", []];
    yield 'closed and reopened' => ["#;< A\n#;> A\n#;< A\n#;> A", []];
    yield 'nested tokens' => ["#;< A\n#;< B\n#;> B\n#;> A", []];
    yield 'overlapping tokens' => ["#;< A\n#;< B\n#;> A\n#;> B", []];
    yield 'never closed' => ["#;< A\ntext", ['line 1 opens A, which is never closed']];
    yield 'closed before opening' => ["text\n#;> A", ['line 2 closes A, which is not open']];
    yield 'opened twice' => ["#;< A\n#;< A\n#;> A", ['line 2 opens A again, open since line 1']];
    yield 'malformed line ignored' => ["#;< A\ntext #;> A\n#;> A", []];
  }

  /**
   * @param array<int, string> $tokens
   * @param array<int, string> $expected
   */
  #[DataProvider('dataProviderPrefixedTokens')]
  public function testPrefixedTokens(array $tokens, array $expected): void {
    $this->assertSame($expected, self::prefixedTokens($tokens));
  }

  public static function dataProviderPrefixedTokens(): \Iterator {
    yield 'distinct' => [['DEV_A', 'DEV_B'], []];
    yield 'shared ending' => [['DEV_COMMAND_WRAPPER', 'DEV_NO_COMMAND_WRAPPER'], []];
    yield 'prefix listed first' => [['DEV_PHPCS', 'DEV_PHPCS_FIXER'], ['stripping DEV_PHPCS also strips DEV_PHPCS_FIXER blocks']];
    yield 'prefix listed last' => [['DEV_PHPCS_FIXER', 'DEV_PHPCS'], ['stripping DEV_PHPCS also strips DEV_PHPCS_FIXER blocks']];
    yield 'duplicate' => [['DEV_A', 'DEV_A'], ['stripping DEV_A also strips DEV_A blocks']];
  }

  /**
   * @param array<int, string> $expected
   */
  #[DataProvider('dataProviderDrupalMajors')]
  public function testDrupalMajors(string $contents, array $expected): void {
    $this->assertSame($expected, self::drupalMajors(self::markers($contents)));
  }

  public static function dataProviderDrupalMajors(): \Iterator {
    yield 'no Drupal blocks' => ["#;< META\n#;> META", []];
    yield 'numeric order' => ["#;< DRUPAL_11\n#;> DRUPAL_11\n#;< DRUPAL_9\n#;> DRUPAL_9", ['9', '11']];
    yield 'repeated blocks' => ["#;< DRUPAL_10\n#;> DRUPAL_10\n#;< DRUPAL_10\n#;> DRUPAL_10", ['10']];
  }

  /**
   * List the template files that hold '#;'.
   *
   * Walks the tree that 'get_files()' in 'init.php' walks, minus '.eddy',
   * which 'init.php' deletes, and local build output. 'init.php' is skipped
   * because its code spells out marker syntax.
   *
   * @return \Iterator<string, array{path: string}>
   *   The absolute paths, keyed by path relative to the project root.
   */
  protected static function markedFiles(): \Iterator {
    $root = self::rootDir();
    $skipped = ['.git', '.idea', 'vendor', 'node_modules', '.eddy', '.artifacts', '.logs', '.phpunit.cache', 'build'];

    $directory = new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS);
    $filter = new \RecursiveCallbackFilterIterator($directory, static fn(\SplFileInfo $current): bool => !$current->isDir() || !in_array($current->getFilename(), $skipped, TRUE));

    $paths = [];

    foreach (new \RecursiveIteratorIterator($filter) as $file) {
      if (!$file instanceof \SplFileInfo) {
        continue;
      }

      if (!$file->isFile()) {
        continue;
      }

      if ($file->getPathname() === $root . '/init.php') {
        continue;
      }

      $contents = self::read($file->getPathname());

      if (!str_contains($contents, "\0") && str_contains($contents, '#;')) {
        $paths[] = $file->getPathname();
      }
    }

    self::assertNotSame([], $paths, 'No template file holds a marker.');

    sort($paths);

    foreach ($paths as $path) {
      yield substr($path, strlen($root) + 1) => ['path' => $path];
    }
  }

  /**
   * Parse the lines of a file that hold '#;'.
   *
   * @param string $contents
   *   The file contents.
   *
   * @return array<int, array{direction: string, token: string}|null>
   *   The marker on each such line, keyed by line number, or NULL for a line
   *   that holds more than a marker.
   */
  protected static function markers(string $contents): array {
    $markers = [];

    foreach (explode("\n", $contents) as $index => $line) {
      if (str_contains($line, '#;')) {
        $markers[$index + 1] = self::marker($line);
      }
    }

    return $markers;
  }

  /**
   * Parse a line that holds '#;'.
   *
   * A marker may be indented and wrapped in the comment syntax of its file:
   * '<!-- ... -->', '// ', or the '@' of a Makefile recipe.
   *
   * @param string $line
   *   The line.
   *
   * @return array{direction: string, token: string}|null
   *   The marker, or NULL when the line holds anything else.
   */
  protected static function marker(string $line): ?array {
    $marker = trim($line);

    if (str_starts_with($marker, '<!-- ') && str_ends_with($marker, ' -->')) {
      $marker = substr($marker, 5, -4);
    }
    elseif (str_starts_with($marker, '// ')) {
      $marker = substr($marker, 3);
    }
    elseif (str_starts_with($marker, '@')) {
      $marker = substr($marker, 1);
    }

    if (preg_match('/^#;([<>]) (\S+)$/', $marker, $matches) !== 1) {
      return NULL;
    }

    return ['direction' => $matches[1], 'token' => $matches[2]];
  }

  /**
   * Find the markers that break the open and close order of their token.
   *
   * 'remove_tokens_with_content()' strips 1 token at a time, so blocks of
   * different tokens may nest or overlap.
   *
   * @param array<int, array{direction: string, token: string}|null> $markers
   *   The markers, keyed by line number.
   *
   * @return array<int, string>
   *   A description of each marker out of order.
   */
  protected static function unclosedBlocks(array $markers): array {
    $open = [];
    $unclosed = [];

    foreach ($markers as $line => $marker) {
      if ($marker === NULL) {
        continue;
      }

      $token = $marker['token'];
      $since = $open[$token] ?? NULL;

      if ($marker['direction'] === '<') {
        $open[$token] = $line;
        $problem = $since === NULL ? NULL : sprintf('line %d opens %s again, open since line %d', $line, $token, $since);
      }
      else {
        unset($open[$token]);
        $problem = $since === NULL ? sprintf('line %d closes %s, which is not open', $line, $token) : NULL;
      }

      if ($problem !== NULL) {
        $unclosed[] = $problem;
      }
    }

    foreach ($open as $token => $line) {
      $unclosed[] = sprintf('line %d opens %s, which is never closed', $line, $token);
    }

    return $unclosed;
  }

  /**
   * List the tokens 'init.php' strips.
   *
   * @return array<int, string>
   *   The tokens.
   */
  protected static function strippedTokens(): array {
    $majors = array_map(static fn(int $major): string => 'DRUPAL_' . $major, array_keys(drupal_version_options()));

    return [...self::NAMED_TOKENS, ...$majors, ...array_column(tool_specs(), 'token')];
  }

  /**
   * Find the tokens whose markers also match another token.
   *
   * @param array<int, string> $tokens
   *   The tokens.
   *
   * @return array<int, string>
   *   A description of each pair of tokens that collide.
   */
  protected static function prefixedTokens(array $tokens): array {
    $prefixed = [];

    foreach ($tokens as $index => $token) {
      $others = array_diff_key($tokens, [$index => TRUE]);
      $prefixes = array_filter($others, static fn(string $other): bool => str_starts_with($token, $other));

      foreach ($prefixes as $prefix) {
        $prefixed[] = sprintf('stripping %s also strips %s blocks', $prefix, $token);
      }
    }

    return array_values(array_unique($prefixed));
  }

  /**
   * List the Drupal majors a file has blocks for.
   *
   * @param array<int, array{direction: string, token: string}|null> $markers
   *   The markers, keyed by line number.
   *
   * @return array<int, string>
   *   The majors, in numeric order.
   */
  protected static function drupalMajors(array $markers): array {
    $majors = [];

    foreach ($markers as $marker) {
      if ($marker !== NULL && preg_match('/^DRUPAL_(\d+)$/', $marker['token'], $matches) === 1) {
        $majors[] = $matches[1];
      }
    }

    $majors = array_unique($majors);
    sort($majors, SORT_NUMERIC);

    return $majors;
  }

  protected static function read(string $path): string {
    $contents = file_get_contents($path);
    self::assertIsString($contents);

    return $contents;
  }

  protected static function rootDir(): string {
    return dirname(__DIR__, 4);
  }

}
