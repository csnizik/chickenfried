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
 * Formats a datetime field as sheep age.
 */
#[FieldFormatter(
  id: 'sheep_age',
  label: new TranslatableMarkup('Sheep Age'),
  description: new TranslatableMarkup('Displays age in days or years based on sheep station conventions.'),
  field_types: ['datetime', 'timestamp', 'created', 'changed'],
)]
class SheepAgeFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

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
      'display_format' => 'age',
      'reference_source' => 'now',
      'reference_field' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);

    $elements['display_format'] = [
      '#type' => 'radios',
      '#title' => $this->t('Display format'),
      '#options' => [
        'age' => $this->t('Age (days before 1 year, then years) — "182 days" or "2.5 years"'),
        'days' => $this->t('Always days — "912 days"'),
        'age_with_days' => $this->t('Age with days — "2.5 years (912 days)"'),
      ],
      '#default_value' => $this->getSetting('display_format'),
    ];

    $field_options = $this->getAvailableDatetimeFields();

    if (!empty($field_options)) {
      $elements['reference_source'] = [
        '#type' => 'radios',
        '#title' => $this->t('Reference date'),
        '#options' => [
          'now' => $this->t('Now (current date)'),
          'field' => $this->t('Another date field'),
        ],
        '#default_value' => $this->getSetting('reference_source'),
      ];

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

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];

    $format_labels = [
      'age' => $this->t('Age (days/years)'),
      'days' => $this->t('Always days'),
      'age_with_days' => $this->t('Age with days'),
    ];
    $summary[] = $format_labels[$this->getSetting('display_format')] ?? $format_labels['age'];

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
    $display_format = $this->getSetting('display_format');

    foreach ($items as $delta => $item) {
      $birthDate = $this->getDateTimeFromItem($item);
      if ($birthDate === NULL) {
        continue;
      }

      $referenceDate = $this->getReferenceDate($entity);
      if ($referenceDate === NULL) {
        $elements[$delta] = [
          '#markup' => $this->t('N/A'),
        ];
        continue;
      }

      $elements[$delta] = [
        '#markup' => $this->formatAge($birthDate, $referenceDate, $display_format),
      ];
    }

    return $elements;
  }

  /**
   * Formats age based on display format setting.
   *
   * @param \DateTimeInterface $birthDate
   *   The birth date.
   * @param \DateTimeInterface $referenceDate
   *   The reference date.
   * @param string $display_format
   *   One of 'age', 'days', or 'age_with_days'.
   *
   * @return string
   *   The formatted age string.
   */
  protected function formatAge(\DateTimeInterface $birthDate, \DateTimeInterface $referenceDate, string $display_format): string {
    $interval = $this->sheepCalendar->calculateInterval($birthDate, $referenceDate);
    $totalDays = $this->sheepCalendar->getTotalDays($interval);

    switch ($display_format) {
      case 'days':
        return $this->sheepCalendar->formatInterval($interval);

      case 'age_with_days':
        $ageString = $this->sheepCalendar->formatSheepAge($birthDate, $referenceDate);
        if ($totalDays >= 365) {
          $daysString = $this->sheepCalendar->formatInterval($interval);
          return $ageString . ' (' . $daysString . ')';
        }
        return $ageString;

      case 'age':
      default:
        return $this->sheepCalendar->formatSheepAge($birthDate, $referenceDate);
    }
  }

}
