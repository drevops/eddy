<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

use function DrevOps\Eddy\Tooling\client_host;
use function DrevOps\Eddy\Tooling\is_absolute_path;
use function DrevOps\Eddy\Tooling\is_windows;
use function DrevOps\Eddy\Tooling\kill_processes;
use function DrevOps\Eddy\Tooling\null_device;
use function DrevOps\Eddy\Tooling\port_listener_pids;
use function DrevOps\Eddy\Tooling\process_command;
use function DrevOps\Eddy\Tooling\process_cwd;
use function DrevOps\Eddy\Tooling\run_in_background;
use function DrevOps\Eddy\Tooling\shell_path;
use function DrevOps\Eddy\Tooling\site_db_file;
use function DrevOps\Eddy\Tooling\site_db_url;
use function DrevOps\Eddy\Tooling\webdriver_listeners;
use function DrevOps\Eddy\Tooling\webserver_docroot_argument;
use function DrevOps\Eddy\Tooling\webserver_serves;

#[CoversFunction('DrevOps\Eddy\Tooling\is_windows')]
#[CoversFunction('DrevOps\Eddy\Tooling\null_device')]
#[CoversFunction('DrevOps\Eddy\Tooling\shell_path')]
#[CoversFunction('DrevOps\Eddy\Tooling\site_db_file')]
#[CoversFunction('DrevOps\Eddy\Tooling\site_db_url')]
#[CoversFunction('DrevOps\Eddy\Tooling\client_host')]
#[CoversFunction('DrevOps\Eddy\Tooling\is_absolute_path')]
#[CoversFunction('DrevOps\Eddy\Tooling\run_in_background')]
#[CoversFunction('DrevOps\Eddy\Tooling\port_listener_pids')]
#[CoversFunction('DrevOps\Eddy\Tooling\port_listeners')]
#[CoversFunction('DrevOps\Eddy\Tooling\process_command')]
#[CoversFunction('DrevOps\Eddy\Tooling\process_cwd')]
#[CoversFunction('DrevOps\Eddy\Tooling\kill_processes')]
#[CoversFunction('DrevOps\Eddy\Tooling\webserver_docroot_argument')]
#[CoversFunction('DrevOps\Eddy\Tooling\webserver_serves')]
#[CoversFunction('DrevOps\Eddy\Tooling\webdriver_listeners')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersPlatformTest extends UnitTestCase {

  #[DataProvider('dataProviderIsWindows')]
  public function testIsWindows(string $uname, bool $expected): void {
    $this->mockFunction('php_uname', static fn(string $mode = 'a'): string => $mode === 's' ? $uname : 'unexpected mode');

    $this->assertSame($expected, is_windows());
  }

  public static function dataProviderIsWindows(): \Iterator {
    yield 'windows' => ['Windows NT', TRUE];
    yield 'linux' => ['Linux', FALSE];
    yield 'macos' => ['Darwin', FALSE];
  }

  #[DataProvider('dataProviderNullDevice')]
  public function testNullDevice(bool $windows, string $expected): void {
    $this->mockWindows($windows);

    $this->assertSame($expected, null_device());
  }

  public static function dataProviderNullDevice(): \Iterator {
    yield 'windows' => [TRUE, 'NUL'];
    yield 'posix' => [FALSE, '/dev/null'];
  }

  #[DataProvider('dataProviderShellPath')]
  public function testShellPath(bool $windows, string $path, string $expected): void {
    $this->mockWindows($windows);

    $this->assertSame($expected, shell_path($path));
  }

  public static function dataProviderShellPath(): \Iterator {
    yield 'windows' => [TRUE, 'build/vendor/bin/drush', 'build\\vendor\\bin\\drush'];
    yield 'windows without separators' => [TRUE, 'drush', 'drush'];
    yield 'posix' => [FALSE, 'build/vendor/bin/drush', 'build/vendor/bin/drush'];
  }

  #[DataProvider('dataProviderSiteDb')]
  public function testSiteDb(bool $windows, string $expected_file, string $expected_url): void {
    $this->mockWindows($windows);
    chdir(self::$sut);
    $cwd = File::cwd();

    $this->assertSame(str_replace('<cwd>', $cwd, $expected_file), site_db_file('my_ext'));
    $this->assertSame(str_replace('<cwd>', $cwd, $expected_url), site_db_url('my_ext'));
  }

  public static function dataProviderSiteDb(): \Iterator {
    yield 'windows' => [TRUE, '<cwd>/build/site_my_ext.sqlite', 'sqlite://localhost/../site_my_ext.sqlite'];
    yield 'posix' => [FALSE, '/tmp/site_my_ext.sqlite', 'sqlite://localhost//tmp/site_my_ext.sqlite'];
  }

  #[DataProvider('dataProviderClientHost')]
  public function testClientHost(string $host, string $expected): void {
    $this->assertSame($expected, client_host($host));
  }

  public static function dataProviderClientHost(): \Iterator {
    yield 'IPv4 wildcard' => ['0.0.0.0', '127.0.0.1'];
    yield 'IPv6 wildcard' => ['::', '[::1]'];
    yield 'bracketed IPv6 wildcard' => ['[::]', '[::1]'];
    yield 'name' => ['localhost', 'localhost'];
    yield 'address' => ['127.0.0.1', '127.0.0.1'];
  }

  #[DataProvider('dataProviderIsAbsolutePath')]
  public function testIsAbsolutePath(bool $windows, string $path, bool $expected): void {
    $this->mockWindows($windows);

    $this->assertSame($expected, is_absolute_path($path));
  }

  public static function dataProviderIsAbsolutePath(): \Iterator {
    yield 'posix, absolute' => [FALSE, '/www/build/web', TRUE];
    yield 'posix, relative' => [FALSE, 'build/web', FALSE];
    yield 'posix, drive letter' => [FALSE, 'C:/www/build/web', TRUE];
    yield 'windows, drive letter with a backslash' => [TRUE, 'C:\\www\\build\\web', TRUE];
    yield 'windows, drive letter with a slash' => [TRUE, 'd:/www/build/web', TRUE];
    yield 'windows, root' => [TRUE, '/www/build/web', TRUE];
    yield 'windows, relative' => [TRUE, 'build\\web', FALSE];
    yield 'windows, drive-relative' => [TRUE, 'C:build', FALSE];
  }

  public function testRunInBackground(): void {
    $this->mockPassthru(['cmd' => "nohup php -S 'localhost':'8000' >'.logs/php.log' 2>&1 &", 'result_code' => 0]);

    run_in_background("php -S 'localhost':'8000'", '.logs/php.log');
  }

  public function testRunInBackgroundOnWindows(): void {
    $this->mockWindows();
    $this->mockFunction('getcwd', static fn(): string => "C:\\Users\\O'Brien\\project");
    $commands = [];
    $this->mockFunction('exec', function (string $command) use (&$commands): string {
      $commands[] = $command;

      return '';
    });

    run_in_background('"C:\\php\\php.exe" -S "localhost":"8000"', '.logs/php.log');

    $prefix = 'powershell -NoProfile -NonInteractive -EncodedCommand ';
    $this->assertCount(1, $commands);
    $this->assertStringStartsWith($prefix, $commands[0]);
    $this->assertStringEndsWith(' 2>NUL', $commands[0]);

    $encoded = substr($commands[0], strlen($prefix), -strlen(' 2>NUL'));
    $script = (string) iconv('UTF-16LE', 'UTF-8', (string) base64_decode($encoded, TRUE));

    // A single quote in a value is doubled inside the PowerShell string.
    $command_line = 'cmd.exe /c ""C:\\php\\php.exe" -S "localhost":"8000" >".logs/php.log" 2>&1"';
    $this->assertSame("exit (Invoke-CimMethod -ClassName Win32_Process -MethodName Create -Arguments @{CommandLine = '" . $command_line . "'; CurrentDirectory = 'C:\\Users\\O''Brien\\project'}).ReturnValue", $script);
  }

  public function testProcessCommandOnWindows(): void {
    $this->mockWindows();
    $this->mockShellExecOutputs(['powershell -NoProfile -NonInteractive -Command "(Get-CimInstance Win32_Process -Filter \'ProcessId=1234\').CommandLine" 2>NUL' => "php  -S \"localhost\":\"8000\"\r\n"]);

    $this->assertSame('php  -S "localhost":"8000"', process_command(1234));
  }

  public function testProcessCwdOnWindows(): void {
    $this->mockWindows();
    $this->mockFunction('shell_exec', static fn(string $command): never => throw new \RuntimeException('No command runs on Windows: ' . $command));

    $this->assertSame('', process_cwd(1234));
  }

  /**
   * @param array<int, int> $pids
   *   The process IDs to kill.
   */
  #[DataProvider('dataProviderKillProcessesOnWindows')]
  public function testKillProcessesOnWindows(array $pids, string $expected): void {
    $this->mockWindows();
    $this->mockPassthru(['cmd' => $expected, 'result_code' => 0]);

    kill_processes($pids);
  }

  public static function dataProviderKillProcessesOnWindows(): \Iterator {
    yield '1 process' => [[1234], 'taskkill /F /PID 1234 >NUL 2>&1'];
    yield 'several processes, skipping IDs below 1' => [[0, 1234, -5, 5678], 'taskkill /F /PID 1234 /PID 5678 >NUL 2>&1'];
  }

  /**
   * @param array<int, int> $expected
   *   The process IDs.
   */
  #[DataProvider('dataProviderPortListenerPidsOnWindows')]
  public function testPortListenerPidsOnWindows(string $netstat, array $expected): void {
    $this->mockWindows();
    $this->mockShellExecOutputs(['netstat -ano 2>NUL' => $netstat]);

    $this->assertSame($expected, port_listener_pids('8000'));
  }

  public static function dataProviderPortListenerPidsOnWindows(): \Iterator {
    $header = "\r\nActive Connections\r\n\r\n  Proto  Local Address          Foreign Address        State           PID\r\n";

    yield 'IPv4 and IPv6 listeners of 1 process' => [$header . "  TCP    0.0.0.0:8000           0.0.0.0:0              LISTENING       1234\r\n  TCP    [::]:8000              [::]:0                 LISTENING       1234\r\n", [1234]];
    yield 'other states, ports and protocols' => [$header . "  TCP    127.0.0.1:8000         127.0.0.1:50000        ESTABLISHED     4321\r\n  TCP    0.0.0.0:18000          0.0.0.0:0              LISTENING       1111\r\n  UDP    0.0.0.0:8000           *:*                                    2222\r\n  TCP    127.0.0.1:8000         0.0.0.0:0              LISTENING       5678\r\n", [5678]];
    yield 'a process ID below 1' => [$header . "  TCP    0.0.0.0:8000           0.0.0.0:0              LISTENING       0\r\n", []];
    yield 'no output' => ['', []];
  }

  public function testPortListenerPidsSkipsDuplicates(): void {
    $this->mockShellExecOutputs([sprintf('lsof -ti tcp:%s -sTCP:LISTEN 2>/dev/null', escapeshellarg('8000')) => "1234\n1234\n"]);

    $this->assertSame([1234], port_listener_pids('8000'));
  }

  #[DataProvider('dataProviderWebserverServesOnWindows')]
  public function testWebserverServesOnWindows(string $docroot_argument, bool $expected): void {
    $this->mockWindows();
    // A space in the docroot keeps the quoted argument whole.
    $docroot = File::mkdir(self::$tmp . '/my www/build/web');
    $command = sprintf('php  -S "localhost":"8000" -t %s "%s/.ht.router.php"', str_replace('<docroot>', $docroot, $docroot_argument), $docroot);

    $this->assertSame(trim(str_replace('<docroot>', $docroot, $docroot_argument), '"'), webserver_docroot_argument($command));
    $this->assertSame($expected, webserver_serves($command, $docroot, 1234));
  }

  public static function dataProviderWebserverServesOnWindows(): \Iterator {
    yield 'quoted absolute docroot' => ['"<docroot>"', TRUE];
    yield 'quoted other docroot' => ['"<docroot>/other"', FALSE];
    yield 'relative docroot, which has no working directory to resolve against' => ['"build/web"', FALSE];
  }

  #[DataProvider('dataProviderWebdriverListenersOnWindows')]
  public function testWebdriverListenersOnWindows(string $command, bool $expected_own): void {
    $this->mockWindows();
    $this->mockShellExecOutputs([
      'netstat -ano 2>NUL' => "  TCP    127.0.0.1:4444         0.0.0.0:0              LISTENING       1234\r\n",
      'powershell -NoProfile -NonInteractive -Command "(Get-CimInstance Win32_Process -Filter \'ProcessId=1234\').CommandLine" 2>NUL' => $command . "\r\n",
    ]);

    $this->assertSame($expected_own ? ['own' => [1234 => $command], 'other' => []] : ['own' => [], 'other' => [1234 => $command]], webdriver_listeners('4444', self::PROJECT_ROOT));
  }

  public static function dataProviderWebdriverListenersOnWindows(): \Iterator {
    yield 'quoted path with an extension' => ['"C:\\project\\.chromedriver\\chromedriver.exe" --port="4444"', TRUE];
    yield 'quoted path with a space' => ['"C:\\Program Files\\WebDriver\\chromedriver.exe" --port=4444', TRUE];
    yield 'unquoted path' => ['C:\\SeleniumWebDrivers\\ChromeDriver\\chromedriver.exe --port=4444', TRUE];
    yield 'another process' => ['"C:\\tools\\php\\php.exe" -S localhost:4444', FALSE];
  }

}
