<?php

declare(strict_types=1);

namespace Drupal\sheep_calendar\Plugin\views\filter;

use Drupal\views\Attribute\ViewsFilter;

/**
 * Filter by sheep year (Mar 1 - Feb 28/29).
 *
 * Sheep year runs from March 1 of the starting year through
 * February 28/29 of the following year.
 */
#[ViewsFilter('sheep_year')]
class SheepYearFilter extends YearFilterBase {

  /**
   * {@inheritdoc}
   */
  protected function getYearOptions(): array {
    return $this->sheepCalendar->getSheepYearOptions();
  }

  /**
   * {@inheritdoc}
   */
  protected function getDateRange(int $year): array {
    $startDate = $year . '-03-01';

    $endYear = $year + 1;
    $endDay = $this->sheepCalendar->isLeapYear(
      new \DateTimeImmutable($endYear . '-01-01')
    ) ? '29' : '28';
    $endDate = $endYear . '-02-' . $endDay;

    return [
      'start' => $startDate,
      'end' => $endDate,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getFilterLabel() {
    return $this->t('Sheep Year');
  }

}
