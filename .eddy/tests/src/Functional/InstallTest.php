<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Functional;

use AlexSkrypnyk\File\File;
use AlexSkrypnyk\Snapshot\Testing\SnapshotTrait;
use DrevOps\Eddy\Tests\Traits\GitTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;

#[Group('p1')]
final class InstallTest extends FunctionalTestCase {

  use GitTrait;
  use SnapshotTrait;

  /**
   * The directory that holds the template archive and its extraction.
   *
   * The tests of the class share it, and tearDownAfterClass() removes it.
   */
  protected static ?string $archiveDir = NULL;

  /**
   * The commit that the template archive holds.
   */
  protected static string $archiveCommit;

  public static function tearDownAfterClass(): void {
    if (self::$archiveDir !== NULL) {
      File::rmdir(self::$archiveDir);
      self::$archiveDir = NULL;
    }

    parent::tearDownAfterClass();
  }

  /**
   * Point the repository at the template archive, built once per class.
   *
   * Each test copies what it needs from it.
   */
  protected function sutPrepare(): void {
    self::$archiveDir ??= self::archiveTemplate(dirname(self::$workspace));
    self::$repo = self::$archiveDir . '/eddy';
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if (empty(self::$fixtures)) {
      throw new \RuntimeException('Fixtures directory is not set.');
    }

    if (str_contains(self::$fixtures, '/install/')) {
      $this->snapshotUpdateOnFailure(self::$fixtures, self::$sut, self::$tmp);
    }

    parent::tearDown();
  }

  #[DataProvider('dataProviderInstall')]
  public function testInstall(array $answers = []): void {
    $fixtures = $this->locationsFixtureDir();
    self::$fixtures = $fixtures;

    File::copy(self::$repo, self::$sut);
    // The archive's copy holds the version that 'git archive' stamps, so the
    // installer of the working tree replaces it.
    File::copy(self::rootDir() . '/install.php', self::$sut . '/install.php');

    $this->processRun('php', [self::$sut . '/install.php'], [], self::installEnv(array_replace(self::defaultAnswers(), $answers)));

    $this->assertProcessSuccessful();

    $this->assertProcessOutputContainsOrNot([
      'Eddy - Drupal extension scaffold',
    ]);

    // A kept installer must still run.
    if (File::exists(self::$sut . '/install.php')) {
      $this->processRun('php', [self::$sut . '/install.php', '--help']);
      $this->assertProcessSuccessful();
      $this->assertProcessOutputContains('Eddy - project installation.');
    }

    $baseline = File::dir($fixtures . '/../' . self::BASELINE_DIR);
    self::replaceVersions(self::$sut);

    $this->assertSnapshotMatchesBaseline($baseline, $fixtures, self::$sut);
  }

