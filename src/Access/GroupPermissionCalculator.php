<?php

namespace Drupal\group_permissions\Access;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\GroupMembershipLoaderInterface;
use Drupal\group\Access\GroupPermissionCalculatorBase;
use Drupal\group\Access\RefinableCalculatedGroupPermissions;
use Drupal\group\Access\CalculatedGroupPermissionsItem;
use Drupal\group\Access\CalculatedGroupPermissionsItemInterface;
use Drupal\group_permissions\Entity\GroupPermission;

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
   * Constructs a DefaultGroupPermissionCalculator object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\group\GroupMembershipLoaderInterface $membership_loader
   *   The group membership loader service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, GroupMembershipLoaderInterface $membership_loader) {
    $this->entityTypeManager = $entity_type_manager;
    $this->membershipLoader = $membership_loader;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateMemberPermissions(AccountInterface $account) {
    $calculated_permissions = new RefinableCalculatedGroupPermissions();
    $calculated_permissions->addCacheContexts(['user']);

    $user = $this->entityTypeManager->getStorage('user')->load($account->id());
    $calculated_permissions->addCacheableDependency($user);

    $groups = $this->entityTypeManager->getStorage('group')->loadMultiple();

    foreach ($groups as $group) {
      $group_permission = GroupPermission::loadByGroup($group);
      if (!empty($group_permission)) {

        $group_role = $group->getGroupType()->getAnonymousRole();

        $custom_permissions = [];
        if (!empty($group_permission)) {
          $custom_permissions = $group_permission->getPermissions()->first()->getValue();
        }

        if (!empty($custom_permissions[$group_role->id()])) {

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
  public function calculateAnonymousPermissions() {

    $calculated_permissions = new RefinableCalculatedGroupPermissions();

    $groups = $this->entityTypeManager->getStorage('group')->loadMultiple();
    foreach ($groups as $group) {
      $calculated_permissions->addCacheableDependency($group);
      $group_permission = GroupPermission::loadByGroup($group);

      if (!empty($group_permission)) {
        $custom_permissions = $group_permission->getPermissions()->first()->getValue();
        $group_roles = $group->getGroupType()->getRoles();

        foreach ($group_roles as $group_role) {
          if (!empty($custom_permissions[$group_role->id()])) {
            $item = new CalculatedGroupPermissionsItem(
              CalculatedGroupPermissionsItemInterface::SCOPE_GROUP,
              $group->id(),
              $custom_permissions[$group_role->id()]
            );

            $calculated_permissions->addItem($item);
            $calculated_permissions->addCacheableDependency($group_role);
          }
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
    $calculated_permissions->addCacheContexts(['user']);

    $user = $this->entityTypeManager->getStorage('user')->load($account->id());
    $calculated_permissions->addCacheableDependency($user);

    $groups = $this->entityTypeManager->getStorage('group')->loadMultiple();
    foreach ($groups as $group) {
      $group_permission = GroupPermission::loadByGroup($group);
      if (!empty($group_permission)) {

        $group_role = $group->getGroupType()->getOutsiderRole();

        $custom_permissions = $group_permission->getPermissions()
          ->first()
          ->getValue();

        if (!empty($custom_permissions[$group_role->id()])) {
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

}
