<?php

declare(strict_types=1);

namespace Drupal\sheep_calendar\Plugin\views\filter;

use Drupal\Core\Database\Connection;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\query\Sql;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter by integer year field with auto-populated options from data.
 *
 * Queries the database for distinct year values present in the data
 * and presents them as a select dropdown. Unlike date-based year filters,
 * this operates on plain integer fields with simple equality matching.
 */
#[ViewsFilter('integer_year')]
class IntegerYearFilter extends YearFilterBase {

  /**
   * The database connection.
   */
  protected Connection $database;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->database = $container->get('database');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getYearOptions(): array {
    $table = $this->definition['table'] ?? $this->table;
    $field = $this->definition['field'] ?? $this->realField;

    $years = $this->database
      ->select($table, 't')
      ->fields('t', [$field])
      ->isNotNull('t.' . $field)
      ->distinct()
      ->orderBy($field, 'DESC')
      ->execute()
      ->fetchCol();

    $options = [];
    foreach ($years as $year) {
      $options[(int) $year] = (string) $year;
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   *
   * Not used — query() is overridden for integer equality.
   * Required by YearFilterBase contract.
   */
  protected function getDateRange(int $year): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function getFilterLabel() {
    return $this->t('Year');
  }

  /**
   * {@inheritdoc}
   *
   * Overrides date-range query with simple integer equality.
   */
  public function query(): void {
    $value = is_array($this->value) ? reset($this->value) : $this->value;

    if ($value === '' || $value === FALSE) {
      return;
    }

    if (!$this->query instanceof Sql) {
      return;
    }

    $this->ensureMyTable();
    $field = "$this->tableAlias.$this->realField";

    $this->query->addWhere(
      $this->options['group'],
      $field,
      (int) $value,
      '='
    );
  }

}
