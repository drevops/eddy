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

  /**
   * Build an environment whose browser commands are stubs.
   *
   * Drush opens a link with 'xdg-open' or 'open', and needs DISPLAY for it on
   * Linux. Each stub appends its arguments to a log instead of opening a
   * browser.
   *
   * @param string $log
   *   The file that the stubs append to.
   *
   * @return array<string, string>
   *   The environment variables to run a command with.
   */
  protected static function browserStubEnv(string $log): array {
    $dir = self::$tmp . '/browser_stubs';

    foreach (['open', 'xdg-open'] as $command) {
      File::dump($dir . '/' . $command, sprintf("#!/bin/sh\necho \"\$@\" >> %s\n", escapeshellarg($log)));
      chmod($dir . '/' . $command, 0755);
    }

    $path = self::envGet('PATH');

    return ['DISPLAY' => ':0', 'PATH' => $dir . (is_string($path) && $path !== '' ? ':' . $path : '')];
  }

  /**
   * Set 'config.platform.php' in the 'composer.dev.json' of the project.
   *
   * @param string $version
   *   The PHP version that Composer resolves the build for, such as '8.4.0'.
   */
  protected function setPlatformPhp(string $version): void {
    $manifest = self::$sut . '/composer.dev.json';
    $config = json_decode(File::read($manifest), TRUE);
    $this->assertIsArray($config);
    /** @var array{config?: array<string, array<string, mixed>>} $config */
    $config['config']['platform']['php'] = $version;
    File::dump($manifest, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
  }

  protected function assertTestCoverage(?string $dir = NULL): void {
    $dir ??= self::$sut;
    $this->assertFileExists($dir . '/.logs/coverage/phpunit/cobertura.xml');
    $this->assertFileNotContainsString($dir . '/.logs/coverage/phpunit/cobertura.xml', 'coverage line-rate="0"');
    $this->assertFileExists($dir . '/.logs/coverage/phpunit/.coverage-html/index.html');
    // A change in the coverage value usually means that PHPUnit discovered a
    // different number of source files.
    $this->assertFileContainsString($dir . '/.logs/coverage/phpunit/.coverage-html/index.html', '7.69% covered');
  }

}
