<?php

declare(strict_types=1);

namespace Drupal\nematode_entities\Group\RelationHandler\Access;

use Drupal\group\Plugin\Group\RelationHandlerDefault\AccessControl as BaseAccessControl;

/**
 * Access control handler for Specimen group relations.
 *
 * Mirrors the pattern used by Group's own entity relation handlers. This class
 * intentionally defers to the base implementation, which integrates with
 * Group's permission system and the relation definition.
 */
final class SpecimenAccess extends BaseAccessControl {

}
