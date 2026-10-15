<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Traits;

use AlexSkrypnyk\File\File;

trait DirectoryFixtureTrait {

  /**
   * Create a directory tree from a nested array description.
   *
   * @param string $base_path
   *   Directory to create the structure in. Created if it does not exist.
   * @param array $structure
   *   Map of entry name to contents. A string value writes a file with that
   *   content; an array value creates a directory and recurses into it.
   */
  protected static function directoryFixtureCreate(string $base_path, array $structure): void {
    File::mkdir($base_path, 0755);

    foreach ($structure as $name => $content) {
      $path = $base_path . '/' . $name;
      if (is_array($content)) {
        self::directoryFixtureCreate($path, $content);
      }
      else {
        File::dump($path, $content);
      }
    }
  }

}
