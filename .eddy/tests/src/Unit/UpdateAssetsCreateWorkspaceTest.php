<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

use function DrevOps\Eddy\Assets\create_workspace;

#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class UpdateAssetsCreateWorkspaceTest extends UnitTestCase {

  protected const string NAMESPACE = 'DrevOps\\Eddy\\Assets';

  protected function setUp(): void {
    parent::setUp();
    self::envSet('SCRIPT_RUN_SKIP', '1');
    require_once self::rootDir() . '/.eddy/assets/update-assets.php';
    $this->getFunctionMock(self::NAMESPACE, 'sys_get_temp_dir')->expects($this->once())->willReturn(self::$tmp);
  }

  public function testCreateWorkspace(): void {
    $this->mockArchive('', TRUE);
    $workspace_dir = '';

    $output = $this->capture(function () use (&$workspace_dir): void {
      $workspace_dir = create_workspace(self::$sut);
    });

    $this->assertStringStartsWith(self::$tmp . '/eddy-assets-', $workspace_dir);
    $this->assertFileExists($workspace_dir . '/install.php');
    $this->assertStringContainsString('Exporting current branch to workspace...', $output);
  }

  #[DataProvider('dataProviderCreateWorkspaceFails')]
  public function testCreateWorkspaceFails(?string $archive_output, string $expected_message): void {
    $this->mockArchive($archive_output, FALSE);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected_message);

    $this->capture(fn(): string => create_workspace(self::$sut));
  }

  public static function dataProviderCreateWorkspaceFails(): \Iterator {
    yield 'archive output' => ['fatal: not a git repository', 'Failed to export git archive: fatal: not a git repository'];
    yield 'no output' => [NULL, 'Failed to export git archive: unknown error'];
  }

  protected function mockArchive(?string $output, bool $exports): void {
    $pattern = sprintf("/^cd %s && git archive HEAD \\| tar -x -C '([^']+)' 2>&1$/", preg_quote(escapeshellarg(self::$sut), '/'));

    $shell_exec = $this->getFunctionMock(self::NAMESPACE, 'shell_exec');
    $shell_exec->expects($this->once())->willReturnCallback(function (string $command) use ($pattern, $output, $exports): ?string {
      $this->assertMatchesRegularExpression($pattern, $command);

      if ($exports && preg_match($pattern, $command, $matches) === 1) {
        File::dump($matches[1] . '/install.php', '<?php');
      }

      return $output;
    });
  }

}
