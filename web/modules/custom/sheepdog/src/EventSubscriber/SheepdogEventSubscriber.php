<?php

declare(strict_types=1);

namespace Drupal\sheepdog\EventSubscriber;

use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigratePreRowSaveEvent;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Sheepdog migration event subscriber - keeping your migrations on track!
 *
 * Like a faithful sheepdog, this service watches over your migrations,
 * logging detailed changes and keeping everything in line.
 */
class SheepdogEventSubscriber implements EventSubscriberInterface {

  /**
   * The main logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The edit-specific logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $editLogger;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Temporary storage for original entity values.
   *
   * @var array
   */
  protected $originalEntityValues = [];

  /**
   * Constructs a new SheepdogEventSubscriber.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;

    $config = $this->configFactory->get('sheepdog.settings');
    $mainChannel = $config->get('logging.main_channel') ?: 'sheepdog';
    $editsChannel = $config->get('logging.edits_channel') ?: 'sheepdog_edits';

    $this->logger = $logger_factory->get($mainChannel);
    $this->editLogger = $logger_factory->get($editsChannel);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      MigrateEvents::PRE_ROW_SAVE => ['onPreSave'],
      MigrateEvents::POST_ROW_SAVE => ['onPostSave'],
    ];
  }

  /**
   * Store original entity values before migration update.
   *
   * Like a good dog, we fetch the current state before any changes.
   */
  public function onPreSave(MigratePreRowSaveEvent $event): void {
    $migration = $event->getMigration();
    $row = $event->getRow();
    $config = $this->configFactory->get('sheepdog.settings');

    // Get configured ID fields or use defaults.
    $configuredIdFields = $config->get('tracking.id_fields') ?: ['id', 'source_id', 'ID10'];
    $primaryIdField = $config->get('tracking.primary_id_field') ?: '';

    // Extract source ID fields from migration configuration.
    $ids_config = $migration->getSourceConfiguration()['ids'] ?? [];
    $id_fields = [];
    foreach ($ids_config as $k => $v) {
      if (is_int($k)) {
        if (is_string($v)) {
          $id_fields[] = $v;
        }
        elseif (is_array($v) && isset($v['source'])) {
          $id_fields[] = $v['source'];
        }
      }
      else {
        $id_fields[] = $k;
      }
    }

    // Fallback to configured defaults if none found in migration.
    if (empty($id_fields)) {
      $id_fields = $configuredIdFields;
    }

    // Build source values map.
    $source_values = [];
    foreach ($id_fields as $idx => $field) {
      $val = $this->getSourceValue($row, $field, $idx);
      $source_values[$field ?? (string) $idx] = $val !== NULL ? (string) $val : '';
    }

    // Check if any ID fields have values.
    $has_any = FALSE;
    foreach ($source_values as $v) {
      if (trim((string) $v) !== '') {
        $has_any = TRUE;
        break;
      }
    }

    // Skip processing if no ID values found.
    if (!$has_any) {
      if ($config->get('logging.log_skipped_rows')) {
        $this->logger->debug(
          '🐕 Sheepdog skipping row - no ID values found for migration: {migration} (configured fields: {fields}). Source: {source_display}',
          [
            'migration' => $migration->id(),
            'fields' => implode(', ', $id_fields),
            'source' => $source_values,
            'source_display' => $this->formatSourceDisplay($source_values),
          ]
        );
      }
      return;
    }

    // Determine primary ID for entity lookup.
    $primaryId = $this->getPrimaryId($source_values, $primaryIdField, $configuredIdFields, $row);

    // Get destination entity type from migration configuration.
    $destinationConfig = $migration->getDestinationConfiguration();
    $entityTypeId = $destinationConfig['plugin'] ?? NULL;

    // Handle entity: prefix for entity destinations.
    if ($entityTypeId && strpos($entityTypeId, 'entity:') === 0) {
      $entityTypeId = substr($entityTypeId, 7);
    }

    if (!$entityTypeId) {
      $this->logger->warning('🐕 Sheepdog cannot determine entity type for migration: {migration}', [
        'migration' => $migration->id(),
      ]);
      return;
    }

    // Find existing entity - try different ID field strategies.
    $original = $this->findExistingEntity($entityTypeId, $primaryId, $source_values, $config);

    $cacheKey = $migration->id() . ':' . $primaryId;

    if ($original) {
      $this->originalEntityValues[$cacheKey] = $this->extractEntityValues($original);
      $this->logger->info('🐕 Sheepdog tracking update for entity @id (primary_id: @primary_id)', [
        '@id' => $original->id(),
        '@primary_id' => $primaryId,
      ]);
    }
    else {
      if ($config->get('logging.log_new_entities')) {
        $this->logger->info('🐕 Sheepdog tracking new entity creation (primary_id: @primary_id)', [
          '@primary_id' => $primaryId,
        ]);
      }
      $this->originalEntityValues[$cacheKey] = NULL;
    }
  }

