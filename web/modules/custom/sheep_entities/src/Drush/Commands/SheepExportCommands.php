<?php

declare(strict_types=1);

namespace Drupal\sheep_entities\Drush\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for exporting sheep records.
 */
final class SheepExportCommands extends DrushCommands {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  /**
   * Export sheep records to CSV format.
   *
   * @param array $options
   *   Command options including limit and output file path.
   *
   * @throws \RuntimeException
   *   When the output file cannot be opened.
   */
  #[CLI\Command(name: 'sheep:export-csv', aliases: ['sex'])]
  #[CLI\Option(name: 'limit', description: 'Limit number of records (0 = all)')]
  #[CLI\Option(name: 'output', description: 'Output file path')]
  #[CLI\Usage(name: 'sheep:export-csv --output=/tmp/sheep-dump.csv', description: 'Export all sheep records to CSV')]
  public function exportCsv(
    array $options = ['limit' => 0, 'output' => '/tmp/sheep-export.csv'],
  ): void {
    $storage = $this->entityTypeManager->getStorage('sheep_entities_sheep_record');

    $query = $storage->getQuery()->accessCheck(FALSE)->sort('id');
    if ($options['limit'] > 0) {
      $query->range(0, (int) $options['limit']);
    }
    $ids = $query->execute();

    $handle = fopen($options['output'], 'w');
    if (!$handle) {
      throw new \RuntimeException('Cannot open output file: ' . $options['output']);
    }

    // Define columns: field_name => human label.
    // Entity ref fields get resolved to their label.
    $columns = [
      'id' => 'Entity ID',
      'field_s_id10' => 'ID10',
      'field_s_etag' => 'Ear Tag',
      'field_s_breed' => 'Breed',
      'field_s_sex' => 'Sex',
      'field_s_line' => 'Line',
      'field_s_subtype_mating' => 'STM',
      'field_s_sire' => 'Sire',
      'field_s_dam' => 'Dam',
      'field_s_foster_dam' => 'Foster Dam',
      'field_s_birth_date' => 'Birth Date',
      'field_s_type_of_birth' => 'Type of Birth',
      'field_s_card_num' => 'Card Number',
      'field_s_color' => 'Color',
      'field_s_cut' => 'Cut',
      'field_s_inbreeding' => 'Inbreeding',
      'field_s_disposal' => 'Disposal Code',
      'field_s_preliminary_disp' => 'Preliminary Disposal',
      'field_s_cause' => 'Cause',
      'field_s_disposal_date' => 'Disposal Date',
      'field_s_dna' => 'DNA',
      'field_s_altid' => 'Alternate ID',
      'field_s_scrapie_tag' => 'Scrapie Tag',
      'field_s_rearing_type' => 'Rearing Type',
      'field_s_rearing_type_equiv' => 'TBRE',
      'field_s_rearing_type_17' => 'TBR17',
      'field_s_is_purchased' => 'Purchased',
      'field_s_purchased_date' => 'Purchased Date',
      'field_s_is_unidentified_parent' => 'Unidentified Parent',
      'field_s_migration_legacy_data' => 'Legacy Data',
      'field_s_migration_source' => 'Migration Source',
    ];

    // Write header.
    fputcsv($handle, array_values($columns));

    $entityRefFields = [
      'field_s_breed', 'field_s_sex', 'field_s_line',
      'field_s_subtype_mating', 'field_s_sire', 'field_s_dam',
      'field_s_foster_dam', 'field_s_disposal', 'field_s_preliminary_disp',
      'field_s_cause', 'field_s_rearing_type',
    ];

    $multiValueFields = [
      'field_s_migration_legacy_data',
      'field_s_migration_source',
      'field_s_migration_notes',
    ];

    $total = count($ids);
    $batch = 0;
    foreach (array_chunk($ids, 200) as $chunk) {
      $entities = $storage->loadMultiple($chunk);
      foreach ($entities as $entity) {
        $row = [];
        foreach (array_keys($columns) as $field) {
          if ($field === 'id') {
            $row[] = $entity->id();
            continue;
          }

          if (in_array($field, $multiValueFields, TRUE)) {
            // Concatenate multi-value with pipe.
            $values = [];
            foreach ($entity->get($field) as $item) {
              $values[] = $item->value;
            }
            $row[] = implode('|', $values);
            continue;
          }

          if (in_array($field, $entityRefFields, TRUE)) {
            $ref = $entity->get($field)->entity;
            $row[] = $ref ? $ref->label() : '';
            continue;
          }

          $row[] = $entity->get($field)->value ?? '';
        }
        fputcsv($handle, $row);
      }

      // Free memory.
      $storage->resetCache($chunk);

      $batch += count($chunk);
      $this->io()->writeln("Exported $batch / $total");
    }

    fclose($handle);
    $this->io()->success("Exported $total records to " . $options['output']);
  }

}
