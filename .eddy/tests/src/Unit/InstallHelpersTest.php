<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use DrevOps\Eddy\Tests\Traits\GitTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

use function add_dependent_tools;
use function command_wrapper_text;
use function committed_workflow_branches;
use function convert_string;
use function core_version_requirement;
use function cspell_text_words;
use function drupal_version_build_default;
use function drupal_version_default;
use function drupal_version_options;
use function drupal_version_specs;
use function eddy_skills;
use function get_files;
use function github_repository_from_url;
use function gitignore_rules;
use function is_binary_file;
use function is_gitignored;
use function is_template_path;
use function join_list;
use function normalize_cspell_words;
use function origin_github_repository;
use function parse_branch_list;
use function parse_github_repository;
use function phpunit_config_comment;
use function print_help;
use function process;
use function process_command_wrapper_text;
use function process_drupal_version_text;
use function process_extension_type_text;
use function read_composer_dev_json;
use function remove_cspell_eddy_paths;
use function remove_dir;
use function remove_drupal_versions;
use function remove_lines;
use function remove_npm;
use function remove_special_comments;
use function remove_string_content;
use function remove_tokens_with_content;
use function remove_unused_cspell_words;
use function replace_string_content;
use function set_composer_dev_eddy_value;
use function set_workflow_branches;
use function sort_drupal_majors;
use function template_version;
use function tool_cspell_words;
use function tool_specs;
use function uncomment_line;
use function workflow_push_branches;

/**
 * Unit tests for helper functions in install.php.
 */
#[Group('p0')]
final class InstallHelpersTest extends UnitTestCase {

  use GitTrait;

  protected string $originalCwd;

  public static function setUpBeforeClass(): void {
    putenv('SCRIPT_RUN_SKIP=1');
    require_once dirname(__DIR__, 4) . '/install.php';
    parent::setUpBeforeClass();
  }

  protected function setUp(): void {
    parent::setUp();
    $this->originalCwd = getcwd() ?: '/';
  }

  protected function tearDown(): void {
    chdir($this->originalCwd);
    parent::tearDown();
  }

  #[DataProvider('dataProviderConvertString')]
  public function testConvertString(string $input, string $type, string $expected): void {
    $this->assertSame($expected, convert_string($input, $type));
  }

  public static function dataProviderConvertString(): \Iterator {
    $input = 'I am a_string-With spaces 13';

    yield 'file_name' => [$input, 'file_name', 'i_am_a_string-with_spaces_13'];
    yield 'route_path' => [$input, 'route_path', 'i_am_a_string-with_spaces_13'];
    yield 'deployment_id' => [$input, 'deployment_id', 'i_am_a_string-with_spaces_13'];
    yield 'function_name' => [$input, 'function_name', 'i_am_a_string-with_spaces_13'];
    yield 'ui_id' => [$input, 'ui_id', 'i_am_a_string-with_spaces_13'];
    yield 'cli_command' => [$input, 'cli_command', 'i_am_a_string-with_spaces_13'];
    yield 'domain_name' => [$input, 'domain_name', 'i_am_a_stringwith_spaces_13'];
    yield 'package_namespace' => [$input, 'package_namespace', 'i_am_a_stringwith_spaces_13'];
    yield 'namespace' => [$input, 'namespace', 'IAmAStringWithSpaces13'];
    yield 'class_name' => [$input, 'class_name', 'IAmAStringWithSpaces13'];
    yield 'package_name' => [$input, 'package_name', 'i-am-a_string-with-spaces-13'];
    yield 'log_entry' => [$input, 'log_entry', $input];
    yield 'code_comment_title' => [$input, 'code_comment_title', $input];
  }

  public function testConvertStringInvalidType(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid conversion type: dummy_type');
    convert_string('test', 'dummy_type');
  }

  #[DataProvider('dataProviderIsBinaryFile')]
  public function testIsBinaryFile(string $content, bool $expected): void {
    $file = self::$sut . '/test_file';
    file_put_contents($file, $content);
    $this->assertSame($expected, is_binary_file($file));
  }

  public static function dataProviderIsBinaryFile(): \Iterator {
    yield 'text content' => ['Hello World', FALSE];
    yield 'empty file' => ['', FALSE];
    yield 'binary with null byte' => ["Hello\0World", TRUE];
    yield 'only null bytes' => ["\0\0\0", TRUE];
    yield 'multiline text' => ["line1\nline2\nline3", FALSE];
    yield 'binary at start' => ["\0text after null", TRUE];
    yield 'php content' => ['<?php echo "hello"; ?>', FALSE];
  }

  public function testIsBinaryFileMissing(): void {
    // The only caller, 'get_files()', passes files it has just found, so the
    // defensive 'fopen() === FALSE' branch is unreachable from production
    // code. 'fopen()' is allowed to warn, so suppress 'E_WARNING' to keep
    // PHPUnit's 'failOnWarning' from aborting the test.
    set_error_handler(static fn(): bool => TRUE, E_WARNING);
    try {
      $this->assertTrue(is_binary_file(self::$sut . '/nonexistent_file'));
    }
    finally {
      restore_error_handler();
    }
  }

  public function testPrintHelp(): void {
    ob_start();
    print_help();
    $output = (string) ob_get_clean();

    $this->assertStringContainsString('Eddy - project installation.', $output);
    $this->assertStringContainsString('Usage:', $output);
    $this->assertStringContainsString('install.php', $output);
    $this->assertStringContainsString('--help', $output);
    $this->assertStringContainsString('INSTALL_NAME', $output);
    $this->assertStringContainsString('INSTALL_MACHINE_NAME', $output);
    $this->assertStringContainsString('INSTALL_TYPE', $output);
    $this->assertStringContainsString('INSTALL_DRUPAL_VERSION', $output);
    $this->assertStringContainsString('INSTALL_COMMAND_WRAPPER', $output);
    $this->assertStringContainsString('INSTALL_TOOLS', $output);
    $this->assertStringContainsString('INSTALL_EXAMPLES', $output);
    $this->assertStringContainsString('INSTALL_REMOVE_SELF', $output);
    $this->assertStringContainsString('INSTALL_PROCEED', $output);
    $this->assertStringContainsString('INSTALL_BRANCHES', $output);
    $this->assertStringContainsString('INSTALL_GITHUB_REPOSITORY', $output);
  }

  /**
   * The Drupal version prompt starts with the newest stable major checked.
   *
   * The loop mirrors how the multiselect decides which options render checked.
   * Each option key is compared strictly against the default list, both
   * normalised to strings, so the assertion holds without driving the widget.
   */
  public function testDrupalVersionDefault(): void {
    $default = drupal_version_default();

    $checked = [];
    foreach (array_keys(drupal_version_options()) as $key) {
      $checked[(string) $key] = in_array((string) $key, $default, TRUE);
    }

    $this->assertSame(['10' => FALSE, '11' => TRUE, '12' => FALSE], $checked);
  }

  /**
   * The shipped build default is the highest pre-selected major.
   *
   * The scaffold then builds what a project installed with the default
   * selection builds.
   */
  public function testDrupalVersionShippedBuildDefault(): void {
    $shipped_default = (string) max(array_map(intval(...), drupal_version_default()));

    $config = json_decode((string) file_get_contents(dirname(__DIR__, 4) . '/composer.dev.json'), TRUE);
    $this->assertIsArray($config);
    /** @var array{extra?: array{eddy?: array{'drupal-version'?: string}}} $config */

    $this->assertSame($shipped_default, $config['extra']['eddy']['drupal-version'] ?? NULL);
  }

  /**
   * @param non-empty-array<int|string> $majors
   */
  #[DataProvider('dataProviderDrupalVersionBuildDefault')]
  public function testDrupalVersionBuildDefault(array $majors, string $expected): void {
    $this->assertSame($expected, drupal_version_build_default($majors));
  }

  public static function dataProviderDrupalVersionBuildDefault(): \Iterator {
    yield 'newest stable major' => [['11'], '11'];
    yield 'older major' => [['10'], '10'];
    yield 'highest stable major' => [['10', '11'], '11'];
    yield 'pre-release major next to a stable one' => [['11', '12'], '11'];
    yield 'every major' => [['10', '11', '12'], '11'];
    yield 'pre-release major next to an older one' => [['10', '12'], '10'];
    yield 'pre-release major alone' => [['12'], '12'];
    yield 'lowest of the pre-release majors' => [['13', '12'], '12'];
    yield 'unordered selection' => [['12', '10', '11'], '11'];
    yield 'integers' => [[12, 11], '11'];
  }

  /**
   * @param array<int|string> $majors
   * @param array<int, string> $expected
   */
  #[DataProvider('dataProviderSortDrupalMajors')]
  public function testSortDrupalMajors(array $majors, array $expected): void {
    $this->assertSame($expected, sort_drupal_majors($majors));
  }

  public static function dataProviderSortDrupalMajors(): \Iterator {
    yield 'none' => [[], []];
    yield 'sorted' => [['10', '11'], ['10', '11']];
    yield 'numeric order' => [['12', '9', '10'], ['9', '10', '12']];
    yield 'duplicates' => [['11', '10', '11'], ['10', '11']];
    yield 'integers' => [[11, 10], ['10', '11']];
  }

  /**
   * @param array<int|string> $majors
   */
  #[DataProvider('dataProviderCoreVersionRequirement')]
  public function testCoreVersionRequirement(array $majors, string $expected): void {
    $this->assertSame($expected, core_version_requirement($majors));
  }

  public static function dataProviderCoreVersionRequirement(): \Iterator {
    yield '1 major' => [['11'], 'core_version_requirement: ^11'];
    yield '2 majors' => [['10', '11'], 'core_version_requirement: ^10 || ^11'];
    yield '3 majors' => [['10', '11', '12'], 'core_version_requirement: ^10 || ^11 || ^12'];
    yield 'unordered' => [['12', '10'], 'core_version_requirement: ^10 || ^12'];
  }

  /**
   * @param array<int|string> $majors
   */
  #[DataProvider('dataProviderPhpunitConfigComment')]
  public function testPhpunitConfigComment(array $majors, string $expected): void {
    $this->assertSame($expected, phpunit_config_comment($majors));
  }