  /**
   * Log entity after saving with detailed diff.
   *
   * Time to report back! Good dog.
   */
  public function onPostSave(MigratePostRowSaveEvent $event): void {
    $migration = $event->getMigration();
    $row = $event->getRow();
    $config = $this->configFactory->get('sheepdog.settings');

    // Get the same ID information as in pre-save.
    $configuredIdFields = $config->get('tracking.id_fields') ?: ['id', 'source_id', 'ID10'];
    $primaryIdField = $config->get('tracking.primary_id_field') ?: '';

    $ids_config = $migration->getSourceConfiguration()['ids'] ?? [];
    $id_fields = [];
    foreach ($ids_config as $k => $v) {
      if (is_int($k)) {
        if (is_string($v)) {
          $id_fields[] = $v;
        }
        elseif (is_array($v) && isset($v['source'])) {
          $id_fields[] = $v['source'];
        }
      }
      else {
        $id_fields[] = $k;
      }
    }

    if (empty($id_fields)) {
      $id_fields = $configuredIdFields;
    }

    $source_values = [];
    foreach ($id_fields as $idx => $field) {
      $val = $this->getSourceValue($row, $field, $idx);
      $source_values[$field ?? (string) $idx] = $val !== NULL ? (string) $val : '';
    }

    $primaryId = $this->getPrimaryId($source_values, $primaryIdField, $configuredIdFields, $row);
    $cacheKey = $migration->id() . ':' . $primaryId;

    // Load the saved entity.
    $entity = $this->loadSavedEntity($event, $migration);

    $logContext = [
      'entity_id' => $entity ? $entity->id() : NULL,
      'primary_id' => $primaryId,
      'migration' => $migration->id(),
      'source' => $source_values,
      'source_display' => $this->formatSourceDisplay($source_values),
    ];

    // Generate and log diff if we have original values.
    if (isset($this->originalEntityValues[$cacheKey])) {
      if ($this->originalEntityValues[$cacheKey] === NULL) {
        // New entity.
        if ($config->get('logging.log_new_entities')) {
          $this->logger->info('🐕 Sheepdog: Created new entity @entity_id for @migration. Source: @source_display', $logContext);
        }
      }
      else {
        // Updated entity - check for changes.
        $newValues = $this->extractEntityValues($entity ?: (object) []);
        $diff = $this->generateDiff($this->originalEntityValues[$cacheKey], $newValues);

        if (!empty($diff)) {
          if ($config->get('logging.log_updated_entities')) {
            $diffMessage = "🐕 Sheepdog: Updated entity @entity_id for @migration. Source: @source_display\n@diff";
            $logContext['@diff'] = $diff;

            // Log to both channels for updates with changes.
            $this->logger->info($diffMessage, $logContext);
            $this->editLogger->info($diffMessage, $logContext);
          }
        }
        else {
          // No changes detected.
          if ($config->get('logging.log_unchanged_entities')) {
            $this->logger->info('🐕 Sheepdog: No changes detected for entity @entity_id. Source: @source_display', $logContext);
          }
        }
      }

      // Clean up stored values.
      unset($this->originalEntityValues[$cacheKey]);
    }
    else {
      // No pre-save data available.
      $this->logger->info('🐕 Sheepdog: Processed entity @entity_id for @migration. Source: @source_display', $logContext);
    }
  }

