<?php

declare(strict_types=1);

namespace Drupal\sheep_entities\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Form\DeleteMultipleForm;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\sheep_entities\Form\SheepNoteForm;
use Drupal\sheep_entities\Routing\SheepNoteHtmlRouteProvider;
use Drupal\sheep_entities\SheepNoteAccessControlHandler;
use Drupal\sheep_entities\SheepNoteInterface;
use Drupal\sheep_entities\SheepNoteListBuilder;
use Drupal\sheep_entities\SheepNoteViewsData;

/**
 * Defines the Sheep Note entity.
 */
#[ContentEntityType(
  id: 'sheep_entities_sheep_note',
  label: new TranslatableMarkup('Sheep Note'),
  label_collection: new TranslatableMarkup('Sheep Notes'),
  label_singular: new TranslatableMarkup('sheep note'),
  label_plural: new TranslatableMarkup('sheep notes'),
  handlers: [
    'list_builder' => SheepNoteListBuilder::class,
    'views_data' => SheepNoteViewsData::class,
    'access' => SheepNoteAccessControlHandler::class,
    'form' => [
      'add' => SheepNoteForm::class,
      'edit' => SheepNoteForm::class,
      'delete' => ContentEntityDeleteForm::class,
      'delete-multiple-confirm' => DeleteMultipleForm::class,
    ],
    'route_provider' => [
      'html' => SheepNoteHtmlRouteProvider::class,
    ],
  ],
  base_table: 'sheep_entities_sheep_note',
  admin_permission: 'administer sheep_entities_sheep_note',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'label' => 'id',
  ],
  links: [
    'collection' => '/admin/content/sheep-notes',
    'add-form' => '/sheep-note/add',
    'canonical' => '/sheep-note/{sheep_entities_sheep_note}',
    'edit-form' => '/sheep-note/{sheep_entities_sheep_note}/edit',
    'delete-form' => '/sheep-note/{sheep_entities_sheep_note}/delete',
    'delete-multiple-form' => '/admin/content/sheep-notes/delete-multiple',
  ],
  label_count: [
    'singular' => '@count sheep note',
    'plural' => '@count sheep notes',
  ],
)]
final class SheepNote extends ContentEntityBase implements SheepNoteInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = [];

    // Primary keys.
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setReadOnly(TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setReadOnly(TRUE);



    // Sheep reference
    $fields['field_s_sheep'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Sheep'))
      ->setDescription(t('The sheep this note belongs to.'))
      ->setSetting('target_type', 'sheep_entities_sheep_record')
      ->setSetting('handler', 'default:sheep_entities_sheep_record')
      ->setSetting('handler_settings', [
        'sort' => ['field' => '_none', 'direction' => 'ASC'],
        'auto_create' => FALSE,
      ])
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // field_s_note (text_long, single value)
    $fields['field_s_note'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Note'))
      ->setDescription(t('Note or remark text. [INV,EWEMAS,PRESHR:RMK; LAMB:ERMK,LRMK]'))
      ->setCardinality(1)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // field_s_date (date only, no time)
    $fields['field_s_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Date'))
      ->setDescription(t('Date the note was recorded.'))
      ->setSetting('datetime_type', 'date')
      ->setCardinality(1)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

          // ── Overflow & provenance ────────────────────────────────────────

    $fields['field_s_migration_legacy_data'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Legacy data (JSON)'))
      ->setDescription(t('JSON object containing fields not represented as dedicated base fields. Keyed by original source field name.'))
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_s_migration_notes'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Migration notes'))
      ->setDescription(t('Auto-generated migration warnings and validation discrepancies.'))
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['field_s_migration_source'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Migration source (CSV/DBF)'))
      ->setDescription(t('The filename of the source data. Original files from FoxPro were in .dbf format; we convert to .csv for migration.'))
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['field_s_migration_year'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Migration year (CSV/DBF)'))
      ->setDescription(t('The year of the source data.'))
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    return $fields;
  }

}
