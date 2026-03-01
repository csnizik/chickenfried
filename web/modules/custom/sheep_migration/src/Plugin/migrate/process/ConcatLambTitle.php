<?php

namespace Drupal\sheep_migration\Plugin\migrate\process;

use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Concatenates YR, DAYBRN, DAM, and CDNO to create a title field.
 *
 * @MigrateProcessPlugin(
 *   id = "concat_lamb_title"
 * )
 */
class ConcatLambTitle extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $yr = $row->getSourceProperty('YR');
    $daybrn = $row->getSourceProperty('DAYBRN');
    $dam = $row->getSourceProperty('DAM');
    $cdno = $row->getSourceProperty('CDNO');
    if (empty($cdno)) {
      $cdno = 0;
    }

    // Check if any required values are empty.
    $missing_fields = [];
    $field_values = [
      'YR' => $yr,
      'DAYBRN' => $daybrn,
      'DAM' => $dam,
      'CDNO' => $cdno,
    ];

    foreach ($field_values as $field_name => $field_value) {
      if (empty($field_value)) {
        $missing_fields[] = $field_name;
      }
    }

    if (!empty($missing_fields)) {
      $source_row_number = 'unknown';

      if ($row->hasSourceProperty('csvRowNumber')) {
        $source_row_number = $row->getSourceProperty('csvRowNumber') + 2;
      }
      elseif ($row->hasSourceProperty('MigrateSourceRowNumber')) {
        $source_row_number = $row->getSourceProperty('MigrateSourceRowNumber') + 2;
      }
      else {
        // As a fallback, set to 'unknown' since row number is not available.
        $source_row_number = 'unknown';
      }

      // Build the log message with field values.
      $log_parts = [];
      foreach ($field_values as $field_name => $field_value) {
        $display_value = empty($field_value) ? 'missing' : $field_value;
        $log_parts[] = "{$field_name}: {$display_value}";
      }

      $log_message = "Skipped row {$source_row_number} (" . implode(', ', $log_parts) . ")";

      // Log the message and skip the row.
      $migrate_executable->saveMessage($log_message);

      throw new MigrateSkipRowException($log_message);
    }

    // Concatenate the values: YR-DAYBRN|DAM|CDNO.
    return "{$yr}{$daybrn}{$dam}.{$cdno}";
  }

}
