<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests the content types recipe that 'eddy-provision' applies.
 *
 * Drupal validates a recipe only when a site applies it, so these tests check
 * the recipe files on their own.
 */
#[Group('p0')]
final class ContentTypesRecipeTest extends UnitTestCase {

  #[DataProvider('dataProviderConfigIsNamedAfterId')]
  public function testConfigIsNamedAfterId(string $name): void {
    $config = self::config($name);
    // A content type holds its ID in 'type'.
    $id = $config['id'] ?? $config['type'] ?? NULL;

    $this->assertIsString($id, $name . ' holds no ID.');
    $this->assertStringEndsWith('.' . $id, $name);
  }

  public static function dataProviderConfigIsNamedAfterId(): \Iterator {
    foreach (self::configNames() as $name) {
      yield $name => [$name];
    }
  }

  #[DataProvider('dataProviderConfigDependenciesAreProvided')]
  public function testConfigDependenciesAreProvided(string $name): void {
    $dependencies = self::config($name)['dependencies'] ?? [];
    $this->assertIsArray($dependencies);
    $config = $dependencies['config'] ?? [];
    $this->assertIsArray($config);

    $missing = array_diff(array_filter($config, is_string(...)), self::providedConfig());
    $this->assertSame([], array_values($missing), $name . ' depends on config that the recipe neither ships, imports nor creates.');
  }

  public static function dataProviderConfigDependenciesAreProvided(): \Iterator {
    yield from self::dataProviderConfigIsNamedAfterId();
  }

  public function testRecipeProvidesContentTypes(): void {
    $this->assertSame(['node.type.article', 'node.type.page'], array_values(preg_grep('/^node\.type\./', self::configNames()) ?: []));
  }

  /**
   * Drupal 12 removes the 'text_with_summary' field type with its widget and
   * formatter, so the recipe uses none of them.
   */
  public function testRecipeUsesNoTextWithSummary(): void {
    $values = [];

    foreach ([self::recipeDir() . '/recipe.yml', ...self::configFiles()] as $file) {
      $data = Yaml::parse(File::read($file));
      $this->assertIsArray($data, $file . ' holds no YAML mapping.');

      array_walk_recursive($data, static function (mixed $value) use (&$values): void {
        if (is_string($value)) {
          $values[] = $value;
        }
      });
    }

    $this->assertSame([], array_values(array_intersect(['text_with_summary', 'text_textarea_with_summary', 'text_summary_or_trimmed'], $values)));
  }

  protected static function recipeDir(): string {
    return self::toolingDir() . '/recipes/content_types';
  }

  /**
   * List the config files of the recipe.
   *
   * @return array<int, string>
   *   The paths, sorted.
   */
  protected static function configFiles(): array {
    $files = glob(self::recipeDir() . '/config/*.yml') ?: [];
    sort($files);

    return $files;
  }

  /**
   * List the names of the config that the recipe ships.
   *
   * @return array<int, string>
   *   The names, such as 'node.type.page', sorted.
   */
  protected static function configNames(): array {
    return array_map(static fn(string $file): string => basename($file, '.yml'), self::configFiles());
  }

  /**
   * Read a config file of the recipe.
   *
   * @return array<mixed>
   *   The config.
   */
  protected static function config(string $name): array {
    $config = Yaml::parse(File::read(self::recipeDir() . '/config/' . $name . '.yml'));

    return is_array($config) ? $config : [];
  }

  /**
   * List the config that the recipe ships, imports or creates.
   *
   * @return array<int, string>
   *   The config names.
   */
  protected static function providedConfig(): array {
    $recipe = Yaml::parse(File::read(self::recipeDir() . '/recipe.yml'));
    $recipe = is_array($recipe) ? $recipe : [];
    $config = is_array($recipe['config'] ?? NULL) ? $recipe['config'] : [];
    $imports = is_array($config['import'] ?? NULL) ? $config['import'] : [];
    $actions = is_array($config['actions'] ?? NULL) ? $config['actions'] : [];

    $imported = [];

    foreach ($imports as $names) {
      $imported = [...$imported, ...(is_array($names) ? array_filter($names, is_string(...)) : [])];
    }

    $created = array_keys(array_filter($actions, static fn(mixed $action): bool => is_array($action) && isset($action['createIfNotExists'])));

    return [...self::configNames(), ...$imported, ...array_map(strval(...), $created)];
  }

}
