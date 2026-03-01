<?php

declare(strict_types=1);

namespace Drupal\nematode_migration\Plugin\migrate\source;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Source plugin for entities not attached to a group.
 *
 * Example usage:
 * @code
 * source:
 *   plugin: unattached_entities
 *   entity_type: nematode_entities_specimen
 *   group_plugin_id: nematode_entities_specimen
 *   exclude_group: 8
 * @endcode
 *
 * @MigrateSource(
 *   id = "unattached_entities",
 *   source_module = "nematode_migration"
 * )
 */
class UnattachedEntities extends SourcePluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs an UnattachedEntities plugin.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    MigrationInterface $migration,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ?MigrationInterface $migration = NULL,
  ): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function fields(): array {
    return [
      'id' => $this->t('Entity ID'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds(): array {
    return [
      'id' => [
        'type' => 'integer',
        'unsigned' => TRUE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function __toString(): string {
    $entity_type = $this->configuration['entity_type'] ?? 'unknown';
    return 'unattached_entities:' . $entity_type;
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator(): \Iterator {
    $entity_type = $this->configuration['entity_type'] ?? NULL;
    if (empty($entity_type)) {
      \Drupal::logger('nematode_migration')->error('UnattachedEntities: entity_type configuration is required.');
      return new \ArrayIterator([]);
    }

    // Validate entity type exists.
    if (!$this->entityTypeManager->hasDefinition($entity_type)) {
      \Drupal::logger('nematode_migration')->error('UnattachedEntities: entity_type @type does not exist.', ['@type' => $entity_type]);
      return new \ArrayIterator([]);
    }

    $storage = $this->entityTypeManager->getStorage($entity_type);

    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->execute();

    if (empty($ids)) {
      return new \ArrayIterator([]);
    }

    // Filter out entities already attached to the group.
    $exclude_group = $this->configuration['exclude_group'] ?? NULL;
    $group_plugin_id = $this->configuration['group_plugin_id'] ?? $entity_type;

    if ($exclude_group) {
      $ids = $this->filterAttachedEntities($ids, (int) $exclude_group, $group_plugin_id);
    }

    if (empty($ids)) {
      return new \ArrayIterator([]);
    }

    $rows = [];
    foreach ($ids as $id) {
      $rows[] = [
        'id' => (int) $id,
      ];
    }

    return new \ArrayIterator($rows);
  }

  /**
   * Filter out entity IDs already attached to the group.
   *
   * @param array $ids
   *   Array of entity IDs to filter.
   * @param int $group_id
   *   The group ID to check relationships against.
   * @param string $group_plugin_id
   *   The group relationship plugin ID.
   *
   * @return array
   *   Filtered array of entity IDs not yet attached to the group.
   */
  protected function filterAttachedEntities(array $ids, int $group_id, string $group_plugin_id): array {
    $relationship_storage = $this->entityTypeManager->getStorage('group_relationship');

    // Process in chunks to avoid query limits on large datasets.
    $chunk_size = 500;
    $attached_ids = [];

    foreach (array_chunk($ids, $chunk_size) as $chunk) {
      $existing = $relationship_storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('gid', $group_id)
        ->condition('plugin_id', $group_plugin_id)
        ->condition('entity_id', $chunk, 'IN')
        ->execute();

      if (!empty($existing)) {
        $relationships = $relationship_storage->loadMultiple($existing);
        foreach ($relationships as $rel) {
          if ($rel instanceof GroupRelationshipInterface) {
            $attached_ids[] = (int) $rel->getEntityId();
          }
        }
      }
    }

    if (empty($attached_ids)) {
      return $ids;
    }

    return array_values(array_diff($ids, $attached_ids));
  }

  /**
   * {@inheritdoc}
   */
  public function allRowsProcessed(): bool {
    $source_count = $this->count();
    $imported_count = $this->migration->getIdMap()->importedCount();
    $calculated_unprocessed = $source_count - $imported_count;

    if ($source_count === 0) {
      if ($calculated_unprocessed !== 0) {
        \Drupal::logger('nematode_migration')->info(
          'Migration @id: Overriding allRowsProcessed() to TRUE. source_count=@source, imported_count=@imported, calculated_unprocessed=@unprocessed',
          [
            '@id' => $this->migration->id(),
            '@source' => $source_count,
            '@imported' => $imported_count,
            '@unprocessed' => $calculated_unprocessed,
          ]
        );
      }
      return TRUE;
    }

    return $source_count === $imported_count;
  }

}
