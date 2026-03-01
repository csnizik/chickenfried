<?php

declare(strict_types=1);

namespace Drupal\sheep_entities\Controller;

use Drupal\sheep_entities\Entity\SheepRecord;

/**
 * Title callback for /sheep/{sheep_entities_sheep_record}.
 */
final class SheepTitle {

  /**
   * Builds a friendly page title from ETAG, falling back to the ID.
   *
   * @param \Drupal\sheep_entities\Entity\SheepRecord $sheep_entities_sheep_record
   *   The upcast SheepRecord entity from the route.
   *
   * @return string
   *   The page title.
   */
  public static function title(SheepRecord $sheep_entities_sheep_record): string {
    if ($sheep_entities_sheep_record->hasField('field_s_etag')
      && !$sheep_entities_sheep_record->get('field_s_etag')->isEmpty()) {
      return (string) $sheep_entities_sheep_record->get('field_s_etag')->value;
    }
    return 'Sheep record ' . $sheep_entities_sheep_record->id();
  }

}
