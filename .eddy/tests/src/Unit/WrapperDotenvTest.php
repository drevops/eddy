<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use AlexSkrypnyk\File\File;
use AlexSkrypnyk\PhpunitHelpers\Traits\ProcessTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests how the command wrappers load '.env'.
 *
 * A variable that the shell sets to a non-empty value keeps it, and '.env'
 * provides the rest.
 *
 * The real 'Makefile' runs with a probe makefile added through a second '-f'.
 * The real Ahoy entrypoint runs with the arguments that Ahoy passes to it.
 */
#[Group('p0')]
final class WrapperDotenvTest extends UnitTestCase {

  use ProcessTrait;

  /**
   * The variables that each probe prints, 1 'NAME=value' line each.
   */
  protected const array PROBE_VARIABLES = ['WEBSERVER_PORT', 'DRUPAL_PROFILE', 'SIMPLETEST_BASE_URL'];

  /**
   * @param array<string, string|false> $env
   * @param array<string, string> $expected
   */
  #[DataProvider('dataProviderMake')]
  public function testMake(?string $dotenv, array $env, array $expected): void {
    $output = $this->runMake('probe', $dotenv, $env);

    $this->assertSame(self::probeOutput($expected), $output);
  }

  public static function dataProviderMake(): \Iterator {
    yield from self::dotenvCases();
  }

  /**
   * @param array<string, string|false> $env
   * @param array<string, string> $expected
   */
  #[DataProvider('dataProviderMakeSubMake')]
  public function testMakeSubMake(?string $dotenv, array $env, array $expected): void {
    $output = $this->runMake('sub-make', $dotenv, $env);

    $this->assertSame(self::probeOutput($expected), $output);
  }

  public static function dataProviderMakeSubMake(): \Iterator {
    yield from self::dotenvCases();
  }

  /**
   * @param array<string, string|false> $env
   * @param array<string, string> $expected
   */
  #[DataProvider('dataProviderAhoy')]
  public function testAhoy(?string $dotenv, array $env, array $expected): void {
    self::writeDotenv($dotenv);

    $command = array_map(static fn(string $item): string => match ($item) {
      '{{cmd}}' => self::ahoyProbe(),
      '{{name}}' => 'probe',
      default => $item,
    }, self::ahoyEntrypoint());

    // The entrypoint installs the tooling unless this variable is set.
    $output = $this->runProbe($command, $env + ['AHOY_EDDY_TOOLING_READY' => '1']);

    $this->assertSame(self::probeOutput($expected), $output);
  }

  public static function dataProviderAhoy(): \Iterator {
    yield from self::dotenvCases();
  }

  public function testAhoyLoadsNoEnvFile(): void {
    $config = self::ahoyConfig();

    $this->assertArrayNotHasKey('env', $config, 'Ahoy would load the env files over the shell environment.');

    $commands = $config['commands'] ?? NULL;
    $this->assertIsArray($commands);

    foreach ($commands as $name => $command) {
      $this->assertIsArray($command);
      $this->assertArrayNotHasKey('env', $command, sprintf('Ahoy would load the env files of "%s" over the shell environment.', $name));
    }
  }

  /**
   * List the '.env' cases that every wrapper resolves the same way.
   *
   * @return \Iterator<string, array{0: string|null, 1: array<string, string>, 2: array<string, string>}>
   *   The contents of '.env' or NULL for no file, the shell environment, and
   *   the value of each probe variable, keyed by case name.
   */
  protected static function dotenvCases(): \Iterator {
    yield 'no .env and no shell value' => [
      NULL,
      [],
      ['WEBSERVER_PORT' => '', 'DRUPAL_PROFILE' => '', 'SIMPLETEST_BASE_URL' => 'http://localhost:8000'],
    ];
    yield 'shell value without .env' => [
      NULL,
      ['WEBSERVER_PORT' => '9001'],
      ['WEBSERVER_PORT' => '9001', 'DRUPAL_PROFILE' => '', 'SIMPLETEST_BASE_URL' => 'http://localhost:9001'],
    ];
    yield '.env value without shell value' => [
      "WEBSERVER_PORT=8001\nDRUPAL_PROFILE=minimal\n",
      [],
      ['WEBSERVER_PORT' => '8001', 'DRUPAL_PROFILE' => 'minimal', 'SIMPLETEST_BASE_URL' => 'http://localhost:8001'],
    ];
    yield 'shell value overrides .env' => [
      "WEBSERVER_PORT=8001\nDRUPAL_PROFILE=minimal\n",
      ['WEBSERVER_PORT' => '9001', 'DRUPAL_PROFILE' => 'testing'],
      ['WEBSERVER_PORT' => '9001', 'DRUPAL_PROFILE' => 'testing', 'SIMPLETEST_BASE_URL' => 'http://localhost:9001'],
    ];
    yield 'shell value overrides 1 variable of .env' => [
      "WEBSERVER_PORT=8001\nDRUPAL_PROFILE=minimal\n",
      ['WEBSERVER_PORT' => '9001'],
      ['WEBSERVER_PORT' => '9001', 'DRUPAL_PROFILE' => 'minimal', 'SIMPLETEST_BASE_URL' => 'http://localhost:9001'],
    ];
    yield 'empty shell value counts as unset' => [
      "WEBSERVER_PORT=8001\nDRUPAL_PROFILE=minimal\n",
      ['WEBSERVER_PORT' => '', 'DRUPAL_PROFILE' => ''],
      ['WEBSERVER_PORT' => '8001', 'DRUPAL_PROFILE' => 'minimal', 'SIMPLETEST_BASE_URL' => 'http://localhost:8001'],
    ];
    yield 'shell value with special characters' => [
      "DRUPAL_PROFILE=minimal\n",
      ['DRUPAL_PROFILE' => 'a$b $(c) #d'],
      ['WEBSERVER_PORT' => '', 'DRUPAL_PROFILE' => 'a$b $(c) #d', 'SIMPLETEST_BASE_URL' => 'http://localhost:8000'],
    ];
    yield 'shell value overrides a repeated key' => [
      "DRUPAL_PROFILE=standard\nDRUPAL_PROFILE=minimal\n",
      ['DRUPAL_PROFILE' => 'testing'],
      ['WEBSERVER_PORT' => '', 'DRUPAL_PROFILE' => 'testing', 'SIMPLETEST_BASE_URL' => 'http://localhost:8000'],
    ];
    yield 'shell value overrides an indented assignment' => [
      "  WEBSERVER_PORT=8001\n",
      ['WEBSERVER_PORT' => '9001'],
      ['WEBSERVER_PORT' => '9001', 'DRUPAL_PROFILE' => '', 'SIMPLETEST_BASE_URL' => 'http://localhost:9001'],
    ];
  }

