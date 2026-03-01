<?php

namespace Drupal\sheep_migration\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Converts 2-digit year to 4-digit year.
 *
 * Years 41-99 become 1941-1999.
 * Years 1-40 become 2001-2040.
 *
 * @MigrateProcessPlugin(
 *   id = "pad_birth_year"
 * )
 */
class PadBirthYear extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (empty($value)) {
      return NULL;
    }

    $year = (int) $value;

    // Already 4-digit year.
    if ($year >= 1000) {
      return $year;
    }

    // 41-99 → 1941-1999.
    if ($year > 40 && $year < 100) {
      return 1900 + $year;
    }

    // 1-40 → 2001-2040.
    if ($year > 0 && $year <= 40) {
      return 2000 + $year;
    }

    // Invalid or zero.
    return NULL;
  }

}
