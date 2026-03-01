<?php

declare(strict_types=1);

namespace Drupal\sheep_entities;

use Drupal\views\EntityViewsData;

/**
 * Provides views data for Sheep Record entities.
 */
class SheepRecordViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData(): array {
    $data = parent::getViewsData();

    // Taxonomy reference fields that should use entity_reference filter.
    $taxonomy_fields = [
      'field_s_sex',
      'field_s_breed',
      'field_s_line',
      'field_s_disposal',
      'field_s_subtype_mating',
      'field_s_rearing_type',
    ];

    foreach ($taxonomy_fields as $field_name) {
      if (isset($data['sheep_entities_sheep_record'][$field_name])) {
        $data['sheep_entities_sheep_record'][$field_name]['filter']['id'] = 'entity_reference';
      }
    }

    return $data;
  }

}
