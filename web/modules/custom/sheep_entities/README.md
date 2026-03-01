# Sheep Entities (sheep_entities)

This module provides a small set of custom Content Entity types used by the ARS Apps platform to model sheep and lamb-related tabular data. It is intentionally lightweight and focuses on structured data that maps to legacy identifiers and taxonomies used by the research teams.

This README documents the module's entity types, fields, forms, list builders, routes, permissions, and developer notes to help maintainers and integrators.

---

## Module summary

- Machine name: `sheep_entities`
- Purpose: Provide domain-specific content entities (Sheep Record, Lamb Card, Annual Assignment) with administration UI, forms, list builders, and integrations with taxonomies and views.
- Core compatibility: Drupal 10+ / 11+
- Location: `web/modules/custom/sheep_entities`

---

## Provided entity types

1. sheep_entities_sheep_record (Sheep Record)
	- Base table: `sheep_entities_sheep_record`
	- Admin list: `/admin/content/sheep`
	- Add/edit path: `/sheep/add`, `/sheep/{sheep_entities_sheep_record}/edit`
	- Canonical: `/sheep/{sheep_entities_sheep_record}`
	- Admin permission: `administer sheep_entities_sheep_record`

	Fields (base fields defined in `SheepRecord::baseFieldDefinitions`):
	- `id` (integer, read-only)
	- `uuid` (uuid, read-only)
	- `field_s_id10` (string) — primary human identifier (ID10), Unique constraint
	- `field_s_altid` (string) — alternate IDs
	- `field_s_breed` (entity_reference -> taxonomy_term: s_breeds)
	- `field_s_color` (integer) — color code
	- `field_s_dam` (entity_reference -> sheep_entities_sheep_record)
	- `field_s_born_day_julian` (integer) — born day (Julian 1–366)
	- `field_s_born_year` (integer)
	- `field_s_card_num` (integer) — birth order in lambing event
	- `field_s_disposal_day_julian` (integer)
	- `field_s_disposal_year` (integer)
	- `field_s_disposal` (entity_reference -> taxonomy_term: s_disposal_codes)
	- `field_s_dna` (text_long)
	- `field_s_etag` (string) — ear tag
	- `field_s_inbreeding` (decimal)
	- `field_s_line` (entity_reference -> taxonomy_term: s_lines)
	- `field_s_notes` (entity_reference -> node)
	- `field_s_rearing_type` (entity_reference -> taxonomy_term: s_rearing_types)
	- `field_s_scrapie_tag` (string)
	- `field_s_sex` (entity_reference -> taxonomy_term: s_sex)
	- `field_s_sire` (entity_reference -> sheep_entities_sheep_record)
	- `field_s_subtype_mating` (entity_reference -> taxonomy_term: s_subtype_matings)
	- `field_s_legacy_sire` (string)

	Handlers & support files:
	- Entity class: `\\Drupal\\sheep_entities\\Entity\\SheepRecord`
	- Interface: `\\Drupal\\sheep_entities\\SheepRecordInterface`
	- Access control handler: `SheepRecordAccessControlHandler.php`
	- List builder: `SheepRecordListBuilder.php`
	- Form: `Form/SheepRecordForm.php`
	- Route provider: `Routing/SheepRecordHtmlRouteProvider.php`
	- Views integration: `EntityViewsData`