  public static function dataProviderInstall(): \Iterator {
    yield self::BASELINE_DATASET => [
      [],
    ];

    yield 'theme' => [
      [
        'type' => 'theme',
      ],
    ];

    yield 'gha_makefile' => [
      [
        'command_wrapper' => 'makefile',
      ],
    ];

    yield 'gha_both_wrappers' => [
      [
        'command_wrapper' => 'ahoy,makefile',
      ],
    ];

    yield 'gha_no_command_wrapper' => [
      [
        'command_wrapper' => '',
      ],
    ];

    yield 'd11_only' => [
      [
        'drupal_version' => '11',
      ],
    ];

    yield 'd10_only' => [
      [
        'drupal_version' => '10',
      ],
    ];

    yield 'd12_only' => [
      [
        'drupal_version' => '12',
      ],
    ];

    yield 'keep_script' => [
      [
        'remove_self' => 'false',
      ],
    ];

    yield 'keep_examples' => [
      [
        'examples' => 'true',
      ],
    ];

    yield 'branches' => [
      [
        'branches' => '2.x,3.x',
      ],
    ];

    yield 'github_repository' => [
      [
        'github_repository' => 'alexskrypnyk/force_crystal',
      ],
    ];

    yield 'no_php_lint' => [
      [
        'tools' => 'sdc_devel,eslint,stylelint,cspell,jest,phpunit,functional_javascript,renovate',
      ],
    ];

    yield 'no_javascript_lint' => [
      [
        'tools' => 'phpcs,phpstan,rector,twigcs,sdc_devel,cspell,jest,phpunit,functional_javascript,renovate',
      ],
    ];

    yield 'no_cspell' => [
      [
        'tools' => 'phpcs,phpstan,rector,twigcs,sdc_devel,eslint,stylelint,jest,phpunit,functional_javascript,renovate',
      ],
    ];

    yield 'no_sdc_devel' => [
      [
        'tools' => 'phpcs,phpstan,rector,twigcs,eslint,stylelint,cspell,jest,phpunit,functional_javascript,renovate',
      ],
    ];

    yield 'no_jest' => [
      [
        'tools' => 'phpcs,phpstan,rector,twigcs,sdc_devel,eslint,stylelint,cspell,phpunit,functional_javascript,renovate',
      ],
    ];

    yield 'no_npm' => [
      [
        'tools' => 'phpcs,phpstan,rector,twigcs,sdc_devel,phpunit,functional_javascript,renovate',
      ],
    ];

    yield 'no_functional_javascript' => [
      [
        'tools' => 'phpcs,phpstan,rector,twigcs,sdc_devel,eslint,stylelint,cspell,jest,phpunit,renovate',
      ],
    ];

    yield 'no_phpunit' => [
      [
        'tools' => 'phpcs,phpstan,rector,twigcs,sdc_devel,eslint,stylelint,cspell,jest,functional_javascript,renovate',
      ],
    ];

    yield 'no_renovate' => [
      [
        'tools' => 'phpcs,phpstan,rector,twigcs,sdc_devel,eslint,stylelint,cspell,jest,phpunit,functional_javascript',
      ],
    ];

    yield 'no_tools' => [
      [
        'command_wrapper' => 'ahoy,makefile',
        'tools' => '',
      ],
    ];
  }

  public function testTemplateArchive(): void {
    $root = self::rootDir();

    $tree = new Process(['git', 'ls-tree', '-r', '-z', '--name-only', self::$archiveCommit], $root);
    $tree->mustRun();
    $tracked = array_values(array_filter(explode("\0", $tree->getOutput()), static fn(string $path): bool => $path !== ''));

    // 'git check-attr' reports an 'export-ignore' directory on the directory
    // only, so the ancestors of each file are checked too.
    $paths = array_unique(array_merge(...array_map(self::pathAncestors(...), $tracked)));
    $check = new Process(['git', 'check-attr', '-z', '--stdin', 'export-ignore'], $root);
    $check->setInput(implode("\0", $paths) . "\0");
    $check->mustRun();

    $ignored = [];

    foreach (array_chunk(explode("\0", rtrim($check->getOutput(), "\0")), 3) as [$path, , $value]) {
      if ($value === 'set') {
        $ignored[$path] = TRUE;
      }
    }

    $expected = array_values(array_filter($tracked, static fn(string $file): bool => array_intersect_key(array_flip(self::pathAncestors($file)), $ignored) === []));
    $repo = File::dir(self::$repo);
    $actual = array_map(static fn(string $file): string => str_replace(DIRECTORY_SEPARATOR, '/', substr($file, strlen($repo) + 1)), File::scandir($repo));
    sort($expected);
    sort($actual);

    $this->assertContains('install.php', $actual, 'The archive holds the installer.');
    $this->assertSame($expected, $actual, 'The archive holds every tracked file that .gitattributes does not mark export-ignore, and nothing else.');
  }