  /**
   * Get source value from row, trying different methods.
   */
  protected function getSourceValue($row, $field, $idx) {
    $val = NULL;
    if ($row->hasSourceProperty($field)) {
      $val = $row->getSourceProperty($field);
    }
    elseif ($row->hasSourceProperty($idx)) {
      $val = $row->getSourceProperty($idx);
    }
    else {
      $src = $row->getSource();
      if (is_array($src) && array_key_exists($field, $src)) {
        $val = $src[$field];
      }
      elseif (is_array($src) && array_key_exists($idx, $src)) {
        $val = $src[$idx];
      }
    }
    return $val;
  }

  /**
   * Determine the primary ID for entity lookup.
   */
  protected function getPrimaryId(array $source_values, string $primaryIdField, array $configuredIdFields, $row): string {
    // If specific primary field is configured, use it.
    if (!empty($primaryIdField) && isset($source_values[$primaryIdField])) {
      $val = $source_values[$primaryIdField];
      if (is_array($val)) {
        $val = reset($val);
      }
      return (string) $val;
    }

    // Try configured ID fields in order of preference.
    foreach ($configuredIdFields as $field) {
      if (isset($source_values[$field]) && trim((string) $source_values[$field]) !== '') {
        $val = $source_values[$field];
        if (is_array($val)) {
          $val = reset($val);
        }
        return (string) $val;
      }
    }

    // Fallback to first non-empty source value.
    foreach ($source_values as $val) {
      if (trim((string) $val) !== '') {
        if (is_array($val)) {
          $val = reset($val);
        }
        return (string) $val;
      }
    }

    return '';
  }

  /**
   * Find existing entity using various strategies.
   */
  protected function findExistingEntity(string $entityTypeId, string $primaryId, array $source_values, $config): ?ContentEntityInterface {
    if (empty($primaryId)) {
      return NULL;
    }

    try {
      $storage = $this->entityTypeManager->getStorage($entityTypeId);
    }
    catch (\Exception $e) {
      $this->logger->error('🐕 Sheepdog cannot load storage for entity type: {type}. Error: {error}', [
        'type' => $entityTypeId,
        'error' => $e->getMessage(),
      ]);
      return NULL;
    }

    // Try different field strategies to find the entity.
    $searchFields = [];

    // Add configured entity label field.
    $labelField = $config->get('tracking.entity_label_field');
    if ($labelField) {
      $searchFields[] = $labelField;
    }

    // Add common ID field patterns.
    $commonIdFields = [
    // Sheep entities.
      'field_s_id10',
    // Nematode entities.
      'field_n_entry_number',
      'source_id',
      'external_id',
      'field_id',
      'field_source_id',
    ];

    foreach ($commonIdFields as $field) {
      $searchFields[] = $field;
    }

    // Try each search field.
    foreach ($searchFields as $field) {
      try {
        $matches = $storage->loadByProperties([$field => $primaryId]);
        if (!empty($matches)) {
          return reset($matches);
        }
      }
      catch (\Exception $e) {
        // Field might not exist, continue to next one.
        continue;
      }
    }

    return NULL;
  }

