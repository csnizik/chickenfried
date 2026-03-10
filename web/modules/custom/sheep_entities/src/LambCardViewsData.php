<?php

declare(strict_types=1);

namespace Drupal\sheep_entities;

use Drupal\views\EntityViewsData;

/**
 * Provides views data for Lamb Card entities.
 */
class SheepNoteViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData(): array {
    $data = parent::getViewsData();

    // Reverse relationship: from sheep_record to lamb_card
    // via field_s_sheep_record (base field) on lamb_card.
    $data['sheep_entities_sheep_record']['lamb_card_reverse'] = [
      'title' => t('Lamb Card'),
      'help' => t('Relate sheep records to their lamb card via field_s_sheep.'),
      'relationship' => [
        'id' => 'standard',
        'base' => 'sheep_entities_lamb_card',
        'base field' => 'field_s_sheep',
        'relationship field' => 'id',
        'label' => t('Lamb Card'),
      ],
    ];

    return $data;
  }

}
