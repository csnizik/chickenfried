<?php

declare(strict_types=1);

namespace Drupal\sheep_calendar\Plugin\Field\FieldFormatter;

use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Provides date extraction methods for sheep calendar formatters.
 */
trait DateTimeFieldItemTrait {

  /**
   * Extracts a DateTime object from a field item.
   *
   * @param mixed $item
   *   The field item.
   *
   * @return \DateTimeInterface|null
   *   The DateTime object, or NULL if not available.
   */
  protected function getDateTimeFromItem(mixed $item): ?\DateTimeInterface {
    // Datetime field (datetime, daterange) - uses magic property.
    if (isset($item->date)) {
      $date = $item->date;
      if ($date instanceof DrupalDateTime && !$date->hasErrors()) {
        return $date->getPhpDateTime();
      }
      if ($date instanceof \DateTimeInterface) {
        return $date;
      }
    }

    // Timestamp fields (timestamp, created, changed).
    if (isset($item->value) && is_numeric($item->value)) {
      return (new \DateTimeImmutable())->setTimestamp((int) $item->value);
    }

    return NULL;
  }

  /**
   * Gets available datetime fields on the entity, excluding the current field.
   *
   * @return array
   *   An array of field labels keyed by field name.
   */
  protected function getAvailableDatetimeFields(): array {
    $options = [];
    $entity_type_id = $this->fieldDefinition->getTargetEntityTypeId();
    $bundle = $this->fieldDefinition->getTargetBundle();
    $current_field = $this->fieldDefinition->getName();

    $field_definitions = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions($entity_type_id, $bundle);

    $datetime_types = ['datetime', 'timestamp', 'created', 'changed'];

    foreach ($field_definitions as $field_name => $definition) {
      if ($field_name === $current_field) {
        continue;
      }
      if (in_array($definition->getType(), $datetime_types, TRUE)) {
        $options[$field_name] = $definition->getLabel();
      }
    }

    return $options;
  }

  /**
   * Gets the reference date based on formatter settings.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity being rendered.
   *
   * @return \DateTimeInterface|null
   *   The reference date, or NULL if reference field is empty.
   */
  protected function getReferenceDate($entity): ?\DateTimeInterface {
    if ($this->getSetting('reference_source') === 'now') {
      return new \DateTimeImmutable('now');
    }

    $reference_field = $this->getSetting('reference_field');
    if (empty($reference_field) || !$entity->hasField($reference_field)) {
      return new \DateTimeImmutable('now');
    }

    $field_items = $entity->get($reference_field);
    if ($field_items->isEmpty()) {
      return NULL;
    }

    return $this->getDateTimeFromItem($field_items->first());
  }

}
