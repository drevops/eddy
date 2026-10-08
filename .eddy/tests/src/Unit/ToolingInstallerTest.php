<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

use function DrevOps\Eddy\ToolingInstaller\main;

/**
 * Tests for the 'scripts/eddy-tooling' installer.
 *
 * Each test runs in an empty project directory with real files. Only the
 * Composer call is mocked, and the mock writes what a successful install
 * leaves: the package manifest and a proxy for each command.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class ToolingInstallerTest extends UnitTestCase {

  protected const string NAMESPACE = 'DrevOps\\Eddy\\ToolingInstaller';

  protected const string COMMAND = "COMPOSER='vendor/eddy-tooling.json' composer update --no-dev --no-interaction --no-audit";

  protected const array BINS = ['src/eddy-assemble', 'src/eddy-info'];

  /**
   * Commands passed to passthru().
   *
   * @var array<int, string>
   */
  protected array $commands = [];

  /**
   * Whether each watched path existed when Composer ran, keyed by path.
   *
   * @var array<string, bool>
   */
  protected array $pathsAtComposerRun = [];

  protected string $originalCwd = '';

  protected function setUp(): void {
    parent::setUp();

    require_once dirname(__DIR__, 4) . '/scripts/eddy-tooling';

    $this->originalCwd = (string) getcwd();
    chdir(self::$sut);

    $this->envUnset('DEBUG');
    $this->envUnset('GITHUB_TOKEN');
    $this->envUnset('COMPOSER_AUTH');
    $this->envUnset('TERM');
  }

  protected function tearDown(): void {
    chdir($this->originalCwd);

    parent::tearDown();
  }

  /**
   * @param array<string, string> $files
   *   Files to create before the install, keyed by path.
   */
  #[DataProvider('dataProviderInstall')]
  public function testInstall(array $dev_manifest, bool $dev_mode, array $expected_manifest, string $expected_constraint, array $files = []): void {
    $this->writeDevManifest($dev_manifest);
    $this->writeFiles($files);

    if ($dev_mode) {
      mkdir('.eddy/tooling', 0755, TRUE);
    }

    $this->mockComposer(0, 'Composer progress');

    $output = $this->runMain(0);

    $this->assertStringContainsString('[INFO] Installing drevops/eddy-tooling ' . $expected_constraint . '.', $output);
    $this->assertStringContainsString('[ OK ] Installed drevops/eddy-tooling.', $output);
    $this->assertStringNotContainsString('Composer progress', $output, 'Composer output is shown only on failure.');
    $this->assertSame([self::COMMAND . ' 2>&1'], $this->commands);
    $this->assertSame(json_encode($expected_manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, file_get_contents('vendor/eddy-tooling.json'));
  }

  public static function dataProviderInstall(): \Iterator {
    $constraint = ['require' => ['drevops/eddy-tooling' => '~1.0.0']];
    $patches = ['Fix the build' => 'patches/eddy-tooling-fix.patch'];
    $dev = [
      'require' => ['drevops/eddy-tooling' => '@dev'],
      'repositories' => [['type' => 'path', 'url' => '.eddy/tooling']],
    ];

    yield 'constraint only' => [
      ['require-dev' => ['drevops/eddy-tooling' => '~1.0.0']],
      FALSE,
      $constraint,
      '~1.0.0',
    ];

    yield 'other dependencies and their patches stay out' => [
      [
        'require-dev' => ['drupal/coder' => '^8', 'drevops/eddy-tooling' => '~1.0.0'],
        'extra' => ['patches' => ['drupal/coder' => $patches]],
      ],
      FALSE,
      $constraint,
      '~1.0.0',
    ];

    yield 'patches for the package' => [
      [
        'require-dev' => ['drevops/eddy-tooling' => '~1.0.0'],
        'extra' => ['patches' => ['drevops/eddy-tooling' => $patches]],
      ],
      FALSE,
      [
        'require' => ['drevops/eddy-tooling' => '~1.0.0', 'cweagans/composer-patches' => '^2'],
        'config' => ['allow-plugins' => ['cweagans/composer-patches' => TRUE]],
        'extra' => ['patches' => ['drevops/eddy-tooling' => $patches]],
      ],
      '~1.0.0',
    ];

    $local_patches = [
      'Fix the build' => 'patches/eddy-tooling-fix.patch',
      'Fix the deploy' => 'https://example.com/eddy-tooling-deploy.patch',
      'Fix the start' => 'patches/eddy-tooling-missing.patch',
    ];

    yield 'checksums of local patch files' => [
      [
        'require-dev' => ['drevops/eddy-tooling' => '~1.0.0'],
        'extra' => ['patches' => ['drevops/eddy-tooling' => $local_patches]],
      ],
      FALSE,
      [
        'require' => ['drevops/eddy-tooling' => '~1.0.0', 'cweagans/composer-patches' => '^2'],
        'config' => ['allow-plugins' => ['cweagans/composer-patches' => TRUE]],
        'extra' => [
          'patches' => ['drevops/eddy-tooling' => $local_patches],
          'eddy-tooling' => ['patch-checksums' => ['patches/eddy-tooling-fix.patch' => hash('sha256', 'fix')]],
        ],
      ],
      '~1.0.0',
      ['patches/eddy-tooling-fix.patch' => 'fix'],
    ];

    $patch_definitions = [
      ['description' => 'Fix the build', 'url' => 'patches/eddy-tooling-fix.patch'],
      ['description' => 'Fix the deploy', 'url' => 'https://example.com/eddy-tooling-deploy.patch'],
      ['description' => 'Fix the start'],
    ];

    yield 'checksums of local patch files in a definition list' => [
      [
        'require-dev' => ['drevops/eddy-tooling' => '~1.0.0'],
        'extra' => ['patches' => ['drevops/eddy-tooling' => $patch_definitions]],
      ],
      FALSE,
      [
        'require' => ['drevops/eddy-tooling' => '~1.0.0', 'cweagans/composer-patches' => '^2'],
        'config' => ['allow-plugins' => ['cweagans/composer-patches' => TRUE]],
        'extra' => [
          'patches' => ['drevops/eddy-tooling' => $patch_definitions],
          'eddy-tooling' => ['patch-checksums' => ['patches/eddy-tooling-fix.patch' => hash('sha256', 'fix')]],
        ],
      ],
      '~1.0.0',
      ['patches/eddy-tooling-fix.patch' => 'fix'],
    ];

    yield 'empty patch list for the package' => [
      [
        'require-dev' => ['drevops/eddy-tooling' => '~1.0.0'],
        'extra' => ['patches' => ['drevops/eddy-tooling' => []]],
      ],
      FALSE,
      $constraint,
      '~1.0.0',
    ];

    yield 'patches that are not a map' => [
      [
        'require-dev' => ['drevops/eddy-tooling' => '~1.0.0'],
        'extra' => ['patches' => 'patches.json'],
      ],
      FALSE,
      $constraint,
      '~1.0.0',
    ];

    yield 'scaffold development' => [
      ['require-dev' => ['drevops/eddy-tooling' => '~1.0.0']],
      TRUE,
      $dev,
      '@dev',
    ];

    yield 'scaffold development leaves patches out' => [
      [
        'require-dev' => ['drevops/eddy-tooling' => '~1.0.0'],
        'extra' => ['patches' => ['drevops/eddy-tooling' => $patches]],
      ],
      TRUE,
      $dev,
      '@dev',
    ];
  }

  public function testSkipsCurrentInstall(): void {
    $this->writeDevManifest(['require-dev' => ['drevops/eddy-tooling' => '~1.0.0']]);
    $this->mockComposer();
    $this->runMain(0);

    $output = $this->runMain(0);

    $this->assertSame('', $output, 'A current install prints nothing.');
    $this->assertCount(1, $this->commands, 'A current install does not run Composer.');
  }

  /**
   * @param array<string, string> $write
   *   Files to write after the first install, keyed by path.
   * @param array<int, string> $remove
   *   Files to remove after the first install.
   */
  #[DataProvider('dataProviderReinstalls')]
  public function testReinstalls(array $write, array $remove, string $expected_constraint): void {
    $this->writeDevManifest(['require-dev' => ['drevops/eddy-tooling' => '~1.0.0']]);
    $this->mockComposer();
    $this->runMain(0);

    foreach ($write as $path => $contents) {
      file_put_contents($path, $contents);
    }

    foreach ($remove as $path) {
      unlink($path);
    }

    $output = $this->runMain(0);

    $this->assertStringContainsString('[INFO] Installing drevops/eddy-tooling ' . $expected_constraint . '.', $output);
    $this->assertCount(2, $this->commands);
  }

  public static function dataProviderReinstalls(): \Iterator {
    yield 'constraint changed' => [
      ['composer.dev.json' => '{"require-dev": {"drevops/eddy-tooling": "~1.1.0"}}'],
      [],
      '~1.1.0',
    ];

    yield 'patches added' => [
      ['composer.dev.json' => '{"require-dev": {"drevops/eddy-tooling": "~1.0.0"}, "extra": {"patches": {"drevops/eddy-tooling": {"Fix": "patches/fix.patch"}}}}'],
      [],
      '~1.0.0',
    ];

    yield 'install manifest removed' => [[], ['vendor/eddy-tooling.json'], '~1.0.0'];
    yield 'command proxy removed' => [[], ['vendor/bin/eddy-info'], '~1.0.0'];
    yield 'package manifest removed' => [[], ['vendor/drevops/eddy-tooling/composer.json'], '~1.0.0'];
    yield 'package declares no commands' => [['vendor/drevops/eddy-tooling/composer.json' => '{"bin": []}'], [], '~1.0.0'];
    yield 'package declares a command that is not a path' => [['vendor/drevops/eddy-tooling/composer.json' => '{"bin": [1]}'], [], '~1.0.0'];
  }

  #[DataProvider('dataProviderLocalPatchChange')]
  public function testLocalPatchChange(?string $contents, bool $expected_reinstall): void {
    $this->writeFiles(['patches/fix.patch' => 'fix']);
    $this->writeDevManifest([
      'require-dev' => ['drevops/eddy-tooling' => '~1.0.0'],
      'extra' => ['patches' => ['drevops/eddy-tooling' => ['Fix' => 'patches/fix.patch']]],
    ]);
    $this->mockComposer();
    $this->runMain(0);

    if ($contents === NULL) {
      unlink('patches/fix.patch');
    }
    else {
      file_put_contents('patches/fix.patch', $contents);
    }

    $output = $this->runMain(0);

    $this->assertSame($expected_reinstall, str_contains($output, '[INFO] Installing drevops/eddy-tooling ~1.0.0.'));
    $this->assertCount($expected_reinstall ? 2 : 1, $this->commands);
  }

  public static function dataProviderLocalPatchChange(): \Iterator {
    yield 'contents changed' => ['fixed again', TRUE];
    yield 'same contents written again' => ['fix', FALSE];
    yield 'file removed' => [NULL, TRUE];
  }

  #[DataProvider('dataProviderRemovesPreviousInstall')]
  public function testRemovesPreviousInstall(bool $symlinked): void {
    $this->writeDevManifest(['require-dev' => ['drevops/eddy-tooling' => '~1.0.0']]);

    // The previous package holds nested files and a symlink to a directory
    // outside of it, which must survive the removal.
    $outside = self::$tmp . '/outside';
    mkdir($outside, 0755, TRUE);
    file_put_contents($outside . '/kept.txt', 'kept');

    $package = $symlinked ? self::$tmp . '/source' : 'vendor/drevops/eddy-tooling';
    mkdir($package . '/src', 0755, TRUE);
    file_put_contents($package . '/src/eddy-old', 'old');
    symlink($outside, $package . '/linked');

    if ($symlinked) {
      mkdir('vendor/drevops', 0755, TRUE);
      symlink($package, 'vendor/drevops/eddy-tooling');
    }

    mkdir('vendor/bin', 0755, TRUE);
    file_put_contents('vendor/bin/eddy-old', 'old');
    file_put_contents('vendor/bin/other-tool', 'other');
    file_put_contents('vendor/eddy-tooling-patches.lock.json', '{}');

    $this->mockComposer(0, '', self::BINS, ['vendor/drevops/eddy-tooling', 'vendor/bin/eddy-old', 'vendor/bin/other-tool', 'vendor/eddy-tooling-patches.lock.json']);

    $this->runMain(0);

    $this->assertSame([
      'vendor/drevops/eddy-tooling' => FALSE,
      'vendor/bin/eddy-old' => FALSE,
      'vendor/bin/other-tool' => TRUE,
      'vendor/eddy-tooling-patches.lock.json' => FALSE,
    ], $this->pathsAtComposerRun);

    $this->assertFileExists($outside . '/kept.txt', 'A symlink inside the package is removed without following it.');

    if ($symlinked) {
      $this->assertFileExists($package . '/src/eddy-old', 'A symlinked package is removed without following it.');
    }
  }

  public static function dataProviderRemovesPreviousInstall(): \Iterator {
    yield 'directory' => [FALSE];
    yield 'symlink' => [TRUE];
  }

  #[DataProvider('dataProviderFailsWithoutConstraint')]
  public function testFailsWithoutConstraint(?string $contents, string $expected_message): void {
    if ($contents !== NULL) {
      file_put_contents('composer.dev.json', $contents);
    }

    $this->mockPassthruNever(self::NAMESPACE);

    $output = $this->runMain(1);

    $this->assertStringContainsString('[FAIL] ' . $expected_message, $output);
    $this->assertFileDoesNotExist('vendor/eddy-tooling.json');
  }

  public static function dataProviderFailsWithoutConstraint(): \Iterator {
    $unreadable = 'Unable to read composer.dev.json.';
    $missing = 'composer.dev.json does not require drevops/eddy-tooling in "require-dev".';

    yield 'missing file' => [NULL, $unreadable];
    yield 'invalid JSON' => ['{', $unreadable];
    yield 'not an object' => ['"text"', $unreadable];
    yield 'no require-dev' => ['{}', $missing];
    yield 'require-dev is not a map' => ['{"require-dev": "drevops/eddy-tooling"}', $missing];
    yield 'no constraint' => ['{"require-dev": {"drupal/coder": "^8"}}', $missing];
    yield 'empty constraint' => ['{"require-dev": {"drevops/eddy-tooling": ""}}', $missing];
    yield 'constraint is not a string' => ['{"require-dev": {"drevops/eddy-tooling": 1}}', $missing];
  }

  public function testComposerFailure(): void {
    $this->writeDevManifest(['require-dev' => ['drevops/eddy-tooling' => '~1.0.0']]);
    $this->mockComposer(3, 'Composer error output');

    $output = $this->runMain(3);

    $this->assertStringContainsString('Composer error output', $output, 'Composer output is shown on failure.');
    $this->assertStringContainsString('[FAIL] Unable to install drevops/eddy-tooling.', $output);
    $this->assertStringNotContainsString('[ OK ]', $output);
    $this->assertFileDoesNotExist('vendor/eddy-tooling.json', 'A failed install leaves no manifest, so the next run installs again.');
  }

  public function testManifestWriteFailure(): void {
    $this->writeDevManifest(['require-dev' => ['drevops/eddy-tooling' => '~1.0.0']]);

    // A file where the directory belongs makes the manifest unwritable.
    file_put_contents('vendor', '');

    $this->mockPassthruNever(self::NAMESPACE);

    $output = $this->runMain(1);

    $this->assertStringContainsString('[FAIL] Unable to write vendor/eddy-tooling.json.', $output);
  }

  #[DataProvider('dataProviderDebug')]
  public function testDebug(int $exit_code): void {
    $this->envSet('DEBUG', '1');
    $this->writeDevManifest(['require-dev' => ['drevops/eddy-tooling' => '~1.0.0']]);
    $this->mockComposer($exit_code, 'Composer progress');

    $output = $this->runMain($exit_code);

    $this->assertSame([self::COMMAND], $this->commands, 'Debug mode streams the output instead of capturing it.');
    $this->assertSame(1, substr_count($output, 'Composer progress'));
  }

  public static function dataProviderDebug(): \Iterator {
    yield 'success' => [0];
    yield 'failure' => [2];
  }

  #[DataProvider('dataProviderGithubToken')]
  public function testGithubToken(?string $token, ?string $auth, string|false $expected_auth): void {
    if ($token !== NULL) {
      $this->envSet('GITHUB_TOKEN', $token);
    }

    if ($auth !== NULL) {
      $this->envSet('COMPOSER_AUTH', $auth);
    }

    $this->writeDevManifest(['require-dev' => ['drevops/eddy-tooling' => '~1.0.0']]);
    $this->mockComposer();

    $this->runMain(0);

    $this->assertSame($expected_auth, getenv('COMPOSER_AUTH'));
  }

  public static function dataProviderGithubToken(): \Iterator {
    $token_auth = '{"github-oauth":{"github.com":"token123"}}';

    yield 'no token' => [NULL, NULL, FALSE];
    yield 'empty token' => ['', NULL, FALSE];
    yield 'token' => ['token123', NULL, $token_auth];
    yield 'token with empty auth' => ['token123', '', $token_auth];
    yield 'token with existing auth' => ['token123', '{"http-basic":{}}', '{"http-basic":{}}'];
  }

  #[DataProvider('dataProviderOutputColor')]
  public function testOutputColor(?string $term, bool $tty, bool $expected_color): void {
    if ($term !== NULL) {
      $this->envSet('TERM', $term);
    }

    // The color check calls posix_isatty() once per printed line, or never
    // when it short-circuits, so the mock returns a fixed answer, not queued
    // responses.
    $this->registerMock('posix_isatty', self::NAMESPACE, fn(): bool => $tty);
    $this->writeDevManifest(['require-dev' => ['drevops/eddy-tooling' => '~1.0.0']]);
    $this->mockComposer();

    $output = $this->runMain(0);

    $this->assertSame($expected_color, str_contains($output, "\033[36m[INFO] Installing"));
    $this->assertSame($expected_color, str_contains($output, "\033[32m[ OK ] Installed"));
  }

  public static function dataProviderOutputColor(): \Iterator {
    yield 'terminal' => ['xterm', TRUE, TRUE];
    yield 'no terminal' => ['xterm', FALSE, FALSE];
    yield 'dumb terminal' => ['dumb', TRUE, FALSE];
    yield 'no TERM' => [NULL, TRUE, FALSE];
  }

  protected function runMain(int $expected_exit_code): string {
    ob_start();
    $exit_code = main();
    $output = (string) ob_get_clean();

    $this->assertSame($expected_exit_code, $exit_code, 'Unexpected exit code. Output: ' . $output);

    return $output;
  }

  protected function writeDevManifest(array $contents): void {
    file_put_contents('composer.dev.json', json_encode($contents, JSON_THROW_ON_ERROR));
  }

  /**
   * Write files into the project directory.
   *
   * @param array<string, string> $files
   *   File contents, keyed by path.
   */
  protected function writeFiles(array $files): void {
    foreach ($files as $path => $contents) {
      if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0755, TRUE);
      }

      file_put_contents($path, $contents);
    }
  }

  /**
   * Mock the Composer run.
   *
   * @param int $exit_code
   *   The exit code Composer returns.
   * @param string $output
   *   The output Composer prints.
   * @param array<int, string> $bins
   *   The commands the installed package declares.
   * @param array<int, string> $watched_paths
   *   Paths whose existence is recorded when Composer runs.
   */
  protected function mockComposer(int $exit_code = 0, string $output = '', array $bins = self::BINS, array $watched_paths = []): void {
    $this->registerMock('passthru', self::NAMESPACE, function (string $command, mixed &...$args) use ($exit_code, $output, $bins, $watched_paths): null {
      $this->commands[] = $command;

      foreach ($watched_paths as $path) {
        $this->pathsAtComposerRun[$path] = file_exists($path) || is_link($path);
      }

      if ($exit_code === 0) {
        mkdir('vendor/drevops/eddy-tooling', 0755, TRUE);
        file_put_contents('vendor/drevops/eddy-tooling/composer.json', json_encode(['bin' => $bins], JSON_THROW_ON_ERROR));

        if (!is_dir('vendor/bin')) {
          mkdir('vendor/bin', 0755, TRUE);
        }

        foreach ($bins as $bin) {
          file_put_contents('vendor/bin/' . basename($bin), '<?php');
        }
      }

      echo $output;

      if (count($args) > 0) {
        $args[0] = $exit_code;
      }

      return NULL;
    });
  }

}
