<?php

declare(strict_types=1);

namespace Drupal\nematode_entities;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * List builder for Nematode entities.
 */
final class SpecimenListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['id'] = $this->t('ID');
    $header['field_n_entry_number'] = $this->t('Entry number');
    $header['field_n_nematode_genus'] = $this->t('Nematode genus');
    $header['field_n_nematode_species'] = $this->t('Nematode species');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\nematode_entities\SpecimenInterface $entity */
    $row['id'] = $entity->id();
    $row['field_n_entry_number'] = $entity->get('field_n_entry_number')->value;
    $row['field_n_nematode_genus'] = $entity->get('field_n_nematode_genus')->value;
    $row['field_n_nematode_species'] = $entity->get('field_n_nematode_species')->value;
    return $row + parent::buildRow($entity);
  }

}