  public static function dataProviderPhpunitConfigComment(): \Iterator {
    yield 'no major' => [[], '<!-- PHPUnit configuration. -->'];
    yield '1 major' => [['11'], '<!-- Drupal 11 PHPUnit configuration. -->'];
    yield '2 majors' => [['11', '12'], '<!-- Drupal 11 and 12 PHPUnit configuration. -->'];
    yield '3 majors' => [['10', '11', '12'], '<!-- Drupal 10, 11 and 12 PHPUnit configuration. -->'];
    yield 'unordered' => [['12', '11'], '<!-- Drupal 11 and 12 PHPUnit configuration. -->'];
  }

  /**
   * @param array<string> $majors
   * @param array<string, string> $files
   * @param array<string, string> $expected
   */
  #[DataProvider('dataProviderProcessDrupalVersionText')]
  public function testProcessDrupalVersionText(array $majors, array $files, array $expected): void {
    chdir(self::$sut);

    foreach ($files as $name => $content) {
      file_put_contents(self::$sut . '/' . $name, $content);
    }

    process_drupal_version_text($majors);

    foreach ($expected as $name => $content) {
      $this->assertSame($content, file_get_contents(self::$sut . '/' . $name), $name . ' content mismatch.');
    }
  }

  public static function dataProviderProcessDrupalVersionText(): \Iterator {
    $info = static fn(string $constraint): string => "name: Test\ncore_version_requirement: " . $constraint . "\n";
    $phpunit = static fn(string $comment): string => "<?xml version=\"1.0\"?>\n" . $comment . "\n<phpunit/>\n";
    $template = [
      'test.info.yml' => $info('^10 || ^11 || ^12'),
      'phpunit.xml' => $phpunit('<!-- Drupal 11 and 12 PHPUnit configuration. -->'),
      'phpunit.d10.xml' => $phpunit('<!-- Drupal 10 PHPUnit configuration. -->'),
    ];

    yield 'every major' => [['10', '11', '12'], $template, $template];

    yield 'a major with a configuration of its own' => [
      ['10', '11'],
      $template,
      [
        'test.info.yml' => $info('^10 || ^11'),
        'phpunit.xml' => $phpunit('<!-- Drupal 11 PHPUnit configuration. -->'),
        'phpunit.d10.xml' => $phpunit('<!-- Drupal 10 PHPUnit configuration. -->'),
      ],
    ];

    yield 'only a major with a configuration of its own' => [
      ['10'],
      $template,
      [
        'test.info.yml' => $info('^10'),
        'phpunit.xml' => $phpunit('<!-- PHPUnit configuration. -->'),
      ],
    ];

    yield 'no configuration of their own' => [
      ['10', '12'],
      [
        'test.info.yml' => $info('^10 || ^11 || ^12'),
        'phpunit.xml' => $phpunit('<!-- Drupal 10, 11 and 12 PHPUnit configuration. -->'),
      ],
      [
        'test.info.yml' => $info('^10 || ^12'),
        'phpunit.xml' => $phpunit('<!-- Drupal 10 and 12 PHPUnit configuration. -->'),
      ],
    ];

    yield 'unordered selection' => [
      ['12', '11'],
      $template,
      [
        'test.info.yml' => $info('^11 || ^12'),
        'phpunit.xml' => $phpunit('<!-- Drupal 11 and 12 PHPUnit configuration. -->'),
      ],
    ];

    yield 'text for other majors stays' => [
      ['11'],
      ['test.info.yml' => $info('^10 || ^11'), 'phpunit.xml' => $phpunit('<!-- Drupal 11 PHPUnit configuration. -->')],
      ['test.info.yml' => $info('^10 || ^11'), 'phpunit.xml' => $phpunit('<!-- Drupal 11 PHPUnit configuration. -->')],
    ];
  }

  /**
   * @param array<string> $majors
   * @param array<string, string> $files
   * @param array<string, string|null> $expected
   */
  #[DataProvider('dataProviderRemoveDrupalVersions')]
  public function testRemoveDrupalVersions(array $majors, array $files, array $expected): void {
    chdir(self::$sut);

    foreach ($files as $name => $content) {
      file_put_contents(self::$sut . '/' . $name, $content);
    }

    remove_drupal_versions($majors);

    foreach ($expected as $name => $content) {
      $path = self::$sut . '/' . $name;

      if ($content === NULL) {
        $this->assertFileDoesNotExist($path);

        continue;
      }

      $this->assertSame($content, file_get_contents($path), $name . ' content mismatch.');
    }
  }

  public static function dataProviderRemoveDrupalVersions(): \Iterator {
    $workflow = static fn(string ...$majors): string => "jobs:\n" . implode('', array_map(static fn(string $major): string => '#;< DRUPAL_' . $major . "\n  d" . $major . ": {}\n#;> DRUPAL_" . $major . "\n", $majors));
    $contributing = static fn(string $sentence): string => 'The `phpunit.xml` file configures PHPUnit to run the tests' . $sentence . " Drupal core's bootstrap file.\n";
    $template = [
      'test.yml' => $workflow('10', '11'),
      '.gitattributes' => "# phpunit.d10.xml    export-ignore\n# phpunit.xml        export-ignore\n",
      'CONTRIBUTING.md' => $contributing(', and `phpunit.d10.xml` replaces it in a Drupal 10 build. Both use'),
      'phpunit.d10.xml' => "<phpunit/>\n",
    ];
    $without_d10 = [
      '.gitattributes' => "# phpunit.xml        export-ignore\n",
      'CONTRIBUTING.md' => $contributing('. It uses'),
      'phpunit.d10.xml' => NULL,
    ];

    yield 'no major' => [[], $template, $template];
    yield 'a major with a specification' => [['10'], $template, ['test.yml' => $workflow('11')] + $without_d10];
    yield 'a major without a specification' => [['11'], $template, ['test.yml' => $workflow('10')] + $template];
    yield 'every major' => [['10', '11'], $template, ['test.yml' => $workflow()] + $without_d10];
  }

  /**
   * Every major in 'drupal_version_specs()' is supported, and every file it
   * lists is in the template.
   */
  public function testDrupalVersionSpecsNameTemplateFiles(): void {
    $supported = array_keys(drupal_version_options());

    foreach (drupal_version_specs() as $major => $spec) {
      $this->assertContains($major, $supported, sprintf('Drupal %s has a specification but is not a supported major.', $major));

      foreach ($spec['files'] as $file) {
        $this->assertFileExists(dirname(__DIR__, 4) . '/' . $file, sprintf('Drupal %s lists "%s", which the template does not have.', $major, $file));
      }
    }
  }

  /**
   * @param array<string> $command_wrapper
   */
  #[DataProvider('dataProviderCommandWrapperText')]
  public function testCommandWrapperText(array $command_wrapper, string $command, string $expected): void {
    $this->assertSame($expected, command_wrapper_text($command_wrapper, $command));
  }

  public static function dataProviderCommandWrapperText(): \Iterator {
    yield 'both wrappers' => [['ahoy', 'makefile'], 'build', '`make build` or `ahoy build`'];
    yield 'both wrappers, selected in the other order' => [['makefile', 'ahoy'], 'build', '`make build` or `ahoy build`'];
    yield 'ahoy' => [['ahoy'], 'lint', '`ahoy lint`'];
    yield 'makefile' => [['makefile'], 'test', '`make test`'];
    yield 'binaries' => [['ahoy', 'makefile'], '', '`make` or `ahoy`'];
    yield 'no wrapper' => [[], 'build', ''];
    yield 'unknown wrapper' => [['unknown'], 'build', ''];
  }

  /**
   * @param array<string> $command_wrapper
   */
  #[DataProvider('dataProviderProcessCommandWrapperText')]
  public function testProcessCommandWrapperText(array $command_wrapper, string $expected): void {
    chdir(self::$sut);
    file_put_contents(self::$sut . '/AGENTS.md', self::wrapperText('`make` or `ahoy`', '`make build` or `ahoy build`', '`make lint` or `ahoy lint`', '`make test` or `ahoy test`', 'The command wrappers and CI run it'));

    process_command_wrapper_text($command_wrapper);

    $this->assertSame($expected, file_get_contents(self::$sut . '/AGENTS.md'));
  }

  public static function dataProviderProcessCommandWrapperText(): \Iterator {
    $template = self::wrapperText('`make` or `ahoy`', '`make build` or `ahoy build`', '`make lint` or `ahoy lint`', '`make test` or `ahoy test`', 'The command wrappers and CI run it');

    yield 'both wrappers' => [['ahoy', 'makefile'], $template];
    yield 'ahoy' => [['ahoy'], self::wrapperText('`ahoy`', '`ahoy build`', '`ahoy lint`', '`ahoy test`', 'The command wrappers and CI run it')];
    yield 'makefile' => [['makefile'], self::wrapperText('`make`', '`make build`', '`make lint`', '`make test`', 'The command wrappers and CI run it')];
    yield 'no wrapper' => [[], self::wrapperText('`make` or `ahoy`', '`make build` or `ahoy build`', '`make lint` or `ahoy lint`', '`make test` or `ahoy test`', 'CI runs it')];
  }

  /**
   * @param array<string> $items
   */
  #[DataProvider('dataProviderJoinList')]
  public function testJoinList(array $items, string $conjunction, string $expected): void {
    $this->assertSame($expected, join_list($items, $conjunction));
  }

  public static function dataProviderJoinList(): \Iterator {
    yield 'no items' => [[], 'and', ''];
    yield '1 item' => [['A'], 'and', 'A'];
    yield '2 items' => [['A', 'B'], 'and', 'A and B'];
    yield '3 items' => [['A', 'B', 'C'], 'and', 'A, B and C'];
    yield 'keyed items' => [[3 => 'A', 7 => 'B'], 'and', 'A and B'];
    yield 'other conjunction' => [['A', 'B', 'C'], 'or', 'A, B or C'];
  }

  #[DataProvider('dataProviderReadComposerDevJson')]
  public function testReadComposerDevJson(?string $contents, ?array $expected): void {
    chdir(self::$sut);

    if ($contents !== NULL) {
      file_put_contents('composer.dev.json', $contents);
    }

    $this->assertSame($expected, read_composer_dev_json());
  }

