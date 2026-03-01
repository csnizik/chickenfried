<?php

namespace Drupal\sheep_migration\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Process castration (CUT) field based on SEX value.
 *
 * Females (SEX=2) always get 0 (not castrated).
 * Males (SEX=1,3) get 1 if CUT='Y', otherwise 0.
 *
 * Usage:
 * @code
 * field_s_cut:
 *   plugin: process_cut
 *   source:
 *     - SEX
 *     - CUT
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "process_cut",
 *   handle_multiples = TRUE
 * )
 */
class ProcessCut extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // Expect array with [SEX, CUT].
    if (!is_array($value) || count($value) < 2) {
      return 0;
    }

    [$sex, $cut] = $value;

    // Normalize SEX value.
    $sex = trim((string) $sex);

    // Females (SEX=2) are never castrated.
    if ($sex === '2') {
      return 0;
    }

    // Males (SEX=1 or 3): check CUT value.
    if ($sex === '1' || $sex === '3') {
      // Normalize CUT value: trim and uppercase.
      $cut = strtoupper(trim((string) $cut));

      // If CUT is 'Y', return 1 (castrated).
      if ($cut === 'Y') {
        return 1;
      }

      // Otherwise return 0 (intact).
      return 0;
    }

    // Unknown SEX value: default to 0.
    return 0;
  }

}
