<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

use function DrevOps\Eddy\Assets\convert_to_svg;

#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class UpdateAssetsConvertToSvgTest extends UnitTestCase {

  protected const string NAMESPACE = 'DrevOps\\Eddy\\Assets';

  protected const string COMMAND = "node '/assets/svg-term-render.js' '/casts/install.cast' '/assets/install.svg' --line-height 1.1 2>&1";

  protected function setUp(): void {
    parent::setUp();
    self::envSet('SCRIPT_RUN_SKIP', '1');
    require_once self::rootDir() . '/.eddy/assets/update-assets.php';
  }

  public function testConvertToSvg(): void {
    $this->mockRenderer(0, []);

    convert_to_svg('/casts/install.cast', '/assets/install.svg', '/assets');
  }

  /**
   * @param list<string> $renderer_output
   *   The lines the renderer prints.
   */
  #[DataProvider('dataProviderConvertToSvgFails')]
  public function testConvertToSvgFails(array $renderer_output, string $expected_message): void {
    $this->mockRenderer(1, $renderer_output);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected_message);

    convert_to_svg('/casts/install.cast', '/assets/install.svg', '/assets');
  }

  public static function dataProviderConvertToSvgFails(): \Iterator {
    yield 'renderer output' => [['Error: invalid cast', 'at line 3'], 'Failed to convert cast to SVG: /casts/install.cast' . PHP_EOL . 'Error: invalid cast' . PHP_EOL . 'at line 3'];
    yield 'no output' => [[], 'Failed to convert cast to SVG: /casts/install.cast'];
  }

  /**
   * @param list<string> $renderer_output
   *   The lines the renderer prints.
   */
  protected function mockRenderer(int $exit_code, array $renderer_output): void {
    $exec = $this->getFunctionMock(self::NAMESPACE, 'exec');
    $exec->expects($this->once())->with(self::COMMAND)->willReturnCallback(static function (string $command, ?array &$output = NULL, ?int &$result_code = NULL) use ($exit_code, $renderer_output): string {
      $output = $renderer_output;
      $result_code = $exit_code;

      return (string) end($renderer_output);
    });
  }

}
