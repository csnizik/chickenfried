<?php

declare(strict_types=1);

namespace Drupal\sheep_entities;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * List builder for Sheep Record entities.
 */
final class SheepRecordListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['id'] = $this->t('ID');
    $header['field_s_etag'] = $this->t('Ear tag');
    $header['field_s_id10'] = $this->t('ID10');
    $header['field_s_birth_year'] = $this->t('Birth year');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\sheep_entities\SheepRecordInterface $entity */
    $row['id'] = $entity->id();
    $row['field_s_etag'] = $entity->get('field_s_etag')->value;
    $row['field_s_id10'] = $entity->get('field_s_id10')->value;
    $row['field_s_birth_year'] = $entity->get('field_s_birth_year')->value;
    return $row + parent::buildRow($entity);
  }

}
