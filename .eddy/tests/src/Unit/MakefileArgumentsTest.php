<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use AlexSkrypnyk\File\File;
use AlexSkrypnyk\PhpunitHelpers\Traits\ProcessTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests how 'make drush' and the 'make test*' targets pass their arguments.
 *
 * The real 'Makefile' runs in a project whose Drush, PHPUnit and tooling
 * commands are stubs. Each stub logs the arguments it receives, after the
 * recipe shell has parsed them.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[Group('p0')]
final class MakefileArgumentsTest extends UnitTestCase {

  use ProcessTrait;

  /**
   * The path of each stub that logs its arguments, keyed by command.
   */
  protected const array STUBS = [
    'drush' => 'build/vendor/bin/drush',
    'phpunit' => 'build/vendor/bin/phpunit',
    'eddy-tooling' => 'scripts/eddy-tooling',
    'eddy-browser-start' => 'vendor/bin/eddy-browser-start',
    'eddy-browser-stop' => 'vendor/bin/eddy-browser-stop',
    'eddy-stop' => 'vendor/bin/eddy-stop',
  ];

  /**
   * The arguments that the 'drush' recipe passes before its own.
   */
  protected const array DRUSH = ['-l', 'http://localhost:8000'];

  protected function setUp(): void {
    parent::setUp();

    File::copy(self::rootDir() . '/Makefile', self::$sut . '/Makefile');
    File::dump(self::$sut . '/your_extension.info.yml');

    foreach (self::STUBS as $command => $path) {
      self::writeStub($path, sprintf('file_put_contents(%s, json_encode(array_slice($argv, 1)) . PHP_EOL, FILE_APPEND);', var_export(self::callsFile($command), TRUE)));
    }

    self::writeStub('vendor/bin/eddy-info', "echo ['site-url' => 'http://localhost:8000', 'webdriver-port' => '4444'][\$argv[1] ?? ''] ?? '', PHP_EOL;");
  }

  /**
   * @param array<int, string> $arguments
   * @param array<int, string> $expected
   */
  #[DataProvider('dataProviderArguments')]
  public function testArguments(array $arguments, string $command, array $expected): void {
    $this->runMake($arguments);

    $this->assertProcessSuccessful();
    $this->assertSame([$expected], self::readCalls($command));
  }

  public static function dataProviderArguments(): \Iterator {
    yield 'drush without arguments' => [['drush'], 'drush', self::DRUSH];
    yield 'drush command' => [['drush', 'status'], 'drush', [...self::DRUSH, 'status']];
    yield 'drush command with a colon' => [['drush', 'config:get', 'system.site'], 'drush', [...self::DRUSH, 'config:get', 'system.site']];
    yield 'drush option and value joined with =' => [['drush', '--', 'config:get', 'system.site', '--format=json'], 'drush', [...self::DRUSH, 'config:get', 'system.site', '--format=json']];
    yield 'drush option and value separated by a space' => [['drush', '--', 'status', '--format', 'json'], 'drush', [...self::DRUSH, 'status', '--format', 'json']];
    yield 'drush options joined with = keep their order' => [['drush', '--', 'status', '--format=json', '--fields=bootstrap'], 'drush', [...self::DRUSH, 'status', '--format=json', '--fields=bootstrap']];
    yield 'drush options joined with = follow the other arguments' => [['drush', '--', '--format=json', 'config:get', 'system.site'], 'drush', [...self::DRUSH, 'config:get', 'system.site', '--format=json']];
    yield 'drush short option' => [['drush', '--', '-y', 'en', 'devel'], 'drush', [...self::DRUSH, '-y', 'en', 'devel']];
    yield 'drush shell characters' => [['drush', '--', 'php:eval', 'echo(1);', '*', '$HOME', "it's", '#1'], 'drush', [...self::DRUSH, 'php:eval', 'echo(1);', '*', '$HOME', "it's", '#1']];
    yield 'drush make variable' => [['drush', 'status', 'DEBUG=1'], 'drush', [...self::DRUSH, 'status']];
    yield 'unit tests' => [['test-unit', '--', '--group=wip'], 'phpunit', ['--testsuite', 'unit', '--group=wip']];
    yield 'unit tests filtered by a method' => [['test-unit', '--', '--filter', 'MyTest::testFoo'], 'phpunit', ['--testsuite', 'unit', '--filter', 'MyTest::testFoo']];
    yield 'unit tests filtered by a value with spaces and quotes' => [['test-unit', '--', '--filter=/^a$/ (b|c) "q" it\'s'], 'phpunit', ['--testsuite', 'unit', '--filter=/^a$/ (b|c) "q" it\'s']];
    yield 'unit tests filtered by a value holding an option' => [['test-unit', '--', '--filter=a -b=c'], 'phpunit', ['--testsuite', 'unit', '--filter=a -b=c']];
    yield 'unit tests filtered by the name of a target' => [['test-unit', '--', '--filter=reset'], 'phpunit', ['--testsuite', 'unit', '--filter=reset']];
    yield 'kernel tests' => [['test-kernel', '--', '--group=wip'], 'phpunit', ['--testsuite', 'kernel', '--group=wip']];
    yield 'functional tests' => [['test-functional', '--', '--group=wip'], 'phpunit', ['--testsuite', 'functional', '--group=wip']];
    yield 'FunctionalJavascript tests' => [['test-functional-javascript', 'WEBDRIVER_BACKEND=selenium', '--', '--group=wip'], 'phpunit', ['--testsuite', 'functional-javascript', '--group=wip']];
    yield 'all tests' => [['test', '--', '--group=wip'], 'phpunit', ['--group=wip']];
    yield 'all tests filtered by the name of their target' => [['test', '--', '--filter', 'test'], 'phpunit', ['--filter', 'test']];
  }

