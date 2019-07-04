<?php

namespace Drupal\group_permissions\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\group\Entity\Group;

/**
 * Provides an interface for defining Group permission entities.
 *
 * @ingroup group_permissions
 */
interface GroupPermissionInterface extends ContentEntityInterface {

  /**
   * Gets the Group.
   *
   * @return \Drupal\group_permissions\Entity\GroupPermissionInterface
   *   The called Group permission entity.
   */
  public function getGroup();

  /**
   * Sets the Group.
   *
   * @param Group $gid
   *   The Group.
   */
  public function setGroup($group);

  /**
   * Gets group permissions.
   *
   * @return array
   *   Permissions.
   */
  public function getPermissions();

  /**
   * Sets the Group.
   *
   * @param Group $permissions
   *   Group permissions.
   */
  public function setPermissions(array $permissions);


}