  public static function dataProviderReadComposerDevJson(): \Iterator {
    yield 'missing file' => [NULL, NULL];
    yield 'not an object' => ['"text"', NULL];
    yield 'empty object' => ['{}', []];
    yield 'object' => ['{"require-dev": {"drupal/coder": "^8"}}', ['require-dev' => ['drupal/coder' => '^8']]];
  }

  #[DataProvider('dataProviderSetComposerDevEddyValue')]
  public function testSetComposerDevEddyValue(?string $contents, string $key, string $value, ?string $expected): void {
    chdir(self::$sut);

    if ($contents !== NULL) {
      file_put_contents('composer.dev.json', $contents);
    }

    set_composer_dev_eddy_value($key, $value);

    if ($expected === NULL) {
      $this->assertFileDoesNotExist('composer.dev.json');

      return;
    }

    $this->assertSame($expected, file_get_contents('composer.dev.json'));
  }

  public static function dataProviderSetComposerDevEddyValue(): \Iterator {
    yield 'missing file' => [NULL, 'drupal-version', '12', NULL];

    yield 'no extra' => [
      '{"require-dev": {"drupal/coder": "^8"}}',
      'drupal-version',
      '12',
      "{\n    \"require-dev\": {\n        \"drupal/coder\": \"^8\"\n    },\n    \"extra\": {\n        \"eddy\": {\n            \"drupal-version\": \"12\"\n        }\n    }\n}\n",
    ];

    yield 'extra is not a map' => [
      '{"extra": "none"}',
      'drupal-version',
      '12',
      "{\n    \"extra\": {\n        \"eddy\": {\n            \"drupal-version\": \"12\"\n        }\n    }\n}\n",
    ];

    yield 'existing value and an empty patches map' => [
      '{"extra": {"eddy": {"drupal-version": "11"}, "patches": {}}}',
      'drupal-version',
      '12',
      "{\n    \"extra\": {\n        \"eddy\": {\n            \"drupal-version\": \"12\"\n        },\n        \"patches\": {}\n    }\n}\n",
    ];

    yield 'new key after an existing one' => [
      '{"extra": {"eddy": {"drupal-version": "11"}}}',
      'version',
      '5.0.0',
      "{\n    \"extra\": {\n        \"eddy\": {\n            \"drupal-version\": \"11\",\n            \"version\": \"5.0.0\"\n        }\n    }\n}\n",
    ];
  }

  #[DataProvider('dataProviderTemplateVersion')]
  public function testTemplateVersion(string $describe, ?string $expected): void {
    $this->assertSame($expected, template_version($describe));
  }

  public static function dataProviderTemplateVersion(): \Iterator {
    yield 'release tag' => ['5.0.0', '5.0.0'];
    yield 'pre-release tag' => ['5.0.0-alpha1', '5.0.0-alpha1'];
    yield 'commit after a tag' => ['5.0.0-3-g1a2b3c4', '5.0.0-3-g1a2b3c4'];
    yield 'build metadata' => ['5.0.0+build.1', '5.0.0+build.1'];
    yield 'unexpanded placeholder' => ['$Format:%(describe:tags=true)$', NULL];
    yield 'no tag to describe' => ['', NULL];
    yield 'whitespace' => ['5.0.0 ', NULL];
    yield 'slash' => ['release/5.0.0', NULL];
  }

  /**
   * 'git archive' expands the version placeholder and nothing else.
   */
  #[DataProvider('dataProviderTemplateVersionArchive')]
  public function testTemplateVersionArchive(?string $tag, int $commits_after_tag, string $expected_pattern): void {
    $root = dirname(__DIR__, 4);
    $filesystem = new Filesystem();
    $filesystem->copy($root . '/install.php', self::$sut . '/install.php');
    $filesystem->copy($root . '/.gitattributes', self::$sut . '/.gitattributes');

    $this->git(self::$sut, ['init']);
    $this->git(self::$sut, ['config', 'user.name', 'Test User']);
    $this->git(self::$sut, ['config', 'user.email', 'test@example.com']);
    $this->git(self::$sut, ['add', '--all']);
    $this->git(self::$sut, ['commit', '--message', 'Release']);

    if ($tag !== NULL) {
      $this->git(self::$sut, ['tag', $tag]);
    }

    for ($i = 0; $i < $commits_after_tag; $i++) {
      $this->git(self::$sut, ['commit', '--allow-empty', '--message', 'Change']);
    }

    $this->git(self::$sut, ['archive', '--output=archive.tar', 'HEAD', 'install.php']);
    $process = new Process(['tar', '-xOf', 'archive.tar', 'install.php'], self::$sut);
    $process->mustRun();
    $archived = $process->getOutput();

    $source = (string) file_get_contents($root . '/install.php');
    $pattern = "/^const TEMPLATE_VERSION = '(.*)';$/m";

    if (preg_match($pattern, $source, $source_match) !== 1) {
      $this->fail('The source holds no version constant.');
    }

    if (preg_match($pattern, $archived, $archived_match) !== 1) {
      $this->fail('The archive holds no version constant.');
    }

    $this->assertMatchesRegularExpression($expected_pattern, $archived_match[1]);
    $this->assertSame(str_replace($source_match[0], $archived_match[0], $source), $archived, 'The archive changes only the version constant.');
    $this->assertSame($archived_match[1] === '' ? NULL : $archived_match[1], template_version($archived_match[1]));
  }

  public static function dataProviderTemplateVersionArchive(): \Iterator {
    yield 'tagged commit' => ['1.2.3', 0, '/^1\.2\.3$/'];
    yield 'commit after a tag' => ['1.2.3', 2, '/^1\.2\.3-2-g[0-9a-f]+$/'];
    yield 'untagged commit' => [NULL, 0, '/^$/'];
  }

  /**
   * @param array<int, string> $expected
   */
  #[DataProvider('dataProviderWorkflowPushBranches')]
  public function testWorkflowPushBranches(string $content, array $expected): void {
    $this->assertSame($expected, workflow_push_branches($content));
  }

  public static function dataProviderWorkflowPushBranches(): \Iterator {
    yield 'block sequence' => ["on:\n  push:\n    branches:\n      - 1.x\n      - '2.x'\n", ['1.x', "'2.x'"]];
    yield 'block sequence at the indentation of its key' => ["on:\n  push:\n    branches:\n    - 1.x\n    tags:\n    - '*'\n", ['1.x']];
    yield 'flow sequence' => ["on:\n  push:\n    branches: [1.x, \"2.x\"]\n", ['1.x', '"2.x"']];
    yield 'empty flow sequence' => ["on:\n  push:\n    branches: []\n", []];
    yield 'tags and paths around the branches' => ["on:\n  push:\n    tags:\n      - '**'\n    branches:\n      - 1.x\n    paths:\n      - src/**\n", ['1.x']];
    yield 'comments' => ["# Build.\non: # Triggers.\n  push:\n    branches:\n      # The release branch.\n      - 1.x # Current.\n", ['1.x']];
    yield 'quoted on key' => ["\"on\":\n  push:\n    branches:\n      - 1.x\n", ['1.x']];
    yield 'windows line endings' => ["on:\r\n  push:\r\n    branches:\r\n      - 1.x\r\n", ['1.x']];
    yield 'pull request branches only' => ["on:\n  pull_request:\n    branches:\n      - 1.x\n", []];
    yield 'push after pull request' => ["on:\n  pull_request:\n    branches:\n      - 2.x\n  push:\n    branches:\n      - 1.x\n", ['1.x']];
    yield 'push without branches' => ["on:\n  push:\n    tags:\n      - '*'\n", []];
    yield 'branches-ignore' => ["on:\n  push:\n    branches-ignore:\n      - 1.x\n", []];
    yield 'push as a list item' => ["on: [push, pull_request]\n", []];
    yield 'branches outside the triggers' => ["on:\n  workflow_dispatch:\njobs:\n  push:\n    branches:\n      - 1.x\n", []];
    yield 'empty' => ['', []];
  }

  /**
   * @param non-empty-array<int, string> $branches
   */
  #[DataProvider('dataProviderSetWorkflowBranches')]
  public function testSetWorkflowBranches(?string $content, array $branches, ?string $expected): void {
    chdir(self::$sut);

    if ($content !== NULL) {
      file_put_contents('workflow.yml', $content);
    }

    set_workflow_branches('workflow.yml', $branches);

    if ($expected === NULL) {
      $this->assertFileDoesNotExist('workflow.yml');

      return;
    }

    $this->assertSame($expected, file_get_contents('workflow.yml'));
  }

  public static function dataProviderSetWorkflowBranches(): \Iterator {
    yield 'missing file' => [NULL, ['1.x'], NULL];

    yield 'every trigger' => [
      "on:\n  push:\n    branches:\n      - main\n  pull_request:\n    branches:\n      - main\n",
      ['1.x'],
      "on:\n  push:\n    branches:\n      - 1.x\n  pull_request:\n    branches:\n      - 1.x\n",
    ];

    yield 'several branches' => [
      "on:\n  push:\n    branches:\n      - main\n",
      ['1.x', "'2.x'"],
      "on:\n  push:\n    branches:\n      - 1.x\n      - '2.x'\n",
    ];

    yield 'other items' => [
      "on:\n  push:\n    branches:\n      - main\n      - mainline\n    tags:\n      - main-*\n",
      ['1.x'],
      "on:\n  push:\n    branches:\n      - 1.x\n      - mainline\n    tags:\n      - main-*\n",
    ];

    yield 'windows line endings' => [
      "on:\r\n  push:\r\n    branches:\r\n      - main\r\n",
      ['1.x', '2.x'],
      "on:\r\n  push:\r\n    branches:\r\n      - 1.x\r\n      - 2.x\r\n",
    ];
  }

