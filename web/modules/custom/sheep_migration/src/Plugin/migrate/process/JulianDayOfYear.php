<?php

namespace Drupal\sheep_migration\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Converts a month/day and year into a Julian day of year (1-366).
 *
 * Usage (simple):
 *   field_s_born_day:
 *     plugin: julian_day_of_year
 *     source: DAYDIS
 *     year_source: YR.
 *
 * DAYDIS may be provided as MMDD or MDD (e.g. 0904 or 904). The plugin also
 * supports supplying month/day separately using `month_source` and
 * `day_source` configuration keys. If the input is invalid the plugin returns
 * NULL so Migrate can skip the value.
 *
 * @MigrateProcessPlugin(
 *   id = "julian_day_of_year"
 * )
 */
class JulianDayOfYear extends ProcessPluginBase {

  /**
   * Transform the source to a Julian day-of-year integer.
   *
   * @param mixed $value
   *   The source value (typically DAYDIS like "0904").
   * @param \Drupal\migrate\MigrateExecutableInterface $migrate_executable
   *   The migration executable.
   * @param \Drupal\migrate\Row $row
   *   The row being processed.
   * @param string|null $destination_property
   *   The destination property name.
   *
   * @return int|null
   *   The day-of-year (1..366) or NULL on invalid/missing input.
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property = NULL) {
    $year_source = $this->configuration['year_source'] ?? 'YR';
    $month_source = $this->configuration['month_source'] ?? NULL;
    $day_source = $this->configuration['day_source'] ?? NULL;

    // Determine year.
    $year_raw = $row->get($year_source);
    if ($year_raw === NULL || $year_raw === '') {
      return NULL;
    }
    $year = (int) trim((string) $year_raw);
    if ($year <= 0) {
      return NULL;
    }

    // Determine month/day.
    $month = NULL;
    $day = NULL;

    // If month/day are provided as separate sources, prefer them.
    if (!empty($month_source) && !empty($day_source)) {
      $month = $row->get($month_source);
      $day = $row->get($day_source);
    }

    // If a combined value was provided, parse MMDD or MDD (e.g. 0904 or 904).
    if ($value !== NULL && $value !== '') {
      $s = preg_replace('/[^0-9]/', '', (string) $value);
      if ($s !== '') {
        $len = strlen($s);
        if ($len >= 3) {
          $day = (int) substr($s, -2);
          $month = (int) substr($s, 0, $len - 2);
        }
        elseif ($len == 2) {
          return NULL;
        }
      }
    }

    if ($month === NULL || $day === NULL || $month === '' || $day === '') {
      return NULL;
    }

    $month = (int) $month;
    $day = (int) $day;

    // Validate month/day ranges.
    if ($month < 1 || $month > 12) {
      return NULL;
    }

    $month_lengths = [
      1 => 31,
      2 => ($this->isLeapYear($year) ? 29 : 28),
      3 => 31,
      4 => 30,
      5 => 31,
      6 => 30,
      7 => 31,
      8 => 31,
      9 => 30,
      10 => 31,
      11 => 30,
      12 => 31,
    ];

    if ($day < 1 || $day > $month_lengths[$month]) {
      return NULL;
    }

    return self::computeJulian($year, $month, $day);
  }

  /**
   * Determine if a year is a leap year.
   *
   * @param int $year
   *   The year number.
   *
   * @return bool
   *   TRUE if leap year, FALSE otherwise.
   */
  public function isLeapYear(int $year): bool {
    return ($year % 4 === 0) && ($year % 100 !== 0 || $year % 400 === 0);
  }

  /**
   * Compute the Julian day of year for a validated Y/M/D.
   *
   * @param int $year
   *   The year number.
   * @param int $month
   *   The month number (1-12).
   * @param int $day
   *   The day of month.
   *
   * @return int
   *   1-based day of year.
   */
  public static function computeJulian(int $year, int $month, int $day): int {
    $month_days = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    if ((($year % 4) === 0) && ((($year % 100) !== 0) || (($year % 400) === 0))) {
      $month_days[1] = 29;
    }
    $julian = 0;
    for ($i = 0; $i < $month - 1; $i++) {
      $julian += $month_days[$i];
    }
    $julian += $day;
    return $julian;
  }

}
