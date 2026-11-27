<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Functional;

use AlexSkrypnyk\File\File;
use AlexSkrypnyk\PhpunitHelpers\Traits\ProcessTrait;
use AlexSkrypnyk\PhpunitHelpers\Traits\TuiTrait;
use DrevOps\Eddy\Tests\Unit\UnitTestCase;

abstract class FunctionalTestCase extends UnitTestCase {

  use ProcessTrait;
  use TuiTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Functional tests run the tooling as it runs on the host.
    $this->mockHost();

    $this->sutPrepare();

    chdir(self::$sut);
  }

  /**
   * Fill the SUT with a copy of the working tree.
   */
  protected function sutPrepare(): void {
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
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->processTearDown();

    // Composer links the tooling with a directory junction on Windows, which
    // the removal of the workspace cannot unlink.
    $junction = self::$sut . '/vendor/drevops/eddy-tooling';

    if (PHP_OS_FAMILY === 'Windows' && is_dir($junction) && realpath($junction) !== realpath(dirname($junction)) . DIRECTORY_SEPARATOR . basename($junction)) {
      rmdir($junction);
    }

    parent::tearDown();
  }

  /**
   * {@inheritdoc}
   */
  public static function locationsFixturesDir(): string {
    return 'fixtures';
  }

}
