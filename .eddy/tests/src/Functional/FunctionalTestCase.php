<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Functional;

use AlexSkrypnyk\File\File;
use AlexSkrypnyk\PhpunitHelpers\Traits\ProcessTrait;
use AlexSkrypnyk\PhpunitHelpers\Traits\TuiTrait;
use DrevOps\Eddy\Tests\Unit\UnitTestCase;

/**
 * FunctionalTestCase fixture class.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
abstract class FunctionalTestCase extends UnitTestCase {

  use ProcessTrait;
  use TuiTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    static::locationsCopy('../../', static::$sut, [], [
      '*.png',
      '.idea',
      '.logs',
      '.phpunit.cache',
      '.artifacts',
      '.chromedriver',
      '.docusaurus',
      'build',
    ]);

    // The exclusions match directories only, so git-ignored root files are
    // removed after the copy.
    File::remove([static::$sut . '/.env', static::$sut . '/composer.lock']);

    chdir(static::$sut);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if (empty(static::$fixtures)) {
      throw new \RuntimeException('Fixtures directory is not set.');
    }

    static::processTearDown();

    parent::tearDown();
  }

  /**
   * {@inheritdoc}
   */
  public static function locationsFixturesDir(): string {
    return 'fixtures';
  }

}
