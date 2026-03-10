<?php

declare(strict_types=1);

namespace Drupal\sheep_entities\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Entity\Form\DeleteMultipleForm;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\sheep_entities\SheepRecordAccessControlHandler;
use Drupal\sheep_entities\SheepRecordInterface;
use Drupal\sheep_entities\SheepRecordListBuilder;
use Drupal\sheep_entities\Form\SheepRecordForm;
use Drupal\sheep_entities\Routing\SheepRecordHtmlRouteProvider;
use Drupal\sheep_entities\SheepRecordViewsData;

/**
 * Defines the Sheep Record entity.
 */
#[ContentEntityType(
  id: 'sheep_entities_sheep_record',
  label: new TranslatableMarkup('Sheep Record'),
  label_collection: new TranslatableMarkup('Sheep Records'),
  label_singular: new TranslatableMarkup('sheep record'),
  label_plural: new TranslatableMarkup('sheep records'),
  handlers: [
    'view_builder' => EntityViewBuilder::class,
    'list_builder' => SheepRecordListBuilder::class,
    'views_data' => SheepRecordViewsData::class,
    'access' => SheepRecordAccessControlHandler::class,
    'form' => [
      'add' => SheepRecordForm::class,
      'edit' => SheepRecordForm::class,
      'delete' => ContentEntityDeleteForm::class,
      'delete-multiple-confirm' => DeleteMultipleForm::class,
    ],
    'route_provider' => [
      'html' => SheepRecordHtmlRouteProvider::class,
    ],
  ],
  base_table: 'sheep_entities_sheep_record',
  admin_permission: 'administer sheep_entities_sheep_record',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'label' => 'field_s_id10',
  ],
  links: [
    'collection' => '/admin/content/sheep',
    'add-form' => '/sheep/add',
    'canonical' => '/sheep/{sheep_entities_sheep_record}',
    'edit-form' => '/sheep/{sheep_entities_sheep_record}/edit',
    'delete-form' => '/sheep/{sheep_entities_sheep_record}/delete',
    'delete-multiple-form' => '/admin/content/sheep/delete-multiple',
  ],
  label_count: [
    'singular' => '@count sheep record',
    'plural' => '@count sheep records',
  ],
  field_ui_base_route: 'entity.sheep_entities_sheep_record.collection'
)]
final class SheepRecord extends ContentEntityBase implements SheepRecordInterface {

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