  /**
   * @param array<int, string> $expected_present
   * @param array<int, string> $expected_absent
   */
  #[DataProvider('dataProviderInstallDownload')]
  public function testInstallDownload(string $installer, callable $setup, array $answers, array $expected_present, array $expected_absent): void {
    $archive = self::$archiveDir . '/eddy.tar.gz';
    $project = $this->createProject($installer, $setup);

    $this->processRun('php', [$installer], [], self::installEnv(array_replace(self::defaultAnswers(), $answers, ['template_archive' => $archive])));

    $this->assertProcessSuccessful();
    $this->assertProcessOutputContains([
      'Downloading Eddy development',
      'Downloaded from',
      'No SHA-256 checksum is known for',
      'Force Crystal (force_crystal) is ready.',
    ]);
    $this->assertSame(1, substr_count($this->processGet()->getOutput(), 'Eddy - Drupal extension scaffold'), 'The installer of the template draws a second intro.');

    foreach ($expected_present as $path) {
      $this->assertFileExists($project . '/' . $path);
    }

    foreach ($expected_absent as $path) {
      $this->assertFileDoesNotExist($project . '/' . $path);
    }
  }

  public static function dataProviderInstallDownload(): \Iterator {
    $none = static function (string $dir): void {};
    $installed = ['force_crystal.info.yml', 'README.md', 'composer.dev.json'];
    $template = ['your_extension.info.yml', 'README.dist.md', '.eddy', '.eddy-template.tar.gz'];

    yield 'installer' => ['install.php', $none, [], $installed, [...$template, 'install.php']];

    yield 'git repository' => [
      'install.php',
      static function (string $dir): void {
        self::gitInit($dir);
        File::dump($dir . '/.DS_Store', '');
      },
      [],
      [...$installed, '.git/HEAD', '.DS_Store'],
      [...$template, 'install.php'],
    ];

    yield 'installer under another name' => ['installer.php', $none, [], $installed, [...$template, 'install.php', 'installer.php']];

    yield 'kept installer' => ['install.php', $none, ['remove_self' => 'false'], [...$installed, 'install.php'], $template];
  }

  public function testInstallDownloadToFile(): void {
    $archive = self::$archiveDir . '/eddy.tar.gz';
    $project = $this->createProject('install.php', static function (string $dir): void {});

    // The installer of the template writes to the same file as this one. The
    // file is outside of the project, which must be empty.
    $this->processRun('sh', ['-c', 'php install.php > ../install.log'], [], self::installEnv(array_replace(self::defaultAnswers(), ['template_archive' => $archive])));

    $output = File::read(dirname($project) . '/install.log');
    $this->assertSame(0, $this->processGet()->getExitCode(), $output . $this->processGet()->getErrorOutput());
    $this->assertStringContainsString('Downloaded from', $output, 'The installer of the template overwrites the output of this installer.');
    $this->assertStringContainsString('Force Crystal (force_crystal) is ready.', $output);
  }

  /**
   * @param array<int, string> $expected_output
   * @param array<int, string> $expected_present
   */
  #[DataProvider('dataProviderInstallDownloadFailure')]
  public function testInstallDownloadFailure(callable $setup, array $answers, bool $missing_archive, array $expected_output, array $expected_present): void {
    $archive = $missing_archive ? self::$tmp . '/missing.tar.gz' : self::$archiveDir . '/eddy.tar.gz';
    $project = $this->createProject('install.php', $setup);

    $this->processRun('php', ['install.php'], [], self::installEnv(array_replace(self::defaultAnswers(), $answers, ['template_archive' => $archive])));

    $this->assertProcessFailed();
    $this->assertProcessOutputContains($expected_output);

    foreach ($expected_present as $path) {
      $this->assertFileExists($project . '/' . $path);
    }
  }

  public static function dataProviderInstallDownloadFailure(): \Iterator {
    $none = static function (string $dir): void {};

    yield 'directory with other files' => [
      static function (string $dir): void {
        File::dump($dir . '/README.md', 'A project.');
      },
      [],
      FALSE,
      ['The current directory holds no copy of the template.'],
      ['install.php', 'README.md'],
    ];

    yield 'missing archive' => [$none, [], TRUE, ['Unable to download the template from', 'Failed.'], ['install.php']];

    yield 'cancelled' => [
      $none,
      ['proceed' => 'false'],
      FALSE,
      ['Cancelled.'],
      ['install.php', 'your_extension.info.yml'],
    ];
  }

