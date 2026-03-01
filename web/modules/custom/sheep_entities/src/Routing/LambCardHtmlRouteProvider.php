<?php

declare(strict_types=1);

namespace Drupal\sheep_entities\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Symfony\Component\Routing\Route;

/**
 * HTML routes for Lamb Card entities.
 */
final class LambCardHtmlRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  protected function getCanonicalRoute(EntityTypeInterface $entity_type): ?Route {
    // Keep canonical route simple: land on edit form.
    return $this->getEditFormRoute($entity_type);
  }

}
