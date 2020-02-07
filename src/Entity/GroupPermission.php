<?php

namespace Drupal\group_permissions\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\group\Entity\GroupInterface;

/**
 * Defines the Group permission entity.
 *
 * @ingroup group_permissions
 *
 * @ContentEntityType(
 *   id = "group_permission",
 *   label = @Translation("Group permission"),
 *   handlers = {
 *     "storage" = "Drupal\group_permissions\Entity\Storage\GroupPermissionStorage",
 *     "access" = "Drupal\group_permissions\GroupPermissionAccessControlHandler",
 *   },
 *   base_table = "group_permission",
 *   admin_permission = "administer group permission entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "gid" = "gid",
 *     "permissions" = "permissions",
 *   },
 * )
 */
class GroupPermission extends ContentEntityBase implements GroupPermissionInterface {

  /**
   * Whether entity validation is required before saving the entity.
   *
   * @var bool
   * @see https://www.drupal.org/project/drupal/issues/2847319
   */
  protected $validationRequired = TRUE;

  /**
   * {@inheritdoc}
   */
  public function getGroup() {
    return $this->gid->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setGroup($group) {
    return $this->gid = $group->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getPermissions() {
    return !empty($this->permissions->first()) ? $this->permissions->first()->getValue() : [];
  }

  /**
   * {@inheritdoc}
   */
  public function setPermissions(array $permissions) {
    $this->permissions = $permissions;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['gid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Group'))
      ->setDescription(t('The group entity.'))
      ->setSetting('target_type', 'group')
      ->setReadOnly(TRUE)
      ->setRequired(TRUE)
      ->addConstraint('UniqueReferenceField');

    $fields['permissions'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Permissions'))
      ->setRevisionable(FALSE)
      ->setTranslatable(FALSE)
      ->setDescription(t('Group permissions.'))
      ->setRequired(TRUE);

    return $fields;
  }

  /**
   * Retrieves Group permission entity for a group.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group entity to load the group content entities for.
   *
   * @return \Drupal\group\Entity\GroupPermissiontInterface|null
   *   The GroupPermission entity of given group OR NULL if not existing.
   */
  public static function loadByGroup(GroupInterface $group) {
    $storage = \Drupal::entityTypeManager()->getStorage('group_permission');
    return $storage->loadByGroup($group);
  }

}