  /**
   * @param array<int, string> $arguments
   */
  #[DataProvider('dataProviderArgumentNamesTarget')]
  public function testArgumentNamesTarget(array $arguments, string $target): void {
    $this->runMake($arguments);

    $this->assertProcessFailed();
    $this->assertProcessErrorOutputContains([sprintf("'%s' is a target of this Makefile, so make would run it too.", $target), sprintf("such as '--filter=%s'", $target)]);

    foreach (array_keys(self::STUBS) as $command) {
      $this->assertSame([], self::readCalls($command), sprintf('%s ran.', $command));
    }
  }

  public static function dataProviderArgumentNamesTarget(): \Iterator {
    yield 'alias of reset' => [['test-unit', '--', '--filter', 'delete'], 'delete'];
    yield 'another test target' => [['test-unit', '--', '--group', 'test'], 'test'];
    yield 'drush command' => [['drush', 'help'], 'help'];
  }

  public function testOtherTargetsRejectUnknownGoals(): void {
    $this->runMake(['help', 'config:get']);

    $this->assertProcessFailed();
    $this->assertProcessErrorOutputContains('No rule to make target');
  }

  public function testTargetsListsEveryTarget(): void {
    $makefile = File::read(self::rootDir() . '/Makefile');

    preg_match_all('/^TARGETS [:+]= (.+)$/m', $makefile, $list_matches);
    preg_match_all('/^([a-z][a-z -]*):(?!=)/m', $makefile, $rule_matches);

    $listed = array_unique(explode(' ', implode(' ', $list_matches[1])));
    $defined = array_unique(explode(' ', implode(' ', array_map(trim(...), $rule_matches[1]))));
    sort($listed);
    sort($defined);

    $this->assertNotEmpty($defined);
    $this->assertSame($defined, $listed);
  }

  /**
   * Run make in the project.
   *
   * @param array<int, string> $arguments
   *   The arguments of make.
   */
  protected function runMake(array $arguments): void {
    $this->processCwd = self::$sut;
    // The variables of an outer make would reach MAKEOVERRIDES.
    $this->processRun('make', ['--no-print-directory', ...$arguments], [], ['MAKEFLAGS' => FALSE, 'MAKELEVEL' => FALSE]);
  }

  /**
   * Write a PHP stub that runs as a command.
   *
   * @param string $path
   *   The path of the stub in the project.
   * @param string $code
   *   The PHP code of the stub.
   */
  protected static function writeStub(string $path, string $code): void {
    $file = self::$sut . '/' . $path;

    File::dump($file, "#!/usr/bin/env php\n<?php\n" . $code . "\n");
    chmod($file, 0755);
  }

  /**
   * Read the arguments of each call of a stub.
   *
   * @return array<int, array<int, string>>
   *   The arguments of each call, in call order.
   */
  protected static function readCalls(string $command): array {
    $file = self::callsFile($command);

    if (!File::exists($file)) {
      return [];
    }

    $lines = preg_split('/\R/', File::read($file), -1, PREG_SPLIT_NO_EMPTY) ?: [];

    return array_map(static function (string $line): array {
      $arguments = json_decode($line, TRUE, 512, JSON_THROW_ON_ERROR);

      if (!is_array($arguments)) {
        throw new \RuntimeException(sprintf('The stub logged "%s", which holds no arguments.', $line));
      }

      return array_map(static fn(mixed $argument): string => is_string($argument) ? $argument : throw new \RuntimeException('The stub logged an argument that is not a string.'), array_values($arguments));
    }, $lines);
  }

  protected static function callsFile(string $command): string {
    return self::$tmp . '/' . $command . '.log';
  }

  protected static function rootDir(): string {
    return dirname(__DIR__, 4);
  }

}
