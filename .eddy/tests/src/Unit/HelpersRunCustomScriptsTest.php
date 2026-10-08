<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\DevTools\run_custom_scripts;
use DrevOps\Eddy\Tests\Exceptions\QuitErrorException;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Group;

#[CoversFunction('DrevOps\Eddy\DevTools\run_custom_scripts')]
#[Group('p0')]
final class HelpersRunCustomScriptsTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';
  }

  public function testMissingDirectoryDoesNothing(): void {
    run_custom_scripts(self::$tmp . '/nonexistent_' . uniqid(), 'assemble-');
    $this->expectNotToPerformAssertions();
  }

  public function testEmptyDirectoryDoesNothing(): void {
    $dir = $this->createScriptsDir();
    run_custom_scripts($dir, 'assemble-');
    $this->expectNotToPerformAssertions();
  }

  public function testDirectoryWithNoPrefixMatchesDoesNothing(): void {
    $dir = $this->createScriptsDir(['unrelated.sh' => 'echo nope', 'README.md' => '']);
    run_custom_scripts($dir, 'assemble-');
    $this->expectNotToPerformAssertions();
  }

  public function testExecutesMatchingScriptsInOrder(): void {
    $dir = $this->createScriptsDir([
      'assemble-zeta.sh' => 'echo zeta',
      'assemble-alpha.sh' => 'echo alpha',
      'assemble-beta.sh' => 'echo beta',
    ]);

    $this->mockPassthruMultiple([
      ['cmd' => escapeshellarg($dir . '/assemble-alpha.sh'), 'result_code' => 0],
      ['cmd' => escapeshellarg($dir . '/assemble-beta.sh'), 'result_code' => 0],
      ['cmd' => escapeshellarg($dir . '/assemble-zeta.sh'), 'result_code' => 0],
    ]);

    ob_start();
    run_custom_scripts($dir, 'assemble-');
    $output = ob_get_clean();
    $this->assertIsString($output);

    $this->assertStringContainsString('assemble-alpha.sh', $output);
    $this->assertStringContainsString('assemble-beta.sh', $output);
    $this->assertStringContainsString('assemble-zeta.sh', $output);
  }

  public function testIgnoresDifferentPrefix(): void {
    $dir = $this->createScriptsDir([
      'assemble-run.sh' => 'echo a',
      'provision-skip.sh' => 'echo p',
    ]);

    $this->mockPassthru(['cmd' => escapeshellarg($dir . '/assemble-run.sh'), 'result_code' => 0]);

    ob_start();
    run_custom_scripts($dir, 'assemble-');
    $output = ob_get_clean();
    $this->assertIsString($output);

    $this->assertStringContainsString('assemble-run.sh', $output);
    $this->assertStringNotContainsString('provision-skip.sh', $output);
  }

  public function testSkipsDirectoriesMatchingPrefix(): void {
    $dir = self::$tmp . '/scripts_' . uniqid();
    mkdir($dir, 0755, TRUE);
    mkdir($dir . '/assemble-rogue.sh', 0755, TRUE);
    file_put_contents($dir . '/assemble-real.sh', 'echo real');

    $this->mockPassthru(['cmd' => escapeshellarg($dir . '/assemble-real.sh'), 'result_code' => 0]);

    ob_start();
    run_custom_scripts($dir, 'assemble-');
    $output = ob_get_clean();
    $this->assertIsString($output);

    $this->assertStringContainsString('assemble-real.sh', $output);
    $this->assertStringNotContainsString('assemble-rogue.sh', $output);
  }

  public function testIgnoresNonShellExtension(): void {
    $dir = $this->createScriptsDir([
      'assemble-real.sh' => 'echo run',
      'assemble-readme.md' => '# notes',
      'assemble-script.txt' => 'echo nope',
    ]);

    $this->mockPassthru(['cmd' => escapeshellarg($dir . '/assemble-real.sh'), 'result_code' => 0]);

    ob_start();
    run_custom_scripts($dir, 'assemble-');
    $output = ob_get_clean();
    $this->assertIsString($output);

    $this->assertStringContainsString('assemble-real.sh', $output);
    $this->assertStringNotContainsString('assemble-readme.md', $output);
    $this->assertStringNotContainsString('assemble-script.txt', $output);
  }

  public function testNonZeroExitAbortsParent(): void {
    $dir = $this->createScriptsDir([
      'provision-first.sh' => 'echo first',
      'provision-second.sh' => 'echo second',
    ]);

    $this->mockPassthru(['cmd' => escapeshellarg($dir . '/provision-first.sh'), 'result_code' => 7]);
    $this->mockQuit(7);

    ob_start();
    try {
      run_custom_scripts($dir, 'provision-');
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(7, $e->getCode());
    }
    finally {
      $output = ob_get_clean();
      $this->assertIsString($output);
      $this->assertStringContainsString('provision-first.sh', $output);
      $this->assertStringContainsString('Custom script', $output);
      $this->assertStringContainsString('failed', $output);
      $this->assertStringNotContainsString('provision-second.sh', $output);
    }
  }

  protected function createScriptsDir(array $files = []): string {
    $dir = self::$tmp . '/scripts_' . uniqid();
    mkdir($dir, 0755, TRUE);
    foreach ($files as $name => $contents) {
      file_put_contents($dir . '/' . $name, $contents);
    }

    return $dir;
  }

}
