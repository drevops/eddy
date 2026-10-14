<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Traits;

use AlexSkrypnyk\File\File;

/**
 * Serves the in-tree tooling package to Composer as a release.
 *
 * A Composer home with a global path repository installs '.eddy/tooling' by
 * version, the way a generated project installs a published release.
 */
trait ComposerHomeTrait {

  /**
   * Write a Composer home that serves the in-tree package as a release.
   *
   * @param string $version
   *   The version the package is served as.
   * @param bool $packagist
   *   Whether Packagist stays available for other packages.
   */
  protected function composerHomeWrite(string $version, bool $packagist): void {
    $repositories = [
      [
        'type' => 'path',
        'url' => dirname(__DIR__, 4) . '/.eddy/tooling',
        'options' => ['symlink' => FALSE, 'versions' => ['drevops/eddy-tooling' => $version]],
      ],
    ];

    if (!$packagist) {
      $repositories[] = ['packagist.org' => FALSE];
    }

    File::dump(self::composerHomeDir() . '/config.json', json_encode(['repositories' => $repositories], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
  }

  /**
   * Get the directory that 'composerHomeWrite()' writes the Composer home to.
   *
   * @return string
   *   The directory path.
   */
  protected static function composerHomeDir(): string {
    return self::$tmp . '/composer-home';
  }

}
