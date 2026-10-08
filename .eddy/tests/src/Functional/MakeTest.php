<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Functional;

use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the make-based devtools workflow.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[Group('p4')]
final class MakeTest extends DevtoolsTestCase {

  public function testAssembleSkipNpmBuild(): void {
    touch(self::$sut . '/.skip_npm_build');

    $this->processRun('make', ['assemble'], [], [], $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();

    $this->assertProcessAnyOutputContains('ASSEMBLE COMPLETE');
    $this->assertDirectoryExists(self::$sut . '/build/vendor');
    $this->assertFileExists(self::$sut . '/build/composer.json');
    $this->assertFileExists(self::$sut . '/build/composer.lock');
    $this->assertDirectoryDoesNotExist(self::$sut . '/build/node_modules');
    $this->assertProcessAnyOutputNotContains('Would run build');
  }

  public function testLintTwigFollowsSymlinkedTemplates(): void {
    $this->processRun('make', ['assemble'], [], [], $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('ASSEMBLE COMPLETE');

    $this->processRun('make', ['lint'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();

    // The assemble command symlinks each top-level extension item into the
    // build, so a real templates/ directory becomes a symlink in the tree
    // twig-cs-fixer scans. Recreate that layout with a template carrying a
    // fixable delimiter-spacing violation, reachable from the scanned build
    // directory only by following the templates/ symlink.
    mkdir(self::$sut . '/templates');
    file_put_contents(self::$sut . '/templates/your_extension.html.twig', '{{content}}' . PHP_EOL);
    symlink(self::$sut . '/templates', self::$sut . '/build/web/modules/custom/your_extension/templates');

    $this->processRun('make', ['lint'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessFailed();
    $this->assertProcessAnyOutputContains('your_extension.html.twig');

    $this->processRun('make', ['lint-fix'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);

    $this->processRun('make', ['lint'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
  }

  public function testWorkflow(): void {
    $this->processRun('make', ['start'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessFailed();

    $this->processRun('make', ['stop'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('ENVIRONMENT STOPPED');

    $this->processRun('make', ['assemble'], [], [], $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('ASSEMBLE COMPLETE');
    $this->assertProcessAnyOutputContains('[example] post-assemble script ran.');
    $this->assertDirectoryExists(self::$sut . '/build/vendor');
    $this->assertFileExists(self::$sut . '/build/composer.json');
    $this->assertFileExists(self::$sut . '/build/composer.lock');
    $this->assertDirectoryExists(self::$sut . '/build/node_modules');
    $this->assertProcessAnyOutputContains('Would run build');

    $this->processRun('make', ['start'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('ENVIRONMENT READY');
    $this->assertProcessAnyOutputContains('[example] post-start script ran.');

    $this->processRun('make', ['stop'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('ENVIRONMENT STOPPED');
    $this->assertProcessAnyOutputContains('[example] pre-stop script ran.');

    $this->processRun('make', ['start'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();

    $this->processRun('make', ['provision'], [], [], $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('PROVISION COMPLETE');
    $this->assertProcessAnyOutputContains('[example] post-provision script ran.');
    $this->assertProcessAnyOutputNotContains('Do you really want to drop all tables in the database');

    // Provision is idempotent.
    $this->processRun('make', ['provision'], [], [], $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('PROVISION COMPLETE');
    $this->assertProcessAnyOutputContains('[example] post-provision script ran.');
    $this->assertProcessAnyOutputNotContains('Do you really want to drop all tables in the database');

    $this->processRun('make', ['drush', 'status'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('Database         : Connected');
    $this->assertProcessAnyOutputContains('Drupal bootstrap : Successful');

    $this->processRun('make', ['login'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('user/reset/1/');

    $this->processRun('make', ['info'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('ENVIRONMENT INFO');
    $this->assertProcessAnyOutputContains('Drupal version:');
    $this->assertProcessAnyOutputContains('Webserver port:');
    $this->assertProcessAnyOutputContains('Site URL:           http://');

    $this->runLint();

    $this->processRun('make', ['test'], [], [], $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertDirectoryExists(self::$sut . '/build/web/sites/simpletest/browser_output');

    $this->runJavascriptTests();

    $this->runUnitTests();

    $this->runKernelTests();

    $this->runFunctionalTests();

    $this->runFunctionalJavascriptTests();
  }

  protected function runLint(): void {
    $this->processRun('make', ['lint'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();

    // The lint and test wrappers chdir into build/ before running PHP tools,
    // so a relative sys_get_temp_dir() resolves against build/ and tempnam()
    // fails. The wrapper resolves TMPDIR to an absolute path first, so
    // linting still passes when TMPDIR is relative.
    if (!is_dir(self::$sut . '/.logs/relative-tmp')) {
      mkdir(self::$sut . '/.logs/relative-tmp', 0777, TRUE);
    }
    $this->processRun('make', ['lint'], [], ['TMPDIR' => '.logs/relative-tmp'], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();

    // PHP: introduce a PHPCS violation.
    File::append(self::$sut . '/your_extension.module', '$a=123;echo $a;' . PHP_EOL);

    $this->processRun('make', ['lint'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessFailed();

    $this->processRun('make', ['lint-fix'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);

    $this->processRun('make', ['lint'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();

    // JS: introduce a Prettier formatting violation (fixable).
    File::append(self::$sut . '/js/your_extension.js', 'Drupal.behaviors.yourExtension.testValue =     "test"   ;' . PHP_EOL);

    $this->processRun('make', ['lint'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessFailed();

    $this->processRun('make', ['lint-fix'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);

    $this->processRun('make', ['lint'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();

    // CSS: introduce a Stylelint order violation (fixable).
    File::append(self::$sut . '/css/your_extension.css', PHP_EOL . '.test {' . PHP_EOL . '  z-index: 1;' . PHP_EOL . '  color: red;' . PHP_EOL . '}' . PHP_EOL);

    $this->processRun('make', ['lint'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessFailed();

    $this->processRun('make', ['lint-fix'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);

    $this->processRun('make', ['lint'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();

    // Spelling: introduce a CSpell violation. CSpell has no autofix; restore
    // the file manually after asserting the failure.
    File::append(self::$sut . '/README.md', PHP_EOL . 'A wnoirly word.' . PHP_EOL);

    $this->processRun('make', ['lint'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessFailed();

    File::replaceContentInFile(self::$sut . '/README.md', PHP_EOL . 'A wnoirly word.' . PHP_EOL, '');

    $this->processRun('make', ['lint'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
  }

  protected function runJavascriptTests(): void {
    $this->processRun('make', ['test-javascript'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();

    // The 'test-js' alias resolves to the same target.
    $this->processRun('make', ['test-js'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();

    File::replaceContentInFile(self::$sut . '/js/your_extension.test.js', 'toMatch', 'not.toMatch');

    $this->processRun('make', ['test-javascript'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessFailed();

    // `test-javascript` must succeed in projects without any JavaScript tests
    // (CSS-only modules, modules without custom JavaScript).
    unlink(self::$sut . '/js/your_extension.test.js');

    $this->processRun('make', ['test-javascript'], [], [], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
  }

  protected function runUnitTests(): void {
    $this->processRun('make', ['test-unit'], [], [], $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();

    $this->assertTestCoverage();

    File::replaceContentInFile(self::$sut . '/tests/src/Unit/YourExtensionServiceUnitTest.php', 'assertEquals', 'assertNotEquals');

    $this->processRun('make', ['test-unit'], [], [], $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessFailed();
  }

  protected function runKernelTests(): void {
    $this->processRun('make', ['test-kernel'], [], [], $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();

    File::replaceContentInFile(self::$sut . '/tests/src/Kernel/YourExtensionServiceKernelTest.php', 'assertEquals', 'assertNotEquals');

    $this->processRun('make', ['test-kernel'], [], [], $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessFailed();
  }

  protected function runFunctionalTests(): void {
    $this->processRun('make', ['test-functional'], [], [], $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertDirectoryExists(self::$sut . '/build/web/sites/simpletest/browser_output');

    File::replaceContentInFile(self::$sut . '/tests/src/Functional/YourExtensionFunctionalTest.php', 'responseContains', 'responseNotContains');

    $this->processRun('make', ['test-functional'], [], [], $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessFailed();
  }

  protected function runFunctionalJavascriptTests(): void {
    $this->processRun('make', ['test-functional-javascript'], [], [], $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();

    File::replaceContentInFile(self::$sut . '/tests/src/FunctionalJavascript/YourExtensionSmokeFunctionalJavascriptTest.php', 'assertNotEmpty', 'assertEmpty');

    $this->processRun('make', ['test-functional-javascript'], [], [], $this->longTimeout, $this->defaultIdleTimeout);
    $this->assertProcessFailed();
  }

}