  /**
   * Build the template archive the way a release does, and extract it.
   *
   * 'git stash create' records the tracked changes of the working tree in a
   * commit and prints nothing for a clean tree. So the archive holds the
   * tracked files as they are now, and a new file once it is added.
   *
   * @param string $parent
   *   The directory to create the archive directory in.
   *
   * @return string
   *   The directory that holds 'eddy.tar.gz' and its extraction in 'eddy'.
   */
  protected static function archiveTemplate(string $parent): string {
    $dir = self::locationsMkdir($parent . '/template-' . microtime(TRUE) . '-' . getmypid());
    $root = self::rootDir();

    // A stash commit needs an identity, which a CI checkout has none of.
    $stash = new Process(['git', '-c', 'user.name=Tests', '-c', 'user.email=tests@example.com', 'stash', 'create'], $root);
    $stash->mustRun();
    self::$archiveCommit = trim($stash->getOutput()) ?: 'HEAD';

    (new Process(['git', 'archive', '--format=tar.gz', '--prefix=eddy/', '--output=' . $dir . '/eddy.tar.gz', self::$archiveCommit], $root))->mustRun();
    // GNU tar reads a drive letter in the archive path as a remote host, so
    // the archive is named relative to the directory it is extracted in.
    (new Process(['tar', '-xzf', 'eddy.tar.gz'], $dir))->mustRun();

    return $dir;
  }

  /**
   * List a path and the directories above it.
   *
   * @param string $path
   *   A path relative to the repository root, separated with '/'.
   *
   * @return array<int, string>
   *   The path first, then each parent up to the top-level directory.
   */
  protected static function pathAncestors(string $path): array {
    $paths = [];

    for (; $path !== '.'; $path = dirname($path)) {
      $paths[] = $path;
    }

    return $paths;
  }

  /**
   * Create a project directory that holds a copy of the installer.
   *
   * @param string $installer
   *   The file name of the copy.
   * @param callable $setup
   *   Adds other entries to the directory.
   *
   * @return string
   *   The directory, which the processes run in.
   */
  protected function createProject(string $installer, callable $setup): string {
    $project = self::$tmp . '/project';
    File::copy(self::rootDir() . '/install.php', $project . '/' . $installer);
    $setup($project);
    $this->processCwd = $project;

    return $project;
  }

  /**
   * Build the variables that answer the prompts of 'install.php'.
   *
   * @param array<string, string> $answers
   *   The answers, keyed by the name of the prompt or the variable.
   *
   * @return array<string, string>
   *   The variables, such as 'INSTALL_NAME'.
   */
  protected static function installEnv(array $answers): array {
    $env = [];

    foreach ($answers as $key => $value) {
      $env['INSTALL_' . strtoupper($key)] = $value;
    }

    return $env;
  }

  protected static function defaultAnswers(): array {
    return [
      'name' => 'Force Crystal',
      'machine_name' => 'force_crystal',
      'type' => 'module',
      'drupal_version' => '10,11',
      'command_wrapper' => 'ahoy',
      'tools' => 'phpcs,phpstan,rector,twigcs,sdc_devel,eslint,stylelint,cspell,jest,phpunit,functional_javascript,renovate',
      'examples' => 'false',
      'remove_self' => 'true',
      'proceed' => 'true',
    ];
  }

  protected static function replaceVersions(string $dir): void {
    File::getReplacer()
      ->addVersionReplacements()
      ->addExclusions(['127.0.0.1'])
      // Increase max replacements to handle large files with many version
      // strings (GHA workflows, lock files, etc). The value is empirical.
      ->setMaxReplacements(5)
      ->replaceInDir($dir);
  }

  protected function snapshotUpdateBefore(string $actual): void {
    self::replaceVersions($actual);
  }

}
