<?php

declare(strict_types=1);

namespace Drupal\sheep_calendar;

use Drupal\Component\Datetime\TimeInterface;

/**
 * Service for sheep calendar calculations.
 */
class SheepCalendarService implements SheepCalendarServiceInterface {

  /**
   * Constructs a SheepCalendarService.
   *
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    protected TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function isLeapYear(\DateTimeInterface $date): bool {
    $year = (int) $date->format('Y');

    return ($year % 4 === 0 && $year % 100 !== 0) || ($year % 400 === 0);
  }

  /**
   * {@inheritdoc}
   */
  public function getSheepYear(\DateTimeInterface $date): int {
    $year = (int) $date->format('Y');
    $month = (int) $date->format('n');

    return $month < 3 ? $year - 1 : $year;
  }

  /**
   * {@inheritdoc}
   */
  public function formatSheepYear(\DateTimeInterface $date): string {
    $startYear = $this->getSheepYear($date);
    $endYear = $startYear + 1;

    return $startYear . '/' . substr((string) $endYear, -2);
  }

  /**
   * {@inheritdoc}
   */
  public function getJulianDay(\DateTimeInterface $date): int {
    return (int) $date->format('z') + 1;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateInterval(\DateTimeInterface $date1, \DateTimeInterface $date2): \DateInterval {
    return $date1->diff($date2, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getTotalDays(\DateInterval $interval): int {
    return (int) $interval->days;
  }

  /**
   * {@inheritdoc}
   */
  public function formatInterval(\DateInterval $interval): string {
    $days = $this->getTotalDays($interval);

    return $days === 1 ? '1 day' : $days . ' days';
  }

  /**
   * {@inheritdoc}
   */
  public function formatSheepAge(\DateTimeInterface $birthDate, \DateTimeInterface $referenceDate): string {
    $interval = $this->calculateInterval($birthDate, $referenceDate);
    $totalDays = $this->getTotalDays($interval);

    if ($totalDays < 365) {
      return $totalDays === 1 ? '1 day' : $totalDays . ' days';
    }

    $fullYears = (int) floor($totalDays / 365);
    $remainderDays = $totalDays % 365;

    if ($remainderDays < 182) {
      return $fullYears === 1 ? '1 year' : $fullYears . ' years';
    }

    return $fullYears . '.5 years';
  }

  /**
   * {@inheritdoc}
   */
  public function getSheepYearOptions(int $startYear = 1940, ?int $endYear = NULL, string $sort = 'desc'): array {
    if ($endYear === NULL) {
      $endYear = (int) date('Y', $this->time->getCurrentTime());
    }

    $options = [];
    for ($year = $startYear; $year <= $endYear; $year++) {
      $options[$year] = $this->formatSheepYear(
        new \DateTimeImmutable($year . '-03-01')
      );
    }

    if ($sort === 'desc') {
      $options = array_reverse($options, TRUE);
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getCalendarYearOptions(int $startYear = 1940, ?int $endYear = NULL, string $sort = 'desc'): array {
    if ($endYear === NULL) {
      $endYear = (int) date('Y', $this->time->getCurrentTime());
    }

    $options = [];
    for ($year = $startYear; $year <= $endYear; $year++) {
      $options[$year] = (string) $year;
    }

    if ($sort === 'desc') {
      $options = array_reverse($options, TRUE);
    }

    return $options;
  }

}
