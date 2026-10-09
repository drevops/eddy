<?php

declare(strict_types=1);

namespace Drupal\force_crystal;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Provides the sanitized text configured for the force_crystal module.
 */
class ForceCrystalService {

  /**
   * Constructs a new ForceCrystalService instance.
   */
  public function __construct(
    /**
     * The config factory.
     */
    protected ConfigFactoryInterface $configFactory,
  ) {
  }

  /**
   * Returns the configured text with HTML tags stripped.
   *
   * @return string
   *   The 'text' value of 'force_crystal.settings', sanitized.
   */
  public function getText(): string {
    $text = $this->configFactory->get('force_crystal.settings')->get('text');

    return static::sanitize($text);
  }

  /**
   * Sanitizes text.
   *
   * @param string $string
   *   The string to be cleaned.
   *
   * @return string
   *   The sanitized string.
   */
  public static function sanitize(string $string): string {
    return strip_tags($string);
  }

}
