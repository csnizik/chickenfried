<?php

declare(strict_types=1);

namespace Drupal\sheep_entities;

use Drupal\views\EntityViewsData;

/**
 * Provides views data for Sheep Note entities.
 */
class SheepNoteViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData(): array {
    $data = parent::getViewsData();

    // Reverse relationship: from sheep_record to sheep_note
    // via field_s_sheep_record (base field) on sheep_note.
    $data['sheep_entities_sheep_record']['sheep_note_reverse'] = [
      'title' => t('Sheep Notes'),
      'help' => t('Relate sheep records to their notes via field_s_sheep_record.'),
      'relationship' => [
        'id' => 'standard',
        'base' => 'sheep_entities_sheep_note',
        'base field' => 'field_s_sheep',
        'relationship field' => 'id',
        'label' => t('Sheep Notes'),
      ],
    ];

    return $data;
  }

}