  /**
   * Run a target of the probe makefile with the real 'Makefile'.
   *
   * @param string $target
   *   The target of the probe makefile.
   * @param string|null $dotenv
   *   The contents of '.env', or NULL for no file.
   * @param array<string, string|false> $env
   *   The shell environment. FALSE removes a variable.
   *
   * @return string
   *   The output of the probe.
   */
  protected function runMake(string $target, ?string $dotenv, array $env): string {
    self::writeDotenv($dotenv);
    File::dump(self::$sut . '/probe.mk', self::makeProbe());

    return $this->runProbe(['make', '--no-print-directory', '-f', self::rootDir() . '/Makefile', '-f', 'probe.mk', $target], $env);
  }

  /**
   * Run a probe command in the system under test.
   *
   * @param array<int, string> $command
   *   The command and its arguments.
   * @param array<string, string|false> $env
   *   The shell environment. FALSE removes a variable.
   *
   * @return string
   *   The standard output of the command.
   */
  protected function runProbe(array $command, array $env): string {
    // Variables inherited from the runner would stand in for shell values.
    $env += ['WEBSERVER_HOST' => FALSE, 'WEBSERVER_PORT' => FALSE, 'DRUPAL_PROFILE' => FALSE, 'SIMPLETEST_BASE_URL' => FALSE];

    $this->processCwd = self::$sut;
    $this->processRun((string) array_shift($command), $command, [], $env);
    $this->assertProcessSuccessful();

    return $this->processGet()->getOutput();
  }

  protected static function writeDotenv(?string $dotenv): void {
    if ($dotenv !== NULL) {
      File::dump(self::$sut . '/.env', $dotenv);
    }
  }

  /**
   * Build the probe makefile.
   *
   * 'probe' prints the variables that its recipe receives, and 'sub-make'
   * runs 'probe' through a recursive make with the same makefiles.
   */
  protected static function makeProbe(): string {
    $values = array_map(static fn(string $name): string => sprintf('"%1$s=$$%1$s"', $name), self::PROBE_VARIABLES);

    return implode("\n", [
      'probe:',
      "\t@printf '%s\\n' " . implode(' ', $values),
      '',
      'sub-make:',
      "\t@\$(MAKE) --no-print-directory -f \$(firstword \$(MAKEFILE_LIST)) -f probe.mk probe",
      '',
    ]);
  }

  /**
   * Build the probe command that the Ahoy entrypoint runs as '{{cmd}}'.
   */
  protected static function ahoyProbe(): string {
    $values = array_map(static fn(string $name): string => sprintf('"%1$s=$%1$s"', $name), self::PROBE_VARIABLES);

    return "printf '%s\\n' " . implode(' ', $values);
  }

  /**
   * Build the output that a probe prints.
   *
   * @param array<string, string> $values
   *   The value of each probe variable.
   */
  protected static function probeOutput(array $values): string {
    return implode('', array_map(static fn(string $name): string => $name . '=' . $values[$name] . "\n", self::PROBE_VARIABLES));
  }

  /**
   * Read the entrypoint of the Ahoy configuration.
   *
   * @return array<int, string>
   *   The entrypoint command, with the '{{cmd}}' and '{{name}}' placeholders.
   */
  protected static function ahoyEntrypoint(): array {
    $entrypoint = self::ahoyConfig()['entrypoint'] ?? NULL;

    if (!is_array($entrypoint)) {
      throw new \RuntimeException('.ahoy.yml declares no entrypoint.');
    }

    return array_map(static fn(mixed $item): string => is_string($item) ? $item : throw new \RuntimeException('.ahoy.yml declares an entrypoint item that is not a string.'), array_values($entrypoint));
  }

  /**
   * Read the Ahoy configuration.
   *
   * @return array<mixed>
   *   The parsed '.ahoy.yml'.
   */
  protected static function ahoyConfig(): array {
    $config = Yaml::parse(File::read(self::rootDir() . '/.ahoy.yml'));

    if (!is_array($config)) {
      throw new \RuntimeException('.ahoy.yml holds no configuration.');
    }

    return $config;
  }

}
