<?php

declare(strict_types=1);

namespace Drupal\sheep_calendar;

/**
 * Interface for sheep calendar calculations.
 */
interface SheepCalendarServiceInterface extends YearOptionsServiceInterface {

  /**
   * Determines if the year of the given date is a leap year.
   *
   * @param \DateTimeInterface $date
   *   The date to check.
   *
   * @return bool
   *   TRUE if leap year, FALSE otherwise.
   */
  public function isLeapYear(\DateTimeInterface $date): bool;

  /**
   * Gets the sheep year for a given date.
   *
   * Sheep year runs March 1 through February 28/29.
   * A date before March 1 belongs to the previous sheep year.
   *
   * @param \DateTimeInterface $date
   *   The date to evaluate.
   *
   * @return int
   *   The starting year of the sheep year (e.g., 2024 for "2024/25").
   */
  public function getSheepYear(\DateTimeInterface $date): int;

  /**
   * Formats a date as a sheep year string.
   *
   * @param \DateTimeInterface $date
   *   The date to format.
   *
   * @return string
   *   Formatted as "2024/25".
   */
  public function formatSheepYear(\DateTimeInterface $date): string;

  /**
   * Gets the Julian day (day of year) for a given date.
   *
   * January 1 = 1, December 31 = 365 (or 366 in leap year).
   *
   * @param \DateTimeInterface $date
   *   The date to evaluate.
   *
   * @return int
   *   Day of year, 1-366.
   */
  public function getJulianDay(\DateTimeInterface $date): int;

  /**
   * Calculates the absolute interval between two dates.
   *
   * @param \DateTimeInterface $date1
   *   First date.
   * @param \DateTimeInterface $date2
   *   Second date.
   *
   * @return \DateInterval
   *   Absolute difference (always positive).
   */
  public function calculateInterval(\DateTimeInterface $date1, \DateTimeInterface $date2): \DateInterval;

  /**
   * Formats a date interval as days.
   *
   * @param \DateInterval $interval
   *   The interval to format. Must be created via calculateInterval().
   *
   * @return string
   *   Formatted as "298 days" or "1 day".
   */
  public function formatInterval(\DateInterval $interval): string;

  /**
   * Formats age according to sheep station conventions.
   *
   * Formats as follows:
   * - 0-364 days: "X days"
   * - 365+ days: "Y years" or "Y.5 years" based on 182-day half-year threshold.
   *
   * @param \DateTimeInterface $birthDate
   *   The birth date.
   * @param \DateTimeInterface $referenceDate
   *   The reference date (e.g., now, or date of an event).
   *
   * @return string
   *   Formatted age string.
   */
  public function formatSheepAge(\DateTimeInterface $birthDate, \DateTimeInterface $referenceDate): string;

  /**
   * Gets the total days from a date interval.
   *
   * @param \DateInterval $interval
   *   The interval. Must be created via calculateInterval().
   *
   * @return int
   *   Total days.
   */
  public function getTotalDays(\DateInterval $interval): int;

}
