<?php

/**
 * @file
 * Delete sheep_record entities matching ID10 values in a CSV.
 *
 * CSV format: single column, header row "ID10", one ID10 per line.
 * Edit $path below, then run: ddev drush scr path/to/delete_by_id10.php.
 */

// EDIT THIS PATH before running.
$path = '/var/www/html/web/modules/custom/sheep_migration/data/_preprocessed/JUNK-ID10S-DEL.csv';

if (!file_exists($path)) {
  throw new \RuntimeException("File not found: $path");
}

$storage = \Drupal::entityTypeManager()->getStorage('sheep_entities_sheep_record');
$handle = fopen($path, 'r');
// Skip header row.
fgetcsv($handle);

$deleted = 0;
$not_found = 0;
$batch = [];

while ($row = fgetcsv($handle)) {
  $id10 = trim($row[0]);
  if (empty($id10)) {
    continue;
  }

  $ids = $storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('field_s_id10', $id10)
    ->execute();

  if ($ids) {
    $batch = array_merge($batch, $ids);
    $deleted += count($ids);
  }
  else {
    $not_found++;
  }

  // Flush in chunks of 100 to manage memory.
  if (count($batch) >= 100) {
    $storage->delete($storage->loadMultiple($batch));
    $storage->resetCache($batch);
    $batch = [];
  }
}

// Flush remainder.
if ($batch) {
  $storage->delete($storage->loadMultiple($batch));
}

fclose($handle);
echo "Deleted: $deleted | Not found: $not_found\n";
