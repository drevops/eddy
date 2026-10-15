<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Functional;

use AlexSkrypnyk\File\File;
use AlexSkrypnyk\Snapshot\Testing\SnapshotTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Functional tests for install.php script.
 */
#[Group('p1')]
final class InstallTest extends FunctionalTestCase {

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

    $answers = array_replace(self::defaultAnswers(), $answers);

    $env = [];
    foreach ($answers as $key => $value) {
      $env['INSTALL_' . strtoupper((string) $key)] = $value;
    }

    $this->processRun(self::$sut . '/install.php', [], [], $env);

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
