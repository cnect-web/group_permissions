<?php

namespace Drupal\group_permissions\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\group\Access\GroupPermissionHandlerInterface;
use Drupal\group\Entity\Group;
use Drupal\Core\Form\FormStateInterface;
use Drupal\group\Form\GroupPermissionsForm as BasePermissionForm;
use Drupal\group_permissions\Entity\GroupPermission;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the group permissions administration form.
 */
class GroupPermissionsForm extends BasePermissionForm {

  const USE_DEFAULT = 1;

  const OVERRIDE = 0;

  /**
   * Group.
   *
   * @var \Drupal\group\Entity\Group;
   */
  protected $group;

  /**
   * GroupPermission.
   *
   * @var \Drupal\group_permissions\Entity\GroupPermission;
   */
  protected $groupPermission;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'group_permissions';
  }

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new GroupPermissionsTypeSpecificForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\group\Access\GroupPermissionHandlerInterface $permission_handler
   *   The group permission handler.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, GroupPermissionHandlerInterface $permission_handler, ModuleHandlerInterface $module_handler) {
    parent::__construct($permission_handler, $module_handler);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('group.permissions'),
      $container->get('module_handler')
    );
  }

  /**
   * Gets the group type to build the form for.
   *
   * @return \Drupal\group\Entity\GroupTypeInterface
   *   The group type some or more roles belong to.
   */
  protected function getGroupType() {
    return $this->group->getGroupType();
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Group $group = NULL) {
    $this->group = $group;

    $role_info = [];

    $this->groupPermission = GroupPermission::loadByGroup($group);

    $custom_permissions = [];
    if (!empty($this->groupPermission)) {
      $custom_permissions = $this->groupPermission->getPermissions()->first()->getValue();
    }

    $form['override_permissions'] = [
      '#type' => 'select',
      '#title' => $this->t('Override or use default permissions'),
      '#options' => [
        self::OVERRIDE => $this->t('Override'),
        self::USE_DEFAULT => $this->t('Use default'),
      ],
      '#default_value' => empty($this->groupPermission) ? self::USE_DEFAULT : self::OVERRIDE,
    ];

    // Sort the group roles using the static sort() method.
    // See \Drupal\Core\Config\Entity\ConfigEntityBase::sort().
    $group_roles = $this->getGroupRoles();


    // Retrieve information for every role to user further down. We do this to
    // prevent the same methods from being fired (rows * permissions) times.
    foreach ($group_roles as $role_name => $group_role) {
      $permissions = [];
      if (!empty($this->groupPermission)) {
        // Permissions should be explicitly assigned another case we don't
        // provide the permission.
        if (!empty($custom_permissions[$role_name])) {
          $permissions = $custom_permissions[$role_name];
        }
      }
      else {
        $permissions = $group_role->getPermissions();
      }

      $role_label = $group_role->label();
      if ($group_role->isOutsider() && !$group_role->inPermissionsUI()) {
        $role_label .= ' (Outsider)';
      }

      $role_info[$role_name] = [
        'label' => $role_label,
        'permissions' => $permissions,
        'is_anonymous' => $group_role->isAnonymous(),
        'is_outsider' => $group_role->isOutsider(),
        'is_member' => $group_role->isMember(),
      ];
    }

    // Render the general information.
    if ($info = $this->getInfo()) {
      $form['info'] = $info;
    }

    // Render the link for hiding descriptions.
    $form['system_compact_link'] = [
      '#id' => FALSE,
      '#type' => 'system_compact_link',
    ];

    // Render the roles and permissions table.
    $form['permissions'] = [
      '#type' => 'table',
      '#header' => [$this->t('Permission')],
      '#id' => 'permissions',
      '#attributes' => ['class' => ['permissions', 'js-permissions']],
      '#sticky' => TRUE,
    ];

    // Create a column with header for every group role.
    foreach ($role_info as $info) {
      $form['permissions']['#header'][] = [
        'data' => $info['label'],
        'class' => ['checkbox'],
      ];
    }

    // Render the permission as sections of rows.
    $hide_descriptions = system_admin_compact_mode();

    foreach ($this->getPermissions() as $provider => $sections) {
      // Print a full width row containing the provider name for each provider.
      $form['permissions'][$provider] = [
        [
          '#wrapper_attributes' => [
            'colspan' => count($group_roles) + 1,
            'class' => ['module'],
            'id' => "module-$provider",
          ],
          '#markup' => $this->moduleHandler->getName($provider),
        ]
      ];

      foreach ($sections as $section => $permissions) {
        // Create a clean section ID.
        $section_id = $provider. '-' . preg_replace('/[^a-z0-9_]+/', '_', strtolower($section));

        // Start each section with a full width row containing the section name.
        $form['permissions'][$section_id] = [
          [
            '#wrapper_attributes' => [
              'colspan' => count($group_roles) + 1,
              'class' => ['section'],
              'id' => "section-$section_id",
            ],
            '#markup' => $section,
          ]
        ];

        // Then list all of the permissions for that provider and section.
        foreach ($permissions as $perm => $perm_item) {
          // Create a row for the permission, starting with the description cell.
          $form['permissions'][$perm]['description'] = [
            '#type' => 'inline_template',
            '#template' => '<span class="title">{{ title }}</span>{% if description or warning %}<div class="description">{% if warning %}<em class="permission-warning">{{ warning }}</em><br />{% endif %}{{ description }}</div>{% endif %}',
            '#context' => [
              'title' => $perm_item['title'],
            ],
            '#wrapper_attributes' => [
              'class' => ['permission'],
            ],
          ];

          // Show the permission description and warning if toggled on.
          if (!$hide_descriptions) {
            $form['permissions'][$perm]['description']['#context']['description'] = $perm_item['description'];
            $form['permissions'][$perm]['description']['#context']['warning'] = $perm_item['warning'];
          }

          // Finally build a checkbox cell for every group role.
          foreach ($role_info as $role_name => $info) {
            // Determine whether the permission is available for this role.
            $na = $info['is_anonymous'] && !in_array('anonymous', $perm_item['allowed for']);
            $na = $na || ($info['is_outsider'] && !in_array('outsider', $perm_item['allowed for']));
            $na = $na || ($info['is_member'] && !in_array('member', $perm_item['allowed for']));

            // Show a red '-' if the permission is unavailable.
            if ($na) {
              $form['permissions'][$perm][$role_name] = [
                '#title' => "{$info['label']}: {$perm_item['title']}",
                '#title_display' => 'invisible',
                '#wrapper_attributes' => [
                  'class' => ['checkbox'],
                  'style' => 'color: #ff0000;',
                ],
                '#markup' => '-',
              ];
            }
            // Show a checkbox if the permissions is available.
            else {
              $form['permissions'][$perm][$role_name] = [
                '#title' => "{$info['label']}: {$perm_item['title']}",
                '#title_display' => 'invisible',
                '#wrapper_attributes' => [
                  'class' => ['checkbox'],
                ],
                '#type' => 'checkbox',
                '#default_value' => in_array($perm, $info['permissions']) ? 1 : 0,
                '#attributes' => [
                  'class' => [
                    "rid-$role_name",
                    "js-rid-$role_name",
                  ]
                ],
                '#parents' => [$role_name, $perm],
                '#states' => [
                  'disabled' => [
                    ':input[name="override_permissions"]' => [
                      'value' => self::USE_DEFAULT
                    ],
                  ],
                ],
              ];
            }
          }
        }
      }
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save permissions'),
      '#button_type' => 'primary',
    ];

    // @todo Do something like the global permissions page JS for 'member'.
    // @todo See user/drupal.user.permissions for JS example.
    $form['#attached']['library'][] = 'group/permissions';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function getInfo() {
    $list = [
      'role_info' => [
        '#prefix' => '<p>' . $this->t('Group types use three special roles:') . '</p>',
        '#theme' => 'item_list',
        '#items' => [
          ['#markup' => $this->t('<strong>Anonymous:</strong> This is the same as the global Anonymous role, meaning the user has no account.')],
          ['#markup' => $this->t('<strong>Outsider:</strong> This means the user has an account on the site, but is not a member of the group.')],
          ['#markup' => $this->t('<strong>Member:</strong> The default role for anyone in the group. Behaves like the "Authenticated user" role does globally.')],
        ],
      ],
    ];

    return $list + parent::getInfo();
  }

  /**
   * {@inheritdoc}
   */
  function submitForm(array &$form, FormStateInterface $form_state) {

    if ($form_state->getValue('override_permissions') == self::OVERRIDE) {
      $permissions = [];
      foreach ($this->getGroupRoles() as $role_name => $group_role) {
        /** @var \Drupal\group\Entity\GroupRoleInterface $group_role */
        $permissions[$role_name] = array_keys(array_filter($form_state->getValue($role_name)));
      }

      if (!empty($this->groupPermission)) {
        $this->groupPermission->setPermissions($permissions);
      }
      else {
        $this->groupPermission = GroupPermission::create([
          'gid' => $this->group->id(),
          'permissions' => $permissions,
        ]);
      }

      $violations = $this->groupPermission->validate();
      if (count($violations) == 0) {
        $this->groupPermission->save();
        $this->messenger()->addMessage($this->t('The changes have been saved.'));
      }
      else {
        foreach ($violations as $violation) {
          $this->messenger()->addError($this->t($violation->getMessage()));
        }
      }
    }
    else {
      if (!empty($this->groupPermission)) {
        $this->groupPermission->delete();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getPermissions() {
    $full_permissions = parent::getPermissions();
    // Only keep permissions for the current group.
    foreach ($full_permissions as $provider => $sections) {
      foreach ($sections as $section => $permissions) {
        foreach ($permissions as $perm => $perm_item) {
          if (!empty($perm_item['gid']) && $perm_item['gid'] != $this->group->id()) {
            unset($full_permissions[$provider][$section][$perm]);
          }
        }
      }
    }

    return $full_permissions;
  }

  /**
   * {@inheritdoc}
   */
  protected function getGroupRoles() {
    $group_type_id = $this->group->getGroupType()->id();
    $properties = [
      'group_type' => $group_type_id,
      'permissions_ui' => TRUE,
    ];

    $roles = $this->entityTypeManager
      ->getStorage('group_role')
      ->loadByProperties($properties);

    uasort($roles, '\Drupal\group\Entity\GroupRole::sort');

    $storage = $this->entityTypeManager->getStorage('group_role');
    $outsider_roles = $storage->loadSynchronizedByGroupTypes([$group_type_id]);
    return array_merge($roles, $outsider_roles);
  }

}
