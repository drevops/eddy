<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Functional;

use AlexSkrypnyk\File\File;
use DrevOps\Eddy\Tests\Traits\WebserverTrait;

abstract class ToolingTestCase extends FunctionalTestCase {

  use WebserverTrait;

  protected int $longTimeout = 600;

  protected int $defaultTimeout = 300;

  protected int $defaultIdleTimeout = 120;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->assertDirectoryContainsString(self::$sut, 'scaffold');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    self::webserverStop(self::$sut, self::webserverPort());
    sleep(1);

    // Give enough permission to clean the build dir.
    if (File::exists('build')) {
      @exec('chmod -Rf 777 build');
    }

    parent::tearDown();
  }

  /**
   * Resolve the webserver port of the project under test.
   *
   * The environment comes first, then the project '.env', then the default,
   * in the order the tooling resolves it.
   */
  protected static function webserverPort(): string {
    $port = self::envGet('WEBSERVER_PORT');

    if (is_string($port) && $port !== '') {
      return $port;
    }

    $dotenv = self::$sut . '/.env';
    $env = File::exists($dotenv) ? parse_ini_string(File::read($dotenv)) : FALSE;
    $value = is_array($env) ? ($env['WEBSERVER_PORT'] ?? NULL) : NULL;

    return is_scalar($value) ? (string) $value : '8000';
  }

  protected function assertTestCoverage(?string $dir = NULL): void {
    $dir ??= self::$sut;
    $this->assertFileExists($dir . '/.logs/coverage/phpunit/cobertura.xml');
    $this->assertFileNotContainsString($dir . '/.logs/coverage/phpunit/cobertura.xml', 'coverage line-rate="0"');
    $this->assertFileExists($dir . '/.logs/coverage/phpunit/.coverage-html/index.html');
    // A change in the coverage value usually means that PHPUnit discovered a
    // different number of source files.
    $this->assertFileContainsString($dir . '/.logs/coverage/phpunit/.coverage-html/index.html', '33.33% covered');
  }

}
