<?php

namespace Drupal\group_permissions;

use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Provides an interface for defining GroupPermissionNodeAccessRecordsBuilder service.
 */
interface GroupPermissionsNodeAccessRecordsBuilderInterface {

  /**
   * Builds Access Record for given Node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node which access record is being built.
   *
   * @return array
   *   Access Records.
   */
  public function buildAccessRecords(NodeInterface $node);

  /**
   * Assemble a list of "grant IDs" for given account.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account object whose grants are requested.
   * @param string $op
   *   The node operation.
   *
   * @return array
   *   An array whose keys are "realms" of grants, and whose values are arrays of
   *   the grant IDs within this realm that this user is being granted.
   */
  public function grantAccess(AccountInterface $account, string $op);

}
