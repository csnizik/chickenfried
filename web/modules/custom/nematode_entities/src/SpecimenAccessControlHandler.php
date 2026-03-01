<?php

declare(strict_types=1);

namespace Drupal\nematode_entities;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
// Correct import for EntityInterface.
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control handler for Specimen entities.
 */
final class SpecimenAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    if ($account->hasPermission($this->entityType->getAdminPermission())) {
      return AccessResult::allowed()->cachePerPermissions();
    }
    return match ($operation) {
      'view' => AccessResult::allowedIfHasPermission($account, 'view nematode_entities_specimen'),
      'update' => AccessResult::allowedIfHasPermission($account, 'edit nematode_entities_specimen'),
      'delete' => AccessResult::allowedIfHasPermission($account, 'delete nematode_entities_specimen'),
      default => AccessResult::neutral(),
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return AccessResult::allowedIfHasPermissions(
      $account,
      ['create nematode_entities_specimen', 'administer nematode_entities_specimen'],
      'OR'
    );
  }

}
