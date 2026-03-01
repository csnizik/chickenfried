<?php

declare(strict_types=1);

namespace Drupal\sheep_calendar\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\sheep_calendar\SheepCalendarServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formats a datetime field as a sheep year (e.g., "2024/25").
 */
#[FieldFormatter(
  id: 'sheep_year',
  label: new TranslatableMarkup('Sheep Year'),
  description: new TranslatableMarkup('Displays date as sheep year (Mar 1 - Feb 28/29), e.g., "2024/25".'),
  field_types: ['datetime', 'timestamp', 'created', 'changed'],
)]
class SheepYearFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

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
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];

    foreach ($items as $delta => $item) {
      $date = $this->getDateTimeFromItem($item);
      if ($date === NULL) {
        continue;
      }

      $elements[$delta] = [
        '#markup' => $this->sheepCalendar->formatSheepYear($date),
      ];
    }

    return $elements;
  }

}
