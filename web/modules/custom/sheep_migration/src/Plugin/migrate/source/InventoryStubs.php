<?php

declare(strict_types=1);

namespace Drupal\sheep_migration\Plugin\migrate\source;

use Drupal\Core\Database\Connection;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Extracts unique ID10 values from INV CSVs that don't exist in sheep_record.
 *
 * This source plugin is idempotent: it only yields IDs for sheep_records
 * that do not already exist in the database. Safe to run multiple times.
 *
 * Configuration:
 * - paths: Array of CSV file paths relative to DRUPAL_ROOT.
 * - id_column: Column name for ID10 (default: ID10).
 *
 * Example usage:
 * @code
 * source:
 *   plugin: inventory_stubs
 *   paths:
 *     - modules/custom/sheep_migration/data/inv/INV2010.preprocessed.csv
 *     - modules/custom/sheep_migration/data/inv/INV2011.preprocessed.csv
 *   id_column: ID10
 * @endcode
 *
 * @MigrateSource(
 *   id = "inventory_stubs",
 *   source_module = "sheep_migration"
 * )
 */
class InventoryStubs extends SourcePluginBase implements ContainerFactoryPluginInterface {

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
    $id_column = $this->configuration['id_column'] ?? 'ID10';

    // Collect all unique ID10 values from all CSV files.
    $all_ids = [];

    foreach ($paths as $path) {
      $full_path = DRUPAL_ROOT . '/' . $path;
      if (!file_exists($full_path)) {
        \Drupal::logger('sheep_migration')->warning('INV file not found: @path', ['@path' => $full_path]);
        continue;
      }

      $handle = fopen($full_path, 'r');
      if ($handle === FALSE) {
        \Drupal::logger('sheep_migration')->error('Could not open INV file: @path', ['@path' => $full_path]);
        continue;
      }

      $headers = fgetcsv($handle);
      $id_idx = array_search($id_column, $headers, TRUE);

      if ($id_idx === FALSE) {
        \Drupal::logger('sheep_migration')->error('Column @col not found in @path', [
          '@col' => $id_column,
          '@path' => $path,
        ]);
        fclose($handle);
        continue;
      }

      while (($row = fgetcsv($handle)) !== FALSE) {
        if (isset($row[$id_idx])) {
          $id10 = trim($row[$id_idx]);
          if ($id10 !== '' && $this->isValidId10($id10)) {
            $all_ids[$id10] = TRUE;
          }
        }
      }
      fclose($handle);
    }

    if (empty($all_ids)) {
      \Drupal::logger('sheep_migration')->notice('No ID10 values found in INV files.');
      return new \EmptyIterator();
    }

    // Query existing sheep_records to find which IDs already exist.
    $existing_ids = [];
    $id_chunks = array_chunk(array_keys($all_ids), 1000);

    foreach ($id_chunks as $chunk) {
      $result = $this->database->select('sheep_entities_sheep_record', 's')
        ->fields('s', ['field_s_id10'])
        ->condition('field_s_id10', $chunk, 'IN')
        ->execute()
        ->fetchCol();
      $existing_ids = array_merge($existing_ids, $result);
    }

    // Yield only IDs that don't exist.
    $missing_ids = array_diff(array_keys($all_ids), $existing_ids);
    sort($missing_ids);

    \Drupal::logger('sheep_migration')->notice('InventoryStubs: @total unique IDs, @existing exist, @missing to create.', [
      '@total' => count($all_ids),
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
    if (strlen($id10) !== 10 || !ctype_digit($id10)) {
      return FALSE;
    }
    $year = (int) substr($id10, 0, 4);
    return $year >= 1950 && $year <= 2099;
  }

  /**
   * Extracts 4-digit year from ID10.
   */
  protected function extractYear(string $id10): string {
    return substr($id10, 0, 4);
  }

  /**
   * Extracts 2-digit breed code from ID10.
   */
  protected function extractBreedCode(string $id10): string {
    return substr($id10, 4, 2);
  }

  /**
   * Extracts last 4 digits (ETAG portion) from ID10.
   */
  protected function extractEtagDigits(string $id10): string {
    return substr($id10, 6, 4);
  }

  /**
   * {@inheritdoc}
   */
  public function fields(): array {
    return [
      'id10' => $this->t('10-digit ID'),
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
    return 'inventory_stubs';
  }

}
