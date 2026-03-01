<?php

declare(strict_types=1);

namespace Drupal\sheep_entities;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control handler for Lamb Card entities.
 */
final class LambCardAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    if ($account->hasPermission($this->entityType->getAdminPermission())) {
      return AccessResult::allowed()->cachePerPermissions();
    }
    return match ($operation) {
      'view' => AccessResult::allowedIfHasPermission($account, 'view sheep_entities_lamb_card'),
      'update' => AccessResult::allowedIfHasPermission($account, 'edit sheep_entities_lamb_card'),
      'delete' => AccessResult::allowedIfHasPermission($account, 'delete sheep_entities_lamb_card'),
      default => AccessResult::neutral(),
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return AccessResult::allowedIfHasPermissions($account, ['create sheep_entities_lamb_card',
      'administer sheep_entities_lamb_card',
    ], 'OR');
  }

}
