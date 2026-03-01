<?php

namespace Drupal\sheep_migration\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\migrate\MigrateSkipRowException;

/**
 * Validates that ID10 is exactly 10 characters long.
 *
 * @MigrateProcessPlugin(
 *   id = "validate_id10_length"
 * )
 */
class ValidateId10Length extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {

    // Convert to string and trim.
    $value = trim((string) $value);

    // Skip empty values.
    if (empty($value)) {
      throw new MigrateSkipRowException('ID10 is empty');
    }

    // Skip if value is '0'.
    if ($value === '0') {
      throw new MigrateSkipRowException('ID10 value is zero');
    }

    // Check length.
    if (strlen($value) !== 10) {
      throw new MigrateSkipRowException(sprintf('ID10 length is %d characters, expected exactly 10: "%s"', strlen($value), $value));
    }

    return $value;
  }

}