2. sheep_entities_lamb_card (Lamb Card)
	- Base table: `sheep_entities_lamb_card`
	- Admin list: `/admin/content/lamb_card`
	- Add/edit path: `/lamb_card/add`, `/lamb_card/{sheep_entities_lamb_card}/edit`
	- Admin permission: `administer sheep_entities_lamb_card`

	Fields (base fields defined in `LambCard::baseFieldDefinitions`):
	- `id`, `uuid`
	- `field_s_sheep` (entity_reference -> sheep_entities_sheep_record)
	- `field_s_abnormality` (entity_reference -> taxonomy_term: s_abnormalities)
	- `field_s_body_condition` (decimal)
	- `field_s_face_score` (integer)
	- `field_s_horn_score` (integer)
	- `field_s_jaw_score` (integer)
	- `field_s_migration_notes` (string_long) — internal migration logging

	Handlers & support files:
	- Entity class: `\\Drupal\\sheep_entities\\Entity\\LambCard`
	- Interface: `LambCardInterface` (under `src/`)
	- Access control: `LambCardAccessControlHandler.php`
	- List builder: `LambCardListBuilder.php`
	- Form: `Form/LambCardForm.php`
	- Route provider: `Routing/LambCardHtmlRouteProvider.php`

3. sheep_entities_annual_assignment (Annual Assignment)
	- Base table: `sheep_entities_annual_assignment`
	- Admin list: `/admin/content/annual_assignment`
	- Add/edit path: `/annual_assignment/add`, `/annual_assignment/{sheep_entities_annual_assignment}/edit`
	- Admin permission: `administer sheep_entities_annual_assignment`

	Fields (base fields defined in `AnnualAssignment::baseFieldDefinitions`):
	- `id`, `uuid`
	- `field_s_year` (integer)
	- `field_s_sheep` (entity_reference -> sheep_entities_sheep_record)
	- Taxonomy references: `field_s_band`, `field_s_breeding_group`, `field_s_breeding_pen`, `field_s_group`, `field_s_lot`, `field_s_research_group`, `field_s_study`, `field_s_study_treatment`, `field_s_treatment`, `field_s_treatment_pen` (all taxonomy term refs to various bundles: s_bands, s_breeding_groups, etc.)

	Handlers & support files:
	- Entity class: `\\Drupal\\sheep_entities\\Entity\\AnnualAssignment`
	- Interface: `AnnualAssignmentInterface`
	- Access control handler: `AnnualAssignmentAccessControlHandler.php`
	- List builder: `AnnualAssignmentListBuilder.php`
	- Form: `Form/AnnualAssignmentForm.php`
	- Route provider: `Routing/AnnualAssignmentHtmlRouteProvider.php`

---

## Permissions

Defined in `sheep_entities.permissions.yml`:

- `administer sheep_entities_annual_assignment` (restrict access)
- `view sheep_entities_annual_assignment`
- `edit sheep_entities_annual_assignment`
- `delete sheep_entities_annual_assignment`
- `create sheep_entities_annual_assignment`

- `administer sheep_entities_sheep_record` (restrict access)
- `view sheep_entities_sheep_record`
- `edit sheep_entities_sheep_record`
- `delete sheep_entities_sheep_record`
- `create sheep_entities_sheep_record`

- `administer sheep_entities_lamb_card` (restrict access)
- `view sheep_entities_lamb_card`
- `edit sheep_entities_lamb_card`
- `delete sheep_entities_lamb_card`
- `create sheep_entities_lamb_card`

Assign these permissions to roles as appropriate.

---

## Routes & Menu links

Routes are declared in `sheep_entities.routing.yml` and route providers under `src/Routing/*` customize HTML routes. The module also provides administrative menu links and local tasks via `sheep_entities.links.*.yml` files. Common routes include entity collection pages under `/admin/content/*` and entity CRUD paths under the `/sheep/`, `/lamb_card/`, and `/annual_assignment/` paths.

---

## Forms

- `src/Form/SheepRecordForm.php` — Add/Edit form for Sheep Record entities.
- `src/Form/LambCardForm.php` — Add/Edit form for Lamb Card entities.
- `src/Form/AnnualAssignmentForm.php` — Add/Edit form for Annual Assignment entities.

These forms implement standard EntityForm behaviors and inherit from Drupal core form base classes. They expose base fields and any custom widgets defined in the project.

---

## List builders & admin UI

