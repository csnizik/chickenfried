<?php

declare(strict_types=1);

namespace Drupal\sheep_entities;

use Drupal\views\EntityViewsData;

/**
 * Provides views data for Observation Record entities.
 */
class ObservationRecordViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData(): array {
    $data = parent::getViewsData();

    // Reverse relationship: from sheep_record to observation_record
    // via field_s_sheep_record (base field) on observation_record.
    $data['sheep_entities_sheep_record']['observation_record_reverse'] = [
      'title' => t('Observation Record'),
      'help' => t('Relate sheep records to an Observation Record via field_s_sheep.'),
      'relationship' => [
        'id' => 'standard',
        'base' => 'sheep_entities_observation_rec',
        'base field' => 'field_s_sheep',
        'relationship field' => 'id',
        'label' => t('Observation Record'),
      ],
    ];

    return $data;
  }

}
