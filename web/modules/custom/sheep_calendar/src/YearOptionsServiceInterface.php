<?php

declare(strict_types=1);

namespace Drupal\sheep_calendar;

/**
 * Interface for year options generation.
 */
interface YearOptionsServiceInterface {

  /**
   * Gets an array of sheep year options for form selects.
   *
   * Sheep year runs March 1 through February 28/29.
   *
   * @param int $startYear
   *   The earliest sheep year to include (e.g., 1940).
   * @param int|null $endYear
   *   The latest sheep year to include. Defaults to current year.
   * @param string $sort
   *   Sort order: 'desc' (newest first) or 'asc' (oldest first).
   *
   * @return array
   *   Associative array keyed by sheep year start (e.g., 2024) with
   *   formatted labels as values (e.g., "2024/25").
   */
  public function getSheepYearOptions(int $startYear = 1940, ?int $endYear = NULL, string $sort = 'desc'): array;

  /**
   * Gets an array of calendar year options for form selects.
   *
   * Calendar year runs January 1 through December 31.
   *
   * @param int $startYear
   *   The earliest year to include (e.g., 1940).
   * @param int|null $endYear
   *   The latest year to include. Defaults to current year.
   * @param string $sort
   *   Sort order: 'desc' (newest first) or 'asc' (oldest first).
   *
   * @return array
   *   Associative array keyed by year (e.g., 2024) with
   *   the year as the label (e.g., "2024").
   */
  public function getCalendarYearOptions(int $startYear = 1940, ?int $endYear = NULL, string $sort = 'desc'): array;

}
