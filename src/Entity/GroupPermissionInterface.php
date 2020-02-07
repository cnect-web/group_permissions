<?php

namespace Drupal\group_permissions\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\group\Entity\GroupInterface;

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
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The Group.
   */
  public function setGroup(GroupInterface $group);

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
   * @param array $permissions
   *   Group permissions.
   */
  public function setPermissions(array $permissions);

}
