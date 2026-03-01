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
use Drupal\sheep_entities\Form\ObservationRecordForm;
use Drupal\sheep_entities\ObservationRecordAccessControlHandler;
use Drupal\sheep_entities\ObservationRecordInterface;
use Drupal\sheep_entities\ObservationRecordListBuilder;
use Drupal\sheep_entities\Routing\ObservationRecordHtmlRouteProvider;
use Drupal\views\EntityViewsData;

/**
 * Defines the Observation Record entity.
 *
 * One record per animal per life-stage event per year. Each row captures
 * all measurements and scores co-collected at a single handling event.
 * A single legacy source row (e.g., WEANING) may produce multiple
 * Observation Records when it contains measurements taken on different
 * dates (e.g., WNWT at weaning vs. CUWT weeks later).
 *
 * Fields not represented as dedicated base fields are stored in the
 * field_s_migration_legacy_data JSON overflow field. This includes: selection
 * indices (IDX, MENB, MELW, M_IDX, F_IDX), wool character scores
 * (F, H, N, T, C), reproductive scores (LIB, SEMEN, FERT, PROL),
 * Y-traits (Y3–Y6), EBV fields, CLPG genotyping, C-prefix coded
 * fields, date-stamped weights, fleece grades, eligibility codes,
 * disposition codes at observation, abnormality codes, and all
 * other historical or one-off fields.
 *
 * @todo Add computed display formatter for USDA diameter grade derived
 *   from field_s_micron value. USDA spinning count maps deterministically
 *   from micron ranges (e.g., 20.60–22.04 µm = 64s). During migration,
 *   validate source DIGR against calculated grade and log discrepancies
 *   in field_s_migration_notes.
 *
 * @todo Add sheep_calendar module function for "Coded age of dam" (CADAM)
 *   display format. Source: WNMAS and other tables have CADAM field for
 *   "Coded age of dam; age of dam, coded by production stages of life."
 *   This should be a display formatter or Twig function that converts a
 *   dam's age into the coded format used historically.
 *
 * @todo WT120/Y6 (120-day adjusted weight) values from legacy data are
 *   stored in field_s_migration_legacy_data JSON. Consider implementing a computed
 *   display that derives 120-day adjusted weight from field_s_body_weight
 *   + field_s_observation_date + SheepRecord.field_s_birth_date using
 *   sheep_calendar module.
 */
