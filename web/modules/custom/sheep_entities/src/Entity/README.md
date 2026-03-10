NOTES ABOUT COMPUTED & DERIVED FIELDS IN MIGRATION DATA:
Body weight is always the actual measured weight at the observation
date; we capture it in field_s_body_weight.

Legacy adjusted weight-related values (WT120, Y6, AWNWT) are stored in
field_s_migration_legacy_data JSON keyed by original field name.

Avg daily gain: computed field; moving to legacy
$fields['field_s_adg'] = BaseFieldDefinition::create('physical_measurement')
  ->setLabel(t('Average daily gain (ADG)'))
  ->setDescription(t('Average daily gain from birth to weaning. [WEANING,RAMS: ADG]'))
  ->setSetting('measurement_type', 'weight')
  ->setDisplayOptions('form', [
    'type' => 'physical_measurement_default',
    'settings' => [
      'default_unit' => 'lb',
      'allow_unit_change' => TRUE,
      'available_units' => ['lb', 'kg'],
    ],
  ])
  ->setDisplayOptions('view', [
    'type' => 'physical_measurement_default',
    'settings' => [
      'output_unit' => '',
    ],
  ])
  ->setDisplayConfigurable('form', TRUE)
  ->setDisplayConfigurable('view', TRUE);

Coefficient of variation is derived from the ratio of the Standard Deviation to the mean diameter (recorded
in field_s_micron). For migrating legacy data, we will capture all calculated values in field_s_migration_legacy_data
for integrity checks but the Drupal data model will only record measurements; calculations will be computed
from field data and displayed.
$fields['field_s_cv'] = BaseFieldDefinition::create('decimal')
  ->setLabel(t('Coefficient of variation'))
  ->setDescription(t('Coefficient of variation of fiber diameter. [PRESHEARING,RAMS: CV]'))
  ->setSetting('precision', 6)
  ->setSetting('scale', 2)
  ->setDisplayConfigurable('form', TRUE)
  ->setDisplayConfigurable('view', TRUE);

Diameter grade: moving this to legacy_data for migration.
USDA spinning count maps deterministically from micron ranges
(e.g., 20.60–22.04 µm = 64s). A computed display formatter should
be implemented to derive this from field_s_micron for new records.
@see @todo in class docblock.
$fields['field_s_diameter_grade'] = BaseFieldDefinition::create('string')
  ->setLabel(t('Diameter grade'))
  ->setDescription(t('USDA grade for fiber diameter. Can be computed from fiber diameter (µm). Stored for legacy migration data integrity; new records should derive this value. [PRESHEARING,RAMS,SHEARING: DIGR]'))
  ->setSetting('max_length', 16)
  ->setDisplayConfigurable('form', TRUE)
  ->setDisplayConfigurable('view', TRUE);

── Overflow & provenance ────────────────────────────────────────

All fields not represented above are stored here as a JSON object
keyed by original source field name. Always includes _year and
_source_table metadata keys.


From SheepRecord.php:

Originally had legacy fields; these were removed when the "Overflow & provenance" fields were added.
    // field_s_born_day_julian (integer) - DEPRECATED - Use field_s_birth_date instead
    // This field exists for legacy data compatibility only
    $fields['field_s_born_day_julian'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Born (Julian day) [DEPRECATED]'))
      ->setDescription(t('Day of year (1-366) on which the animal was born. DEPRECATED: Use field_s_birth_date instead. [LAMB,WNMAS:DAYBRN]'))
      ->setSetting('unsigned', TRUE)
      ->addConstraint('Range', ['min' => 1, 'max' => 366])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // field_s_born_year (integer) - DEPRECATED - Use field_s_birth_date instead
    // This field exists for legacy data compatibility only
    $fields['field_s_born_year'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Born (year) [DEPRECATED]'))
      ->setDescription(t('Four-digit year of birth. DEPRECATED: Use field_s_birth_date instead. [Derived from LAMB:YR for on-site births or from INV:YRREC of first appearance in INV]'))
      ->setSetting('unsigned', TRUE)
      ->addConstraint('Range', ['min' => 1000, 'max' => 9999])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

// field_s_disposal_day_julian (integer) - DEPRECATED - Use field_s_disposal_date instead
    // This field exists for legacy data compatibility only
    $fields['field_s_disposal_day_julian'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Disposal (Julian day) [DEPRECATED]'))
      ->setDescription(t('Day of year (1-366) of disposal. DEPRECATED: Use field_s_disposal_date instead. [WNMAS,INV,LAMB:DAYDIS]'))
      ->setSetting('unsigned', TRUE)
      ->addConstraint('Range', ['min' => 1, 'max' => 366])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // field_s_disposal_year (integer) - DEPRECATED - Use field_s_disposal_date instead
    // This field exists for legacy data compatibility only
    $fields['field_s_disposal_year'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Disposal (year) [DEPRECATED]'))
      ->setDescription(t('Four-digit year of disposal. DEPRECATED: Use field_s_disposal_date instead. [Derived from LAMB:YR for stillbirths or from INV:YRREC of disposal code in INV]'))
      ->setSetting('unsigned', TRUE)
      ->addConstraint('Range', ['min' => 1900, 'max' => 2099])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
