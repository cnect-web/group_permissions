<?php

namespace Drupal\group_permissions\Entity\Storage;

use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\group\Entity\GroupInterface;

/**
 * Defines an interface for group content entity storage classes.
 */
interface GroupPermissionStorageInterface extends ContentEntityStorageInterface {

  /**
   * Retrieves Group permission entity for a group.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group entity to load the group content entities for.
   *
   * @return \Drupal\group\Entity\GroupPermissiontInterface[]
   *   A list of GroupPermission entity matching the criteria.
   */
  public function loadByGroup(GroupInterface $group);

}