#[
    ContentEntityType(
        id: "sheep_entities_observation_rec",
        label: new TranslatableMarkup("Observation Record"),
        label_collection: new TranslatableMarkup("Observation Records"),
        label_singular: new TranslatableMarkup("observation record"),
        label_plural: new TranslatableMarkup("observation records"),
        handlers: [
            "list_builder" => ObservationRecordListBuilder::class,
            "views_data" => EntityViewsData::class,
            "access" => ObservationRecordAccessControlHandler::class,
            "form" => [
                "add" => ObservationRecordForm::class,
                "edit" => ObservationRecordForm::class,
                "delete" => ContentEntityDeleteForm::class,
                "delete-multiple-confirm" => DeleteMultipleForm::class,
            ],
            "route_provider" => [
                "html" => ObservationRecordHtmlRouteProvider::class,
            ],
        ],
        base_table: "sheep_entities_observation_rec",
        admin_permission: "administer sheep_entities_observation_rec",
        entity_keys: [
            "id" => "id",
            "uuid" => "uuid",
            "label" => "id",
        ],
        links: [
            "collection" => "/admin/content/observation-records",
            "add-form" => "/observation-record/add",
            "canonical" =>
                "/observation-record/{sheep_entities_observation_rec}",
            "edit-form" =>
                "/observation-record/{sheep_entities_observation_rec}/edit",
            "delete-form" =>
                "/observation-record/{sheep_entities_observation_rec}/delete",
            "delete-multiple-form" =>
                "/admin/content/observation-records/delete-multiple",
        ],
        label_count: [
            "singular" => "@count observation record",
            "plural" => "@count observation records",
        ]
    )
]
final class ObservationRecord extends ContentEntityBase implements
    ObservationRecordInterface
{
    /**
     * {@inheritdoc}
     */
    public static function baseFieldDefinitions(
        EntityTypeInterface $entity_type
    ): array {
        $fields = [];

        // ── Primary keys ────────────────────────────────────────────────

        $fields["id"] = BaseFieldDefinition::create("integer")
            ->setLabel(t("ID"))
            ->setReadOnly(true);

        $fields["uuid"] = BaseFieldDefinition::create("uuid")
            ->setLabel(t("UUID"))
            ->setReadOnly(true);



        // Sheep reference
        $fields['field_s_sheep'] = BaseFieldDefinition::create('entity_reference')
          ->setLabel(t('Sheep'))
          ->setDescription(t('The sheep this observation record belongs to.'))
          ->setSetting('target_type', 'sheep_entities_sheep_record')
          ->setSetting('handler', 'default:sheep_entities_sheep_record')
          ->setSetting('handler_settings', [
            'sort' => ['field' => '_none', 'direction' => 'ASC'],
            'auto_create' => FALSE,
          ])
          ->setRequired(TRUE)
          ->setDisplayConfigurable('form', TRUE)
          ->setDisplayConfigurable('view', TRUE);

        $fields["field_s_observation_date"] = BaseFieldDefinition::create(
            "datetime"
        )
            ->setLabel(t("Observation date"))
            ->setDescription(
                t(
                    'Date when measurements were collected. All measurements on this record were co-collected at this date. Base field type is "datetime" using the "date" format.'
                )
            )
            ->setSetting("datetime_type", "date")
            ->setDisplayConfigurable("form", true)
            ->setDisplayConfigurable("view", true);

        $fields["field_s_life_stage_event"] = BaseFieldDefinition::create(
            "entity_reference"
        )
            ->setLabel(t("Life stage event"))
            ->setDescription(
                t(
                    "The type of handling/observation event (e.g., Weaning, Shearing, Fall Weight). Determines which fields on this record are expected to be populated."
                )
            )
            ->setSetting("target_type", "taxonomy_term")
            ->setSetting("handler", "default:taxonomy_term")
            ->setSetting("handler_settings", [
                "target_bundles" => [
                    "s_life_stage_events" => "s_life_stage_events",
                ],
                "sort" => ["field" => "name", "direction" => "ASC"],
                "auto_create" => false,
            ])
            ->setDisplayConfigurable("form", true)
            ->setDisplayConfigurable("view", true);

        // ── Body weight & condition ──────────────────────────────────────

        $fields["field_s_body_weight"] = BaseFieldDefinition::create(
            "physical_measurement"
        )
            ->setLabel(t("Body weight"))
            ->setDescription(
                t(
                    "Body weight measured at the observation date. Maps to WNWT, SPWT, FALLWT, CUWT, SAWT, LBS, or other weight fields depending on life stage event. Legacy adjusted values (WT120, Y6) are stored in the legacy data JSON field."
                )
            )
            ->setSetting("measurement_type", "weight")
            ->setDisplayOptions("form", [
                "type" => "physical_measurement_default",
                "settings" => [
                    "default_unit" => "lb",
                    "allow_unit_change" => true,
                    "available_units" => ["lb", "kg"],
                ],
            ])
            ->setDisplayOptions("view", [
                "type" => "physical_measurement_default",
                "settings" => [
                    "output_unit" => "",
                ],
            ])
            ->setDisplayConfigurable("form", true)
            ->setDisplayConfigurable("view", true);

        $fields["field_s_bcs"] = BaseFieldDefinition::create("decimal")
            ->setLabel(t("Body condition score"))
            ->setDescription(
                t(
                    "Body condition score, 1.0 to 5.0 in 0.5 increments. Not collected during lamb first year. [BREEDING: BCS_FALL, BCS_NOV, BCS_WIN; SHEARING: BCS]"
                )
            )
            ->setSetting("precision", 3)
            ->setSetting("scale", 1)
            ->setDisplayConfigurable("form", true)
            ->setDisplayConfigurable("view", true);

        // ── Reproductive / ewe assessment ────────────────────────────────

        $fields["field_s_udder_score"] = BaseFieldDefinition::create("integer")
            ->setLabel(t("Udder score (BAG)"))
            ->setDescription(
                t(
                    "13-point udder scoring system. Collected on mature ewes in September at fall weight. [INV: BAG]"
                )
            )
            ->setDisplayConfigurable("form", true)
            ->setDisplayConfigurable("view", true);

        $fields["field_s_pregnancy_status"] = BaseFieldDefinition::create(
            "boolean"
        )
            ->setLabel(t("Pregnancy status"))
            ->setDescription(
                t(
                    "Pregnancy detection result. Applies to both first-year ewe lambs and mature ewes. [INV: PREG]"
                )
            )
            ->setDisplayConfigurable("form", true)
            ->setDisplayConfigurable("view", true);

        // ── Physical scores ──────────────────────────────────────────────

        $fields["field_s_face"] = BaseFieldDefinition::create("integer")
            ->setLabel(t("Face score"))
            ->setDescription(
                t(
                    "15-point scoring system for wool cover on face. [PRESHEARING,WEANING: FACE]"
                )
            )
            ->setDisplayConfigurable("form", true)
            ->setDisplayConfigurable("view", true);

        $fields["field_s_horn"] = BaseFieldDefinition::create("integer")
            ->setLabel(t("Horn score"))
            ->setDescription(
                t("7-point horn scoring system. [PRESHEARING,WEANING: HORN]")
            )
            ->setDisplayConfigurable("form", true)
            ->setDisplayConfigurable("view", true);

        // ── Fleece & wool ────────────────────────────────────────────────

        $fields["field_s_fleece_weight"] = BaseFieldDefinition::create(
            "physical_measurement"
        )
            ->setLabel(t("Fleece weight"))
            ->setDescription(
                t(
                    "Fleece weight collected at shearing in February. Stores both grease fleece (GRFL, recorded in lbs to 0.1 precision) and processed fleece weight (FLWT) depending on source. [SHEARING: GRFL, FLWT]"
                )
            )
            ->setSetting("measurement_type", "weight")
            ->setDisplayOptions("form", [
                "type" => "physical_measurement_default",
                "settings" => [
                    "default_unit" => "lb",
                    "allow_unit_change" => true,
                    "available_units" => ["lb", "kg", "g", "oz"],
                ],
            ])
            ->setDisplayOptions("view", [
                "type" => "physical_measurement_default",
                "settings" => [
                    "output_unit" => "",
                ],
            ])
            ->setDisplayConfigurable("form", true)
            ->setDisplayConfigurable("view", true);

        $fields["field_s_staple"] = BaseFieldDefinition::create(
            "physical_measurement"
        )
            ->setLabel(t("Staple length"))
            ->setDescription(
                t(
                    "Staple length of wool sample. Yearling measurement. [PRESHEARING: SL, STAPLE_IN, STAPLE_CM; RAMS: SL]"
                )
            )
            ->setSetting("measurement_type", "length")
            ->setDisplayOptions("form", [
                "type" => "physical_measurement_default",
                "settings" => [
                    "default_unit" => "in",
                    "allow_unit_change" => true,
                    "available_units" => ["in", "cm"],
                ],
            ])
            ->setDisplayOptions("view", [
                "type" => "physical_measurement_default",
                "settings" => [
                    "output_unit" => "",
                ],
            ])
            ->setDisplayConfigurable("form", true)
            ->setDisplayConfigurable("view", true);

        // Fiber diameter is measured in microns (µm), which is not a unit
        // supported by the drupal/physical module. Stored as plain decimal; can have µm added as suffix in displays.
        $fields["field_s_fiber_diameter"] = BaseFieldDefinition::create(
            "decimal"
        )
            ->setLabel(t("Fiber diameter"))
            ->setDescription(
                t(
                    "Fiber diameter of yearling wool sample, in microns (µm). [PRESHEARING,RAMS,SHEARING: MICRON]"
                )
            )
            ->setSetting("precision", 6)
            ->setSetting("scale", 2)
            ->setDisplayConfigurable("form", true)
            ->setDisplayConfigurable("view", true);

        $fields["field_s_standard_deviation"] = BaseFieldDefinition::create(
            "decimal"
        )
            ->setLabel(t("Standard deviation"))
            ->setDescription(
                t(
                    "Standard deviation of fiber diameter. [PRESHEARING,RAMS: SD]"
                )
            )
            ->setSetting("precision", 6)
            ->setSetting("scale", 2)
            ->setDisplayConfigurable("form", true)
            ->setDisplayConfigurable("view", true);

        $fields["field_s_clean_fleece"] = BaseFieldDefinition::create("decimal")
            ->setLabel(t("Clean fleece (%)"))
            ->setDescription(
                t(
                    "Clean fleece expressed as a percentage. [PRESHEARING,RAMS: CF]"
                )
            )
            ->setSetting("precision", 6)
            ->setSetting("scale", 2)
            ->setDisplayConfigurable("form", true)
            ->setDisplayConfigurable("view", true);

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
