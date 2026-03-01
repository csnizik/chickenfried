<?php

declare(strict_types=1);

namespace Drupal\nematode_entities\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Form\DeleteMultipleForm;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\nematode_entities\SpecimenAccessControlHandler;
use Drupal\nematode_entities\SpecimenInterface;
use Drupal\nematode_entities\SpecimenListBuilder;
use Drupal\nematode_entities\Form\SpecimenForm;
use Drupal\nematode_entities\Routing\SpecimenHtmlRouteProvider;
use Drupal\views\EntityViewsData;

/**
 * Defines the Specimen entity.
 */
#[ContentEntityType(
  id: 'nematode_entities_specimen',
  label: new TranslatableMarkup('Specimen'),
  label_collection: new TranslatableMarkup('Specimens'),
  label_singular: new TranslatableMarkup('specimen'),
  label_plural: new TranslatableMarkup('specimens'),
  handlers: [
    'view_builder' => \Drupal\Core\Entity\EntityViewBuilder::class,
    'list_builder' => SpecimenListBuilder::class,
    'views_data' => EntityViewsData::class,
    'access' => SpecimenAccessControlHandler::class,
    'form' => [
      'add' => SpecimenForm::class,
      'edit' => SpecimenForm::class,
      'delete' => ContentEntityDeleteForm::class,
      'delete-multiple-confirm' => DeleteMultipleForm::class,
    ],
    'route_provider' => [
      'html' => SpecimenHtmlRouteProvider::class,
    ],
  ],
  base_table: 'nematode_entities_specimen',
  admin_permission: 'administer nematode_entities_specimen',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'label' => 'field_n_entry_number',
  ],
  links: [
    'canonical' => '/specimen/{nematode_entities_specimen}',
    'add-form' => '/specimen/add',
    'edit-form' => '/specimen/{nematode_entities_specimen}/edit',
    'delete-form' => '/specimen/{nematode_entities_specimen}/delete',
    'collection' => '/admin/content/nematode-specimens',
  ],
  label_count: [
    'singular' => '@count specimen record',
    'plural' => '@count specimen records',
  ],
)]
final class Specimen extends ContentEntityBase implements SpecimenInterface {

  use EntityChangedTrait;

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

    // Timestamp fields.
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the specimen was created.'))
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the specimen was last edited.'))
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_n_entry_number'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Entry Number'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_n_origin_locale'] = BaseFieldDefinition::create('string')
      ->setLabel(t('City/Locale'))
      ->setSetting('max_length', 255)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_n_date_collected'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Date Collected'))
      ->setSetting('max_length', 100)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_n_host_species'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Host Species'))
      ->setSetting('max_length', 50)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_n_nematode_species'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Nematode Species'))
      ->setSetting('max_length', 50)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_n_collector'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Collector'))
      ->setSetting('max_length', 120)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_n_common_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Common Name'))
      ->setSetting('max_length', 40)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_n_confidential'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Confidential'))
      ->setSetting('max_length', 255)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_n_origin_country'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Country'))
      ->setSetting('max_length', 40)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_n_origin_state'] = BaseFieldDefinition::create('string')
      ->setLabel(t('State'))
      ->setSetting('max_length', 40)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_n_date_added'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Date Added (Legacy)'))
      ->setDescription(t('Original date added from imported data.'))
      ->setSetting('max_length', 50)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_n_date_received'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Date Received'))
      ->setSetting('max_length', 50)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_n_determined_by'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Determined By'))
      ->setSetting('max_length', 100)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_n_disposition'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Disposition of Sample'))
      ->setSetting('max_length', 100)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_n_host_genus'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Host Genus'))
      ->setSetting('max_length', 50)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_n_nematode_genus'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Nematode Genus'))
      ->setSetting('max_length', 50)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_n_nematodes_in'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Nematodes In'))
      ->setSetting('max_length', 40)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_n_other_nematodes'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Other Nematodes'))
      ->setSetting('max_length', 255)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_n_reference'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Reference'))
      ->setSetting('max_length', 255)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_n_remarks'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Remarks'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_n_sample_number'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Sample Number'))
      ->setSetting('max_length', 100)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_n_slides'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Slide Number'))
      ->setDescription(t('One or more slide numbers for this specimen'))
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setSetting('max_length', 255)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_n_vials'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Vial Number'))
      ->setDescription(t('One or more vial numbers for this specimen'))
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setSetting('max_length', 255)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_n_author'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Taxon Author'))
      ->setSetting('max_length', 255)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_n_total_samples'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Total Samples'))
      ->setSetting('max_length', 50)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
