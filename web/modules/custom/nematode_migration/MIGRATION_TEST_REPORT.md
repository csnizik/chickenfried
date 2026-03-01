# Nematode Migration Test Simulation Report

**Test Date:** December 3, 2025
**Test Environment:** GitHub Codespaces
**Migration Module:** nematode_migration
**Group ID:** 8 (Nematode Collection)

---

## Executive Summary

This report simulates a test run of the two-phase migration process:
1. **Phase 1:** Import specimen entities from CSV (`specimen` migration)
2. **Phase 2:** Attach imported specimens to Group 8 (`nematode_group_attach_specimen` migration)

**Status:** ⚠️ **READY FOR TESTING** (Requires one configuration update)

---

## Pre-Test Configuration Review

### ✅ Completed Prerequisites

1. **Group Relation Plugin Created**
   - File: `SpecimenGroupRelation.php`
   - Plugin ID: `nematode_entities_specimen`
   - Label: "Group specimen"
   - Status: ✅ Registered and enabled in UI

2. **Relation Handlers Created**
   - Access Handler: `SpecimenAccess.php`
   - Permission Provider: `SpecimenPermissionProvider.php`
   - Services: Registered in `nematode_entities.services.yml`
   - Status: ✅ Ready

3. **Source Plugin Created**
   - File: `UnattachedEntities.php`
   - Purpose: Filters specimens not yet attached to group 8
   - Status: ✅ Ready

4. **Migration Configurations**
   - Specimen import: ✅ Ready
   - Group attach: ⚠️ Needs relationship type ID update

### ⚠️ Required Action Before Testing

**Export configuration to get the relationship type ID:**

```bash
ddev drush cex -y
```

**Then find the generated ID:**

```bash
grep -l "content_plugin: nematode_entities_specimen" config/sync/group.relationship_type.*.yml
```

**Migration config updated:**
- File: `web/modules/custom/nematode_migration/config/install/migrate_plus.migration.nematode_group_attach_specimen.yml`
- Relationship Type ID: `tenant_access_group-6d2a36bd5db5`
- Status: ✅ Configured

---

## Phase 1: Specimen Import Migration

### Migration: `specimen`

**Configuration Summary:**
- **Source:** CSV file (`specimen_export_pre.csv`)
- **Records:** 41,751 rows (including header)
- **Destination:** `entity:nematode_entities_specimen`
- **Dependencies:** None

**Field Mappings:**
- Entry Number → `field_n_entry_number` (entity label)
- Nematode Genus/Species → `field_n_nematode_genus`, `field_n_nematode_species`
- Host Genus/Species → `field_n_host_genus`, `field_n_host_species`
- Location → `field_n_origin_locale`, `field_n_origin_state`, `field_n_origin_country`
- Dates → `field_n_date_collected`, `field_n_date_received`, `field_n_date_added`
- Multi-value fields → `field_n_slides`, `field_n_vials` (comma-separated)
- Remarks → `field_n_remarks` (long text)
- 20+ additional fields

**Expected Outcome:**
```
Expected to create: ~41,750 specimen entities
Processing time (est): 10-30 minutes depending on server performance
```

**Test Commands:**
```bash
# Check migration status
ddev drush migrate:status specimen

# Run dry-run to check for errors (first 10 items)
ddev drush migrate:import specimen --limit=10 --update

# View import status
ddev drush migrate:status specimen

# Check created entities
ddev drush eval "print_r(\Drupal::entityQuery('nematode_entities_specimen')->accessCheck(FALSE)->count()->execute());"
```

**Potential Issues:**
- ✅ Typo fixed: `ource: DeterminedBy` → `source: DeterminedBy`
- ⚠️ CSV parsing: Multi-line fields may need escaping validation
- ⚠️ Memory: 41K+ records may require increased PHP memory limit

---

## Phase 2: Group Attachment Migration

### Migration: `nematode_group_attach_specimen`

**Configuration Summary:**
- **Source:** `unattached_entities` plugin
- **Entity Type:** `nematode_entities_specimen`
- **Target Group:** 8 (Nematode Collection)
- **Destination:** `entity:group_relationship`
- **Dependencies:** Requires `specimen` migration to complete first

**Process Logic:**
1. Query all `nematode_entities_specimen` entities
2. Filter out any already attached to group 8
3. Create group_relationship entities linking specimens to group 8
4. Use relationship type: `tenant_access_group-6d2a36bd5db5`
5. Use plugin ID: `nematode_entities_specimen`

**Expected Outcome:**
```
Expected to create: ~41,750 group_relationship entities (one per specimen)
Processing time (est): 5-15 minutes
Idempotent: Re-running will only attach unattached specimens
```

**Test Commands:**
```bash
# Check migration status
ddev drush migrate:status nematode_group_attach_specimen

# Run dry-run (first 10)
ddev drush migrate:import nematode_group_attach_specimen --limit=10 --update

# View status
ddev drush migrate:status nematode_group_attach_specimen

# Verify group relationships created
ddev drush eval "print_r(\Drupal::entityQuery('group_relationship')->condition('gid', 8)->condition('plugin_id', 'nematode_entities_specimen')->accessCheck(FALSE)->count()->execute());"
```

**Source Plugin Behavior:**
- Chunks processing: 500 entities per query
- Performance: Optimized for large datasets
- Safety: Prevents duplicate relationships

---

## Complete Test Workflow

### Step 1: Configuration Setup

