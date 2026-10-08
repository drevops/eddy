<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Functional;

use AlexSkrypnyk\File\File;
use AlexSkrypnyk\Snapshot\Testing\SnapshotTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Functional tests for init.php script.
 */
#[Group('p1')]
final class InitTest extends FunctionalTestCase {

  use SnapshotTrait;

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if (empty(self::$fixtures)) {
      throw new \RuntimeException('Fixtures directory is not set.');
    }

    if (str_contains(self::$fixtures, DIRECTORY_SEPARATOR . 'init' . DIRECTORY_SEPARATOR)) {
      $this->snapshotUpdateOnFailure(self::$fixtures, self::$sut, self::$tmp);
    }

    parent::tearDown();
  }

  #[DataProvider('dataProviderInit')]
  public function testInit(array $answers = []): void {
    self::$fixtures = static::locationsFixtureDir();

    $answers = array_replace(self::defaultAnswers(), $answers);

    // Build env vars to pre-fill all prompts.
    $env = [];
    foreach ($answers as $key => $value) {
      $env['EDDY_' . strtoupper((string) $key)] = $value;
    }

    $this->processRun(self::$sut . DIRECTORY_SEPARATOR . 'init.php', [], [], $env);

    $this->assertProcessSuccessful();

    $this->assertProcessOutputContainsOrNot([
      'Eddy - Drupal extension scaffold',
    ]);

    $baseline = File::dir(self::$fixtures . '/../' . self::BASELINE_DIR);
    $this->replaceVersions(self::$sut);

    if (!is_string(self::$fixtures)) {
      throw new \RuntimeException('Fixtures directory is not set.');
    }
    $this->assertSnapshotMatchesBaseline(self::$sut, $baseline, self::$fixtures);
  }

  public static function dataProviderInit(): \Iterator {
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

  protected function replaceVersions(string $dir): void {
    File::getReplacer()
      ->addVersionReplacements()
      ->addExclusions(['127.0.0.1'])
      // Increase max replacements to handle large files with many version
      // strings (GHA workflows, lock files, etc). The value is empirical.
      ->setMaxReplacements(5)
      ->replaceInDir($dir);
  }

  protected function snapshotUpdateBefore(string $actual): void {
    $this->replaceVersions($actual);
  }

}
