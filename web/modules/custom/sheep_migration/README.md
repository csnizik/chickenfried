# Sheep Migration Module

### Values used in migrations

**List all groups with their IDs and labels**
ddev drush ev "\$groups = \Drupal::entityTypeManager()->getStorage('group')->loadMultiple(); foreach (\$groups as \$group) { echo 'ID: ' . \$group->id() . ' | Type: ' . \$group->bundle() . ' | Label: ' . \$group->label() . PHP_EOL; }"

**List all group relationship types and their details**
ddev drush ev "\$types = \Drupal::entityTypeManager()->getStorage('group_relationship_type')->loadMultiple(); foreach (\$types as \$id => \$type) { echo 'Relationship Type ID: ' . \$id . PHP_EOL; echo '  Plugin ID: ' . \$type->getPluginId() . PHP_EOL; echo '  Group Type: ' . \$type->getGroupTypeId() . PHP_EOL; echo PHP_EOL; }"

**List all group relationship types filtered by entity type (e.g., sheep_entities_sheep_record)**
ddev drush ev "\$types = \Drupal::entityTypeManager()->getStorage('group_relationship_type')->loadMultiple(); foreach (\$types as \$id => \$type) { \$plugin_id = \$type->getPluginId(); if (strpos(\$plugin_id, 'sheep') !== false) { echo 'Relationship Type ID: ' . \$id . PHP_EOL; echo '  Plugin ID: ' . \$plugin_id . PHP_EOL; echo '  Group Type: ' . \$type->getGroupTypeId() . PHP_EOL; echo PHP_EOL; } }"

**Find relationship type for a specific group type and plugin**
ddev drush ev "\$group_type = 'tenant_access_group'; \$plugin = 'sheep_entities_sheep_record'; \$types = \Drupal::entityTypeManager()->getStorage('group_relationship_type')->loadMultiple(); foreach (\$types as \$id => \$type) { if (\$type->getGroupTypeId() == \$group_type && \$type->getPluginId() == \$plugin) { echo 'For Group Type: ' . \$group_type . PHP_EOL; echo 'For Plugin: ' . \$plugin . PHP_EOL; echo 'Relationship Type ID: ' . \$id . PHP_EOL; } }"
