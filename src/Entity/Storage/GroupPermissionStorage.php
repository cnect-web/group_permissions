<?php

namespace Drupal\group_permissions\Entity\Storage;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\group\Entity\GroupInterface;

/**
 * Defines the storage handler class for group permission entities.
 *
 * This extends the base storage class, adding required special handling for
 * loading group permission entities based on group and plugin information.
 */
class GroupPermissionStorage extends SqlContentEntityStorage implements GroupPermissionStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function loadByGroup(GroupInterface $group) {
    $group_permissions = $this->loadByProperties(['gid' => $group->id()]);
    return !empty($group_permissions) ? reset($group_permissions) : NULL;
  }

}
