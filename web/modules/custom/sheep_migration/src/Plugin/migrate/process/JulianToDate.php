<?php

namespace Drupal\sheep_migration\Plugin\migrate\process;

use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Converts Julian day (1-366) and year to a date string (Y-m-d).
 *
 * Usage:
 *   field_s_birth_date:
 *     plugin: julian_to_date
 *     source: DAYBRN
 *     year: constants/year_label.
 *
 *   # Or with 2-digit year from source:
 *   field_s_birth_date:
 *     plugin: julian_to_date
 *     source: DAYBRN
 *     year: YR
 *
 * Returns NULL if input is empty or invalid, allowing migrate to skip.
 */
#[MigrateProcess('julian_to_date')]
class JulianToDate extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // Skip empty Julian day.
    if ($value === NULL || $value === '' || $value === '0' || $value === 0) {
      return NULL;
    }

    $julian_day = (int) $value;
    if ($julian_day < 1 || $julian_day > 366) {
      return NULL;
    }

    // Get year from configuration.
    $year_config = $this->configuration['year'] ?? NULL;
    if ($year_config === NULL) {
      return NULL;
    }

    // Resolve year value (could be source field or constant).
    $year_raw = $row->get($year_config);
    if ($year_raw === NULL || $year_raw === '') {
      return NULL;
    }

    $year = $this->normalizeYear((int) $year_raw);
    if ($year === NULL) {
      return NULL;
    }

    // Validate Julian day for non-leap years.
    if ($julian_day === 366 && !$this->isLeapYear($year)) {
      return NULL;
    }

    return $this->julianToDateString($julian_day, $year);
  }

  /**
   * Converts 2-digit year to 4-digit year.
   *
   * @param int $year
   *   The year (2 or 4 digits).
   *
   * @return int|null
   *   4-digit year, or NULL if invalid.
   */
  protected function normalizeYear(int $year): ?int {
    // Already 4-digit.
    if ($year >= 1000) {
      return $year;
    }

    // 41-99 → 1941-1999.
    if ($year > 40 && $year < 100) {
      return 1900 + $year;
    }

    // 0-40 → 2000-2040.
    if ($year >= 0 && $year <= 40) {
      return 2000 + $year;
    }

    return NULL;
  }

  /**
   * Determines if a year is a leap year.
   *
   * @param int $year
   *   The 4-digit year.
   *
   * @return bool
   *   TRUE if leap year.
   */
  protected function isLeapYear(int $year): bool {
    return ($year % 4 === 0 && $year % 100 !== 0) || ($year % 400 === 0);
  }

  /**
   * Converts Julian day and year to Y-m-d string.
   *
   * @param int $julian_day
   *   Day of year (1-366).
   * @param int $year
   *   Four-digit year.
   *
   * @return string
   *   Date in Y-m-d format.
   */
  protected function julianToDateString(int $julian_day, int $year): string {
    $date = \DateTimeImmutable::createFromFormat(
      'Y z',
      $year . ' ' . ($julian_day - 1)
    );

    return $date->format('Y-m-d');
  }

}
