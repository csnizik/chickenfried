<?php

declare(strict_types=1);

namespace Drupal\sheep_calendar\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\sheep_calendar\SheepCalendarServiceInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\Plugin\views\query\Sql;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for year-based date filters.
 *
 * Provides shared functionality for filtering by year ranges on date fields.
 * Subclasses define the specific year boundaries and options source.
 */
abstract class YearFilterBase extends FilterPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The sheep calendar service.
   */
  protected SheepCalendarServiceInterface $sheepCalendar;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->sheepCalendar = $container->get('sheep_calendar.calendar');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['value'] = ['default' => ''];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state): void {
    $options = $this->getYearOptions();

    // Only add "- Any -" for non-exposed forms (admin UI).
    // Exposed forms get this automatically from Views.
    if (!$form_state->get('exposed')) {
      $options = ['' => $this->t('- Any -')] + $options;
    }

    $form['value'] = [
      '#type' => 'select',
      '#title' => $this->getFilterLabel(),
      '#options' => $options,
      '#default_value' => is_array($this->value) ? reset($this->value) : $this->value,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildExposedForm(&$form, FormStateInterface $form_state): void {
    parent::buildExposedForm($form, $form_state);

    $identifier = $this->options['expose']['identifier'];
    if (isset($form[$identifier])) {
      $form[$identifier]['#title'] = $this->options['expose']['label'] ?? $this->getFilterLabel();
      $form[$identifier]['#title_display'] = 'before';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary(): string {
    if (empty($this->value)) {
      return $this->t('any')->render();
    }

    $options = $this->getYearOptions();
    $year = is_array($this->value) ? (int) reset($this->value) : (int) $this->value;

    return $options[$year] ?? (string) $year;
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $value = is_array($this->value) ? reset($this->value) : $this->value;

    if ($value === '' || $value === FALSE) {
      return;
    }

    if (!$this->query instanceof Sql) {
      return;
    }

    $year = (int) $value;
    $dateRange = $this->getDateRange($year);

    $this->ensureMyTable();
    $field = "$this->tableAlias.$this->realField";

    $this->query->addWhere(
      $this->options['group'],
      $field,
      $dateRange['start'],
      '>='
    );
    $this->query->addWhere(
      $this->options['group'],
      $field,
      $dateRange['end'],
      '<='
    );
  }

  /**
   * Gets the year options for the filter dropdown.
   *
   * @return array
   *   Associative array of year => label.
   */
  abstract protected function getYearOptions(): array;

  /**
   * Gets the date range for a given year.
   *
   * @param int $year
   *   The year to get the date range for.
   *
   * @return array
   *   Array with 'start' and 'end' date strings (Y-m-d format).
   */
  abstract protected function getDateRange(int $year): array;

  /**
   * Gets the default label for the filter.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   The filter label.
   */
  abstract protected function getFilterLabel();

}
