<?php

declare(strict_types=1);

namespace Drupal\sheep_migration\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Source plugin for LAMB lineage using ETAG-based lookups.
 *
 * Only yields rows where:
 * - Record exists in sheep_record
 * - Record does NOT already have sire/dam set
 * - LAMB file has valid (non-placeholder) sire/dam ETAG
 * - ETAG lookup returns exactly 1 match.
 *
 * @MigrateSource(
 *   id = "lamb_lineage",
 *   source_module = "sheep_migration"
 * )
 */
class LambLineage extends SourcePluginBase implements ContainerFactoryPluginInterface {

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

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
    $instance = new static($configuration, $plugin_id, $plugin_definition, $migration);
    $instance->database = $container->get('database');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function initializeIterator(): \Iterator {
    $path = $this->configuration['path'] ?? '';
    $full_path = DRUPAL_ROOT . '/' . $path;

    if (!file_exists($full_path)) {
      \Drupal::logger('sheep_migration')->error('LAMB file not found: @path', ['@path' => $full_path]);
      return new \EmptyIterator();
    }

    $handle = fopen($full_path, 'r');
    if ($handle === FALSE) {
      \Drupal::logger('sheep_migration')->error('Could not open LAMB file: @path', ['@path' => $full_path]);
      return new \EmptyIterator();
    }

    // Parse header row.
    $headers = fgetcsv($handle);
    $id10_idx = array_search('ID10', $headers, TRUE);
    $dam_idx = array_search('DAM', $headers, TRUE);
    $sire_idx = array_search('SIRE', $headers, TRUE);

    if ($id10_idx === FALSE) {
      \Drupal::logger('sheep_migration')->error('ID10 column not found in @path', ['@path' => $path]);
      fclose($handle);
      return new \EmptyIterator();
    }

    // Build ETAG lookup cache.
    $etag_cache = $this->buildEtagCache();

    // Track statistics.
    $stats = [
      'total' => 0,
      'no_record' => 0,
      'already_has_lineage' => 0,
      'no_valid_parent' => 0,
      'yielded' => 0,
    ];

    $rows = [];
    while (($row = fgetcsv($handle)) !== FALSE) {
      $stats['total']++;

      $id10 = trim($row[$id10_idx] ?? '');
      $dam_etag = trim($row[$dam_idx] ?? '');
      $sire_etag = trim($row[$sire_idx] ?? '');

      if (empty($id10)) {
        continue;
      }

      // Check if sheep record exists and get current lineage status.
      $record = $this->getRecordInfo($id10);
      if (!$record) {
        $stats['no_record']++;
        continue;
      }

      // Skip if already has both sire and dam.
      if ($record['has_sire'] && $record['has_dam']) {
        $stats['already_has_lineage']++;
        continue;
      }

      // Look up parent entity IDs.
      $dam_id = NULL;
      $sire_id = NULL;

      if (!$record['has_dam'] && $this->isValidEtag($dam_etag)) {
        $dam_id = $etag_cache[$dam_etag] ?? NULL;
      }

      if (!$record['has_sire'] && $this->isValidEtag($sire_etag)) {
        $sire_id = $etag_cache[$sire_etag] ?? NULL;
      }

      // Skip if no valid parent found.
      if ($dam_id === NULL && $sire_id === NULL) {
        $stats['no_valid_parent']++;
        continue;
      }

      $stats['yielded']++;
      $rows[] = [
        'id10' => $id10,
        'entity_id' => $record['id'],
        'dam_id' => $dam_id,
        'sire_id' => $sire_id,
      ];
    }

    fclose($handle);

    \Drupal::logger('sheep_migration')->notice('LambLineage: @total rows, @no_record no record, @has_lineage already has lineage, @no_parent no valid parent, @yielded to process.', [
      '@total' => $stats['total'],
      '@no_record' => $stats['no_record'],
      '@has_lineage' => $stats['already_has_lineage'],
      '@no_parent' => $stats['no_valid_parent'],
      '@yielded' => $stats['yielded'],
    ]);

    return new \ArrayIterator($rows);
  }

  /**
   * Static cache of ETAG -> entity ID mappings.
   *
   * @var array|null
   */
  protected static ?array $etagCache = NULL;

  /**
   * Builds a cache of ETAG -> entity ID mappings.
   *
   * Only includes ETAGs that map to exactly one record.
   * Cache is shared across all instances for performance.
   *
   * @return array
   *   Associative array of ETAG => entity ID.
   */
  protected function buildEtagCache(): array {
    // Return cached version if available.
    if (self::$etagCache !== NULL) {
      return self::$etagCache;
    }

    $cache = [];

    // Get all ETAGs with their counts.
    $query = $this->database->select('sheep_entities_sheep_record', 's')
      ->fields('s', ['id', 'field_s_etag']);
    $query->isNotNull('field_s_etag');
    $query->condition('field_s_etag', '', '<>');
    $query->condition('field_s_etag', '.', '<>');
    $results = $query->execute()->fetchAll();

    // Count occurrences.
    $counts = [];
    $ids = [];
    foreach ($results as $row) {
      $etag = $row->field_s_etag;
      $counts[$etag] = ($counts[$etag] ?? 0) + 1;
      $ids[$etag] = $row->id;
    }

    // Only include unique ETAGs.
    foreach ($counts as $etag => $count) {
      if ($count === 1) {
        $cache[$etag] = $ids[$etag];
      }
    }

    self::$etagCache = $cache;

    \Drupal::logger('sheep_migration')->notice('LambLineage: Built ETAG cache with @count unique entries.', [
      '@count' => count($cache),
    ]);

    return self::$etagCache;
  }

  /**
   * Gets record info for an ID10.
   *
   * @param string $id10
   *   The ID10 value.
   *
   * @return array|null
   *   Array with 'id', 'has_sire', 'has_dam' or NULL if not found.
   */
  protected function getRecordInfo(string $id10): ?array {
    $result = $this->database->select('sheep_entities_sheep_record', 's')
      ->fields('s', ['id', 'field_s_sire', 'field_s_dam'])
      ->condition('field_s_id10', $id10)
      ->execute()
      ->fetchObject();

    if (!$result) {
      return NULL;
    }

    return [
      'id' => $result->id,
      'has_sire' => !empty($result->field_s_sire),
      'has_dam' => !empty($result->field_s_dam),
    ];
  }

  /**
   * Checks if ETAG is valid (not a placeholder).
   *
   * @param string $etag
   *   The ETAG value.
   *
   * @return bool
   *   TRUE if valid.
   */
  protected function isValidEtag(string $etag): bool {
    if (empty($etag)) {
      return FALSE;
    }

    // Skip placeholders.
    $invalid = ['.', 'X0000', 'H0000', 'R0000', 'T0000', 'K0000', 'A0000', 'Z0000', 'L0000', '0', '00000'];
    if (in_array($etag, $invalid, TRUE)) {
      return FALSE;
    }

    // Skip any pattern matching letter + four zeros.
    if (preg_match('/^[A-Z]0{4}$/i', $etag)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function fields(): array {
    return [
      'id10' => $this->t('10-digit ID'),
      'entity_id' => $this->t('Sheep record entity ID'),
      'dam_id' => $this->t('Dam entity ID'),
      'sire_id' => $this->t('Sire entity ID'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds(): array {
    return [
      'id10' => [
        'type' => 'string',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function __toString(): string {
    return 'lamb_lineage';
  }

}
