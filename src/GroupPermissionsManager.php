<?php

namespace Drupal\group_permissions;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\GroupRoleSynchronizerInterface;
use Drupal\group_permissions\Entity\GroupPermission;

/**
 * Service to handle custom group permissions.
 */
class GroupPermissionsManager {

  /**
   * The array of the group custom permissions.
   *
   * @var array
   */
  protected $customPermissions = [];

  /**
   * The array of the group permissions objects.
   *
   * @var array
   */
  protected $groupPermissions = [];

  /**
   * The array of the outsider group roles.
   *
   * @var array
   */
  protected $outsiderRoles = [];

  /**
   * The cache backend to use.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The group role synchronizer service.
   *
   * @var \Drupal\group\GroupRoleSynchronizerInterface
   */
  protected $groupRoleSynchronizer;

  /**
   * Handles custom permissions.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\group\GroupRoleSynchronizerInterface $group_role_synchronizer
   *   The group role synchronizer service.
   */
  public function __construct(CacheBackendInterface $cache_backend, EntityTypeManagerInterface $entity_type_manager, GroupRoleSynchronizerInterface $group_role_synchronizer) {
    $this->entityTypeManager = $entity_type_manager;
    $this->cacheBackend = $cache_backend;
    $this->groupRoleSynchronizer = $group_role_synchronizer;
  }

  /**
   * Set group permission.
   *
   * @param \Drupal\group_permissions\Entity\GroupPermission $group_permission
   *   Group permission.
   */
  public function setCustomPermission(GroupPermission $group_permission) {
    $this->customPermissions[$group_permission->getGroup()->id()] = $group_permission->getPermissions();
  }

