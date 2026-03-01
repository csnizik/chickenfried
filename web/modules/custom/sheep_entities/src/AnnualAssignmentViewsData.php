<?php

declare(strict_types=1);

namespace Drupal\sheep_entities;

use Drupal\views\EntityViewsData;

/**
 * Provides views data for Annual Assignment entities.
 */
class AnnualAssignmentViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData(): array {
    $data = parent::getViewsData();

    // Add reverse relationship: from sheep_record to annual_assignment
    // via field_s_sheep on the annual assignment entity.
    $data['sheep_entities_sheep_record']['annual_assignment_reverse'] = [
      'title' => t('Annual Assignments'),
      'help' => t('Relate sheep records to their annual assignments (reverse of field_s_sheep on Annual Assignment).'),
      'relationship' => [
        'id' => 'entity_reverse',
        'field_name' => 'field_s_sheep',
        'entity_type' => 'sheep_entities_annual_assignment',
        'field table' => 'sheep_entities_annual_assignment',
        'field field' => 'field_s_sheep',
        'base' => 'sheep_entities_annual_assignment',
        'base field' => 'id',
        'label' => t('Annual Assignments (reverse from field_s_sheep)'),
      ],
    ];

    return $data;
  }

}
