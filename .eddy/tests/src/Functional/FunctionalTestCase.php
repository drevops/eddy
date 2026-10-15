<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Functional;

use AlexSkrypnyk\File\File;
use AlexSkrypnyk\PhpunitHelpers\Traits\ProcessTrait;
use AlexSkrypnyk\PhpunitHelpers\Traits\TuiTrait;
use DrevOps\Eddy\Tests\Unit\UnitTestCase;

/**
 * FunctionalTestCase fixture class.
 */
abstract class FunctionalTestCase extends UnitTestCase {

  use ProcessTrait;
  use TuiTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    self::locationsCopy('../../', self::$sut, [], [
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
    File::remove([self::$sut . '/.env', self::$sut . '/composer.lock']);

    chdir(self::$sut);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if (empty(self::$fixtures)) {
      throw new \RuntimeException('Fixtures directory is not set.');
    }

    $this->processTearDown();

    parent::tearDown();
  }

  /**
   * {@inheritdoc}
   */
  public static function locationsFixturesDir(): string {
    return 'fixtures';
  }

}
