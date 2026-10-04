<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\DevTools\FAIL;
use function DrevOps\Eddy\DevTools\term_supports_color;
use DrevOps\Eddy\Tests\Exceptions\QuitErrorException;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[RunTestsInSeparateProcesses]
#[CoversFunction('DrevOps\Eddy\DevTools\NOTE')]
#[CoversFunction('DrevOps\Eddy\DevTools\TASK')]
#[CoversFunction('DrevOps\Eddy\DevTools\INFO')]
#[CoversFunction('DrevOps\Eddy\DevTools\PASS')]
#[CoversFunction('DrevOps\Eddy\DevTools\FAIL_NO_EXIT')]
#[CoversFunction('DrevOps\Eddy\DevTools\FAIL')]
#[CoversFunction('DrevOps\Eddy\DevTools\term_supports_color')]
#[Group('p0')]
final class HelpersFormatterTest extends UnitTestCase {

  #[DataProvider('dataProviderOutputFormatters')]
  public function testOutputFormatters(string $function, ?bool $is_tty, string $expected_output): void {
    if ($is_tty !== NULL) {
      $this->envSet('TERM', 'xterm-256color');
      $this->mockPosixIsatty($is_tty);
    }
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';
    ob_start();
    $callable = 'DrevOps\\Eddy\\DevTools\\' . $function;
    if (!is_callable($callable)) {
      $this->fail(sprintf('Function %s is not callable.', $callable));
    }
    call_user_func($callable, 'Test message %s', 'arg');
    $output = ob_get_clean();
    $this->assertIsString($output);
    $this->assertSame($expected_output, $output);
  }

  public static function dataProviderOutputFormatters(): \Iterator {
    yield 'note' => ['function' => 'note', 'is_tty' => NULL, 'expected_output' => "       Test message arg\n"];
    yield 'task, no color' => ['function' => 'task', 'is_tty' => FALSE, 'expected_output' => "[TASK] Test message arg\n"];
    yield 'task, with color' => ['function' => 'task', 'is_tty' => TRUE, 'expected_output' => "\033[34m[TASK] Test message arg\033[0m\n"];
    yield 'info, no color' => ['function' => 'info', 'is_tty' => FALSE, 'expected_output' => "[INFO] Test message arg\n"];
    yield 'info, with color' => ['function' => 'info', 'is_tty' => TRUE, 'expected_output' => "\033[36m[INFO] Test message arg\033[0m\n"];
    yield 'pass, no color' => ['function' => 'pass', 'is_tty' => FALSE, 'expected_output' => "[ OK ] Test message arg\n"];
    yield 'pass, with color' => ['function' => 'pass', 'is_tty' => TRUE, 'expected_output' => "\033[32m[ OK ] Test message arg\033[0m\n"];
    yield 'fail_no_exit, no color' => ['function' => 'fail_no_exit', 'is_tty' => FALSE, 'expected_output' => "[FAIL] Test message arg\n"];
    yield 'fail_no_exit, with color' => ['function' => 'fail_no_exit', 'is_tty' => TRUE, 'expected_output' => "\033[31m[FAIL] Test message arg\033[0m\n"];
  }

  #[DataProvider('dataProviderFail')]
  public function testFail(bool $is_tty, string $expected_output): void {
    $this->envSet('TERM', 'xterm-256color');
    $this->mockPosixIsatty($is_tty);
    $this->mockQuit(1);
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';
    ob_start();
    try {
      FAIL('Test failure %s', 'message');
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(1, $e->getCode());
    }
    finally {
      $output = ob_get_clean();
      $this->assertIsString($output);
      $this->assertSame($expected_output, $output);
    }
  }

  public static function dataProviderFail(): \Iterator {
    yield 'no color' => ['is_tty' => FALSE, 'expected_output' => "[FAIL] Test failure message\n"];
    yield 'with color' => ['is_tty' => TRUE, 'expected_output' => "\033[31m[FAIL] Test failure message\033[0m\n"];
  }

  #[DataProvider('dataProviderTermSupportsColor')]
  public function testTermSupportsColor(string|bool $term_value, bool $expected): void {
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';
    if ($term_value === FALSE) {
      $this->envUnset('TERM');
    }
    else {
      $this->envSet('TERM', (string) $term_value);
    }
    $result = term_supports_color();
    $this->assertSame($expected, $result);
  }

  public static function dataProviderTermSupportsColor(): \Iterator {
    yield 'dumb terminal' => ['term_value' => 'dumb', 'expected' => FALSE];
    yield 'no terminal' => ['term_value' => FALSE, 'expected' => FALSE];
  }

}
