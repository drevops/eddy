<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Functional;

use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;

/**
 * Tests for the ahoy-based tooling workflow.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[Group('p3')]
final class AhoyTest extends ToolingTestCase {

  public function testAssembleSkipNpmBuild(): void {
    touch(self::$sut . '/.skip_npm_build');

    $this->processRun('ahoy', ['assemble'], [], [], $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();

    $this->assertProcessAnyOutputContains('ASSEMBLE COMPLETE');
    $this->assertDirectoryExists(self::$sut . '/build/vendor');
    $this->assertFileExists(self::$sut . '/build/composer.json');
    $this->assertFileExists(self::$sut . '/build/composer.lock');
    $this->assertDirectoryDoesNotExist(self::$sut . '/build/node_modules');
    $this->assertProcessAnyOutputNotContains('Would run build');
  }

  public function testLintTwigFollowsSymlinkedTemplates(): void {
    $this->processRun('ahoy', ['assemble'], [], [], $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('ASSEMBLE COMPLETE');

    $this->processRun('ahoy', ['lint'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();

    // The assemble command symlinks each top-level extension item into the
    // build, so a real templates/ directory becomes a symlink in the tree
    // twig-cs-fixer scans. Recreate that layout with a template carrying a
    // fixable delimiter-spacing violation, reachable from the scanned build
    // directory only by following the templates/ symlink.
    mkdir(self::$sut . '/templates');
    file_put_contents(self::$sut . '/templates/your_extension.html.twig', '{{content}}' . PHP_EOL);
    symlink(self::$sut . '/templates', self::$sut . '/build/web/modules/custom/your_extension/templates');

    $this->processRun('ahoy', ['lint'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessFailed();
    $this->assertProcessAnyOutputContains('your_extension.html.twig');

    $this->processRun('ahoy', ['lint-fix'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);

    $this->processRun('ahoy', ['lint'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
  }

  public function testStartDiscoversPort(): void {
    self::webserverCreateDocroot(self::$sut);

    $this->processRun('ahoy', ['start'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('ENVIRONMENT READY');

    $port = self::webserverDotenvPort(self::$sut);
    $this->assertNotNull($port, 'start records the port it discovered in .env.');
    $this->assertGreaterThanOrEqual(8000, (int) $port);
    $this->assertLessThanOrEqual(8099, (int) $port);
    $this->assertTrue(self::webserverPortAccepts($port), 'The webserver listens on the discovered port.');
  }

  public function testShellOverridesDotenv(): void {
    // The tooling reads DRUPAL_PROFILE only from the environment, so 'info'
    // prints the value that the wrapper exported.
    file_put_contents(self::$sut . '/.env', "WEBSERVER_PORT=8001\nDRUPAL_PROFILE=minimal\n");

    $this->processRun('ahoy', ['info'], [], ['WEBSERVER_PORT' => FALSE, 'DRUPAL_PROFILE' => FALSE], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('Webserver port:     8001 (env)');
    $this->assertProcessAnyOutputContains('Drupal profile:     minimal');

    $this->processRun('ahoy', ['info'], [], ['WEBSERVER_PORT' => '9001', 'DRUPAL_PROFILE' => 'testing'], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('Webserver port:     9001 (env)');
    $this->assertProcessAnyOutputContains('Drupal profile:     testing');
  }

  public function testReset(): void {
    self::webserverCreateDocroot(self::$sut);

    $this->processRun('ahoy', ['start'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('ENVIRONMENT READY');

    $port = $this->webserverPort();
    $this->assertTrue(self::webserverPortAccepts($port), 'The webserver of the project is running.');

    // The WebDriver backend of the project, which runs from the project root.
    $webdriver_port = self::webserverFreePort();
    file_put_contents(self::$sut . '/.env', 'WEBDRIVER_PORT=' . $webdriver_port . "\n", FILE_APPEND);
    $browser = self::webserverStartListener(self::$sut, $webdriver_port);

    // Another PHP process of the same user, such as the webserver of another
    // project or a Composer run.
    $other = new Process(['php', '-r', 'sleep(60);']);
    $other->start();

    try {
      $this->assertTrue(self::webserverPortAccepts($webdriver_port), 'The WebDriver backend of the project is running.');
      $this->assertTrue($other->isRunning(), 'The other PHP process is running.');

      // A WEBDRIVER_PORT in the environment takes precedence over .env.
      $this->processRun('ahoy', ['reset'], [], ['WEBDRIVER_PORT' => FALSE], $this->defaultTimeout, $this->defaultIdleTimeout);
      $this->assertProcessSuccessful();

      $this->assertFalse(self::webserverPortAccepts($port), 'reset stops the webserver of the project.');
      $this->assertFalse(self::webserverPortAccepts($webdriver_port), 'reset stops the WebDriver backend of the project.');
      $this->assertTrue($other->isRunning(), 'reset leaves other PHP processes running.');
      $this->assertDirectoryDoesNotExist(self::$sut . '/build');
      $this->assertDirectoryDoesNotExist(self::$sut . '/.logs');
      $this->assertDirectoryDoesNotExist(self::$sut . '/vendor');
    }
    finally {
      $browser->stop();
      $other->stop();
    }
  }

  public function testStopAndResetLeaveServerOfAnotherProject(): void {
    $other_root = self::$tmp . '/other_' . uniqid();

    // Another project recorded the same ports, and its webserver and its
    // WebDriver backend run on them.
    $port = self::webserverFreePort();
    $other = self::webserverStartOther($other_root, $port);
    $webdriver_port = self::webserverFreePort();
    $other_browser = self::webserverStartListener($other_root, $webdriver_port);
    file_put_contents(self::$sut . '/.env', sprintf("WEBSERVER_PORT=%s\nWEBDRIVER_PORT=%s\n", $port, $webdriver_port));

    // Ports in the environment take precedence over .env.
    $env = ['WEBSERVER_PORT' => FALSE, 'WEBDRIVER_PORT' => FALSE];

    try {
      $this->assertTrue(self::webserverPortAccepts($port), 'The webserver of the other project is running.');
      $this->assertTrue(self::webserverPortAccepts($webdriver_port), 'The WebDriver backend of the other project is running.');

      $this->processRun('ahoy', ['stop'], [], $env, $this->defaultTimeout, $this->defaultIdleTimeout);
      $this->assertProcessSuccessful();
      $this->assertProcessAnyOutputContains('Port ' . $port . ' is still in use by another process, which was left running:');
      $this->assertTrue(self::webserverPortAccepts($port), 'stop leaves the webserver of another project running.');

      $this->processRun('ahoy', ['reset'], [], $env, $this->defaultTimeout, $this->defaultIdleTimeout);
      $this->assertProcessSuccessful();
      $this->assertTrue(self::webserverPortAccepts($port), 'reset leaves the webserver of another project running.');
      $this->assertTrue(self::webserverPortAccepts($webdriver_port), 'reset leaves the WebDriver backend of another project running.');
      $this->assertDirectoryDoesNotExist(self::$sut . '/vendor');
    }
    finally {
      $other_browser->stop();
      $other->stop();
    }
  }

  public function testWorkflow(): void {
    $this->processRun('ahoy', ['start'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessFailed();
    $this->assertProcessAnyOutputNotContains('ENVIRONMENT READY');

    $this->processRun('ahoy', ['stop'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('ENVIRONMENT STOPPED');

    $this->processRun('ahoy', ['assemble'], [], [], $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('ASSEMBLE COMPLETE');
    $this->assertProcessAnyOutputContains('[example] post-assemble script ran.');
    $this->assertDirectoryExists(self::$sut . '/build/vendor');
    $this->assertFileExists(self::$sut . '/build/composer.json');
    $this->assertFileExists(self::$sut . '/build/composer.lock');
    $this->assertDirectoryExists(self::$sut . '/build/node_modules');
    $this->assertProcessAnyOutputContains('Would run build');

    $this->processRun('ahoy', ['start'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('ENVIRONMENT READY');
    $this->assertProcessAnyOutputContains('[example] post-start script ran.');

    $this->processRun('ahoy', ['stop'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('ENVIRONMENT STOPPED');
    $this->assertProcessAnyOutputContains('[example] pre-stop script ran.');

    $this->processRun('ahoy', ['start'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();

    $this->processRun('ahoy', ['provision'], [], [], $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('PROVISION COMPLETE');
    $this->assertProcessAnyOutputContains('[example] post-provision script ran.');
    $this->assertProcessAnyOutputNotContains('Do you really want to drop all tables in the database');

    // Provision is idempotent.
    $this->processRun('ahoy', ['provision'], [], [], $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('PROVISION COMPLETE');
    $this->assertProcessAnyOutputContains('[example] post-provision script ran.');
    $this->assertProcessAnyOutputNotContains('Do you really want to drop all tables in the database');

    $this->processRun('ahoy', ['drush', 'status'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('Database         : Connected');
    $this->assertProcessAnyOutputContains('Drupal bootstrap : Successful');

    $this->processRun('ahoy', ['login'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('user/reset/1/');

    $this->processRun('ahoy', ['info'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('ENVIRONMENT INFO');
    $this->assertProcessAnyOutputContains('Drupal version:');
    $this->assertProcessAnyOutputContains('Webserver port:');
    $this->assertProcessAnyOutputContains('Site URL:           http://');

    $this->runLint();

    $this->processRun('ahoy', ['test'], [], [], $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('BROWSER READY');
    $this->assertDirectoryExists(self::$sut . '/build/web/sites/simpletest/browser_output');

    $this->runJavascriptTests();

    $this->runUnitTests();

    $this->runKernelTests();

    $this->runFunctionalTests();

    $this->runFunctionalJavascriptTests();
  }

  protected function runLint(): void {
    $this->processRun('ahoy', ['lint'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();

    // The lint and test wrappers chdir into build/ before running PHP tools,
    // so a relative sys_get_temp_dir() resolves against build/ and tempnam()
    // fails. The wrapper resolves TMPDIR to an absolute path first, so
    // linting still passes when TMPDIR is relative.
    if (!is_dir(self::$sut . '/.logs/relative-tmp')) {
      mkdir(self::$sut . '/.logs/relative-tmp', 0777, TRUE);
    }
    $this->processRun('ahoy', ['lint'], [], ['TMPDIR' => '.logs/relative-tmp'], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();

    // PHP: introduce a PHPCS violation.
    File::append(self::$sut . '/your_extension.module', '$a=123;echo $a;' . PHP_EOL);

    $this->processRun('ahoy', ['lint'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessFailed();

    $this->processRun('ahoy', ['lint-fix'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);

    $this->processRun('ahoy', ['lint'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();

    // JS: introduce a Prettier formatting violation (fixable).
    File::append(self::$sut . '/js/your_extension.js', 'Drupal.behaviors.yourExtension.testValue =     "test"   ;' . PHP_EOL);

    $this->processRun('ahoy', ['lint'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessFailed();

    $this->processRun('ahoy', ['lint-fix'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);

    $this->processRun('ahoy', ['lint'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();

    // CSS: introduce a Stylelint order violation (fixable).
    File::append(self::$sut . '/css/your_extension.css', PHP_EOL . '.test {' . PHP_EOL . '  z-index: 1;' . PHP_EOL . '  color: red;' . PHP_EOL . '}' . PHP_EOL);

    $this->processRun('ahoy', ['lint'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessFailed();

    $this->processRun('ahoy', ['lint-fix'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);

    $this->processRun('ahoy', ['lint'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();

    // Spelling: introduce a CSpell violation. CSpell has no autofix; restore
    // the file manually after asserting the failure.
    File::append(self::$sut . '/README.md', PHP_EOL . 'A wnoirly word.' . PHP_EOL);

    $this->processRun('ahoy', ['lint'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessFailed();

    File::replaceContentInFile(self::$sut . '/README.md', PHP_EOL . 'A wnoirly word.' . PHP_EOL, '');

    $this->processRun('ahoy', ['lint'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
  }

  protected function runJavascriptTests(): void {
    $this->processRun('ahoy', ['test-javascript'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();

    // The 'test-js' alias resolves to the same command.
    $this->processRun('ahoy', ['test-js'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();

    File::replaceContentInFile(self::$sut . '/js/your_extension.test.js', 'toMatch', 'not.toMatch');

    $this->processRun('ahoy', ['test-javascript'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessFailed();

    // `test-javascript` must succeed in projects without any JavaScript tests
    // (CSS-only modules, modules without custom JavaScript).
    unlink(self::$sut . '/js/your_extension.test.js');

    $this->processRun('ahoy', ['test-javascript'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
  }

  protected function runUnitTests(): void {
    $this->processRun('ahoy', ['test-unit'], [], [], $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();

    $this->assertTestCoverage();

    File::replaceContentInFile(self::$sut . '/tests/src/Unit/YourExtensionServiceUnitTest.php', 'assertEquals', 'assertNotEquals');

    $this->processRun('ahoy', ['test-unit'], [], [], $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessFailed();
  }

  protected function runKernelTests(): void {
    $this->processRun('ahoy', ['test-kernel'], [], [], $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();

    File::replaceContentInFile(self::$sut . '/tests/src/Kernel/YourExtensionServiceKernelTest.php', 'assertEquals', 'assertNotEquals');

    $this->processRun('ahoy', ['test-kernel'], [], [], $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessFailed();
  }

  protected function runFunctionalTests(): void {
    $this->processRun('ahoy', ['test-functional'], [], [], $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertDirectoryExists(self::$sut . '/build/web/sites/simpletest/browser_output');

    File::replaceContentInFile(self::$sut . '/tests/src/Functional/YourExtensionFunctionalTest.php', 'responseContains', 'responseNotContains');

    $this->processRun('ahoy', ['test-functional'], [], [], $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessFailed();
  }

  protected function runFunctionalJavascriptTests(): void {
    $this->processRun('ahoy', ['test-functional-javascript'], [], [], $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();

    File::replaceContentInFile(self::$sut . '/tests/src/FunctionalJavascript/YourExtensionSmokeFunctionalJavascriptTest.php', 'assertNotEmpty', 'assertEmpty');

    $this->processRun('ahoy', ['test-functional-javascript'], [], [], $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessFailed();
  }

}
