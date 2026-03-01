<?php

declare(strict_types=1);

namespace Drupal\sheep_migration\Plugin\migrate\source;

use Drupal\Core\Database\Connection;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Extracts unique parent IDs from PEDI CSVs that don't exist in sheep_record.
 *
 * This source plugin is idempotent: it only yields IDs for sheep_records
 * that do not already exist in the database. Safe to run multiple times
 * and supports incremental backfills (e.g., 2010-2025 now, 1990-2009 later).
 *
 * Configuration:
 * - paths: Array of CSV file paths relative to DRUPAL_ROOT.
 * - sire_column: Column name for sire ID (default: SIREID).
 * - dam_column: Column name for dam ID (default: DAMID).
 *
 * Example usage:
 * @code
 * source:
 *   plugin: pedigree_parent_stubs
 *   paths:
 *     - modules/custom/sheep_migration/data/pedi/PEDI2010.csv
 *     - modules/custom/sheep_migration/data/pedi/PEDI2011.csv
 *   sire_column: SIREID
 *   dam_column: DAMID
 * @endcode
 *
 * @MigrateSource(
 *   id = "pedigree_parent_stubs",
 *   source_module = "sheep_migration"
 * )
 */
class PedigreeParentStubs extends SourcePluginBase implements ContainerFactoryPluginInterface {

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
    $paths = $this->configuration['paths'] ?? [];
    $sire_column = $this->configuration['sire_column'] ?? 'SIREID';
    $dam_column = $this->configuration['dam_column'] ?? 'DAMID';

    // Collect all unique parent IDs from all CSV files.
    $all_parent_ids = [];

    foreach ($paths as $path) {
      $full_path = DRUPAL_ROOT . '/' . $path;
      if (!file_exists($full_path)) {
        // Log warning but continue with other files.
        \Drupal::logger('sheep_migration')->warning('PEDI file not found: @path', ['@path' => $full_path]);
        continue;
      }

      $handle = fopen($full_path, 'r');
      if ($handle === FALSE) {
        \Drupal::logger('sheep_migration')->error('Could not open PEDI file: @path', ['@path' => $full_path]);
        continue;
      }

      $headers = fgetcsv($handle);
      $sire_idx = array_search($sire_column, $headers, TRUE);
      $dam_idx = array_search($dam_column, $headers, TRUE);

      if ($sire_idx === FALSE && $dam_idx === FALSE) {
        \Drupal::logger('sheep_migration')->error('Neither @sire nor @dam column found in @path', [
          '@sire' => $sire_column,
          '@dam' => $dam_column,
          '@path' => $path,
        ]);
        fclose($handle);
        continue;
      }

      while (($row = fgetcsv($handle)) !== FALSE) {
        // Collect sire ID if valid.
        if ($sire_idx !== FALSE && isset($row[$sire_idx])) {
          $sire_id = trim($row[$sire_idx]);
          if ($sire_id !== '' && $this->isValidId10($sire_id)) {
            $all_parent_ids[$sire_id] = TRUE;
          }
        }
        // Collect dam ID if valid.
        if ($dam_idx !== FALSE && isset($row[$dam_idx])) {
          $dam_id = trim($row[$dam_idx]);
          if ($dam_id !== '' && $this->isValidId10($dam_id)) {
            $all_parent_ids[$dam_id] = TRUE;
          }
        }
      }
      fclose($handle);
    }

    if (empty($all_parent_ids)) {
      \Drupal::logger('sheep_migration')->notice('No parent IDs found in PEDI files.');
      return new \EmptyIterator();
    }

    // Query existing sheep_records to find which IDs already exist.
    // Process in chunks to avoid query size limits.
    $existing_ids = [];
    $id_chunks = array_chunk(array_keys($all_parent_ids), 1000);

    foreach ($id_chunks as $chunk) {
      $result = $this->database->select('sheep_entities_sheep_record', 's')
        ->fields('s', ['field_s_id10'])
        ->condition('field_s_id10', $chunk, 'IN')
        ->execute()
        ->fetchCol();
      $existing_ids = array_merge($existing_ids, $result);
    }

    // Yield only IDs that don't exist.
    $missing_ids = array_diff(array_keys($all_parent_ids), $existing_ids);
    sort($missing_ids);

    \Drupal::logger('sheep_migration')->notice('PedigreeParentStubs: @total unique parents, @existing exist, @missing to create.', [
      '@total' => count($all_parent_ids),
      '@existing' => count($existing_ids),
      '@missing' => count($missing_ids),
    ]);

    foreach ($missing_ids as $id10) {
      $id10 = (string) $id10;
      yield [
        'id10' => $id10,
        'year' => $this->extractYear($id10),
        'breed_code' => $this->extractBreedCode($id10),
        'etag_digits' => $this->extractEtagDigits($id10),
      ];
    }

  }

  /**
   * Validates ID10 format (10 digits, starts with year).
   *
   * @param string $id10
   *   The ID10 to validate.
   *
   * @return bool
   *   TRUE if valid format.
   */
  protected function isValidId10(string $id10): bool {
    // Must be exactly 10 characters, all digits.
    if (strlen($id10) !== 10 || !ctype_digit($id10)) {
      return FALSE;
    }
    // First 4 digits should be a reasonable year (1950-2099).
    $year = (int) substr($id10, 0, 4);
    return $year >= 1950 && $year <= 2099;
  }

  /**
   * Extracts 4-digit year from ID10.
   *
   * @param string $id10
   *   The ID10 value.
   *
   * @return string
   *   The 4-digit year.
   */
  protected function extractYear(string $id10): string {
    return substr($id10, 0, 4);
  }

  /**
   * Extracts 2-digit breed code from ID10.
   *
   * @param string $id10
   *   The ID10 value.
   *
   * @return string
   *   The 2-digit breed code.
   */
  protected function extractBreedCode(string $id10): string {
    return substr($id10, 4, 2);
  }

  /**
   * Extracts last 4 digits (ETAG portion) from ID10.
   *
   * @param string $id10
   *   The ID10 value.
   *
   * @return string
   *   The 4-digit ETAG portion.
   */
  protected function extractEtagDigits(string $id10): string {
    return substr($id10, 6, 4);
  }

  /**
   * {@inheritdoc}
   */
  public function fields(): array {
    return [
      'id10' => $this->t('10-digit parent ID'),
      'year' => $this->t('4-digit birth year extracted from ID10'),
      'breed_code' => $this->t('2-digit breed code extracted from ID10'),
      'etag_digits' => $this->t('Last 4 digits of ID10 (ETAG numeric portion)'),
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
    return 'pedigree_parent_stubs';
  }

}
