<?php

declare(strict_types=1);

namespace Drupal\sheep_entities;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * List builder for Sheep Note entities.
 */
final class SheepNoteListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['id'] = $this->t('ID');
    $header['field_s_sheep_record'] = $this->t('Sheep record');
    $header['field_s_date'] = $this->t('Date');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\sheep_entities\SheepNoteInterface $entity */
    $sheep = $entity->get('field_s_sheep_record')->entity;
    $row['id'] = $entity->id();
    $row['field_s_sheep_record'] = $sheep ? $sheep->get('field_s_etag')->value : '';
    $row['field_s_date'] = $entity->get('field_s_date')->value;
    return $row + parent::buildRow($entity);
  }

}
