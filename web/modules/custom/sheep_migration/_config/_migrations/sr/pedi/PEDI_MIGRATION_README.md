# PEDI Lineage Migration - Phase 1

## Files to Install

1. **PedigreeParentStubs.php**
   → `modules/custom/sheep_migration/src/Plugin/migrate/source/PedigreeParentStubs.php`

2. **sr_pedi_stubs.yml**
   → `modules/custom/sheep_migration/migrations/sr_pedi_stubs.yml`

3. **sr_pedi_lineage_2010.yml**
   → `modules/custom/sheep_migration/migrations/sr_pedi_lineage_2010.yml`
   → Copy and adjust for each year (2011-2025)

---

## Migration Order

```bash
# 1. Clear cache after adding source plugin
ddev ddev drush cr

# 2. Verify migrations are detected
ddev drush migrate:status --tag=pedi

# 3. Run stub creation (creates missing parent records)
ddev drush migrate:import sr_pedi_stubs

# 4. Check stub count
ddev drush sqlq "SELECT COUNT(*) FROM sheep_entities_sheep_record WHERE field_s_birth_date LIKE '%-01-01'"

# 5. Run lineage updates per year
ddev drush migrate:import sr_pedi_lineage_2010
ddev drush migrate:import sr_pedi_lineage_2011
# ... etc

# Or run all lineage migrations at once:
ddev drush migrate:import --tag=lineage
```

---

## Validation Queries

```bash
# Count records with sire set
ddev drush sqlq "SELECT COUNT(*) FROM sheep_entities_sheep_record WHERE field_s_sire IS NOT NULL"

# Count records with dam set
ddev drush sqlq "SELECT COUNT(*) FROM sheep_entities_sheep_record WHERE field_s_dam IS NOT NULL"

# Find orphan records (have neither parent)
ddev drush sqlq "SELECT field_s_id10 FROM sheep_entities_sheep_record WHERE field_s_sire IS NULL AND field_s_dam IS NULL LIMIT 20"

# Verify a specific lineage chain
ddev drush sqlq "SELECT sr.field_s_id10, sire.field_s_id10 AS sire_id10, dam.field_s_id10 AS dam_id10 FROM sheep_entities_sheep_record sr LEFT JOIN sheep_entities_sheep_record sire ON sr.field_s_sire = sire.id LEFT JOIN sheep_entities_sheep_record dam ON sr.field_s_dam = dam.id WHERE sr.field_s_id10 = '2010055553'"
```

---

## Idempotency Tests

```bash
# Run stubs again - should process 0 rows
ddev drush migrate:import sr_pedi_stubs
# Expected: "Processed 0 items"

# Run lineage again - should update same records (safe)
ddev drush migrate:import sr_pedi_lineage_2010
# Expected: same count, no errors
```

---

## Creating Additional Year Files

For each year 2011-2025, copy sr_pedi_lineage_2010.yml and change:
- `id: sr_pedi_lineage_YYYY`
- `migration_tags: - 'YYYY'`
- `label: 'PEDI → Update sheep_records with lineage (YYYY)'`
- `source.path: modules/custom/sheep_migration/data/pedi/PEDIYYYY.csv`

---

## Future Backfill (1990-2009)

When ready to backfill earlier years:

1. Add PEDI CSV paths for 1990-2009 to sr_pedi_stubs.yml
2. Run `ddev drush migrate:import sr_pedi_stubs` again
   - Plugin will create stubs only for NEW missing parents
   - Existing records (from 2008-2009 parents) remain untouched
3. Create sr_pedi_lineage_YYYY.yml for each year
4. Run lineage migrations

The stub plugin queries the DB each run, so it's always safe to re-run.

---

## Stub Records Characteristics

Stubs created by sr_pedi_stubs have:
- `field_s_id10`: Full 10-digit ID
- `field_s_breed`: Looked up from breed code in ID10 (may be NULL if no match)
- `field_s_birth_date`: January 1 of birth year (placeholder)
- All other fields: NULL

When LAMB data for these years is migrated later, you can either:
- Update stubs with full data (migration with overwrite_properties)
- Delete stubs and re-import (if you prefer clean records)
