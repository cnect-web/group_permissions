<?php

namespace Drupal\group_permissions;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\GroupMembershipLoaderInterface;
use Drupal\node\NodeInterface;

/**
 * Service to build access records based on group_permissions.
 */
class GroupPermissionsNodeAccessRecordsBuilder implements GroupPermissionsNodeAccessRecordsBuilderInterface {

  /**
   * The GroupPermissionsManager service.
   *
   * @var \Drupal\group_permissions\GroupPermissionsManager
   */
  protected $groupPermissionsManager;

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
   * List of group records.
   *
   * @var array
   */
  protected $group_records;

  /**
   * Constructs a new ActivityRecordStorage object.
   *
   * @param \Drupal\group_permissions\GroupPermissionsManager $group_permissions_manager
   *   The GroupPermissionsManager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\group\GroupMembershipLoaderInterface $membership_loader
   *   The group membership loader service.
   */
  public function __construct(GroupPermissionsManager $group_permissions_manager, EntityTypeManagerInterface $entity_type_manager, GroupMembershipLoaderInterface $membership_loader) {
    $this->groupPermissionsManager = $group_permissions_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->membershipLoader = $membership_loader;

  }

  /**
   * {@inheritdoc}
   */
  public function buildAccessRecords(NodeInterface $node) {
    $records = [];

    // Load all of the group content for this node.
    $group_contents = $this->entityTypeManager
      ->getStorage('group_content')
      ->loadByEntity($node);

    // Only act if there are group content entities for this node.
    if (empty($group_contents)) {
      return $records;
    }

    foreach ($group_contents as $group_content) {
      $group = $group_content->getGroup();
      $group_id = $group->id();
      if (isset($this->group_records[$group_id])) {
        $group_records = $this->group_records[$group_id];
      }
      else {
        $group_records = $this->getGroupRecords($group, $node);
        $this->group_records[$group_id] = $group_records;
      }
      $records = array_merge($records, $group_records);
    }

    return $records;
  }

  /**
   * Gets group access records.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group.
   * @param \Drupal\node\NodeInterface $node
   *   Node.
   * @return array
   *   Group access records.
   */
  public function getGroupRecords($group, $node) {
    $records = [];
     if (empty($this->groupPermissionsManager->getCustomPermissions($group))) {
       return $records;
     }

    $group_id = $group->id();

    $plugin_id = "group_node:{$node->bundle()}";
    $uid = $node->getOwnerId();
    $prefix = $node->isPublished() ? 'group_permissions' : 'group_permissions_unpublished';

    $view_permission = $node->isPublished()
      ? "view $plugin_id entity"
      : "view unpublished $plugin_id entity";

    $roles = $this->groupPermissionsManager->getMemberRolesByGroup($group);
    $group_type = $group->getGroupType();
    $group_anonymous_role_id = $group_type->getAnonymousRoleId();
    $group_outsider_role_id = $group->getGroupType()->getOutsiderRoleId();
    foreach ($roles as $role_id => $role) {
      if ($role_id != $group_anonymous_role_id && $role_id != $group_outsider_role_id) {
        // Add per role record.
        $records[] = [
          'gid' => $group_id,
          'realm' => "$prefix:$role_id",
          'grant_view' => (int) $this->groupPermissionsManager->checkGroupRole($view_permission, $group, $role_id),
          'grant_update' => (int) $this->groupPermissionsManager->checkGroupRole("update any $plugin_id entity", $group, $role_id),
          'grant_delete' => (int) $this->groupPermissionsManager->checkGroupRole("delete any $plugin_id entity", $group, $role_id),
          'priority' => 1,
        ];
      }
    }

    // Add author record.
    $node_owner = $node->getOwner();
    $records[] = [
      'gid' => $uid,
      'realm' => "$prefix:author",
      'grant_view' => (int) $this->groupPermissionsManager->checkGroupRoles($view_permission, $group, $node_owner),
      'grant_update' => (int) $this->groupPermissionsManager->checkGroupRoles("update own $plugin_id entity", $group, $node_owner),
      'grant_delete' => (int) $this->groupPermissionsManager->checkGroupRoles("delete own $plugin_id entity", $group, $node_owner),
      'priority' => 0,
    ];

    // Add outsider record.
    $records[] = [
      'gid' => GROUP_PERMISSIONS_GRANT_ID,
      'realm' => "$prefix:outsider",
      'grant_view' => (int) $this->groupPermissionsManager->checkGroupRole($view_permission, $group, $group_outsider_role_id),
      'grant_update' => (int) $this->groupPermissionsManager->checkGroupRole("update any $plugin_id entity", $group, $group_outsider_role_id),
      'grant_delete' => (int) $this->groupPermissionsManager->checkGroupRole("delete any $plugin_id entity", $group, $group_outsider_role_id),
      'priority' => 0,
    ];

    // Set records for anonymous roles.
    $anonymous_record = [
      'gid' => GROUP_PERMISSIONS_GRANT_ID,
      'realm' => "$prefix:anonymous",
      'grant_view' => 0,
      'grant_update' => 0,
      'grant_delete' => 0,
      'priority' => 0,
    ];

    // Get references to the grants for faster and more readable loops below.
    $can_view = &$anonymous_record['grant_view'];
    $can_update = &$anonymous_record['grant_update'];
    $can_delete = &$anonymous_record['grant_delete'];

    $view_permission = $node->isPublished()
      ? "view $plugin_id entity"
      : "view unpublished $plugin_id entity";

    if (!$can_view && $this->groupPermissionsManager->checkAnonymousRole($view_permission, $group)) {
      $can_view = 1;
    }
    if (!$can_update && $this->groupPermissionsManager->checkAnonymousRole("update any $plugin_id entity", $group)) {
      $can_update = 1;
    }
    if (!$can_delete && $this->groupPermissionsManager->checkAnonymousRole("delete any $plugin_id entity", $group)) {
      $can_delete = 1;
    }

    // If the node is owned by anonymous, we also need to check for the author
    // permissions following the pattern "$op own $plugin_id entity".
    if ($uid == 0) {
      if (!$can_update && $this->groupPermissionsManager->checkAnonymousRole("update own $plugin_id entity", $group)) {
        $can_update = 1;
      }
      if (!$can_delete && $this->groupPermissionsManager->checkAnonymousRole("delete own $plugin_id entity", $group)) {
        $can_delete = 1;
      }
    }
    $records[] = $anonymous_record;

    return $records;
  }

