<?php

namespace Drupal\sheep_migration\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Converts date format from MMDDYY or MMDD to Y-m-d.
 *
 * For MMDDYY (6 chars): Year derived from last 2 digits with '20' prefix.
 * For MMDD (4 chars): Year must be provided via 'year' configuration key.
 *
 * Example usage for MMDD:
 * @code
 * field_s_disposal_date:
 *   plugin: convert_date
 *   source: DAYDIS
 *   year: constants/record_year
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "convert_date"
 * )
 */
class ConvertDate extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (empty($value)) {
      return NULL;
    }

    $value = trim($value);
    $length = strlen($value);

    // Handle MMDDYY format (6 characters).
    if ($length === 6) {
      $month = (int) substr($value, 0, 2);
      $day = (int) substr($value, 2, 2);
      $year = (int) ('20' . substr($value, 4, 2));
    }
    // Handle MMDD format (4 characters) - requires year config.
    elseif ($length === 4) {
      if (empty($this->configuration['year'])) {
        return NULL;
      }
      $year_value = $this->configuration['year'];
      // Support both direct value and row source reference.
      if (is_string($year_value) && strpos($year_value, '/') !== FALSE) {
        $year = (int) $row->get($year_value);
      }
      else {
        $year = (int) $year_value;
      }
      $month = (int) substr($value, 0, 2);
      $day = (int) substr($value, 2, 2);
    }
    // Handle 3-digit MMDD where leading zero dropped (e.g., 212 = Feb 12).
    elseif ($length === 3) {
      if (empty($this->configuration['year'])) {
        return NULL;
      }
      $year_value = $this->configuration['year'];
      if (is_string($year_value) && strpos($year_value, '/') !== FALSE) {
        $year = (int) $row->get($year_value);
      }
      else {
        $year = (int) $year_value;
      }
      // Pad with leading zero: 212 becomes 0212.
      $value = str_pad($value, 4, '0', STR_PAD_LEFT);
      $month = (int) substr($value, 0, 2);
      $day = (int) substr($value, 2, 2);
    }
    else {
      return NULL;
    }

    if (!checkdate($month, $day, $year)) {
      return NULL;
    }

    return sprintf('%04d-%02d-%02d', $year, $month, $day);
  }

}
