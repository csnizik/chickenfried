<?php

declare(strict_types=1);

namespace Drupal\sheep_entities;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Provides a list controller for the observation record entity type.
 */
final class ObservationRecordListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['id'] = $this->t('ID');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\sheep_entities\ObservationRecordInterface $entity */
    $row['id'] = $entity->id();
    return $row + parent::buildRow($entity);
  }

}
