<?php

declare(strict_types=1);

namespace Drupal\sheep_calendar\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\sheep_calendar\SheepCalendarServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formats a datetime field as an interval from a reference date.
 */
#[FieldFormatter(
  id: 'date_interval',
  label: new TranslatableMarkup('Date Interval'),
  description: new TranslatableMarkup('Displays the interval between this date and a reference date (e.g., "298 days").'),
  field_types: ['datetime', 'timestamp', 'created', 'changed'],
)]
class DateIntervalFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  use DateTimeFieldItemTrait;

  /**
   * The sheep calendar service.
   *
   * @var \Drupal\sheep_calendar\SheepCalendarServiceInterface
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
  public static function defaultSettings(): array {
    return [
      'reference_source' => 'now',
      'reference_field' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);

    $field_options = $this->getAvailableDatetimeFields();

    $elements['reference_source'] = [
      '#type' => 'radios',
      '#title' => $this->t('Reference date'),
      '#options' => [
        'now' => $this->t('Now (current date)'),
        'field' => $this->t('Another date field'),
      ],
      '#default_value' => $this->getSetting('reference_source'),
    ];

    if (!empty($field_options)) {
      $elements['reference_field'] = [
        '#type' => 'select',
        '#title' => $this->t('Reference date field'),
        '#options' => $field_options,
        '#default_value' => $this->getSetting('reference_field'),
        '#states' => [
          'visible' => [
            ':input[name*="reference_source"]' => ['value' => 'field'],
          ],
          'required' => [
            ':input[name*="reference_source"]' => ['value' => 'field'],
          ],
        ],
      ];
    }
    else {
      // No other datetime fields — force "now" and hide the option.
      $elements['reference_source']['#access'] = FALSE;
      $elements['reference_source']['#default_value'] = 'now';
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];

    if ($this->getSetting('reference_source') === 'now') {
      $summary[] = $this->t('Reference: Now');
    }
    else {
      $field_name = $this->getSetting('reference_field');
      $summary[] = $this->t('Reference: @field', ['@field' => $field_name]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    $entity = $items->getEntity();

    foreach ($items as $delta => $item) {
      $date = $this->getDateTimeFromItem($item);
      if ($date === NULL) {
        continue;
      }

      $referenceDate = $this->getReferenceDate($entity);
      if ($referenceDate === NULL) {
        $elements[$delta] = [
          '#markup' => $this->t('N/A'),
        ];
        continue;
      }

      $interval = $this->sheepCalendar->calculateInterval($date, $referenceDate);
      $elements[$delta] = [
        '#markup' => $this->sheepCalendar->formatInterval($interval),
      ];
    }

    return $elements;
  }

}
