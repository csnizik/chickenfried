<?php

declare(strict_types=1);

namespace Drupal\sheep_entities\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\sheep_entities\Entity\SheepRecord;

/**
 * Controller for rendering a SheepRecord at /sheep/{id}.
 */
final class SheepViewController extends ControllerBase {

  /**
   * Render the entity in the DEFAULT view mode and return its render array.
   *
   * Returning the entity build directly ensures the Twig used for /sheep/n
   * receives the FULL render array (all fields/formatters as configured),
   * just like the canonical entity display.
   *
   * Create/override your Twig as:
   *   - entity--sheep-entities-sheep-record.html.twig
   *   - or entity--sheep-entities-sheep-record--default.html.twig
   * (depending on your theme layer preference).
   *
   * @param \Drupal\sheep_entities\Entity\SheepRecord $sheep_entities_sheep_record
   *   The upcast SheepRecord from the route parameter.
   *
   * @return array
   *   The render array for the entity display (default view mode).
   */
  public function view(SheepRecord $sheep_entities_sheep_record): array {
    $view_builder = $this->entityTypeManager()->getViewBuilder('sheep_entities_sheep_record');
    $build = $view_builder->view($sheep_entities_sheep_record, 'default');

    // The entity view builder already carries correct cacheability metadata
    // (tags/contexts/max-age) for the entity and its fields. We simply return
    // the build so your Twig gets the entire render array.
    return $build;
  }

}
