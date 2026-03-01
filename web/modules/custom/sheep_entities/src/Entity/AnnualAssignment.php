<?php

declare(strict_types=1);

namespace Drupal\sheep_entities\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Entity\Form\DeleteMultipleForm;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\sheep_entities\AnnualAssignmentAccessControlHandler;
use Drupal\sheep_entities\AnnualAssignmentInterface;
use Drupal\sheep_entities\AnnualAssignmentListBuilder;
use Drupal\sheep_entities\Form\AnnualAssignmentForm;
use Drupal\sheep_entities\Routing\AnnualAssignmentHtmlRouteProvider;
use Drupal\views\EntityViewsData;

/**
 * Defines the annual assignment entity class.
 */
#[ContentEntityType(
  id: 'sheep_entities_annual_assignment',
  label: new TranslatableMarkup('Annual Assignment'),
  label_collection: new TranslatableMarkup('Annual Assignments'),
  label_singular: new TranslatableMarkup('annual assignment'),
  label_plural: new TranslatableMarkup('annual assignments'),
  entity_keys: [
    'id' => 'id',
    'label' => 'id',
    'uuid' => 'uuid',
  ],
  handlers: [
    'list_builder' => AnnualAssignmentListBuilder::class,
    'views_data' => EntityViewsData::class,
    'access' => AnnualAssignmentAccessControlHandler::class,
    'form' => [
      'add' => AnnualAssignmentForm::class,
      'edit' => AnnualAssignmentForm::class,
      'delete' => ContentEntityDeleteForm::class,
      'delete-multiple-confirm' => DeleteMultipleForm::class,
    ],
    'route_provider' => [
      'html' => AnnualAssignmentHtmlRouteProvider::class,
    ],
  ],
  links: [
    'collection' => '/admin/content/annual_assignment',
    'add-form' => '/annual_assignment/add',
    'canonical' => '/annual_assignment/{sheep_entities_annual_assignment}',
    'edit-form' => '/annual_assignment/{sheep_entities_annual_assignment}',
    'delete-form' => '/annual_assignment/{sheep_entities_annual_assignment}/delete',
    'delete-multiple-form' => '/admin/content/annual_assignment/delete-multiple',
  ],
  admin_permission: 'administer sheep_entities_annual_assignment',
  base_table: 'sheep_entities_annual_assignment',
  label_count: [
    'singular' => '@count annual assignments',
    'plural' => '@count annual assignments',
  ],
)]
class AnnualAssignment extends ContentEntityBase implements AnnualAssignmentInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
  $fields = [];

  // Primary ID.
  $fields['id'] = BaseFieldDefinition::create('integer')
    ->setLabel(t('ID'))
    ->setReadOnly(TRUE);

  // UUID.
  $fields['uuid'] = BaseFieldDefinition::create('uuid')
    ->setLabel(t('UUID'))
    ->setReadOnly(TRUE);

  // Year (integer: 1950–2099).
  $fields['field_s_year'] = BaseFieldDefinition::create('integer')
    ->setLabel(t('Year'))
    ->setDescription(t('Year. Four-digit record year (e.g., 2025). Field type is integer, not datetime. If exposing a filter on this field, use the "Year select" plugin (provided by sheep_calendar module) to create an autopopulated dropdown of values.'))
    ->setSetting('unsigned', FALSE)
    ->setSetting('size', 'normal')
    ->setDisplayConfigurable('form', TRUE)
    ->setDisplayConfigurable('view', TRUE);

  // Sheep reference
  $fields['field_s_sheep'] = BaseFieldDefinition::create('entity_reference')
    ->setLabel(t('Ear tag'))
    ->setDescription(t('Ear tag. Link to the Sheep record.'))
    ->setSetting('target_type', 'sheep_entities_sheep_record')
    ->setSetting('handler', 'entity:sheep_entities_sheep_record')
    ->setSetting('handler_settings', [
      'sort' => ['field' => '_none', 'direction' => 'ASC'],
      'auto_create' => FALSE,
    ])
    ->setDisplayConfigurable('form', TRUE)
    ->setDisplayConfigurable('view', TRUE);

  // Taxonomy references (one bundle each)…

  $fields['field_s_band'] = BaseFieldDefinition::create('entity_reference')
    ->setLabel(t('Band'))
    ->setDescription(t('Band into which the sheep is branded at spring turnout. [WNMAS,INV,LAMB:BAND]'))
    ->setSetting('target_type', 'taxonomy_term')
    ->setSetting('handler', 'default:taxonomy_term')
    ->setSetting('handler_settings', [
      'target_bundles' => ['s_bands' => 's_bands'],
      'sort' => ['field' => 'name', 'direction' => 'ASC'],
      'auto_create' => FALSE,
    ])
    ->setDisplayConfigurable('form', TRUE)
    ->setDisplayConfigurable('view', TRUE);

  $fields['field_s_breeding_group'] = BaseFieldDefinition::create('entity_reference')
    ->setLabel(t('Breeding group'))
    ->setDescription(t('Breeding group to which a group of breeding pens (see field_s_breeding_pen) are assigned; can change every year. [INV,LAMB:BGRP]'))
    ->setSetting('target_type', 'taxonomy_term')
    ->setSetting('handler', 'default:taxonomy_term')
    ->setSetting('handler_settings', [
      'target_bundles' => ['s_breeding_groups' => 's_breeding_groups'],
      'sort' => ['field' => 'name', 'direction' => 'ASC'],
      'auto_create' => FALSE,
    ])
    ->setDisplayConfigurable('form', TRUE)
    ->setDisplayConfigurable('view', TRUE);

  $fields['field_s_breeding_pen'] = BaseFieldDefinition::create('entity_reference')
    ->setLabel(t('Breeding pen'))
    ->setDescription(t('Breeding pen to which the sheep was assigned for the year. Changes every year. [WNMAS,INV,LAMB:BPEN]'))
    ->setSetting('target_type', 'taxonomy_term')
    ->setSetting('handler', 'default:taxonomy_term')
    ->setSetting('handler_settings', [
      'target_bundles' => ['s_breeding_pens' => 's_breeding_pens'],
      'sort' => ['field' => 'name', 'direction' => 'ASC'],
      'auto_create' => FALSE,
    ])
    ->setDisplayConfigurable('form', TRUE)
    ->setDisplayConfigurable('view', TRUE);

  $fields['field_s_group'] = BaseFieldDefinition::create('entity_reference')
    ->setLabel(t('Group'))
    ->setDescription(t('Group. Historical data point. Also occasionally used to list a project a sheep may be on that year. [INV:GRP]'))
    ->setSetting('target_type', 'taxonomy_term')
    ->setSetting('handler', 'default:taxonomy_term')
    ->setSetting('handler_settings', [
      'target_bundles' => ['s_groups' => 's_groups'],
      'sort' => ['field' => 'name', 'direction' => 'ASC'],
      'auto_create' => FALSE,
    ])
    ->setDisplayConfigurable('form', TRUE)
    ->setDisplayConfigurable('view', TRUE);

  $fields['field_s_lot'] = BaseFieldDefinition::create('entity_reference')
    ->setLabel(t('Lot'))
    ->setDescription(t('Lot. Often, but not always, used to tell what project a ewe and her lambs will be on at lambing. Has other historical uses as well. [WNMAS,INV,LAMB:LOT]'))
    ->setSetting('target_type', 'taxonomy_term')
    ->setSetting('handler', 'default:taxonomy_term')
    ->setSetting('handler_settings', [
      'target_bundles' => ['s_lots' => 's_lots'],
      'sort' => ['field' => 'name', 'direction' => 'ASC'],
      'auto_create' => FALSE,
    ])
    ->setDisplayConfigurable('form', TRUE)
    ->setDisplayConfigurable('view', TRUE);

  $fields['field_s_research_group'] = BaseFieldDefinition::create('entity_reference')
    ->setLabel(t('Research group'))
    ->setDescription(t('Research group. Historical data point. [WNMAS,INV,LAMB,EWEMAS:RGRP]'))
    ->setSetting('target_type', 'taxonomy_term')
    ->setSetting('handler', 'default:taxonomy_term')
    ->setSetting('handler_settings', [
      'target_bundles' => ['s_research_groups' => 's_research_groups'],
      'sort' => ['field' => 'name', 'direction' => 'ASC'],
      'auto_create' => FALSE,
    ])
    ->setDisplayConfigurable('form', TRUE)
    ->setDisplayConfigurable('view', TRUE);

  $fields['field_s_study'] = BaseFieldDefinition::create('entity_reference')
    ->setLabel(t('Study'))
    ->setDescription(t('Study. Historical data point. Also used to list a project a sheep may be on that year. [WNMAS,INV:STUDY]'))
    ->setSetting('target_type', 'taxonomy_term')
    ->setSetting('handler', 'default:taxonomy_term')
    ->setSetting('handler_settings', [
      'target_bundles' => ['s_studies' => 's_studies'],
      'sort' => ['field' => 'name', 'direction' => 'ASC'],
      'auto_create' => FALSE,
    ])
    ->setDisplayConfigurable('form', TRUE)
    ->setDisplayConfigurable('view', TRUE);

  $fields['field_s_study_treatment'] = BaseFieldDefinition::create('entity_reference')
    ->setLabel(t('Study treatment'))
    ->setDescription(t('Study treatment. Historical data point. Also used to list a project a sheep may be on that year. [INV:STUDYTRT]'))
    ->setSetting('target_type', 'taxonomy_term')
    ->setSetting('handler', 'default:taxonomy_term')
    ->setSetting('handler_settings', [
      'target_bundles' => ['s_study_treatments' => 's_study_treatments'],
      'sort' => ['field' => 'name', 'direction' => 'ASC'],
      'auto_create' => FALSE,
    ])
    ->setDisplayConfigurable('form', TRUE)
    ->setDisplayConfigurable('view', TRUE);

  // Treatment can point to either s_study_treatments or s_treatments.
  $fields['field_s_treatment'] = BaseFieldDefinition::create('entity_reference')
    ->setLabel(t('Treatment'))
    ->setDescription(t('Treatment. Historical data point. Also used to list a project a sheep may be on that year. [WNMAS,INV,LAMB:TRT]'))
    ->setSetting('target_type', 'taxonomy_term')
    ->setSetting('handler', 'default:taxonomy_term')
    ->setSetting('handler_settings', [
      'target_bundles' => [
        's_study_treatments' => 's_study_treatments',
        's_treatments' => 's_treatments',
      ],
      'sort' => ['field' => 'name', 'direction' => 'ASC'],
      'auto_create' => FALSE,
    ])
    ->setDisplayConfigurable('form', TRUE)
    ->setDisplayConfigurable('view', TRUE);

  $fields['field_s_treatment_pen'] = BaseFieldDefinition::create('entity_reference')
    ->setLabel(t('Pen'))
    ->setDescription(t('Pen. The breeding pen in which the mating that conceived this sheep took place. This is a lifetime assignment. [WNMAS,INV:PEN]'))
    ->setSetting('target_type', 'taxonomy_term')
    ->setSetting('handler', 'default:taxonomy_term')
    ->setSetting('handler_settings', [
      'target_bundles' => ['s_treatment_pens' => 's_treatment_pens'],
      'sort' => ['field' => 'name', 'direction' => 'ASC'],
      'auto_create' => FALSE,
    ])
    ->setDisplayConfigurable('form', TRUE)
    ->setDisplayConfigurable('view', TRUE);

  return $fields;
}

}


