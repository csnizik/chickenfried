---
applyTo: "web/modules/custom/sheep_migration/**"
---

## Module-Specific Context: Sheep Migration

### Module overview
- **Machine name**: `sheep_migration`
- **Purpose**: Provides all CSV-to-entity migrations for Sheep Experiment Station datasets (INV, LAMB, WEAN, SHEAR, PEDI, etc.).
  Handles preprocessing (ID10 normalization, ETAG cleanup), entity creation, taxonomy mapping, parent/child relationships, group assignment, and multi-year batch execution.
- **Primary dependencies**:
  - `migrate`
  - `migrate_plus`
  - `migrate_tools`
  - `sheep_entities`
  - `group` (for group-content attach migrations)
- **File locations**:
  - **Working directory for migration definitions**: `sheep_migration/_config/_migrations/*/*.yml`
      - This is the **source of truth** for all migration configs during development
      - All migration configs are organized in subdirectories by type (sr/, inv/, etc.)
  - **Import/Export directories**:
      - `./config/sync/`: Drupal's active configuration directory (managed by Drupal)
      - `./web/modules/cusstom/sheep_migration/config/install/`: NOT USED for migration configs during development. Will hold all migration files once module is production-ready.
  - **Custom plugins**: `src/Plugin/migrate/`

### **Migration Config Management Workflow**

#### **Import Workflow** (Apply new/updated migration to site)
1. **Move** (don't copy) the migration file from `_config/_migrations/` to `config/sync/`
2. Run: `ddev drush cim -y`
3. **Move** (don't copy) the file back from `config/sync/` to `_config/_migrations/`

*Why this works:* Drupal imports from `config/sync`, so we temporarily place the file there, import it, then move it back to prevent drift.

#### **Export Workflow** (Sync site changes back to module)
1. Run: `ddev drush cex -y` (this updates files in `config/sync/`)
2. For each updated migration config in `config/sync/`:
   - **Copy** the file contents from `config/sync/migrate_plus.migration.*.yml`
   - **Replace** the corresponding file in `_config/_migrations/`
   - Remove the `uuid:` line from the file in `_config/_migrations/`
3. Leave the file in `config/sync/` (don't move or delete it unless you're permanently removing that config)

*Why this works:* `drush cex` updates `config/sync`, so we copy those changes back to our working directory to keep them in sync.

#### **Workflow Rules**
- ✅ Only **one copy** of each migration config exists in the module codebase at a time
- ✅ `_config/_migrations/` is the source of truth for development
- ✅ Use **move** for imports (temporary placement in `config/sync`)
- ✅ Use **copy** for exports (syncing changes back from `config/sync`)
- ❌ Never edit files in `config/sync/` directly
- ❌ Never use `config/install/` for migration configs during development


### Key APIs and services used by the module
The `sheep_migration` module frequently uses:

- **Core Migrate API**
  `\Drupal\migrate\Plugin\Migration`, `\Drupal\migrate\MigrateExecutable`,
  `process` pipeline plugins, destination plugins (`entity:*`)
- **Migrate Plus**
  Enhanced configuration entities (`migrate_plus.migration.*.yml`), migration groups, and `url`/`csv` source plugins
- **Migrate Tools**
  Drush commands: `migrate:import`, `migrate:rollback`, `migrate:status`
- **Custom process plugins** located at:
  `sheep_migration/src/Plugin/migrate/process/*`
  Examples:
  - `validate_id10_length`
  - `normalize_etag`
  - `sheep_parent_lookup`
  - `sheep_date_julian_to_iso`
- **Custom source plugins** (if applicable) in `src/Plugin/migrate/source`

### Important classes for contributors
Namespace: `Drupal\sheep_migration`

- `Plugin\migrate\process\ValidateId10Length`
- `Plugin\migrate\process\SheepNormalizeEtag`
- `Plugin\migrate\source\*` (source plugins for multi-table joins)
- `Plugin\migrate\process\SheepEntityLookup`
- `Plugin\migrate\process\SheepParentResolve`
- Migration grouping config:
  `config/install/migrate_plus.migration_group.sheep_data.yml`

### Common patterns required in this module

1. **Every migration must use ID10 as canonical identifier**
   Migrations should define:
   ```yaml
   source:
     ids: [ID10]
     ```
2. **Use `entity_lookup` for parents (never `entity_generate`)**
   Ensures no stub animals are created.
3. **Compose multi-step process pipelines**
  Examples:
    - skip_on_value for filtering rows by disposal codes or grafts
    - chains involving callback -> skip_on_empty -> entity_lookup
4. **Always define group-attach migrations separately**
  Pattern:
    - Migration A: creates the sheep entity
    - Migration B: group_content attachment using source: plugin: migration_lookup
5. **Use migration groups for shared config**
      ```yaml
      migration_group: sheep_data
      ```
6. **Keep all custom logic inside process plugins, not YAML**
    YAML should orchestrate; PHP plugins should handle business logic.

### Typical Drush workflow used by this module

#### Run a single migration:
```bash
ddev drush migrate:import sr_lambp_2010
```

#### Rollback:
```bash
ddev drush migrate:rollback sr_lambp_2010
```

#### Import/Export migration configs (see workflow above):
```bash
# Import: After moving file to config/sync
ddev drush cim -y

# Export: To sync site changes
ddev drush cex -y
```

#### List all sheep migrations:
```bash
ddev drush migrate:status | grep sheep
```
## Code examples
1. Referencing a parent via entity_lookup

 ```yaml
    field_s_dam:
      - plugin: skip_on_empty
        source: DAMID
        method: process
      - plugin: callback
        callable: trim
      - plugin: entity_generate
        entity_type: sheep_entities_sheep_record
        value_key: field_s_id10
        values:
          field_s_dam: DAMID
          field_s_breed: DBRD
        ignore_case: true
  ```

## Known issues / gotchas

  - **File naming is strict**

      Migration config entities must use: `migrate_plus.migration.<id>.yml`. (Misnaming it `cim --partial`.)

  - **Order matters**
      Follow logical ordering so that dependencies from another migration are already present.

  - **CSV headers must be exact**

      Migrate does not auto-map fields; header mismatches cause silent row skips.

---

For more details, refer to official documentation for the correct version of the migrate API and contrib modules drupal/migrate_plus, drupal/migrate_child_entity_generate, drupal/migrate_conditions, drupal/migrate_plus, drupal/migrate_source_csv, drupal/migrate_tools, drupal/migmag. Version number found in this project's root composer.lock MUST match version number found on official module-specific documentation, if available.