  /**
   * Helper function to get custom group permissions.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group.
   *
   * @return array
   *   Permissions array.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function getCustomPermissions(GroupInterface $group) {
    $group_id = $group->id();
    $this->customPermissions[$group_id] = [];
    if (empty($this->customPermissions[$group_id])) {
      $cid = "custom_group_permissions:$group_id";
      $data_cached = $this->cacheBackend->get($cid);
      if (!$data_cached) {
        /** @var \Drupal\group_permissions\Entity\GroupPermission $group_permission */
        $group_permission = GroupPermission::loadByGroup($group);
        if ($group_permission) {
          $this->groupPermissions[$group_id] = $group_permission;
          $tags = [];
          $tags[] = "group:$group_id";
          $tags[] = "group_permission:{$group_permission->id()}";
          $this->customPermissions[$group_id] = $group_permission->getPermissions();
          // Store the tree into the cache.
          $this->cacheBackend->set($cid, $this->customPermissions[$group_id], CacheBackendInterface::CACHE_PERMANENT, $tags);
        }
      }
      else {
        $this->customPermissions[$group_id] = $data_cached->data;
      }
    }

    return $this->customPermissions[$group_id];
  }

  /**
   * Get group permission object.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group.
   *
   * @return \Drupal\group_permissions\Entity\GroupPermission|null
   *   Group permission.
   */
  public function getGroupPermission(GroupInterface $group) {
    $group_id = $group->id();
    if (empty($this->groupPermissions[$group_id])) {
      $this->groupPermissions[$group_id] = GroupPermission::loadByGroup($group);
    }

    return $this->groupPermissions[$group_id];
  }

  /**
   * Checks custom for collection.
   *
   * @param string $permission
   *   Permission.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Current user.
   *
   * @return bool
   *   Result of the check.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function hasPermission(string $permission, GroupInterface $group, AccountInterface $account = NULL) {
    if (!empty($account) && $account->hasPermission('bypass group access')) {
      return TRUE;
    }

    if ($account->isAnonymous()) {
      return $this->checkAnonymousRole($permission, $group);
    }
    elseif ($group->getMember($account)) {
      return $this->checkGroupRoles($permission, $group, $account);
    }
    else {
      return $this->checkOutsiderRoles($permission, $group, $account);
    }
  }

  /**
   * Checks anonymous role.
   *
   * @param string $permission
   *   Permission.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group.
   *
   * @return bool
   *   Result of check.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function checkAnonymousRole(string $permission, GroupInterface $group) {
    $result = FALSE;
    $custom_permissions = $this->getCustomPermissions($group);
    if (!empty($custom_permissions)) {
      $role_id = $group->getGroupType()->getAnonymousRoleId();
      if (!empty($custom_permissions[$role_id]) && in_array($permission, $custom_permissions[$role_id])) {
        return TRUE;
      }
    }

    return $result;
  }

  /**
   * Checks outsider roles.
   *
   * @param string $permission
   *   Permission.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Current user.
   *
   * @return bool
   *   Result of check.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function checkOutsiderRoles(string $permission, GroupInterface $group, AccountInterface $account) {
    $custom_permissions = $this->getCustomPermissions($group);
    if (!empty($custom_permissions)) {
      $outsider_roles = $this->getOutsiderRoles($group, $account);
      return $this->checkRoles($permission, $custom_permissions, $outsider_roles);
    }

    return FALSE;
  }

  /**
   * Checks roles for permissions.
   *
   * @param string $permission
   *   Permission.
   * @param array $custom_permissions
   *   Custom permissions.
   * @param array $roles
   *   Roles list.
   *
   * @return bool
   *   Role has the permission or not.
   */
  protected function checkRoles(string $permission, array $custom_permissions = [], array $roles = []) {
    foreach ($roles as $role_name => $role) {
      if (!empty($custom_permissions[$role_name]) && in_array($permission, $custom_permissions[$role_name])) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Get outsider roles.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Current user.
   *
   * @return mixed
   *   List of outsider roles.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getOutsiderRoles(GroupInterface $group, AccountInterface $account) {
    $group_type = $group->getGroupType();
    $group_type_id = $group_type->id();
    if (empty($this->outsiderRoles[$group_type_id])) {

      $account_roles = $account->getRoles(TRUE);
      foreach ($account_roles as $role) {
        $advanced_outsider_role_id = $this->groupRoleSynchronizer->getGroupRoleId($group_type_id, $role);
        $outsider_roles[] = $this->entityTypeManager
          ->getStorage('group_role')
          ->load($advanced_outsider_role_id);
      }
      $outsider_roles[$group_type->getOutsiderRoleId()] = $group_type->getOutsiderRole();
      $this->outsiderRoles[$group_type_id] = $outsider_roles;
    }

    return $this->outsiderRoles[$group_type_id];
  }

  /**
   * Check normal user roles in the group.
   *
   * @param string $permission
   *   Permission.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   User.
   *
   * @return bool
   *   Permission check.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function checkGroupRoles(string $permission, GroupInterface $group, AccountInterface $account) {
    $result = FALSE;
    $custom_permissions = $this->getCustomPermissions($group);
    if (!empty($custom_permissions)) {
      $member = $group->getMember($account);
      if (!empty($member)) {
        return $this->checkRoles($permission, $custom_permissions, $member->getRoles());
      }
    }

    return $result;
  }

  /**
   * Get all group permissions objects.
   *
   * @return \Drupal\group_permissions\Entity\GroupPermissionInterface[]
   *   Group permissions list.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getAll() {
    return $this->entityTypeManager->getStorage('group_permission')->loadMultiple();
  }

  /**
   * Get all member roles of given group.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The Group.
   *
   * @return \Drupal\group\Entity\GroupRoleInterface[]
   *   An array of group roles.
   */
  public function getMemberRolesByGroup(GroupInterface $group) {
    $group_type_id = $group->getGroupType()->id();
    $properties = [
      'group_type' => $group_type_id,
      'permissions_ui' => TRUE,
    ];

    $roles = $this->entityTypeManager
      ->getStorage('group_role')
      ->loadByProperties($properties);

    uasort($roles, '\Drupal\group\Entity\GroupRole::sort');
    return $roles;
  }

  /**
   * Checks if given permissions is in place for given role in given group.
   *
   * @param string $permission
   *   Permission.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The Group.
   * @param string $role_id
   *   The group role ID.
   *
   * @return bool
   *   TRUE if custom permission is in place.
   */
  public function checkGroupRole(string $permission, GroupInterface $group, string $role_id) {
    $custom_permissions = $this->getCustomPermissions($group);
    return !empty($custom_permissions[$role_id]) && in_array($permission, $custom_permissions[$role_id]);
  }

}