    // field_s_id10 (string)
    // This is our source-of-truth unique field identifying a sheep_record.
    $fields['field_s_id10'] = BaseFieldDefinition::create('string')
      ->setLabel(t('ID10'))
      ->setDescription(t('ID10. 10-digit string used as unique identifier. Length must be 10 characters. See README for derivation rules. [INV,PEDI-CM:ID10; WNMAS:ID;LAMB,PEDI,PEDI-CM:LAMBID,DAMID,SIREID]'))
      ->setSetting('max_length', 10)
      ->addConstraint('UniqueField')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    // field_s_altid (string)
    $fields['field_s_altid'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Alternate ID'))
      ->setDescription(t('Alternate ID. Former/purchased ID(s). [INV:ALTID]'))
      ->setSetting('max_length', 255)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // field_s_breed (term ref)
    $fields['field_s_breed'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Breed'))
      ->setDescription(t('Breed of the animal. [WNMAS,INV,LAMB,EWEMAS,PEDI:BRD,DBRD,LBRD,SBRD]'))
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default:taxonomy_term')
      ->setSetting('handler_settings', ['target_bundles' => ['s_breeds' => 's_breeds']])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // field_s_color (integer)
    $fields['field_s_color'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Color'))
      ->setDescription(t('Color. Numeric code representing the animal\'s color/pattern. [INV,WNMAS,LAMB:CLR]'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // field_s_dam.
    $fields['field_s_dam'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Dam'))
      ->setDescription('Dam of the sheep. All 0s or a letter followed by four 0s (X0000) indicates unknown dam. Very rare. [INV,WNMAS,LAMB,EWEMAS,PEDI:DAM]')
      ->setSetting('target_type', 'sheep_entities_sheep_record')
      ->setSetting('handler', 'default:sheep_entities_sheep_record')
      ->setSetting('handler_settings', [
        'sort' => ['field' => '_none', 'direction' => 'ASC'],
        'auto_create' => FALSE,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // field_s_sire.
    $fields['field_s_sire'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Sire'))
      ->setDescription(t('Sire. Reference to the sire sheep record. ID10 beginning with "US" indicates Unknown Sub. See README. [INV,WNMAS,LAMB,PEDI:SIRE]'))
      ->setSetting('target_type', 'sheep_entities_sheep_record')
      ->setSetting('handler', 'default:sheep_entities_sheep_record')
      ->setSetting('handler_settings', [
        'sort' => ['field' => '_none', 'direction' => 'ASC'],
        'auto_create' => FALSE,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // field_s_foster_dam.
    $fields['field_s_foster_dam'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Foster dam'))
      ->setDescription('Foster dam. Entity reference to the dam that raised this lamb, when different from the biological dam (field_s_dam). Migrated from LAMB:FONO on rows where FOPU="-", indicating this lamb was transferred to the foster dam identified by FONO. FOPU "+" rows are ignored (captured from the foster lamb\'s row instead). FOPU "8" indicates orphan/death with no foster placement. [LAMB:FONO,FOPU]')
      ->setSetting('target_type', 'sheep_entities_sheep_record')
      ->setSetting('handler', 'default:sheep_entities_sheep_record')
      ->setSetting('handler_settings', [
        'sort' => ['field' => '_none', 'direction' => 'ASC'],
        'auto_create' => FALSE,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // field_s_birth_date (datetime - date only)
    // Full date of birth. Use sheep_calendar for Julian day conversion.
    // Storage format: Y-m-d (e.g., "2024-03-15").
    // Migration: Use julian_to_date process plugin from sheep_calendar.
    // Example YAML:
    // field_s_birth_date:
    // plugin: julian_to_date
    // source: DAYBRN
    // year: constants/year_label
    // Sheep Year format: 2024/25.
    $fields['field_s_birth_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Birth date'))
      ->setDescription(t('Birth date. Base field type is "datetime" using the "date" format. If exposing a filter on this field, use either the "Birth date (Sheep Year)" or "Birth date (Year)" plugin (provided by the sheep_calendar module) to create an autopopulated dropdown of values.'))
      ->setSetting('datetime_type', 'date')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // field_s_card_num (integer)
    $fields['field_s_card_num'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Card number'))
      ->setDescription(t('Card number. Ordinal number indicates which lamb in the litter. [LAMB:CDNO]'))
      ->setSetting('unsigned', TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // field_s_cut (string)
    $fields['field_s_cut'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Cut'))
      ->setDescription(t('Cut. Boolean value to indicate castrated status. [LAMB:CUT]'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // field_s_disposal_date (datetime - date only)
    // Full date of disposal. Use sheep_calendar for Julian conversion.
    // Storage format: Y-m-d (e.g., "2024-04-28").
    // Migration: Use julian_to_date process plugin from sheep_calendar.
    // Example YAML:
    // field_s_disposal_date:
    // plugin: julian_to_date
    // source: DAYDIS
    // year: constants/year_label
    // Display: Use sheep_calendar Twig functions for date/age calculations.
    $fields['field_s_disposal_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Disposal date'))
      ->setDescription(t('Disposal date. Base field type is "datetime" using the "date" format. If exposing a filter on this field, use either the "Disposal date (Sheep Year)" or "Disposal date (Year)" plugin (provided by the sheep_calendar module) to create an autopopulated dropdown of values.'))
      ->setSetting('datetime_type', 'date')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // field_s_cause (term ref)
    $fields['field_s_cause'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Cause'))
      ->setDescription(t('Cause of death (lamb). References a taxonomy term). [LAMB:CAUSE]'))
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default:taxonomy_term')
      ->setSetting('handler_settings', ['target_bundles' => ['s_causes' => 's_causes']])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // field_s_preliminary_disp (integer)
    $fields['field_s_preliminary_disp'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Preliminary disposal'))
      ->setDescription(t('Preliminary disposal. Single-digit code (taxonomy term). [LAMB:DISP;WNMAS,INV,EWEMAS:PD]'))
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default:taxonomy_term')
      ->setSetting('handler_settings', ['target_bundles' => ['s_preliminary_disposals' => 's_preliminary_disposals']])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // field_s_disposal (term ref)
    $fields['field_s_disposal'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Disposal code'))
      ->setDescription(t('Disposal code. Reason or method of disposal (taxonomy term). [INV,LAMB,EWEMAS:DISP;WNMAS]'))
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default:taxonomy_term')
      ->setSetting('handler_settings', ['target_bundles' => ['s_disposal_codes' => 's_disposal_codes']])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // field_s_dna (text_long)
    $fields['field_s_dna'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('DNA'))
      ->setDescription(t('DNA. Free-text DNA markers (future: normalize to content entity). [WNMAS,INV:DNA]'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // field_s_etag (string) — UI label source.
    $fields['field_s_etag'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Ear tag'))
      ->setDescription(t('Ear tag. Visual ear tag identifier; 5 digits, usually letter followed by 4 numbers. [WNMAS,INV,LAMB:ETAG;INV,SHEAR:ID;LAMB,PEDI:LAMB]'))
      ->setSetting('max_length', 64)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // field_s_inbreeding (decimal 10,3 – matches typical 5.3)
    $fields['field_s_inbreeding'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Inbreeding coefficient'))
      ->setDescription(t('Inbreeding coefficient (e.g., 0.000–1.000). [WNMAS,INV,LAMB:INBRL]'))
      ->setSetting('precision', 10)
      ->setSetting('scale', 3)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // field_s_line (term ref)
    $fields['field_s_line'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Line'))
      ->setDescription(t('Line. Genetic or management line. [WNMAS,INV,LAMB,EWEMAS,PRESHR:LINE;WNMAS,LAMB:LNDAM]'))
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default:taxonomy_term')
      ->setSetting('handler_settings', ['target_bundles' => ['s_lines' => 's_lines']])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // field_s_rearing_type (term ref)
    $fields['field_s_rearing_type'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Rearing type'))
      ->setDescription(t('Rearing type. [WNMAS:TR]'))
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default:taxonomy_term')
      ->setSetting('handler_settings', ['target_bundles' => ['s_rearing_types' => 's_rearing_types']])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // field_s_rearing_type_equiv (string)
    $fields['field_s_rearing_type_equiv'] = BaseFieldDefinition::create('string')
      ->setLabel(t('TBRE'))
      ->setDescription(t('TBRE. Rearing type equivalent; adjustment for type of birth vs. type of rearing. [WNMAS:TBRE]'))
      ->setSetting('max_length', 64)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // field_s_rearing_type_17 (string)
    $fields['field_s_rearing_type_17'] = BaseFieldDefinition::create('string')
      ->setLabel(t('TBR17'))
      ->setDescription(t('TBR17. TBRE with adjustment for birth weight ratio of multiples. [WNMAS:TBR17]'))
      ->setSetting('max_length', 64)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // field_s_scrapie_tag (string)
    $fields['field_s_scrapie_tag'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Scrapie tag/IDZ65'))
      ->setDescription(t('Scrapie tag/IDZ65. Scrapie program tag identifier. [WNMAS,INV:IDZ65]'))
      ->setSetting('max_length', 64)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // field_s_sex (term ref)
    $fields['field_s_sex'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Sex'))
      ->setDescription(t('Sex of the animal (taxonomy term). [WNMAS,PRESHR:SEX]'))
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default:taxonomy_term')
      ->setSetting('handler_settings', ['target_bundles' => ['s_sex' => 's_sex']])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // field_s_subtype_mating (term ref)
    $fields['field_s_subtype_mating'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Subtype mating'))
      ->setDescription(t('STM. Specific mating subtype (taxonomy term). [WNMAS,INV,LAMB,EWEMAS,PRESHR,PEDI,PEDI-CM:STM;LAMB:STMD,STMS]'))
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default:taxonomy_term')
      ->setSetting('handler_settings', ['target_bundles' => ['s_subtype_matings' => 's_subtype_matings']])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_s_type_of_birth'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Type of birth'))
      ->setDescription(t('Type of birth. 10-point scoring system used to account for variability of live/dead status among litters. [LAMB,WNMAS:TB;WNMAS:TYB]'))
      ->setSetting('unsigned', TRUE)
      ->addConstraint('Range', ['min' => 1, 'max' => 10])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // field_s_is_purchased (boolean)
    $fields['field_s_is_purchased'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Purchased'))
      ->setDescription(t('Purchased. Boolean indicating purchased status. Purchased animals are assigned a value of 00 for BRD.'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // field_s_purchased_date (datetime - date only)
    $fields['field_s_purchased_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Purchased (date)'))
      ->setDescription(t('Purchased date. Date when animal was added to inventory. Base field type is "datetime" using the "date" format. If exposing a filter on this field, use either the "Purchased date (Sheep Year)" or "Purchased date (Year)" plugin (provided by the sheep_calendar module) to create an autopopulated dropdown of values.'))
      ->setSetting('datetime_type', 'date')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // field_s_is_unidentified_parent (boolean)
    $fields['field_s_is_unidentified_parent'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Unidentified parent'))
      ->setDescription(t('Boolean indicating that this is an unidentified parent record created to link offspring to a known sire or dam. Unidentified parent records have an ID10 beginning with "US" (Unknown Sire) or "UD" (Unknown Dam). They should not be included in population counts, reports or statistical queries.'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

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
