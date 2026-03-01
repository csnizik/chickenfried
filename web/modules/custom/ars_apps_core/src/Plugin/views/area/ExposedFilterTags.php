<?php

declare(strict_types=1);

namespace Drupal\ars_apps_core\Plugin\views\area;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Attribute\ViewsArea;
use Drupal\views\Plugin\views\area\AreaPluginBase;
use Drupal\views\Plugin\views\filter\InOperator;

/**
 * Displays active exposed filters as USWDS tag components.
 */
#[ViewsArea('exposed_filter_tags')]
class ExposedFilterTags extends AreaPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['tag_variant'] = ['default' => 'default'];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);

    $form['tag_variant'] = [
      '#type' => 'select',
      '#title' => $this->t('Tag variant'),
      '#options' => [
        'default' => $this->t('Default'),
        'big' => $this->t('Big'),
      ],
      '#default_value' => $this->options['tag_variant'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE): array {
    if ($empty && empty($this->options['empty'])) {
      return [];
    }

    $view = $this->view;
    $filters = $view->getExposedInput();

    if (empty($filters)) {
      return [];
    }

    $tags = [];
    $filter_handlers = $view->getHandlers('filter');

    foreach ($filters as $filter_id => $value) {
      $filter_id = (string) $filter_id;

      // Skip empty values.
      if ($this->isEmptyValue($value)) {
        continue;
      }

      // Find the handler for this filter.
      $handler = $filter_handlers[$filter_id] ?? $this->findFilterHandler($filter_handlers, $filter_id);
      if (!$handler) {
        continue;
      }

      // Get label and display value.
      $label = $this->getFilterLabel($handler);
      $display_value = $this->getDisplayValue($handler, $value);

      if ($display_value === '') {
        continue;
      }

      $tags[] = [
        '#type' => 'component',
        '#component' => 'ui_suite_uswds:tag',
        '#variant' => $this->options['tag_variant'],
        '#slots' => [
          'text' => [
            '#markup' => $this->t('@label: @value', [
              '@label' => $label,
              '@value' => $display_value,
            ]),
          ],
        ],
      ];
    }

    if (empty($tags)) {
      return [];
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['exposed-filter-tags', 'display-flex', 'flex-wrap', 'flex-row', 'gap-1'],
      ],
      'tags' => $tags,
      '#cache' => [
        'contexts' => ['url.query_args'],
      ],
    ];
  }

  /**
   * Checks if a filter value is empty.
   *
   * @param mixed $value
   *   The filter value to check.
   *
   * @return bool
   *   TRUE if the value is considered empty.
   */
  protected function isEmptyValue(mixed $value): bool {
    if ($value === '' || $value === NULL) {
      return TRUE;
    }
    if ($value === 'All' || $value === 'all') {
      return TRUE;
    }
    if (is_array($value)) {
      $filtered = array_filter($value, fn($v) => $v !== '' && $v !== 'All');
      return empty($filtered);
    }
    return FALSE;
  }

  /**
   * Finds a filter handler by exposed identifier.
   *
   * @param array<string, array<string, mixed>> $handlers
   *   The filter handlers array.
   * @param string $identifier
   *   The exposed filter identifier.
   *
   * @return array<string, mixed>|null
   *   The handler array or NULL if not found.
   */
  protected function findFilterHandler(array $handlers, string $identifier): ?array {
    foreach ($handlers as $handler) {
      // Check standard exposed identifier.
      if (isset($handler['expose']['identifier']) && $handler['expose']['identifier'] === $identifier) {
        return $handler;
      }
      // Check grouped filter identifier.
      if (isset($handler['group_info']['identifier']) && $handler['group_info']['identifier'] === $identifier) {
        return $handler;
      }
    }
    return NULL;
  }

  /**
   * Gets the label for a filter.
   *
   * @param array<string, mixed> $handler
   *   The filter handler definition.
   *
   * @return string
   *   The filter label.
   */
  protected function getFilterLabel(array $handler): string {
    // Grouped filters use group_info.label.
    if (!empty($handler['is_grouped']) && !empty($handler['group_info']['label'])) {
      return (string) $handler['group_info']['label'];
    }

    // Standard exposed filters use expose.label.
    if (!empty($handler['expose']['label'])) {
      return (string) $handler['expose']['label'];
    }

    return (string) ($handler['id'] ?? 'Filter');
  }

  /**
   * Gets human-readable display value for a filter.
   *
   * @param array<string, mixed> $handler
   *   The filter handler definition.
   * @param mixed $value
   *   The raw filter value.
   *
   * @return string
   *   The display value.
   */
  protected function getDisplayValue(array $handler, mixed $value): string {
    // Handle arrays (multi-select).
    if (is_array($value)) {
      $value = array_filter($value, fn($v) => $v !== '' && $v !== 'All');
      if (empty($value)) {
        return '';
      }
      $value = reset($value);
    }

    $value = (string) $value;

    // Grouped filters: look up the title from group_items.
    if (!empty($handler['is_grouped']) && !empty($handler['group_info']['group_items'])) {
      $group_items = $handler['group_info']['group_items'];
      if (isset($group_items[$value]['title'])) {
        return (string) $group_items[$value]['title'];
      }
    }

    // For select lists with options (InOperator filters), try to get the label.
    $handler_id = $handler['id'] ?? NULL;
    if ($handler_id && isset($this->view->filter[$handler_id])) {
      $filter = $this->view->filter[$handler_id];
      if ($filter instanceof InOperator) {
        $options = $filter->getValueOptions();
        if ($options !== NULL && isset($options[$value])) {
          return (string) $options[$value];
        }
      }
    }

    return $value;
  }

}
