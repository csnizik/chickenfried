---
applyTo: "web/modules/custom/sheep_entities/**"
---

## Module-Specific Context: Sheep Entities

### Module Overview
- **Machine name**: `sheep_entities`
- **Version**: custom module (compatible with Drupal 10+ / 11+)
- **Purpose**: Provides domain-specific content entity types (Sheep Record, Lamb Card, Annual Assignment) plus admin UIs, forms, list builders, Views integration, and entity route providers for managing sheep/lamb-related research data.
- **Official documentation**: internal README at `web/modules/custom/sheep_entities/README.md` (no upstream project page)

### Key APIs and Services
- Primary services commonly used by the module code:
  - `entity_type.manager` (entity storage & queries)
  - `logger.factory` (logging)
  - `config.factory` (configuration reads)
  - `database` / `entity.query` (when performing custom queries)
- Important classes (paths under `Drupal\\sheep_entities` namespace):
  - `Drupal\\sheep_entities\\Entity\\SheepRecord`
  - `Drupal\\sheep_entities\\Entity\\LambCard`
  - `Drupal\\sheep_entities\\Entity\\AnnualAssignment`
  - Access control handlers: `*AccessControlHandler.php`
  - List builders: `*ListBuilder.php`
  - Entity forms: `src/Form/*Form.php`
  - Route providers: `src/Routing/*HtmlRouteProvider.php`
- Hook/extension points to be aware of:
  - The module is primarily entity-driven; there are no special hook implementations required by consumers, but standard Drupal hooks (for example `hook_entity_presave`, `hook_entity_update`) may be used by integrators to react to entity changes.

### Common Patterns
When working with `sheep_entities`:
1. Always use dependency injection for services (for example, inject `EntityTypeManagerInterface` instead of calling `\\Drupal::entityTypeManager()` directly).
2. Interact with the entities using the Entity API and Entity Query (load via the entity storage service, use `loadMultiple()` for batches).
3. Update entity base fields carefully: the module defines base fields programmatically — follow Drupal's entity update process and run `drush updatedb` and `drush entup` when field definitions change.
4. Ensure required taxonomy vocabularies (e.g., `s_breeds`, `s_disposal_codes`, `s_lines`, `s_rearing_types`, `s_sex`, etc.) exist before importing or enabling Views that reference them.
5. Views that reference these entities may list `better_exposed_filters` as a dependency — enable that contrib module before importing Views config.

### Code Examples
1) Load a Sheep Record by ID (inside a service or controller using dependency-injected entity type manager):

```php
// Injected: EntityTypeManagerInterface $entityTypeManager
$storage = $this->entityTypeManager->getStorage('sheep_entities_sheep_record');
$sheep = $storage->load($nid);
if ($sheep) {
  // Access a base field.
  $id10 = $sheep->get('field_s_id10')->value;
}
```

2) Create and save a new Sheep Record (minimal example):

```php
$values = [
  'field_s_id10' => 'ID10-123',
  'field_s_born_year' => 2024,
  // set other base fields as needed
];
$sheep = $this->entityTypeManager->getStorage('sheep_entities_sheep_record')->create($values);
$sheep->save();
```

### Known Issues / Gotchas
- Base fields are defined in code — changing them requires database updates (`drush updatedb`, `drush entup`) and careful migration of existing data.
- Views exported in `config/sync` may depend on `better_exposed_filters` and on taxonomy vocabularies; enable dependencies before importing configuration to avoid config import failures.
- The `field_s_migration_notes` field on `LambCard` is intentionally reserved for migration logging and is not intended for public display.
- Permissions are entity-scoped (for each entity type): `create`, `view`, `edit`, `delete`, and `administer` for `sheep_record`, `lamb_card`, and `annual_assignment` — when adding roles, assign the correct granular permissions rather than relying on broad admin roles.

---

For more details see the full developer README: `web/modules/custom/sheep_entities/README.md` and the entity classes under `web/modules/custom/sheep_entities/src/Entity/`.
