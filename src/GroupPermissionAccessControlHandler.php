<?php

namespace Drupal\group_permissions;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Group permission entity.
 *
 * @see \Drupal\group_permissions\Entity\GroupPermission.
 */
class GroupPermissionAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\group_permissions\Entity\GroupPermissionInterface $entity */
    switch ($operation) {
      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit group permission entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete group permission entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add group permission entities');
  }

}
