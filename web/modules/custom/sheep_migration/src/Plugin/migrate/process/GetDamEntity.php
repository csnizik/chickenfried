<?php

namespace Drupal\sheep_migration\Plugin\migrate\process;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Given a sheep_record entity ID, return the entity ID of its dam.
 *
 * Usage:
 * @code
 * process:
 *   field_s_sheep_record:
 *     -
 *       plugin: entity_lookup
 *       source: ID10
 *       entity_type: sheep_entities_sheep_record
 *       value_key: field_s_id10
 *     -
 *       plugin: get_dam_entity
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "get_dam_entity"
 * )
 */
class GetDamEntity extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (empty($value)) {
      return NULL;
    }

    $storage = \Drupal::entityTypeManager()
      ->getStorage('sheep_entities_sheep_record');
    $sheep = $storage->load($value);

    if (!$sheep instanceof FieldableEntityInterface) {
      return NULL;
    }

    if ($sheep->get('field_s_dam')->isEmpty()) {
      return NULL;
    }

    return $sheep->get('field_s_dam')->target_id;
  }

}
