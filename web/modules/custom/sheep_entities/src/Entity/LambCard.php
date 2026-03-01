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
use Drupal\sheep_entities\LambCardAccessControlHandler;
use Drupal\sheep_entities\LambCardInterface;
use Drupal\sheep_entities\LambCardListBuilder;
use Drupal\sheep_entities\Form\LambCardForm;
use Drupal\sheep_entities\Routing\LambCardHtmlRouteProvider;
use Drupal\views\EntityViewsData;

/**
 * Defines the Lamb Card entity.
 */
#[ContentEntityType(
  id: 'sheep_entities_lamb_card',
  label: new TranslatableMarkup('Lamb Card'),
  label_collection: new TranslatableMarkup('Lamb Cards'),
  label_singular: new TranslatableMarkup('lamb card'),
  label_plural: new TranslatableMarkup('lamb cards'),
  handlers: [
    'list_builder' => LambCardListBuilder::class,
    'views_data' => EntityViewsData::class,
    'access' => LambCardAccessControlHandler::class,
    'form' => [
      'add' => LambCardForm::class,
      'edit' => LambCardForm::class,
      'delete' => ContentEntityDeleteForm::class,
      'delete-multiple-confirm' => DeleteMultipleForm::class,
    ],
    'route_provider' => [
      'html' => LambCardHtmlRouteProvider::class,
    ],
  ],
  base_table: 'sheep_entities_lamb_card',
  admin_permission: 'administer sheep_entities_lamb_card',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
  ],
  links: [
    'collection' => '/admin/content/lamb_card',
    'add-form' => '/lamb_card/add',
    'canonical' => '/lamb_card/{sheep_entities_lamb_card}',
    'edit-form' => '/lamb_card/{sheep_entities_lamb_card}/edit',
    'delete-form' => '/lamb_card/{sheep_entities_lamb_card}/delete',
    'delete-multiple-form' => '/admin/content/lamb_card/delete-multiple',
  ],
  label_count: [
    'singular' => '@count lamb card',
    'plural' => '@count lamb cards',
  ],
)]
final class LambCard extends ContentEntityBase implements LambCardInterface {

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

    // Sheep reference.
    $fields['field_s_sheep'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Ear tag'))
      ->setDescription(t('Link to the Sheep record.'))
      ->setSetting('target_type', 'sheep_entities_sheep_record')
      ->setSetting('handler', 'entity:sheep_entities_sheep_record')
      ->setSetting('handler_settings', [
        'sort' => ['field' => '_none', 'direction' => 'ASC'],
        'auto_create' => FALSE,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_s_abnormality'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Abnormality code'))
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default:taxonomy_term')
      ->setSetting('handler_settings', [
        'target_bundles' => ['s_abnormalities' => 's_abnormalities'],
        'sort' => ['field' => 'name', 'direction' => 'ASC'],
        'auto_create' => FALSE,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // field_s_body_condition (decimal)
    $fields['field_s_body_condition'] = BaseFieldDefinition::create('decimal')
      ->setLabel('Body condition score')
      ->setSetting('min', 1.0)
      ->setSetting('max', 5.0)
      ->setDescription(t('1.0 to 5.0 in 0.5 increments'))
      ->setDisplayOptions('form', ['type' => 'number_decimal', 'weight' => 0])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // field_s_face_score (integer)
    $fields['field_s_face_score'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Face score'))
      ->setSetting('max', 15)
      ->setDisplayOptions('form', ['type' => 'number', 'weight' => 0])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // field_s_horn_score (integer)
    $fields['field_s_horn_score'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Horn score'))
      ->setSetting('max', 7)
      ->setDisplayOptions('form', ['type' => 'number', 'weight' => 0])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // field_s_jaw_score (integer)
    $fields['field_s_jaw_score'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Jaw score'))
      ->setSetting('max', 7)
      ->setDisplayOptions('form', ['type' => 'number', 'weight' => 0])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // field_s_migration_notes (string_long)
    $fields['field_s_migration_notes'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Migration notes (auto-generated)'))
      ->setDescription(t('Used for logging during migration imports.'))
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    return $fields;
  }

}