  /**
   * @param array<string, string>|null $committed
   * @param array<int, string> $expected
   */
  #[DataProvider('dataProviderCommittedWorkflowBranches')]
  public function testCommittedWorkflowBranches(?array $committed, string $directory, array $expected): void {
    $filesystem = new Filesystem();

    if ($committed !== NULL) {
      $this->git(self::$sut, ['init']);
      $this->git(self::$sut, ['config', 'user.name', 'Test User']);
      $this->git(self::$sut, ['config', 'user.email', 'test@example.com']);
      $filesystem->dumpFile(self::$sut . '/README.md', 'Project');

      foreach ($committed as $path => $content) {
        $filesystem->dumpFile(self::$sut . '/' . $path, $content);
      }

      $this->git(self::$sut, ['add', '--all']);
      $this->git(self::$sut, ['commit', '--message', 'Initial commit']);
    }

    // The working tree holds the template's copy, which the function ignores.
    $filesystem->dumpFile(self::$sut . '/' . $directory . '/.github/workflows/test.yml', "on:\n  push:\n    branches:\n      - main\n");
    chdir(self::$sut . '/' . $directory);

    $this->assertSame($expected, committed_workflow_branches('.github/workflows/test.yml'));
  }

  public static function dataProviderCommittedWorkflowBranches(): \Iterator {
    $project = "on:\n  push:\n    branches:\n      - 2.x\n";
    $template = "on:\n  push:\n    branches:\n      - main\njobs:\n  test:\n    #;< DRUPAL_11\n    name: test\n    #;> DRUPAL_11\n";

    yield 'no repository' => [NULL, '', []];
    yield 'no committed workflow' => [[], '', []];
    yield 'committed template copy' => [['.github/workflows/test.yml' => $template], '', []];
    yield 'committed project copy' => [['.github/workflows/test.yml' => $project], '', ['2.x']];

    yield 'project in a subdirectory of the repository' => [
      ['.github/workflows/test.yml' => "on:\n  push:\n    branches:\n      - 3.x\n", 'extension/.github/workflows/test.yml' => $project],
      'extension',
      ['2.x'],
    ];
  }

  /**
   * @param array<int, string> $expected
   */
  #[DataProvider('dataProviderParseBranchList')]
  public function testParseBranchList(string $list, array $expected): void {
    $this->assertSame($expected, parse_branch_list($list));
  }

  public static function dataProviderParseBranchList(): \Iterator {
    yield 'empty' => ['', []];
    yield 'single branch' => ['1.x', ['1.x']];
    yield 'several branches' => ['1.x,2.x', ['1.x', '2.x']];
    yield 'spaces around branches' => [' 1.x , 2.x ', ['1.x', '2.x']];
    yield 'empty items' => [',1.x,,2.x,', ['1.x', '2.x']];
    yield 'only separators' => [' , ', []];
    yield 'quotes kept' => ["'2.x',\"3.x\"", ["'2.x'", '"3.x"']];
    yield 'pattern' => ['releases/**', ['releases/**']];
  }

  #[DataProvider('dataProviderParseBranchListInvalid')]
  public function testParseBranchListInvalid(string $list, string $expected_message): void {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage($expected_message);
    parse_branch_list($list);
  }

  public static function dataProviderParseBranchListInvalid(): \Iterator {
    yield 'space inside a branch' => ['1.x 2.x', 'Invalid branch: "1.x 2.x". Separate branches with commas.'];
    yield 'tab inside a branch' => ["1.x,2.x\t3.x", "Invalid branch: \"2.x\t3.x\"."];
    yield 'new line inside a branch' => ["1.x\n- 2.x", "Invalid branch: \"1.x\n- 2.x\"."];
    yield 'control character' => ["1.x\x1b", "Invalid branch: \"1.x\x1b\"."];
  }