- `SheepRecordListBuilder` provides an administrative table view for Sheep Records used on the collection route (`/admin/content/sheep`). It defines header columns and row rendering for common fields (ear tag, ID10, birth year).
- Similar list builders exist for LambCard and AnnualAssignment (class files under `src/`).

---

## Access control

Access is handled by entity AccessControlHandler classes (`SheepRecordAccessControlHandler`, `LambCardAccessControlHandler`, `AnnualAssignmentAccessControlHandler`). These enforce entity-level permissions (view/edit/delete) and integrate with Drupal's role/permission system.

---

## Integration & dependencies

- Integrates with taxonomy vocabularies used by the project (expected bundles include `s_breeds`, `s_disposal_codes`, `s_lines`, `s_rearing_types`, `s_sex`, `s_studies`, `s_treatments`, `s_bands`, `s_breeding_groups`, etc.). Those vocabularies should be provided elsewhere in the project.
- Views integration is available via core `EntityViewsData` handler so you can build Views that display these entities.

---

## Installation & updates

- Enable the module as normal: `drush en sheep_entities` or via the UI.
- The module ships `sheep_entities.install` providing any schema or update logic required for base tables and initial taxonomy wiring.
- When updating code that changes entity base fields, follow Drupal's entity update procedure and run database updates: `drush updatedb` and `drush entup` if needed.

---

## Developer notes

- All entity classes use PHP 8 attributes (`#[ContentEntityType(...)]`) for Drupal 10+/11+ compatibility.
- Base fields are defined programmatically — field storage and configuration will be created when the module is installed.
- The module prefers entity reference base fields to taxonomy terms and self-referential entity references (e.g., sire/dam) for pedigree modeling.
- The `field_s_migration_notes` field on `LambCard` is intentionally not displayed; it is reserved for migration logging.

---

## Where to look next (files of interest)

- `sheep_entities.info.yml` — module metadata
- `sheep_entities.install` — installation hooks and schema updates
- `sheep_entities.permissions.yml` — permissions
- `sheep_entities.routing.yml` — route definitions
- `src/Entity/*` — entity classes (SheepRecord, LambCard, AnnualAssignment)
- `src/Form/*` — entity forms
- `src/*AccessControlHandler.php` — access handlers
- `src/*ListBuilder.php` — list builders
- `src/Routing/*HtmlRouteProvider.php` — route providers

---

## Maintainers

- Current maintainers: see project CONTRIBUTORS or module header docblocks for author information.


---

If you want, I can also:
- generate a developer-focused UPGRADE.md describing entity schema update steps,
- add example Views or exportable configuration for common admin lists,
- create a sample migration mapping for importing legacy CSV/DB data into these entities.

---

## Views integrations (existing config)

The codebase contains exported Views that reference the `sheep_entities` module and the `sheep_entities_sheep_record` base table. These Views are provided in `config/sync` and can be imported via configuration import. The most relevant Views are:

- `views.view.sheep_overview.yml` — "Sheep overview" view. Uses `sheep_entities_sheep_record` as the base table and provides aggregated/grouped columns such as ID10 and birth year. Suitable as a high-level summary page.
- `views.view.testing_sheep_records_table_page.yml` — "Testing sheep records (table, page)" view. A table-style page showing common sheep record fields (ID10, Breed, etc.) and useful for QA/testing.
- `views.view.test_migrations.yml` — "Test Migrations" view. Intended for migration QA; includes import-related fields and internal migration log columns.
- `views.view.testing_sheep_records_table_page.yml` — test/table variant used by automated tests and QA.

Notes:

- These views depend on the `sheep_entities` module and `better_exposed_filters` (where noted in the view config). Ensure those modules are enabled before importing the Views.
- Import these by running `drush cim` or via the Configuration sync UI. For local testing you can temporarily enable them via `drush cim --partial --source=config/sync` while in a safe environment.
- If you need a lightweight admin list, `sheep_overview` is a good starting point to create a dashboard or admin page.