```bash
# Export configuration to get relationship type ID
ddev drush cex -y

# Find the relationship type ID
RELATIONSHIP_TYPE=$(grep -l "content_plugin: nematode_entities_specimen" config/sync/group.relationship_type.*.yml | sed 's/.*tenant_access_group-/tenant_access_group-/' | sed 's/.yml$//')
echo "Relationship Type ID: $RELATIONSHIP_TYPE"

# Update the migration config manually with the ID
# Edit: web/modules/custom/nematode_migration/config/install/migrate_plus.migration.nematode_group_attach_specimen.yml

# Clear cache and reimport migrations
ddev drush cr
ddev drush config:import --partial --source=modules/custom/nematode_migration/config/install -y
```

### Step 2: Test Specimen Import (Small Batch)

```bash
# Test with 10 specimens first
ddev drush migrate:import specimen --limit=10

# Check results
ddev drush migrate:status specimen
ddev drush eval "echo 'Specimens created: ' . \Drupal::entityQuery('nematode_entities_specimen')->accessCheck(FALSE)->count()->execute() . PHP_EOL;"
```

### Step 3: Test Group Attachment (Small Batch)

```bash
# Test attaching the 10 specimens to group 8
ddev drush migrate:import nematode_group_attach_specimen --limit=10

# Check results
ddev drush migrate:status nematode_group_attach_specimen
ddev drush eval "echo 'Group relationships: ' . \Drupal::entityQuery('group_relationship')->condition('gid', 8)->condition('plugin_id', 'nematode_entities_specimen')->accessCheck(FALSE)->count()->execute() . PHP_EOL;"
```

### Step 4: Verify in UI

```bash
# Get the URL for group 8 content
ddev drush eval "echo \Drupal::service('url_generator')->generateFromRoute('entity.group.content', ['group' => 8], ['absolute' => TRUE]) . PHP_EOL;"
```

Navigate to group 8 content page and verify:
- Specimens appear in the group content list
- "Group specimen" relationship type is shown
- View/edit permissions work correctly

### Step 5: Full Import (if tests pass)

```bash
# Import all specimens
ddev drush migrate:import specimen

# Wait for completion, then attach all to group
ddev drush migrate:import nematode_group_attach_specimen

# Final verification
ddev drush migrate:status | grep nematode
```

---

## Expected Results

### Success Indicators

**Phase 1 Complete:**
- ✅ 41,750 specimen entities created
- ✅ No error messages in migration status
- ✅ Entity query confirms count matches CSV rows

**Phase 2 Complete:**
- ✅ 41,750 group_relationship entities created
- ✅ All relationships point to group 8
- ✅ All use plugin_id: `nematode_entities_specimen`
- ✅ Specimens visible in group 8 content list at `/group/8/content`

### Verification Queries

```bash
# Total specimens
ddev drush sqlq "SELECT COUNT(*) FROM nematode_entities_specimen;"

# Group relationships
ddev drush sqlq "SELECT COUNT(*) FROM group_relationship WHERE gid=8 AND plugin_id='nematode_entities_specimen';"

# Verify no duplicates
ddev drush sqlq "SELECT entity_id, COUNT(*) as cnt FROM group_relationship WHERE gid=8 AND plugin_id='nematode_entities_specimen' GROUP BY entity_id HAVING cnt > 1;"
```

Should return:
- Specimen count: 41,750
- Relationship count: 41,750
- Duplicates: 0 rows

---

## Rollback Procedure

If testing reveals issues:

```bash
# Rollback group attachments (keeps specimens)
ddev drush migrate:rollback nematode_group_attach_specimen

# Rollback specimens (if needed)
ddev drush migrate:rollback specimen

# Reset migration status
ddev drush migrate:reset-status specimen
ddev drush migrate:reset-status nematode_group_attach_specimen
```

---

## Performance Estimates

### Phase 1: Specimen Import
- **Records:** 41,750
- **Est. Time:** 15-30 minutes
- **Rate:** ~23-46 records/second
- **Memory:** ~512MB-1GB peak

### Phase 2: Group Attachment
- **Records:** 41,750
- **Est. Time:** 5-15 minutes
- **Rate:** ~46-139 records/second (lighter processing)
- **Memory:** ~256MB-512MB peak

**Total Migration Time:** 20-45 minutes

---

## Files Reviewed

### Migration Configs
- ✅ `migrate_plus.migration.specimen.yml` - Typo fixed
- ⚠️ `migrate_plus.migration.nematode_group_attach_specimen.yml` - Needs relationship type ID

### Source Plugins
- ✅ `UnattachedEntities.php` - Ready

### Group Integration
- ✅ `SpecimenGroupRelation.php` - Registered
- ✅ `SpecimenAccess.php` - Ready
- ✅ `SpecimenPermissionProvider.php` - Ready
- ✅ `nematode_entities.services.yml` - Services registered

### Data
- ✅ `specimen_export_pre.csv` - 41,751 rows, 27 columns

---

## Recommendations

1. **Before Full Import:**
   - ✅ Complete configuration export and ID update
   - ✅ Test with `--limit=10` first
   - ✅ Verify no PHP errors in logs
   - ✅ Check memory limits (recommend 512MB+)

2. **During Import:**
   - Monitor progress: `watch -n 5 'ddev drush migrate:status | grep nematode'`
   - Check logs: `ddev drush watchdog:tail`

3. **After Import:**
   - Verify counts match
   - Test permissions in UI
   - Check group content display
   - Test search/filtering

---

## Conclusion

**Status:** The migration infrastructure is complete and ready for testing.

**Action Required:**
1. Export configuration: `ddev drush cex -y`
2. Find relationship type ID
3. Update migration config with actual ID
4. Run test import with `--limit=10`

**Risk Assessment:** Low - migrations follow established patterns from sheep_migration module.

**Recommendation:** Proceed with small batch testing, then full import.
