<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Functional;

/**
 * Base class for tooling functional tests.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
abstract class ToolingTestCase extends FunctionalTestCase {

  protected int $longTimeout = 600;

  protected int $defaultTimeout = 300;

  protected int $defaultIdleTimeout = 120;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->assertDirectoryContainsString(static::$sut, 'scaffold');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    // phpcs:ignore
    @exec('lsof -ti:8000 | xargs kill -9 2>/dev/null');
    sleep(1);

    // Give enough permission to clean the build dir.
    if (is_dir('build')) {
      @exec('chmod -Rf 777 build');
    }

    static::envReset();
    $this->processTearDown();

    // Skip FunctionalTestCase::tearDown() which requires static::$fixtures.
    // Manually call what the grandparent chain provides.
    $this->mockTearDown();
    if ($this->tearDownShouldCleanup()) {
      static::locationsTearDown();
    }
  }

  protected function assertTestCoverage(?string $dir = NULL): void {
    $dir ??= static::$sut;
    $this->assertFileExists($dir . '/.logs/coverage/phpunit/cobertura.xml');
    $this->assertFileNotContainsString($dir . '/.logs/coverage/phpunit/cobertura.xml', 'coverage line-rate="0"');
    $this->assertFileExists($dir . '/.logs/coverage/phpunit/.coverage-html/index.html');
    // A change in the coverage value usually means that PHPUnit discovered a
    // different number of source files.
    $this->assertFileContainsString($dir . '/.logs/coverage/phpunit/.coverage-html/index.html', '33.33% covered');
  }

}
