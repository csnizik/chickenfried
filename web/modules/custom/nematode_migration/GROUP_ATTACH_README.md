# Nematode Migration - Group Attachment

## Overview

This migration attaches nematode specimen entities to group 8 (Nematode Collection group).

## Prerequisites

Before running the group_attach migration, you must:

1. **Clear cache to register the Group relation plugin and handlers**:
   ```bash
   ddev drush cr
   ```
   This registers:
   - The `SpecimenGroupRelation` plugin
   - The `SpecimenAccess` relation handler
   - The `SpecimenPermissionProvider` relation handler

2. **Enable nematode_entities_specimen as group content** via the Drupal UI:
   - Navigate to: `/admin/group/types/manage/tenant_access_group/content`
   - Look for "Group specimen" in the list (this is the label from SpecimenGroupRelation)
   - Enable "Group specimen" as group content
   - Configure the relationship settings (group cardinality: unlimited, entity cardinality: 1)

3. **Export the configuration** to get the actual relationship type ID:
   ```bash
   ddev drush cex -y
   ```

4. **Verify the relationship type ID** (already configured as `tenant_access_group-6d2a36bd5db5`):
   ```bash
   grep -l "content_plugin: nematode_entities_specimen" config/sync/group.relationship_type.*.yml
   ```
   This should show `group.relationship_type.tenant_access_group-6d2a36bd5db5.yml`

6. **Import the updated migration config**:
   ```bash
   ddev drush cim -y
   ```

## Running the Migrations

### Initial Import

1. Import specimen data:
   ```bash
   ddev drush migrate:import specimen
   ```

2. Attach specimens to group:
   ```bash
   ddev drush migrate:import nematode_group_attach_specimen
   ```

### Check Status

```bash
ddev drush migrate:status | grep nematode
```

### Rollback

To remove group attachments:
```bash
ddev drush migrate:rollback nematode_group_attach_specimen
```

To remove specimen entities:
```bash
ddev drush migrate:rollback specimen
```

## Migration Dependencies

The `nematode_group_attach_specimen` migration requires the `specimen` migration to run first. This is enforced via migration_dependencies in the config.

## Source Plugin

The group attachment uses the custom `unattached_entities` source plugin, which:
- Queries all nematode_entities_specimen entities
- Filters out any already attached to group 8
- Prevents duplicate group relationships
- Processes entities in chunks for performance

## Testing the Group Relation Handler

After clearing cache, verify that the Group module can see and use the Specimen entity:

### 1. Check Services Registration

```bash
ddev drush eval "print_r(\Drupal::hasService('group.relation_handler.access.nematode_entities_specimen'));"
ddev drush eval "print_r(\Drupal::hasService('group.relation_handler.permission_provider.nematode_entities_specimen'));"
```

Both should output `1` (true).

### 2. Verify Available Content Types in Group UI

Navigate to `/admin/group/types/manage/tenant_access_group/content` and confirm:
- "Group specimen" appears in the list of available content types (this is the label from the plugin)
- You can enable it as group content
- The relation handler classes are being used

**Note:** The label is "Group specimen" (from `SpecimenGroupRelation` plugin), not "Specimen".

### 3. Test Adding Specimen to a Group

1. Navigate to group 8 (Nematode Collection): `/group/8/content`
2. Click "Add existing content" or "Create content"
3. Verify "Specimen" is available as an option
4. Test adding a specimen to the group manually

### 4. Verify Permissions

Navigate to `/admin/group/types/manage/tenant_access_group/permissions`:
- Look for Specimen-related permissions
- These are provided by the SpecimenPermissionProvider
- Configure as needed (view, edit, delete, etc.)

## Notes

- Group ID 8 is hard-coded in the migration config (the Nematode Collection group)
- The migration will skip specimens already attached to the group
- Running the migration multiple times is safe (idempotent)
- The Group relation handlers (SpecimenAccess and SpecimenPermissionProvider) are registered via nematode_entities.services.yml
