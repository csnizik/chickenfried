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
use Drupal\sheep_entities\Form\LambCardForm;
use Drupal\sheep_entities\LambCardAccessControlHandler;
use Drupal\sheep_entities\LambCardInterface;
use Drupal\sheep_entities\LambCardListBuilder;
use Drupal\sheep_entities\Routing\LambCardHtmlRouteProvider;
use Drupal\views\EntityViewsData;

/**
 * Defines the Lamb Card entity.
 *
 * A join entity representing one ewe's lambing event for a given year.
 * Links a dam to her sire and the lambs produced. Per-lamb birth scores
 * (dystocia, depth, jaw, birth weight, entropion, teat score) live on
 * the individual lamb's SheepRecord since they can vary per lamb in a
 * multi-lamb birth. Ewe-level assessments (type of birth, maternal
 * score, milk score, teat score, Y1/Y2) live here.
 *
 * Dam observation measurements taken at lambing time (body weight, BCS,
 * face score, horn score, wool traits) are stored as Observation Records
 * with the "Ewe Lambing Assessment" life stage event.
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
    'collection' => '/admin/content/lamb-cards',
    'add-form' => '/lamb-card/add',
    'canonical' => '/lamb-card/{sheep_entities_lamb_card}',
    'edit-form' => '/lamb-card/{sheep_entities_lamb_card}/edit',
    'delete-form' => '/lamb-card/{sheep_entities_lamb_card}/delete',
    'delete-multiple-form' => '/admin/content/lamb-cards/delete-multiple',
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

    // ── Primary keys ────────────────────────────────────────────────

    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setReadOnly(TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setReadOnly(TRUE);

    // ── Parentage & offspring ────────────────────────────────────────

    $fields['field_s_dam'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Dam'))
      ->setDescription(t('The ewe that lambed.'))
      ->setSetting('target_type', 'sheep_entities_sheep_record')
      ->setSetting('handler', 'default:sheep_entities_sheep_record')
      ->setSetting('handler_settings', [
        'sort' => ['field' => '_none', 'direction' => 'ASC'],
        'auto_create' => FALSE,
      ])
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_s_sire'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Sire'))
      ->setDescription(t('The ram, if known.'))
      ->setSetting('target_type', 'sheep_entities_sheep_record')
      ->setSetting('handler', 'default:sheep_entities_sheep_record')
      ->setSetting('handler_settings', [
        'sort' => ['field' => '_none', 'direction' => 'ASC'],
        'auto_create' => FALSE,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_s_lambs'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Lambs'))
      ->setDescription(t('The lamb(s) born in this lambing event. Multiple values for twins, triplets, etc.'))
      ->setSetting('target_type', 'sheep_entities_sheep_record')
      ->setSetting('handler', 'default:sheep_entities_sheep_record')
      ->setSetting('handler_settings', [
        'sort' => ['field' => '_none', 'direction' => 'ASC'],
        'auto_create' => FALSE,
      ])
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // ── Lambing event context ────────────────────────────────────────

    $fields['field_s_lambing_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Lambing date'))
      ->setDescription(t('Date of lambing. [LAMB: DATE, BDAY]'))
      ->setSetting('datetime_type', 'date')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_s_type_of_birth'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Type of birth (TB)'))
      ->setDescription(t('Litter composition score. Ewe-level classification of the birth event. [LAMB: TB]'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // ── Ewe lambing status (Y-traits) ────────────────────────────────

    $fields['field_s_present_lambing'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Present for lambing (Y1)'))
      ->setDescription(t('Whether the ewe was present for lambing. 1 = present, 0 = absent. [LAMB,WEANING,FERTILITY: Y1]'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_s_lambed'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Lambed (Y2)'))
      ->setDescription(t('Whether the ewe lambed. 1 = yes, 0 = no. [LAMB,WEANING,FERTILITY: Y2]'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // ── Ewe assessment scores ────────────────────────────────────────

    $fields['field_s_teat'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Teat'))
      ->setDescription(t('7-point scoring system for supernumerary teats.'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_s_milk'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Milk score'))
      ->setDescription(t('6-point subjective milk scoring system to evaluate the quality and quantity of ewe milk compared to her litter of lambs.'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_s_maternal'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Maternal score (MAT)'))
      ->setDescription(t('3-point maternal scoring system to evaluate mothering ability of ewe. [LAMB: MAT] (2024+)'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // field_s_disposal (term ref)
    $fields['field_s_breeding_pen'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Breeding pen'))
      ->setDescription(t('The breeding pen in which the mating that led to this lambing event took place. [INV,WNMAS:PEN]'))
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default:taxonomy_term')
      ->setSetting('handler_settings', ['target_bundles' => ['s_breeding_pens' => 's_breeding_pens']])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // ── Overflow & provenance ────────────────────────────────────────

  // ── Overflow & provenance ────────────────────────────────────────

    $fields['field_s_migration_legacy_data'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Legacy data (JSON)'))
      ->setDescription(t('JSON object containing fields not represented as dedicated base fields. Keyed by original source field name.'))
      ->setCardinality(-1)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_s_migration_notes'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Migration notes'))
      ->setDescription(t('Auto-generated migration warnings and validation discrepancies.'))
      ->setCardinality(-1)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['field_s_migration_source'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Migration source (CSV/DBF)'))
      ->setDescription(t('The table and row number the data was sourced from. Format: [YY + first two letters of table name + row number padded to 5 digits, e.g. "24IN01001" for 2024 INV table row 1001.'))
      ->setCardinality(-1)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    return $fields;

}
}
