<?php

declare(strict_types=1);

namespace Drupal\sheep_calendar\TwigExtension;

use Drupal\sheep_calendar\SheepCalendarServiceInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Twig extension for sheep calendar functions.
 */
class SheepCalendarTwigExtension extends AbstractExtension {

  /**
   * The sheep calendar service.
   *
   * @var \Drupal\sheep_calendar\SheepCalendarServiceInterface
   */
  protected SheepCalendarServiceInterface $sheepCalendar;

  /**
   * Constructs a new SheepCalendarTwigExtension.
   *
   * @param \Drupal\sheep_calendar\SheepCalendarServiceInterface $sheepCalendar
   *   The sheep calendar service.
   */
  public function __construct(SheepCalendarServiceInterface $sheepCalendar) {
    $this->sheepCalendar = $sheepCalendar;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilters(): array {
    return [
      new TwigFilter('sheep_year', [$this, 'formatSheepYear']),
      new TwigFilter('julian_day', [$this, 'getJulianDay']),
      new TwigFilter('sheep_age', [$this, 'formatSheepAge']),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions(): array {
    return [
      new TwigFunction('sheep_year', [$this, 'formatSheepYear']),
      new TwigFunction('julian_day', [$this, 'getJulianDay']),
      new TwigFunction('sheep_age', [$this, 'formatSheepAge']),
      new TwigFunction('date_interval', [$this, 'formatInterval']),
    ];
  }

  /**
   * Formats a date as sheep year.
   *
   * @param mixed $date
   *   A DateTime object, timestamp, or date string.
   *
   * @return string|null
   *   The formatted sheep year (e.g., "2024/25"), or NULL if invalid.
   */
  public function formatSheepYear(mixed $date): ?string {
    $dateTime = $this->normalizeDate($date);
    if ($dateTime === NULL) {
      return NULL;
    }

    return $this->sheepCalendar->formatSheepYear($dateTime);
  }

  /**
   * Gets the Julian day for a date.
   *
   * @param mixed $date
   *   A DateTime object, timestamp, or date string.
   *
   * @return int|null
   *   The Julian day (1-366), or NULL if invalid.
   */
  public function getJulianDay(mixed $date): ?int {
    $dateTime = $this->normalizeDate($date);
    if ($dateTime === NULL) {
      return NULL;
    }

    return $this->sheepCalendar->getJulianDay($dateTime);
  }

  /**
   * Formats age based on sheep station conventions.
   *
   * @param mixed $birthDate
   *   A DateTime object, timestamp, or date string for birth.
   * @param mixed $referenceDate
   *   A DateTime object, timestamp, or date string for reference.
   *   Defaults to now.
   *
   * @return string|null
   *   The formatted age, or NULL if invalid.
   */
  public function formatSheepAge(mixed $birthDate, mixed $referenceDate = NULL): ?string {
    $birth = $this->normalizeDate($birthDate);
    if ($birth === NULL) {
      return NULL;
    }

    $reference = $referenceDate !== NULL
      ? $this->normalizeDate($referenceDate)
      : new \DateTimeImmutable('now');

    if ($reference === NULL) {
      return NULL;
    }

    return $this->sheepCalendar->formatSheepAge($birth, $reference);
  }

  /**
   * Formats the interval between two dates.
   *
   * @param mixed $date1
   *   A DateTime object, timestamp, or date string.
   * @param mixed $date2
   *   A DateTime object, timestamp, or date string. Defaults to now.
   *
   * @return string|null
   *   The formatted interval (e.g., "298 days"), or NULL if invalid.
   */
  public function formatInterval(mixed $date1, mixed $date2 = NULL): ?string {
    $dateTime1 = $this->normalizeDate($date1);
    if ($dateTime1 === NULL) {
      return NULL;
    }

    $dateTime2 = $date2 !== NULL
      ? $this->normalizeDate($date2)
      : new \DateTimeImmutable('now');

    if ($dateTime2 === NULL) {
      return NULL;
    }

    $interval = $this->sheepCalendar->calculateInterval($dateTime1, $dateTime2);
    return $this->sheepCalendar->formatInterval($interval);
  }

  /**
   * Normalizes various date inputs to DateTimeInterface.
   *
   * @param mixed $date
   *   A DateTime object, timestamp, or date string.
   *
   * @return \DateTimeInterface|null
   *   The normalized DateTime, or NULL if invalid.
   */
  protected function normalizeDate(mixed $date): ?\DateTimeInterface {
    if ($date instanceof \DateTimeInterface) {
      return $date;
    }

    if (is_numeric($date)) {
      return (new \DateTimeImmutable())->setTimestamp((int) $date);
    }

    if (is_string($date) && !empty($date)) {
      try {
        return new \DateTimeImmutable($date);
      }
      catch (\Exception $e) {
        return NULL;
      }
    }

    return NULL;
  }

}