  /**
   * Load saved entity from the migration event.
   */
  protected function loadSavedEntity($event, $migration): ?ContentEntityInterface {
    // Get destination entity type from migration configuration.
    $destinationConfig = $migration->getDestinationConfiguration();
    $entityTypeId = $destinationConfig['plugin'] ?? NULL;

    // Handle entity: prefix for entity destinations.
    if ($entityTypeId && strpos($entityTypeId, 'entity:') === 0) {
      $entityTypeId = substr($entityTypeId, 7);
    }

    if (!$entityTypeId) {
      return NULL;
    }

    try {
      $storage = $this->entityTypeManager->getStorage($entityTypeId);

      if (method_exists($event, 'getDestinationIdValues')) {
        $dest = $event->getDestinationIdValues();
        if (!empty($dest)) {
          $first = reset($dest);
          if (is_array($first)) {
            $maybe = reset($first);
            if (is_scalar($maybe)) {
              return $storage->load($maybe);
            }
          }
          elseif (is_scalar($first)) {
            return $storage->load($first);
          }
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->error('🐕 Sheepdog cannot load saved entity. Error: {error}', [
        'error' => $e->getMessage(),
      ]);
    }

    return NULL;
  }

  /**
   * Format source values for display.
   */
  protected function formatSourceDisplay(array $source_values): string {
    $display = [];
    foreach ($source_values as $k => $v) {
      $display[] = $k . ':' . ($v === '' ? '[empty]' : $v);
    }
    return implode(', ', $display);
  }

  /**
   * Extract field values from entity for comparison.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to extract values from.
   *
   * @return array
   *   Array of field values, excluding computed fields.
   */
  protected function extractEntityValues(ContentEntityInterface $entity): array {
    $values = [];

    // Get all non-computed field values.
    foreach ($entity->getFields(FALSE) as $field_name => $field) {
      // Skip system fields that aren't useful for diff.
      if (in_array($field_name, ['id', 'uuid', 'langcode'])) {
        continue;
      }

      $field_value = $field->getValue();

      // Handle entity reference fields specially.
      if ($field->getFieldDefinition()->getType() === 'entity_reference') {
        $values[$field_name] = $this->formatEntityReferenceValue($field_value);
      }
      else {
        $values[$field_name] = $this->formatFieldValue($field_value);
      }
    }

    return $values;
  }

  /**
   * Format field value for clean diff display.
   */
  protected function formatFieldValue($value) {
    if (empty($value)) {
      return NULL;
    }

    // Handle single-value fields.
    if (count($value) === 1 && isset($value[0]['value'])) {
      return $value[0]['value'];
    }

    // Handle multi-value or complex fields.
    return $value;
  }

  /**
   * Format entity reference field value for display.
   */
  protected function formatEntityReferenceValue(array $value) {
    if (empty($value)) {
      return NULL;
    }

    if (count($value) === 1 && isset($value[0]['target_id'])) {
      return "→{$value[0]['target_id']}";
    }

    // Multi-value entity references.
    $targets = array_map(function ($item) {
      return "→{$item['target_id']}";
    }, $value);

    return $targets;
  }

  /**
   * Generate a readable diff between original and new values.
   *
   * @param array $original
   *   Original field values.
   * @param array $new
   *   New field values.
   *
   * @return string
   *   Formatted diff string.
   */
  protected function generateDiff(array $original, array $new): string {
    $changes = [];

    // Find all field names that exist in either version.
    $all_fields = array_unique(array_merge(array_keys($original), array_keys($new)));
    sort($all_fields);

    foreach ($all_fields as $field_name) {
      $old_val = $original[$field_name] ?? NULL;
      $new_val = $new[$field_name] ?? NULL;

      // Compare values.
      if ($old_val !== $new_val) {
        $old_display = $this->formatValueForDiff($old_val);
        $new_display = $this->formatValueForDiff($new_val);

        if ($old_val === NULL) {
          $changes[] = "  +{$field_name}: {$new_display}";
        }
        elseif ($new_val === NULL) {
          $changes[] = "  -{$field_name}: {$old_display}";
        }
        else {
          $changes[] = "  ~{$field_name}: {$old_display} → {$new_display}";
        }
      }
    }

    if (empty($changes)) {
      return '';
    }

    $count = count($changes);
    $header = $count . ' ' . ($count === 1 ? 'CHANGE' : 'CHANGES');

    return $header . "\n" . implode("\n", $changes);
  }

  /**
   * Format a value for diff display.
   */
  protected function formatValueForDiff($value): string {
    if ($value === NULL || $value === '') {
      return '[empty]';
    }

    if (is_array($value)) {
      if (empty($value)) {
        return '[empty]';
      }
      return is_array($value[0]) ? '[complex]' : json_encode($value);
    }

    return (string) $value;
  }

}