  /**
   * @param array<string, mixed> $package
   * @param array<string> $tools_remove
   * @param array<string, mixed> $expected
   */
  #[DataProvider('dataProviderRemoveNpm')]
  public function testRemoveNpm(array $package, array $tools_remove, array $expected): void {
    chdir(self::$sut);
    file_put_contents(self::$sut . '/package.json', json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

    remove_npm($tools_remove);

    $this->assertSame($expected, json_decode((string) file_get_contents(self::$sut . '/package.json'), TRUE));
  }

  public static function dataProviderRemoveNpm(): \Iterator {
    $package = ['name' => 'test', 'scripts' => ['lint-js' => 'eslint .', 'lint' => 'npm run lint-js', 'test' => 'jest'], 'devDependencies' => ['eslint' => '^8', 'jest' => '^29']];

    yield 'no npm tool' => [$package, ['phpcs'], $package];
    yield 'dependencies left' => [$package, ['eslint'], ['name' => 'test', 'scripts' => ['test' => 'jest'], 'devDependencies' => ['jest' => '^29']]];
    yield 'no dependencies left' => [['name' => 'test', 'scripts' => ['test' => 'jest'], 'devDependencies' => ['eslint' => '^8']], ['eslint'], ['name' => 'test', 'scripts' => ['test' => 'jest']]];
  }

  #[DataProvider('dataProviderParseGithubRepository')]
  public function testParseGithubRepository(string $value, ?string $expected): void {
    $this->assertSame($expected, parse_github_repository($value));
  }

  public static function dataProviderParseGithubRepository(): \Iterator {
    yield 'empty' => ['', NULL];
    yield 'only spaces' => ['  ', NULL];
    yield 'repository' => ['drevops/eddy', 'drevops/eddy'];
    yield 'spaces around the repository' => [' drevops/eddy ', 'drevops/eddy'];
    yield 'mixed case' => ['AlexSkrypnyk/drupal_helpers', 'AlexSkrypnyk/drupal_helpers'];
    yield 'hyphen in the owner' => ['drevops-org/eddy', 'drevops-org/eddy'];
    yield 'dots and hyphens in the name' => ['drevops/eddy.tooling-mirror', 'drevops/eddy.tooling-mirror'];
  }

  #[DataProvider('dataProviderParseGithubRepositoryInvalid')]
  public function testParseGithubRepositoryInvalid(string $value): void {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage(sprintf('Invalid GitHub repository: "%s". Use the owner/name form, such as drevops/eddy.', trim($value)));
    parse_github_repository($value);
  }

  public static function dataProviderParseGithubRepositoryInvalid(): \Iterator {
    yield 'owner only' => ['drevops'];
    yield 'URL' => ['https://github.com/drevops/eddy'];
    yield 'too many parts' => ['drevops/eddy/main'];
    yield 'owner starting with a hyphen' => ['-drevops/eddy'];
    yield 'space inside' => ['drevops/my eddy'];
    yield 'dot as the name' => ['drevops/.'];
    yield 'double dot as the name' => ['drevops/..'];
    yield 'no owner' => ['/eddy'];
  }

  #[DataProvider('dataProviderGithubRepositoryFromUrl')]
  public function testGithubRepositoryFromUrl(string $url, ?string $expected): void {
    $this->assertSame($expected, github_repository_from_url($url));
  }

  public static function dataProviderGithubRepositoryFromUrl(): \Iterator {
    yield 'SSH' => ['git@github.com:drevops/eddy.git', 'drevops/eddy'];
    yield 'SSH without .git' => ['git@github.com:drevops/eddy', 'drevops/eddy'];
    yield 'SSH URL' => ['ssh://git@github.com/drevops/eddy.git', 'drevops/eddy'];
    yield 'HTTPS' => ['https://github.com/drevops/eddy.git', 'drevops/eddy'];
    yield 'HTTPS without .git' => ['https://github.com/drevops/eddy', 'drevops/eddy'];
    yield 'HTTPS with a trailing slash' => ['https://github.com/drevops/eddy/', 'drevops/eddy'];
    yield 'HTTPS with credentials' => ['https://user:token@github.com/drevops/eddy.git', 'drevops/eddy'];
    yield 'git protocol' => ['git://github.com/drevops/eddy.git', 'drevops/eddy'];
    yield 'mixed case' => ['git@github.com:AlexSkrypnyk/drupal_helpers.git', 'AlexSkrypnyk/drupal_helpers'];
    yield 'surrounding whitespace' => [" git@github.com:drevops/eddy.git\n", 'drevops/eddy'];
    yield 'Drupal.org' => ['git@git.drupal.org:project/eddy.git', NULL];
    yield 'GitLab' => ['https://gitlab.com/drevops/eddy.git', NULL];
    yield 'host that ends with github.com' => ['https://notgithub.com/drevops/eddy.git', NULL];
    yield 'host that starts with github.com' => ['https://github.com.example.com/drevops/eddy.git', NULL];
    yield 'owner only' => ['https://github.com/drevops', NULL];
    yield 'path below the repository' => ['https://github.com/drevops/eddy/tree/main', NULL];
    yield 'empty' => ['', NULL];
  }

  /**
   * @param array<int, array<int, string>> $commands
   */
  #[DataProvider('dataProviderOriginGithubRepository')]
  public function testOriginGithubRepository(array $commands, string $directory, ?string $expected): void {
    foreach ($commands as $command) {
      $this->git(self::$sut, $command);
    }

    (new Filesystem())->mkdir(self::$sut . '/' . $directory);
    chdir(self::$sut . '/' . $directory);

    $this->assertSame($expected, origin_github_repository());
  }

  public static function dataProviderOriginGithubRepository(): \Iterator {
    $init = ['init'];
    $origin = static fn(string $url): array => ['remote', 'add', 'origin', $url];

    yield 'no repository' => [[], '', NULL];
    yield 'no origin' => [[$init], '', NULL];
    yield 'GitHub origin' => [[$init, $origin('git@github.com:drevops/eddy.git')], '', 'drevops/eddy'];
    yield 'origin elsewhere' => [[$init, $origin('git@git.drupal.org:project/eddy.git')], '', NULL];
    yield 'other remote only' => [[$init, ['remote', 'add', 'upstream', 'git@github.com:drevops/eddy.git']], '', NULL];
    yield 'directory inside the repository' => [[$init, $origin('git@github.com:drevops/eddy.git')], 'extension', NULL];
  }

  /**
   * @param array<string, string> $files
   * @param array<string, string> $expected
   */
  #[DataProvider('dataProviderProcessExtensionTypeText')]
  public function testProcessExtensionTypeText(string $type, array $files, array $expected): void {
    chdir(self::$sut);

    foreach ($files as $name => $content) {
      file_put_contents(self::$sut . '/' . $name, $content);
    }

    process_extension_type_text($type);

    foreach ($expected as $name => $content) {
      $this->assertSame($content, file_get_contents(self::$sut . '/' . $name), $name . ' content mismatch.');
    }
  }

  public static function dataProviderProcessExtensionTypeText(): \Iterator {
    $contributing = static fn(string $note, string $dir): string => "a test path starts at the extension's symlink in the assembled site" . $note . ":\n\nmake test-unit web/" . $dir . "/custom/your_extension/tests/src/Unit/MyUnitTest.php\n";
    $agents = static fn(string $dirs): string => '- Extension files are symlinked from root into ' . $dirs . "\n";
    $config = "<file>web/modules/custom/your_extension/rector.php</file>\n<file>web/themes/custom/your_extension/rector.php</file>\n";
    $template = [
      'CONTRIBUTING.md' => $contributing(' (`web/themes/custom/` for a theme)', 'modules'),
      'AGENTS.md' => $agents('`build/web/modules/custom/` (module) or `build/web/themes/custom/` (theme)'),
      'phpunit.xml' => $config,
    ];

    yield 'module' => [
      'module',
      $template,
      [
        'CONTRIBUTING.md' => $contributing('', 'modules'),
        'AGENTS.md' => $agents('`build/web/modules/custom/`'),
        'phpunit.xml' => $config,
      ],
    ];

    yield 'theme' => [
      'theme',
      $template,
      [
        'CONTRIBUTING.md' => $contributing('', 'themes'),
        'AGENTS.md' => $agents('`build/web/themes/custom/`'),
        'phpunit.xml' => $config,
      ],
    ];
  }

  #[DataProvider('dataProviderRemoveDir')]
  public function testRemoveDir(callable $setup, string $dir_name): void {
    $dir = self::$sut . '/' . $dir_name;
    $setup($dir);
    $this->assertDirectoryExists($dir);
    remove_dir($dir);
    $this->assertDirectoryDoesNotExist($dir);
  }

  public static function dataProviderRemoveDir(): \Iterator {
    yield 'empty directory' => [
      function (string $dir): void {
        mkdir($dir, 0755, TRUE);
      },
      'empty_dir',
    ];

    yield 'directory with files' => [
      function (string $dir): void {
        mkdir($dir, 0755, TRUE);
        file_put_contents($dir . '/file1.txt', 'content1');
        file_put_contents($dir . '/file2.txt', 'content2');
      },
      'dir_with_files',
    ];

    yield 'nested directories' => [
      function (string $dir): void {
        mkdir($dir . '/sub1/sub2', 0755, TRUE);
        file_put_contents($dir . '/file.txt', 'content');
        file_put_contents($dir . '/sub1/file.txt', 'content');
        file_put_contents($dir . '/sub1/sub2/file.txt', 'content');
      },
      'nested_dir',
    ];
  }

  public function testRemoveDirMissing(): void {
    remove_dir(self::$sut . '/nonexistent');
    $this->addToAssertionCount(1);
  }

  #[DataProvider('dataProviderGetFiles')]
  public function testGetFiles(callable $setup, array $expected_filenames, array $not_expected_filenames = []): void {
    chdir(self::$sut);
    $setup(self::$sut);
    $files = get_files();
    $basenames = array_map(static fn(string $f): string => str_replace(self::$sut . '/', '', $f), $files);

    foreach ($expected_filenames as $expected_filename) {
      $this->assertContains($expected_filename, $basenames, 'Expected file not found: ' . $expected_filename);
    }
    foreach ($not_expected_filenames as $not_expected_filename) {
      $this->assertNotContains($not_expected_filename, $basenames, 'Unexpected file found: ' . $not_expected_filename);
    }
  }

  public static function dataProviderGetFiles(): \Iterator {
    yield 'text files included' => [
      function (string $dir): void {
        file_put_contents($dir . '/file1.txt', 'hello');
        file_put_contents($dir . '/file2.php', '<?php echo 1;');
      },
      ['file1.txt', 'file2.php'],
    ];

    yield 'binary files excluded' => [
      function (string $dir): void {
        file_put_contents($dir . '/text.txt', 'hello');
        file_put_contents($dir . '/binary.bin', "hello\0world");
      },
      ['text.txt'],
      ['binary.bin'],
    ];

    yield 'excluded directories' => [
      function (string $dir): void {
        file_put_contents($dir . '/root.txt', 'root');
        mkdir($dir . '/.git', 0755, TRUE);
        file_put_contents($dir . '/.git/config', 'git config');
        mkdir($dir . '/vendor', 0755, TRUE);
        file_put_contents($dir . '/vendor/autoload.php', 'vendor');
        mkdir($dir . '/node_modules', 0755, TRUE);
        file_put_contents($dir . '/node_modules/pkg.js', 'pkg');
        mkdir($dir . '/.idea', 0755, TRUE);
        file_put_contents($dir . '/.idea/workspace.xml', 'idea');
        mkdir($dir . '/.chromedriver', 0755, TRUE);
        file_put_contents($dir . '/.chromedriver/chromedriver.log', 'log');
      },
      ['root.txt'],
      ['.git/config', 'vendor/autoload.php', 'node_modules/pkg.js', '.idea/workspace.xml', '.chromedriver/chromedriver.log'],
    ];

    yield 'nested text files included' => [
      function (string $dir): void {
        mkdir($dir . '/src/Sub', 0755, TRUE);
        file_put_contents($dir . '/src/file.php', 'php');
        file_put_contents($dir . '/src/Sub/deep.php', 'deep');
      },
      ['src/file.php', 'src/Sub/deep.php'],
    ];

    yield 'nested excluded directories' => [
      function (string $dir): void {
        mkdir($dir . '/tools/vendor', 0755, TRUE);
        file_put_contents($dir . '/tools/vendor/autoload.php', 'vendor');
        mkdir($dir . '/tools/node_modules', 0755, TRUE);
        file_put_contents($dir . '/tools/node_modules/pkg.js', 'pkg');
        file_put_contents($dir . '/tools/run.php', 'run');
      },
      ['tools/run.php'],
      ['tools/vendor/autoload.php', 'tools/node_modules/pkg.js'],
    ];

    yield 'git-ignored paths excluded' => [
      function (string $dir): void {
        file_put_contents($dir . '/.gitignore', "/build\n/.env\n*.log\n");
        mkdir($dir . '/build/web/core/modules/system', 0755, TRUE);
        file_put_contents($dir . '/build/web/core/modules/system/system.info.yml', 'type: module');
        file_put_contents($dir . '/.env', 'WEBSERVER_PORT=8000');
        mkdir($dir . '/src', 0755, TRUE);
        file_put_contents($dir . '/src/debug.log', 'log');
        file_put_contents($dir . '/src/file.php', 'php');
      },
      ['.gitignore', 'src/file.php'],
      ['build/web/core/modules/system/system.info.yml', '.env', 'src/debug.log'],
    ];

    yield 'git-ignored paths re-included' => [
      function (string $dir): void {
        file_put_contents($dir . '/.gitignore', "/logs/*\n!/logs/keep.txt\n/build\n!/build/keep.txt\n");
        mkdir($dir . '/logs', 0755, TRUE);
        file_put_contents($dir . '/logs/keep.txt', 'keep');
        file_put_contents($dir . '/logs/drop.txt', 'drop');
        mkdir($dir . '/build', 0755, TRUE);
        file_put_contents($dir . '/build/keep.txt', 'keep');
      },
      ['logs/keep.txt'],
      ['logs/drop.txt', 'build/keep.txt'],
    ];

    yield 'claude directory holds only its settings' => [
      function (string $dir): void {
        mkdir($dir . '/.claude/skills/project-skill', 0755, TRUE);
        file_put_contents($dir . '/.claude/settings.json', '{}');
        file_put_contents($dir . '/.claude/settings.local.json', '{}');
        file_put_contents($dir . '/.claude/skills/project-skill/SKILL.md', 'skill');
      },
      ['.claude/settings.json'],
      ['.claude/settings.local.json', '.claude/skills/project-skill/SKILL.md'],
    ];
  }

  #[DataProvider('dataProviderIsTemplatePath')]
  public function testIsTemplatePath(string $path, bool $is_dir, bool $expected): void {
    $rules = gitignore_rules("/build\n/.env\n");

    $this->assertSame($expected, is_template_path($path, $is_dir, $rules));
  }

  public static function dataProviderIsTemplatePath(): \Iterator {
    yield 'file' => ['src/Service.php', FALSE, TRUE];
    yield 'directory' => ['src', TRUE, TRUE];
    yield 'git directory' => ['.git', TRUE, FALSE];
    yield 'idea directory' => ['.idea', TRUE, FALSE];
    yield 'vendor directory' => ['vendor', TRUE, FALSE];
    yield 'nested vendor directory' => ['.eddy/tests/vendor', TRUE, FALSE];
    yield 'node_modules directory' => ['node_modules', TRUE, FALSE];
    yield 'chromedriver directory' => ['.chromedriver', TRUE, FALSE];
    yield 'file named like an excluded directory' => ['docs/vendor', FALSE, TRUE];
    yield 'claude directory' => ['.claude', TRUE, TRUE];
    yield 'claude settings' => ['.claude/settings.json', FALSE, TRUE];
    yield 'claude local settings' => ['.claude/settings.local.json', FALSE, FALSE];
    yield 'claude skills' => ['.claude/skills', TRUE, FALSE];
    yield 'claude directory below the root' => ['docs/.claude/notes.md', FALSE, TRUE];
    yield 'git-ignored directory' => ['build', TRUE, FALSE];
    yield 'git-ignored file' => ['.env', FALSE, FALSE];
    yield 'name that is git-ignored only at the root' => ['docs/build', TRUE, TRUE];
  }

  #[DataProvider('dataProviderIsGitignored')]
  public function testIsGitignored(string $gitignore, string $path, bool $is_dir, bool $expected): void {
    $this->assertSame($expected, is_gitignored($path, $is_dir, gitignore_rules($gitignore)));
  }

  public static function dataProviderIsGitignored(): \Iterator {
    yield 'no rules' => ['', 'build', TRUE, FALSE];
    yield 'anchored name' => ['/build', 'build', TRUE, TRUE];
    yield 'anchored name below the root' => ['/build', 'web/build', TRUE, FALSE];
    yield 'name at the root' => ['vendor', 'vendor', TRUE, TRUE];
    yield 'name at any depth' => ['vendor', 'a/b/vendor', TRUE, TRUE];
    yield 'name matches a file' => ['.env', 'a/.env', FALSE, TRUE];
    yield 'directory pattern matches a directory' => ['logs/', 'logs', TRUE, TRUE];
    yield 'directory pattern skips a file' => ['logs/', 'logs', FALSE, FALSE];
    yield 'directory pattern at any depth' => ['logs/', 'a/logs', TRUE, TRUE];
    yield 'slash inside the pattern anchors it' => ['docs/build', 'docs/build', TRUE, TRUE];
    yield 'anchored path below the root' => ['docs/build', 'x/docs/build', TRUE, FALSE];
    yield 'contents of a matched directory are not matched' => ['/build', 'build/file.txt', FALSE, FALSE];
    yield 'wildcard' => ['*.log', 'a/debug.log', FALSE, TRUE];
    yield 'wildcard stays in its segment' => ['/a/*.log', 'a/b/c.log', FALSE, FALSE];
    yield 'wildcard matches a leading dot' => ['*.json', '.eslintrc.json', FALSE, TRUE];
    yield 'question mark' => ['file?.txt', 'file1.txt', FALSE, TRUE];
    yield 'question mark stays in its segment' => ['/a?b', 'a/b', FALSE, FALSE];
    yield 'escaped wildcard' => ['\*.txt', '*.txt', FALSE, TRUE];
    yield 'escaped wildcard is not a wildcard' => ['\*.txt', 'a.txt', FALSE, FALSE];
    yield 'dot is literal' => ['a.txt', 'abtxt', FALSE, FALSE];
    yield 'regular expression characters are literal' => ['a+b(c)|$.txt', 'a+b(c)|$.txt', FALSE, TRUE];
    yield 'hash inside the pattern is literal' => ['a#b', 'a#b', FALSE, TRUE];
    yield 'character class' => ['file[0-9].txt', 'file5.txt', FALSE, TRUE];
    yield 'character class without a match' => ['file[0-9].txt', 'filex.txt', FALSE, FALSE];
    yield 'negated character class' => ['file[!0-9].txt', 'file5.txt', FALSE, FALSE];
    yield 'negated character class with a match' => ['file[!0-9].txt', 'filex.txt', FALSE, TRUE];
    yield 'caret negated character class' => ['file[^0-9].txt', 'filex.txt', FALSE, TRUE];
    yield 'negated character class stays in its segment' => ['/a[!x]b', 'a/b', FALSE, FALSE];
    yield 'closing bracket as the first member' => ['file[]a].txt', 'file].txt', FALSE, TRUE];
    yield 'closing bracket as the first negated member' => ['file[!]a].txt', 'file].txt', FALSE, FALSE];
    yield 'escaped closing bracket in a class' => ['file[a\]].txt', 'file].txt', FALSE, TRUE];
    yield 'literal characters in a class' => ['file[.+].txt', 'file+.txt', FALSE, TRUE];
    yield 'hyphen at the end of a class' => ['file[a-].txt', 'file-.txt', FALSE, TRUE];
    yield 'posix class' => ['file[[:digit:]].txt', 'file7.txt', FALSE, TRUE];
    yield 'posix class without a match' => ['file[[:digit:]].txt', 'filex.txt', FALSE, FALSE];
    yield 'unclosed bracket is literal' => ['file[.txt', 'file[.txt', FALSE, TRUE];
    yield 'leading double asterisk at the root' => ['**/cache', 'cache', TRUE, TRUE];
    yield 'leading double asterisk at any depth' => ['**/cache', 'a/b/cache', TRUE, TRUE];
    yield 'leading double asterisk keeps the rest anchored' => ['**/a/cache', 'x/b/cache', TRUE, FALSE];
    yield 'middle double asterisk without directories' => ['a/**/b', 'a/b', TRUE, TRUE];
    yield 'middle double asterisk with directories' => ['a/**/b', 'a/x/y/b', TRUE, TRUE];
    yield 'trailing double asterisk matches contents' => ['tmp/**', 'tmp/a/file.txt', FALSE, TRUE];
    yield 'trailing double asterisk skips the directory' => ['tmp/**', 'tmp', TRUE, FALSE];
    yield 'other double asterisks match in a segment' => ['foo**bar', 'a/fooxbar', FALSE, TRUE];
    yield 'other double asterisks stay in their segment' => ['/a**b', 'ax/yb', FALSE, FALSE];
    yield 'double asterisk alone' => ['**', 'a/b/c', FALSE, TRUE];
    yield 'negation re-includes' => [".claude/*\n!.claude/settings.json", '.claude/settings.json', FALSE, FALSE];
    yield 'negation keeps the rest ignored' => [".claude/*\n!.claude/settings.json", '.claude/settings.local.json', FALSE, TRUE];
    yield 'last match wins' => ["!build\nbuild", 'build', TRUE, TRUE];
    yield 'comment' => ['#build', '#build', FALSE, FALSE];
    yield 'escaped hash' => ['\#build', '#build', FALSE, TRUE];
    yield 'escaped exclamation mark' => ['\!build', '!build', FALSE, TRUE];
    yield 'trailing spaces' => ['build   ', 'build', TRUE, TRUE];
    yield 'escaped trailing space' => ['build\ ', 'build ', TRUE, TRUE];
    yield 'blank lines and windows line endings' => ["\r\n/build\r\n\r\n", 'build', TRUE, TRUE];
    yield 'lone slash and exclamation mark' => ["/\n!\n", 'build', TRUE, FALSE];
  }

  /**
   * The template's '.gitignore' leaves only template files to the walk.
   */
  #[DataProvider('dataProviderIsGitignoredTemplate')]
  public function testIsGitignoredTemplate(string $path, bool $is_dir, bool $expected): void {
    $rules = gitignore_rules((string) file_get_contents(dirname(__DIR__, 4) . '/.gitignore'));

    $this->assertSame($expected, is_gitignored($path, $is_dir, $rules));
  }

  public static function dataProviderIsGitignoredTemplate(): \Iterator {
    yield 'build' => ['build', TRUE, TRUE];
    yield 'logs' => ['.logs', TRUE, TRUE];
    yield 'vendor' => ['vendor', TRUE, TRUE];
    yield 'node_modules' => ['node_modules', TRUE, TRUE];
    yield 'env' => ['.env', FALSE, TRUE];
    yield 'chromedriver' => ['.chromedriver', TRUE, TRUE];
    yield 'composer lock' => ['composer.lock', FALSE, TRUE];
    yield 'claude local settings' => ['.claude/settings.local.json', FALSE, TRUE];
    yield 'update skill' => ['.claude/skills/update-consumer-eddy', TRUE, TRUE];
    yield 'claude directory' => ['.claude', TRUE, FALSE];
    yield 'claude settings' => ['.claude/settings.json', FALSE, FALSE];
    yield 'claude skills' => ['.claude/skills', TRUE, FALSE];
    yield 'composer manifest' => ['composer.json', FALSE, FALSE];
    yield 'source directory' => ['src', TRUE, FALSE];
    yield 'nested build directory' => ['tests/build', TRUE, FALSE];
  }

  /**
   * Every skill the repository tracks in '.claude/skills' is one of Eddy's.
   */
  public function testEddySkillsListTrackedSkills(): void {
    $tracked = $this->git(dirname(__DIR__, 4), ['ls-files', '--', '.claude/skills']);
    $paths = $tracked === '' ? [] : explode("\n", $tracked);
    $skills = array_values(array_unique(array_map(static fn(string $path): string => explode('/', $path)[2], $paths)));

    $expected = eddy_skills();
    sort($expected);
    sort($skills);

    $this->assertSame($expected, $skills);
  }

  #[DataProvider('dataProviderReplaceStringContent')]
  public function testReplaceStringContent(array $files, string $needle, string $replacement, array $expected_contents): void {
    chdir(self::$sut);
    foreach ($files as $name => $content) {
      $path = self::$sut . '/' . $name;
      $dir = dirname($path);
      if (!is_dir($dir)) {
        mkdir($dir, 0755, TRUE);
      }
      file_put_contents($path, $content);
    }

    replace_string_content($needle, $replacement);

    foreach ($expected_contents as $name => $expected) {
      $this->assertSame($expected, file_get_contents(self::$sut . '/' . $name));
    }
  }

  public static function dataProviderReplaceStringContent(): \Iterator {
    yield 'simple replacement' => [
      ['file.txt' => 'Hello your_extension world'],
      'your_extension',
      'my_module',
      ['file.txt' => 'Hello my_module world'],
    ];

    yield 'multiple occurrences in file' => [
      ['file.txt' => "your_extension line1\nyour_extension line2"],
      'your_extension',
      'my_module',
      ['file.txt' => "my_module line1\nmy_module line2"],
    ];

    yield 'multiple files' => [
      ['a.txt' => 'has your_extension here', 'b.txt' => 'also your_extension here'],
      'your_extension',
      'my_module',
      ['a.txt' => 'has my_module here', 'b.txt' => 'also my_module here'],
    ];

    yield 'no match leaves file unchanged' => [
      ['file.txt' => 'no match here'],
      'your_extension',
      'my_module',
      ['file.txt' => 'no match here'],
    ];
  }

  #[DataProvider('dataProviderRemoveStringContent')]
  public function testRemoveStringContent(string $content, string $token, string $expected): void {
    chdir(self::$sut);
    file_put_contents(self::$sut . '/file.txt', $content);

    remove_string_content($token);

    $this->assertSame($expected, file_get_contents(self::$sut . '/file.txt'));
  }

  public static function dataProviderRemoveStringContent(): \Iterator {
    yield 'remove line starting with token' => [
      "keep this\n# Remove me\nkeep this too",
      '# Remove me',
      "keep this\nkeep this too",
    ];

    yield 'remove multiple matching lines' => [
      "# Remove\nkeep\n# Remove again",
      '# Remove',
      'keep',
    ];

    yield 'no match leaves content unchanged' => [
      "line1\nline2\nline3",
      'no_match',
      "line1\nline2\nline3",
    ];

    yield 'remove comment instruction line' => [
      "# Uncomment the lines below in your project.\n.ahoy.yml export-ignore\n.circleci export-ignore",
      '# Uncomment the lines below in your project.',
      ".ahoy.yml export-ignore\n.circleci export-ignore",
    ];

    yield 'remove lines between blank lines' => [
      "keep\n\n# Remove me\n# Remove me too\n\nkeep this too\n",
      '# Remove me',
      "keep\n\nkeep this too\n",
    ];

    yield 'remove lines at end of file after a blank line' => [
      "keep\n\n# Remove me\n",
      '# Remove me',
      "keep\n",
    ];
  }

  #[DataProvider('dataProviderRemoveTokensWithContent')]
  public function testRemoveTokensWithContent(string $content, string $token, string $expected): void {
    chdir(self::$sut);
    file_put_contents(self::$sut . '/file.txt', $content);

    remove_tokens_with_content($token);

    $this->assertSame($expected, file_get_contents(self::$sut . '/file.txt'));
  }

  public static function dataProviderRemoveTokensWithContent(): \Iterator {
    yield 'remove single block' => [
      "before\n#;< META\nremove this\n#;> META\nafter",
      'META',
      "before\nafter",
    ];

    yield 'remove multiple blocks' => [
      "before\n#;< META\nblock1\n#;> META\nmiddle\n#;< META\nblock2\n#;> META\nafter",
      'META',
      "before\nmiddle\nafter",
    ];

    yield 'no markers leaves content unchanged' => [
      "line1\nline2\nline3",
      'META',
      "line1\nline2\nline3",
    ];

    yield 'nested content removed' => [
      "keep\n#;< TOKEN\nline1\nline2\nline3\n#;> TOKEN\nkeep",
      'TOKEN',
      "keep\nkeep",
    ];

    yield 'block at start of file' => [
      "#;< META\nremoved\n#;> META\nkept",
      'META',
      'kept',
    ];

    yield 'block at end of file' => [
      "kept\n#;< META\nremoved\n#;> META",
      'META',
      'kept',
    ];

    yield 'block between blank lines' => [
      "before\n\n#;< META\nremoved\n#;> META\n\nafter\n",
      'META',
      "before\n\nafter\n",
    ];

    yield 'adjacent blocks between blank lines' => [
      "before\n\n#;< META\nblock1\n#;> META\n\n#;< META\nblock2\n#;> META\n\nafter\n",
      'META',
      "before\n\nafter\n",
    ];

    yield 'block holding its blank line' => [
      "before\n#;< META\n\nremoved\n#;> META\n\nafter\n",
      'META',
      "before\n\nafter\n",
    ];

    yield 'block between blank lines at start of file' => [
      "#;< META\nremoved\n#;> META\n\nkept\n",
      'META',
      "kept\n",
    ];

    yield 'block between blank lines at end of file' => [
      "kept\n\n#;< META\nremoved\n#;> META\n",
      'META',
      "kept\n",
    ];

    yield 'block of another token kept' => [
      "before\n\n#;< META\nremoved\n#;> META\n#;< OTHER\nkept\n#;> OTHER\n\nafter\n",
      'META',
      "before\n\n#;< OTHER\nkept\n#;> OTHER\n\nafter\n",
    ];
  }

  /**
   * @param array<int, string> $lines
   * @param array<int, bool> $removed
   * @param array<int, string> $expected
   */
  #[DataProvider('dataProviderRemoveLines')]
  public function testRemoveLines(array $lines, array $removed, array $expected): void {
    $this->assertSame($expected, remove_lines($lines, $removed));
  }

  public static function dataProviderRemoveLines(): \Iterator {
    yield 'nothing removed' => [['', 'a', '', '', 'b', ''], [FALSE, FALSE, FALSE, FALSE, FALSE, FALSE], ['', 'a', '', '', 'b', '']];
    yield 'between text' => [['a', 'x', 'b'], [FALSE, TRUE, FALSE], ['a', 'b']];
    yield 'blank line before' => [['a', '', 'x', 'b'], [FALSE, FALSE, TRUE, FALSE], ['a', '', 'b']];
    yield 'blank line after' => [['a', 'x', '', 'b'], [FALSE, TRUE, FALSE, FALSE], ['a', '', 'b']];
    yield 'blank line on each side' => [['a', '', 'x', 'y', '', 'b'], [FALSE, FALSE, TRUE, TRUE, FALSE, FALSE], ['a', '', 'b']];
    yield 'only the doubled blank line goes' => [['a', '', 'x', '', '', 'b'], [FALSE, FALSE, TRUE, FALSE, FALSE, FALSE], ['a', '', '', 'b']];
    yield 'whitespace counts as blank' => [['a', '  ', 'x', "\t", 'b'], [FALSE, FALSE, TRUE, FALSE, FALSE], ['a', '  ', 'b']];
    yield 'separate removals' => [['a', '', 'x', '', 'y', '', 'b'], [FALSE, FALSE, TRUE, FALSE, TRUE, FALSE, FALSE], ['a', '', 'b']];
    yield 'start of content' => [['x', '', 'a'], [TRUE, FALSE, FALSE], ['a']];
    yield 'end of content' => [['a', '', 'x', ''], [FALSE, FALSE, TRUE, FALSE], ['a', '']];
    yield 'everything' => [['x', ''], [TRUE, FALSE], []];
  }

  #[DataProvider('dataProviderUncommentLine')]
  public function testUncommentLine(string $content, string $start_string, string $expected): void {
    $file = self::$sut . '/testfile';
    file_put_contents($file, $content);

    uncomment_line($file, $start_string);

    $this->assertSame($expected, file_get_contents($file));
  }

  public static function dataProviderUncommentLine(): \Iterator {
    yield 'uncomment matching line' => [
      "# .ahoy.yml export-ignore\nother line",
      '.ahoy.yml',
      ".ahoy.yml export-ignore\nother line",
    ];

    yield 'only uncomments matching line' => [
      "# .ahoy.yml export-ignore\n# .circleci export-ignore\n# .eddy export-ignore",
      '.circleci',
      "# .ahoy.yml export-ignore\n.circleci export-ignore\n# .eddy export-ignore",
    ];

    yield 'no match leaves content unchanged' => [
      "# .ahoy.yml export-ignore\nother line",
      '.missing',
      "# .ahoy.yml export-ignore\nother line",
    ];

    yield 'multiple matching lines' => [
      "# tests line1\n# tests line2\nother",
      'tests',
      "tests line1\ntests line2\nother",
    ];

    yield 'line without hash prefix unchanged' => [
      ".ahoy.yml export-ignore\n# .ahoy.yml other",
      '.ahoy.yml',
      ".ahoy.yml export-ignore\n.ahoy.yml other",
    ];
  }

  public function testUncommentLineMissingFile(): void {
    uncomment_line(self::$sut . '/nonexistent', 'test');
    $this->addToAssertionCount(1);
  }

  #[DataProvider('dataProviderRemoveSpecialComments')]
  public function testRemoveSpecialComments(string $content, string $expected): void {
    chdir(self::$sut);
    file_put_contents(self::$sut . '/file.txt', $content);

    remove_special_comments();

    $this->assertSame($expected, file_get_contents(self::$sut . '/file.txt'));
  }

  public static function dataProviderRemoveSpecialComments(): \Iterator {
    yield 'remove lines with #;' => [
      "normal line\n#; special comment\nanother normal",
      "normal line\nanother normal",
    ];

    yield 'remove multiple special comments' => [
      "#; first\nnormal\n#; second\n#; third",
      'normal',
    ];

    yield 'no special comments unchanged' => [
      "line1\nline2\nline3",
      "line1\nline2\nline3",
    ];

    yield 'remove marker lines' => [
      "before\n#;< META\n#;> META\nafter",
      "before\nafter",
    ];

    yield 'mixed content' => [
      "keep\n#; remove\nkeep too\n  #; also remove\nand keep",
      "keep\nkeep too\nand keep",
    ];

    yield 'marker lines between blank lines' => [
      "before\n\n#;< META\n#;> META\n\nafter\n",
      "before\n\nafter\n",
    ];
  }

  public function testNormaliseCspellWordsDeduplicatesAndSorts(): void {
    chdir(self::$sut);
    $input = [
      'dictionaries' => ['php'],
      'words' => ['force_crystal', 'Ahoy', 'force_crystal', 'Force_Crystal', 'yoyodyne', 'ahoy'],
      'ignorePaths' => ['build/'],
    ];
    file_put_contents(self::$sut . '/.cspell.json', json_encode($input, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    normalize_cspell_words();

    $result = json_decode((string) file_get_contents(self::$sut . '/.cspell.json'), TRUE);
    $this->assertIsArray($result);
    /** @var array<string, mixed> $result */
    // Mixed-case duplicates collapse to the first-seen form: 'Ahoy'/'ahoy'
    // -> 'Ahoy'; 'force_crystal'/'Force_Crystal' -> 'force_crystal'.
    $this->assertSame(['Ahoy', 'force_crystal', 'yoyodyne'], $result['words']);
    $this->assertSame(['php'], $result['dictionaries']);
    $this->assertSame(['build/'], $result['ignorePaths']);
  }

  public function testNormaliseCspellWordsMissingFile(): void {
    chdir(self::$sut);

    normalize_cspell_words();

    $this->assertFileDoesNotExist(self::$sut . '/.cspell.json');
  }

  public function testNormaliseCspellWordsInvalidJson(): void {
    chdir(self::$sut);
    file_put_contents(self::$sut . '/.cspell.json', '{not valid json');

    $this->expectException(\JsonException::class);
    normalize_cspell_words();
  }

  public function testNormaliseCspellWordsMissingWordsKey(): void {
    chdir(self::$sut);
    $input = ['dictionaries' => ['php']];
    file_put_contents(self::$sut . '/.cspell.json', json_encode($input));

    normalize_cspell_words();

    $this->assertSame(json_encode($input), file_get_contents(self::$sut . '/.cspell.json'));
  }

  /**
   * @param array<int, string>|null $expected
   */
  #[DataProvider('dataProviderRemoveCspellEddyPaths')]
  public function testRemoveCspellEddyPaths(?string $cspell, ?array $expected): void {
    chdir(self::$sut);

    if ($cspell !== NULL) {
      file_put_contents('.cspell.json', $cspell);
    }

    remove_cspell_eddy_paths();

    if ($expected === NULL) {
      $this->assertSame($cspell, is_file('.cspell.json') ? file_get_contents('.cspell.json') : NULL, '.cspell.json changed.');

      return;
    }

    $config = json_decode((string) file_get_contents('.cspell.json'), TRUE);
    $this->assertIsArray($config);
    $this->assertSame($expected, $config['ignorePaths'] ?? NULL);
    $this->assertSame(['**'], $config['files'] ?? NULL);
  }

  public static function dataProviderRemoveCspellEddyPaths(): \Iterator {
    $cspell = static fn(array $paths): string => (string) json_encode(['files' => ['**'], 'ignorePaths' => $paths]);

    yield 'eddy paths go' => [$cspell(['build/', '.eddy/docs/', '.eddy/tests/fixtures/', 'vendor/']), ['build/', 'vendor/']];
    yield 'anchored eddy path goes' => [$cspell(['/.eddy/docs/', 'build/']), ['build/']];
    yield 'eddy directory goes' => [$cspell(['.eddy', '.eddy/', 'build/']), ['build/']];
    yield 'every path goes' => [$cspell(['.eddy/docs/']), []];
    yield 'eddy path inside another directory stays' => [$cspell(['web/.eddy/', 'build/']), NULL];
    yield 'similar name stays' => [$cspell(['.eddyrc', '.eddy-cache/']), NULL];
    yield 'no eddy paths' => [$cspell(['build/']), NULL];
    yield 'no ignore paths' => ['{"files":["**"]}', NULL];
    yield 'no file' => [NULL, NULL];
  }

  /**
   * @param array<string, string> $files
   * @param array<string> $words
   * @param array<int, string>|null $expected
   */
  #[DataProvider('dataProviderRemoveUnusedCspellWords')]
  public function testRemoveUnusedCspellWords(?string $cspell, array $files, array $words, ?array $expected): void {
    chdir(self::$sut);

    if ($cspell !== NULL) {
      file_put_contents('.cspell.json', $cspell);
    }

    foreach ($files as $name => $content) {
      file_put_contents($name, $content);
    }

    remove_unused_cspell_words($words);

    if ($expected === NULL) {
      $this->assertSame($cspell, is_file('.cspell.json') ? file_get_contents('.cspell.json') : NULL, '.cspell.json changed.');

      return;
    }

    $config = json_decode((string) file_get_contents('.cspell.json'), TRUE);
    $this->assertIsArray($config);
    $this->assertSame($expected, $config['words'] ?? NULL);
  }

  public static function dataProviderRemoveUnusedCspellWords(): \Iterator {
    $cspell = static fn(array $words): string => (string) json_encode(['files' => ['**'], 'words' => $words]);

    yield 'unused word goes' => [$cspell(['ahoy', 'lullabot']), ['Makefile' => 'ahoy'], ['lullabot'], ['ahoy']];
    yield 'word in another case goes' => [$cspell(['Lullabot', 'phpcs']), ['phpcs.xml' => 'phpcs'], ['lullabot'], ['phpcs']];
    yield 'word a file uses stays' => [$cspell(['behat', 'lullabot']), ['README.md' => '# Behat Tools'], ['behat', 'lullabot'], ['behat']];
    yield 'word inside another word goes' => [$cspell(['ahoy', 'rector']), ['README.md' => 'ahoy directory'], ['rector'], ['ahoy']];
    yield 'word not given stays' => [$cspell(['gnomovision', 'lullabot']), [], ['lullabot'], ['gnomovision']];
    yield 'every word goes' => [$cspell(['lullabot']), [], ['lullabot'], []];
    yield 'camel case part of a word stays' => [$cspell(['chromedriver']), ['Test.php' => 'class ChromedriverTest {}'], ['chromedriver'], NULL];
    yield 'no words given' => [$cspell(['lullabot']), [], [], NULL];
    yield 'no words list' => ['{"files":["**"]}', [], ['lullabot'], NULL];
    yield 'no file' => [NULL, ['README.md' => 'text'], ['lullabot'], NULL];
  }

  /**
   * @param array<string> $expected
   */
  #[DataProvider('dataProviderCspellTextWords')]
  public function testCspellTextWords(string $text, array $expected): void {
    $words = array_keys(cspell_text_words($text));
    sort($words);

    $this->assertSame($expected, $words);
  }

  public static function dataProviderCspellTextWords(): \Iterator {
    yield 'empty' => ['', []];
    yield 'separators and digits' => ['lullabot/mink-selenium2-driver', ['driver', 'lullabot', 'mink', 'selenium']];
    yield 'case' => ['Behat BEHAT behat', ['behat']];
    yield 'underscores' => ['CI_TWIGCSFIXER_IGNORE_FAILURE', ['ci', 'failure', 'ignore', 'twigcsfixer']];
    yield 'camel case' => ['ChromedriverTest', ['chromedriver', 'chromedrivertest', 'test']];
    yield 'acronym' => ['PHPUnitTest', ['php', 'phpunittest', 'test', 'unit']];
    yield 'word inside a word' => ['directory', ['directory']];
  }

  /**
   * @param array<string> $tools_remove
   * @param array<string> $expected
   */
  #[DataProvider('dataProviderAddDependentTools')]
  public function testAddDependentTools(array $tools_remove, array $expected): void {
    $this->assertSame($expected, add_dependent_tools($tools_remove));
  }

  public static function dataProviderAddDependentTools(): \Iterator {
    yield 'no tool' => [[], []];
    yield 'independent tool' => [['phpcs'], ['phpcs']];
    yield 'FunctionalJavascript' => [['functional_javascript'], ['functional_javascript']];
    yield 'PHPUnit' => [['phpunit'], ['phpunit', 'functional_javascript']];
    yield 'PHPUnit and FunctionalJavascript' => [['functional_javascript', 'phpunit'], ['functional_javascript', 'phpunit']];
  }

  /**
   * @param array<string> $tools_remove
   * @param array<string> $expected
   */
  #[DataProvider('dataProviderToolCspellWords')]
  public function testToolCspellWords(array $tools_remove, array $expected): void {
    $this->assertSame($expected, tool_cspell_words($tools_remove));
  }

  public static function dataProviderToolCspellWords(): \Iterator {
    $functional_javascript = ['chromedriver', 'headful', 'lullabot', 'webdriver'];

    yield 'no tool' => [[], []];
    yield 'tool without words' => [['eslint'], []];
    yield 'unknown tool' => [['unknown'], []];
    yield 'tools with words' => [['rector', 'renovate'], ['palantirnet', 'renovatebot']];
    yield 'FunctionalJavascript' => [['functional_javascript'], $functional_javascript];
    yield 'PHPUnit takes FunctionalJavascript along' => [['phpunit'], ['behat', 'browserkit', 'codecov', 'mikey', 'phpspec', 'vfsstream', ...$functional_javascript]];
  }

  /**
   * Every word a tool lists is in the 'words' of the template '.cspell.json'.
   */
  public function testToolSpecsCspellWordsAreTemplateWords(): void {
    $config = json_decode((string) file_get_contents(dirname(__DIR__, 4) . '/.cspell.json'), TRUE);
    $this->assertIsArray($config);
    $template_words = $config['words'] ?? NULL;
    $this->assertIsArray($template_words);

    foreach (tool_specs() as $tool => $spec) {
      foreach ($spec['cspell_words'] ?? [] as $word) {
        $this->assertContains($word, $template_words, sprintf('The CSpell word "%s" of "%s" is not in .cspell.json.', $word, $tool));
      }
    }
  }

  /**
   * @param array<string> $drupal_versions
   * @param array<string> $wrapper
   */
  #[DataProvider('dataProviderProcessValidation')]
  public function testProcessValidation(string $extension_name, string $machine_name, string $type, array $drupal_versions, array $wrapper, string $expected_message): void {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage($expected_message);
    process($extension_name, $machine_name, $type, $drupal_versions, $wrapper, [], FALSE, FALSE);
  }

  public static function dataProviderProcessValidation(): \Iterator {
    yield 'empty name' => ['', 'machine', 'module', ['10', '11'], ['ahoy'], 'Name is required.'];
    yield 'empty machine name' => ['Name', '', 'module', ['10', '11'], ['ahoy'], 'Machine name is required.'];
    yield 'machine name with hyphen' => ['Name', 'my-name', 'module', ['10', '11'], ['ahoy'], 'Machine name must start with a lowercase letter'];
    yield 'machine name with uppercase' => ['Name', 'MyName', 'module', ['10', '11'], ['ahoy'], 'Machine name must start with a lowercase letter'];
    yield 'machine name starting with digit' => ['Name', '1name', 'module', ['10', '11'], ['ahoy'], 'Machine name must start with a lowercase letter'];
    yield 'machine name with special char' => ['Name', 'name!', 'module', ['10', '11'], ['ahoy'], 'Machine name must start with a lowercase letter'];
    yield 'empty type' => ['Name', 'machine', '', ['10', '11'], ['ahoy'], 'Type is required.'];
    yield 'empty drupal versions' => ['Name', 'machine', 'module', [], ['ahoy'], 'At least one Drupal version is required.'];
    yield 'unsupported drupal version' => ['Name', 'machine', 'module', ['11', '9'], ['ahoy'], 'Unsupported Drupal version: 9.'];
    yield 'unsupported drupal versions' => ['Name', 'machine', 'module', ['9', '13'], ['ahoy'], 'Unsupported Drupal version: 9, 13.'];
  }

  /**
   * Build text that names command wrappers the way 'AGENTS.md' does.
   *
   * @param string $binaries
   *   The wrapper binaries.
   * @param string $build
   *   The build commands.
   * @param string $lint
   *   The lint commands.
   * @param string $test
   *   The test commands.
   * @param string $tooling
   *   The clause that names what runs the tooling installer.
   *
   * @return string
   *   The text.
   */
  protected static function wrapperText(string $binaries, string $build, string $lint, string $test, string $tooling): string {
    return 'When ' . $binaries . " exposes a command.\n1. Build: " . $build . "\n3. Lint: " . $lint . "\n4. Test: " . $test . "\n" . $tooling . " before the tooling commands.\n";
  }

}
