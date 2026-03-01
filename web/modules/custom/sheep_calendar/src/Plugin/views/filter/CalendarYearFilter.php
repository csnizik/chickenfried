<?php

declare(strict_types=1);

namespace Drupal\sheep_calendar\Plugin\views\filter;

use Drupal\views\Attribute\ViewsFilter;

/**
 * Filter by calendar year (Jan 1 - Dec 31).
 *
 * Calendar year runs from January 1 through December 31 of the same year.
 */
#[ViewsFilter('calendar_year')]
class CalendarYearFilter extends YearFilterBase {

  /**
   * {@inheritdoc}
   */
  protected function getYearOptions(): array {
    return $this->sheepCalendar->getCalendarYearOptions();
  }

  /**
   * {@inheritdoc}
   */
  protected function getDateRange(int $year): array {
    return [
      'start' => $year . '-01-01',
      'end' => $year . '-12-31',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getFilterLabel() {
    return $this->t('Year');
  }

}
