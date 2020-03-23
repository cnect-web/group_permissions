<?php

namespace Drupal\group_permissions\Access;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Access\GroupPermissionCalculatorBase;
use Drupal\group\Access\RefinableCalculatedGroupPermissions;
use Drupal\group\Access\CalculatedGroupPermissionsItem;
use Drupal\group\Access\CalculatedGroupPermissionsItemInterface;
use Drupal\group\Entity\GroupType;
use Drupal\group\GroupMembershipLoaderInterface;
use Drupal\group_permissions\GroupPermissionsManager;

/**
 * Calculates group permissions for an account.
 */
class GroupPermissionCalculator extends GroupPermissionCalculatorBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The membership loader service.
   *
   * @var \Drupal\group\GroupMembershipLoaderInterface
   */
  protected $membershipLoader;

  /**
   * The group permissions manager.
   *
   * @var \Drupal\group_permissions\GroupPermissionsManager
   */
  protected $groupPermissionsManager;

  /**
   * Constructs a DefaultGroupPermissionCalculator object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\group\GroupMembershipLoaderInterface $membership_loader
   *   The group membership loader service.
   * @param \Drupal\group_permissions\GroupPermissionsManager $group_permissions_manager
   *   The group permissions manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, GroupMembershipLoaderInterface $membership_loader, GroupPermissionsManager $group_permissions_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->membershipLoader = $membership_loader;
    $this->groupPermissionsManager = $group_permissions_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateMemberPermissions(AccountInterface $account) {
    $calculated_permissions = new RefinableCalculatedGroupPermissions();
    $calculated_permissions->addCacheContexts(['user']);

    $user = $this->entityTypeManager->getStorage('user')->load($account->id());
    $calculated_permissions->addCacheableDependency($user);

    foreach ($this->membershipLoader->loadByUser($account) as $group_membership) {
      $group = $group_membership->getGroup();
      $custom_permissions = $this->groupPermissionsManager->getCustomPermissions($group);
      if (!empty($custom_permissions)) {
        $calculated_permissions->addCacheableDependency($this->groupPermissionsManager->getGroupPermission($group));
        foreach ($group_membership->getRoles() as $group_role) {
          if (isset($custom_permissions[$group_role->id()])) {
            $item = new CalculatedGroupPermissionsItem(
              CalculatedGroupPermissionsItemInterface::SCOPE_GROUP,
              $group_membership->getGroup()->id(),
              $custom_permissions[$group_role->id()]
            );

            $calculated_permissions->addItem($item);
            $calculated_permissions->addCacheableDependency($group_role);
          }
        }

        $calculated_permissions->addCacheableDependency($group);
      }
    }
    return $calculated_permissions;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateAnonymousPermissions() {
    $calculated_permissions = new RefinableCalculatedGroupPermissions();

    $calculated_permissions->addCacheTags(['group_list']);

    $group_permissions = $this->groupPermissionsManager->getAll();
    foreach ($group_permissions as $group_permission) {
      $group = $group_permission->getGroup();
      if (!empty($group) && !empty($group_permission)) {
        $calculated_permissions->addCacheableDependency($group_permission);

        $custom_permissions = $group_permission->getPermissions();

        $group_role = $group->getGroupType()->getAnonymousRole();

        if (isset($custom_permissions[$group_role->id()])) {
          $item = new CalculatedGroupPermissionsItem(
            CalculatedGroupPermissionsItemInterface::SCOPE_GROUP,
            $group->id(),
            $custom_permissions[$group_role->id()]
          );

          $calculated_permissions->addItem($item);
          $calculated_permissions->addCacheableDependency($group);
          $calculated_permissions->addCacheableDependency($group_role);
        }
      }
    }

    return $calculated_permissions;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateOutsiderPermissions(AccountInterface $account) {

    $calculated_permissions = new RefinableCalculatedGroupPermissions();
    $calculated_permissions->addCacheTags(['group_list']);
    $calculated_permissions->addCacheContexts(['user']);

    $user = $this->entityTypeManager->getStorage('user')->load($account->id());
    $calculated_permissions->addCacheableDependency($user);

    $group_permissions = $this->groupPermissionsManager->getAll();

    foreach ($group_permissions as $group_permission) {
      if (!empty($group_permission)) {
        $group = $group_permission->getGroup();
        $calculated_permissions->addCacheableDependency($group_permission);

        // Get all outsider roles.
        $roles = $this->groupPermissionsManager->getOutsiderRoles($group, $account);
        $calculated_permissions->addCacheableDependency($group_permission);

        $custom_permissions = $group_permission->getPermissions();

        foreach ($roles as $group_role) {
          if (isset($custom_permissions[$group_role->id()])) {
            $item = new CalculatedGroupPermissionsItem(
              CalculatedGroupPermissionsItemInterface::SCOPE_GROUP,
              $group->id(),
              $custom_permissions[$group_role->id()]
            );

            $calculated_permissions->addItem($item);
            $calculated_permissions->addCacheableDependency($group);
            $calculated_permissions->addCacheableDependency($group_role);
          }
        }
      }
    }

    return $calculated_permissions;
  }

  /**
   * Gets account outsider roles.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   User account.
   * @param \Drupal\group\Entity\GroupType $group_type
   *   Group type.
   *
   * @return array
   *   Roles ids.
   */
  protected function getAccountOutsiderRoles(AccountInterface $account, GroupType $group_type) {
    $roles = $account->getRoles(TRUE);
    $group_role_ids = [];
    foreach ($roles as $role_id) {
      $group_role_ids[] = $this->groupRoleSynchronizer->getGroupRoleId($group_type->id(), $role_id);
    }

    return $group_role_ids;
  }

}
