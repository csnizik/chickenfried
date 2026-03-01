<?php

declare(strict_types=1);

namespace Drupal\sheep_migration\Plugin\migrate\source;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Source plugin for taxonomy terms from multiple vocabularies.
 *
 * Optionally filters out terms already attached to a group.
 *
 * Example usage:
 * @code
 * source:
 *   plugin: multi_vocab_terms
 *   vocabularies:
 *     - s_disposal_codes
 *     - s_sex
 *     - s_breeds
 *     - s_lines
 *   exclude_group: 4
 * @endcode
 *
 * @MigrateSource(
 *   id = "multi_vocab_terms",
 *   source_module = "taxonomy"
 * )
 */
class MultiVocabTerms extends SourcePluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a MultiVocabTerms plugin.
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
      'tid' => $this->t('Term ID'),
      'vid' => $this->t('Vocabulary ID'),
      'name' => $this->t('Term name'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds(): array {
    return [
      'tid' => [
        'type' => 'integer',
        'unsigned' => TRUE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function __toString(): string {
    return 'multi_vocab_terms';
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator(): \Iterator {
    $vocabularies = $this->configuration['vocabularies'] ?? [];
    if (empty($vocabularies)) {
      return new \ArrayIterator([]);
    }

    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $tids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', $vocabularies, 'IN')
      ->execute();

    if (empty($tids)) {
      return new \ArrayIterator([]);
    }

    // If exclude_group is set, filter out terms already attached to that group.
    $exclude_group = $this->configuration['exclude_group'] ?? NULL;
    if ($exclude_group) {
      $tids = $this->filterAttachedTerms($tids, (int) $exclude_group, $vocabularies);
    }

    if (empty($tids)) {
      return new \ArrayIterator([]);
    }

    $terms = $storage->loadMultiple($tids);
    $rows = [];
    foreach ($terms as $term) {
      $rows[] = [
        'tid' => (int) $term->id(),
        'vid' => $term->bundle(),
        'name' => $term->label(),
      ];
    }

    return new \ArrayIterator($rows);
  }

  /**
   * Filter out term IDs that already have a group_relationship to the group.
   *
   * @param array $tids
   *   Array of term IDs to filter.
   * @param int $group_id
   *   The group ID to check relationships against.
   * @param array $vocabularies
   *   Array of vocabulary IDs.
   *
   * @return array
   *   Filtered array of term IDs not yet attached to the group.
   */
  protected function filterAttachedTerms(array $tids, int $group_id, array $vocabularies): array {
    $relationship_storage = $this->entityTypeManager->getStorage('group_relationship');

    // Build plugin_id list for these vocabularies.
    $plugin_ids = [];
    foreach ($vocabularies as $vid) {
      $plugin_ids[] = 'group_term:' . $vid;
    }

    // Find existing relationships.
    $existing = $relationship_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('gid', $group_id)
      ->condition('plugin_id', $plugin_ids, 'IN')
      ->condition('entity_id', $tids, 'IN')
      ->execute();

    if (empty($existing)) {
      return $tids;
    }

    // Load relationships to get their entity_ids.
    $relationships = $relationship_storage->loadMultiple($existing);
    $attached_tids = [];
    foreach ($relationships as $rel) {
      if ($rel instanceof GroupRelationshipInterface) {
        $attached_tids[] = (int) $rel->getEntityId();
      }
    }

    return array_diff($tids, $attached_tids);
  }

  /**
   * {@inheritdoc}
   */
  public function allRowsProcessed(): bool {
    $source_count = $this->count();
    $imported_count = $this->migration->getIdMap()->importedCount();
    $calculated_unprocessed = $source_count - $imported_count;

    // If no unattached terms remain, consider migration complete.
    if ($source_count === 0) {
      // Log diagnostic info for troubleshooting.
      if ($calculated_unprocessed !== 0) {
        \Drupal::logger('sheep_migration')->info(
        'Migration @id: Overriding allRowsProcessed() to TRUE. source_count=@source, imported_count=@imported,
         calculated_unprocessed=@unprocessed',
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

    // Default logic: all rows processed when source count == imported count.
    return $source_count === $imported_count;
  }

}
