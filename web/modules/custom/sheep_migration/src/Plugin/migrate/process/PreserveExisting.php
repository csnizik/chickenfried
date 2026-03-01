<?php

declare(strict_types=1);

namespace Drupal\sheep_migration\Plugin\migrate\process;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Preserves existing entity values and logs attempted overwrites.
 *
 * Use this for immutable fields that should not change once set.
 *
 * Usage:
 * @code
 * field_s_born_year:
 *   - plugin: preserve_existing
 *     source: YR
 *     field: field_s_born_year
 *     entity_type: sheep_entities_sheep_record
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "preserve_existing"
 * )
 */
class PreserveExisting extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Constructs a PreserveExisting plugin.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('logger.channel.sheep_migration')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $entity_id = $row->getDestinationProperty('id');

    if (!$entity_id) {
      // New entity - use incoming value.
      return $value;
    }

    $field_name = $this->configuration['field'] ?? $destination_property;
    $entity_type = $this->configuration['entity_type'] ?? 'sheep_entities_sheep_record';

    $entity = $this->entityTypeManager
      ->getStorage($entity_type)
      ->load($entity_id);

    if (!$entity instanceof FieldableEntityInterface) {
      return $value;
    }

    if (!$entity->hasField($field_name)) {
      return $value;
    }

    $field = $entity->get($field_name);

    // If existing field is empty, accept incoming value.
    if ($field->isEmpty()) {
      return $value;
    }

    // Get existing value - handle both scalar and entity reference fields.
    $field_definition = $field->getFieldDefinition();
    $field_type = $field_definition->getType();

    if (in_array($field_type, ['entity_reference', 'entity_reference_revisions'])) {
      $existing_value = $field->target_id;
    }
    else {
      $existing_value = $field->value;
    }

    // Normalize for comparison.
    $incoming_normalized = $this->normalize($value);
    $existing_normalized = $this->normalize($existing_value);

    // If values match, no conflict.
    if ($incoming_normalized === $existing_normalized) {
      return $existing_value;
    }

    // CONFLICT: Log the attempted overwrite.
    $id10 = $row->getSourceProperty('ID10')
      ?? $row->getSourceProperty('LAMBID')
      ?? ($entity->hasField('field_s_id10') ? $entity->get('field_s_id10')->value : 'unknown');

    $this->logger->warning(
      'IMMUTABLE CONFLICT ID10=@id10 | @field: existing="@existing" vs incoming="@incoming" | PRESERVED existing',
      [
        '@id10' => $id10,
        '@field' => $field_name,
        '@existing' => $existing_value,
        '@incoming' => $value,
      ]
    );

    // Preserve existing value.
    return $existing_value;
  }

  /**
   * Normalizes a value for comparison.
   *
   * Handles NULL, trims whitespace, and normalizes numeric values
   * to avoid float precision false positives.
   *
   * @param mixed $value
   *   The value to normalize.
   *
   * @return string
   *   The normalized string representation.
   */
  protected function normalize($value): string {
    if ($value === NULL) {
      return '';
    }

    $trimmed = trim((string) $value);

    // Normalize numeric values to avoid float precision false positives.
    if (is_numeric($trimmed)) {
      return (string) (float) $trimmed;
    }

    return $trimmed;
  }

}