  /**
   * {@inheritdoc}
   */
  public function grantAccess(AccountInterface $account, string $op) {
    // Use the advanced drupal_static() pattern, since this is called very often.
    $grants_cache = &drupal_static(__METHOD__, []);
    $account_id = $account->id();
    if (isset($grants_cache[$account_id][$op])) {
      return $grants_cache[$account_id][$op];
    }

    // Anonymous users get the anonymous grant. See the implementation in the
    // fut_group_node_access_records() function as to why that is.
    if ($account->isAnonymous()) {
      return [
        'group_permissions:anonymous' => [GROUP_PERMISSIONS_GRANT_ID],
        'group_permissions_unpublished:anonymous' => [GROUP_PERMISSIONS_GRANT_ID],
      ];
    }

    $grants = [];
    $grants['group_permissions:outsider'] = [GROUP_PERMISSIONS_GRANT_ID];
    $grants['group_permissions_unpublished:outsider'] = [GROUP_PERMISSIONS_GRANT_ID];

    // Author grants.
    $grants['group_permissions:author'] = [$account_id];
    $grants['group_permissions_unpublished:author'] = [$account_id];

    // Initialize a grant array for members and one for outsider users.
    foreach ($this->membershipLoader->loadByUser($account) as $group_membership) {
      $group = $group_membership->getGroup();
      $group_id = $group->id();
      $member_roles = $group_membership->getRoles();
      foreach ($member_roles as $role_id => $role) {
        $grants["group_permissions:$role_id"][] = $group_id;
        $grants["group_permissions_unpublished:$role_id"][] = $group_id;
      }

      $outsider_roles = $this->groupPermissionsManager->getOutsiderRoles($group, $account);
      foreach ($outsider_roles as $role_id => $role) {
        $grants["group_permissions:$role_id"][] = $group_id;
        $grants["group_permissions_unpublished:$role_id"][] = $group_id;
      }
    }

    // Recursively merge the member grants with the outsider grants.
    $grants_cache[$account_id][$op] = $grants;

    return $grants_cache[$account_id][$op];
  }

}
