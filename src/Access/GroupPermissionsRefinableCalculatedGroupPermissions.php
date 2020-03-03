<?php

namespace Drupal\group_permissions\Access;

use Drupal\group\Access\CalculatedGroupPermissionsInterface;
use Drupal\group\Access\RefinableCalculatedGroupPermissions;

/**
 * Represents a calculated set of group permissions with cacheable metadata.
 *
 * @see \Drupal\group\Access\ChainGroupPermissionCalculator
 */
class GroupPermissionsRefinableCalculatedGroupPermissions extends RefinableCalculatedGroupPermissions {

  /**
   * {@inheritdoc}
   */
  public function merge(CalculatedGroupPermissionsInterface $calculated_permissions, $overwrite = FALSE) {
    foreach ($calculated_permissions->getItems() as $item) {
      $this->addItem($item, $overwrite);
    }
    $this->addCacheableDependency($calculated_permissions);
    return $this;
  }
}
