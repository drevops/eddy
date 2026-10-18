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

    $this->processRun(self::$sut . '/install.php', [], [], self::installEnv(array_replace(self::defaultAnswers(), $answers)));

    $this->assertProcessSuccessful();

    $this->assertProcessOutputContainsOrNot([
      'Eddy - Drupal extension scaffold',
    ]);

    // A kept installer must still run.
    if (File::exists(self::$sut . '/install.php')) {
      $this->processRun(self::$sut . '/install.php', ['--help']);
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
        'tools' => 'eslint,stylelint,cspell,jest,phpunit,functional_javascript,renovate',
      ],
    ];

    yield 'no_javascript_lint' => [
      [
        'tools' => 'phpcs,phpstan,rector,twigcs,cspell,jest,phpunit,functional_javascript,renovate',
      ],
    ];

    yield 'no_cspell' => [
      [
        'tools' => 'phpcs,phpstan,rector,twigcs,eslint,stylelint,jest,phpunit,functional_javascript,renovate',
      ],
    ];

    yield 'no_jest' => [
      [
        'tools' => 'phpcs,phpstan,rector,twigcs,eslint,stylelint,cspell,phpunit,functional_javascript,renovate',
      ],
    ];

    yield 'no_npm' => [
      [
        'tools' => 'phpcs,phpstan,rector,twigcs,phpunit,functional_javascript,renovate',
      ],
    ];

    yield 'no_functional_javascript' => [
      [
        'tools' => 'phpcs,phpstan,rector,twigcs,eslint,stylelint,cspell,jest,phpunit,renovate',
      ],
    ];

    yield 'no_phpunit' => [
      [
        'tools' => 'phpcs,phpstan,rector,twigcs,eslint,stylelint,cspell,jest,functional_javascript,renovate',
      ],
    ];

    yield 'no_renovate' => [
      [
        'tools' => 'phpcs,phpstan,rector,twigcs,eslint,stylelint,cspell,jest,phpunit,functional_javascript',
      ],
    ];

    yield 'no_tools' => [
      [
        'command_wrapper' => 'ahoy,makefile',
        'tools' => '',
      ],
    ];
  }

  /**
   * @param array<int, string> $expected_present
   * @param array<int, string> $expected_absent
   */
  #[DataProvider('dataProviderInstallDownload')]
  public function testInstallDownload(string $installer, callable $setup, array $answers, array $expected_present, array $expected_absent): void {
    $archive = self::archiveTemplate();
    $project = $this->createProject($installer, $setup);

    $this->processRun('php', [$installer], [], self::installEnv(array_replace(self::defaultAnswers(), $answers, ['template_archive' => $archive])));

    $this->assertProcessSuccessful();
    $this->assertProcessOutputContains([
      'Downloading Eddy development from ' . $archive,
      'Eddy - Drupal extension scaffold',
    ]);

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

  /**
   * @param array<int, string> $expected_output
   * @param array<int, string> $expected_present
   */
  #[DataProvider('dataProviderInstallDownloadFailure')]
  public function testInstallDownloadFailure(callable $setup, array $answers, bool $missing_archive, array $expected_output, array $expected_present): void {
    $archive = $missing_archive ? self::$tmp . '/missing.tar.gz' : self::archiveTemplate();
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

    yield 'missing archive' => [$none, [], TRUE, ['Unable to download the template from '], ['install.php']];

    yield 'cancelled' => [
      $none,
      ['proceed' => 'false'],
      FALSE,
      ['Aborting.', 'The installer of the downloaded template exited with code 1.'],
      ['install.php', 'your_extension.info.yml'],
    ];
  }

  /**
   * Archive the template copy the way GitHub does, in 1 top-level directory.
   *
   * @return string
   *   The path of the archive.
   */
  protected static function archiveTemplate(): string {
    $archive = self::$tmp . '/eddy.tar.gz';
    (new Process(['tar', '-czf', $archive, '-C', dirname(self::$sut), basename(self::$sut)]))->mustRun();

    return $archive;
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
    File::copy(self::$sut . '/install.php', $project . '/' . $installer);
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
      'tools' => 'phpcs,phpstan,rector,twigcs,eslint,stylelint,cspell,jest,phpunit,functional_javascript,renovate',
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
