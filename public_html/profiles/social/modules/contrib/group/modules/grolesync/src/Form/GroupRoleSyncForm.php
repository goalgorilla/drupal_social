<?php

namespace Drupal\grolesync\Form;

use Drupal\group\Form\GroupPermissionsTypeSpecificForm;

/**
 * Provides the roles synchronization form for a specific group type.
 */
class GroupRoleSyncForm extends GroupPermissionsTypeSpecificForm {

  /**
   * {@inheritdoc}
   */
  protected function getInfo() {
    $info = [
      'sync_info' => [
        '#prefix' => '<p>',
        '#suffix' => '</p>',
        '#markup' => $this->t("Below you can assign group permissions to global site roles.<br />Anyone with any of those roles will automatically receive the selected permissions, even if they are not a member of the group."),
      ],
      'audience_info' => [
        '#prefix' => '<p>',
        '#suffix' => '</p>',
        '#markup' => $this->t('Please note that the permissions available for configuration are those you would normally be able to assign to the <em>Outsider</em> role.<br />If you need to vary permissions for members, you should create a group role under the <em>Roles</em> tab instead.'),
      ],
    ] + parent::getInfo();

    // Unset the info about the group role audiences.
    unset($info['role_info']);

    return $info;
  }

  /**
   * {@inheritdoc}
   */
  protected function getGroupRoles() {
    $properties = [
      'group_type' => $this->groupType->id(),
      'permissions_ui' => FALSE,
    ];

    /** @var \Drupal\group\Entity\GroupRoleInterface[] $group_roles */
    $group_roles = $this->entityTypeManager->getStorage('group_role')->loadByProperties($properties);

    // Synchronized group roles are saved with an enforced dependency on this
    // module. The easiest way to find those that we need to show, is to load
    // all "hidden" group roles for this group type and check the dependencies.
    foreach ($group_roles as $group_role_id => $group_role) {
      $dependencies = $group_role->getDependencies();
      if (!isset($dependencies['module']) || !in_array('grolesync', $dependencies['module'])) {
        unset($group_roles[$group_role_id]);
      }
    }

    return $group_roles;
  }

}
