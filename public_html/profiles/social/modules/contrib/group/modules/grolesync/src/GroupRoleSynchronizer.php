<?php

namespace Drupal\grolesync;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\group\Entity\GroupRole;
use Drupal\group\Entity\GroupTypeInterface;
use Drupal\User\RoleInterface;

/**
 * Synchronizes user roles to group roles.
 */
class GroupRoleSynchronizer implements GroupRoleSynchronizerInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new GroupRoleSynchronizer.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupRoleId($group_type_id, $role_id) {
    // The maximum length of a group role's machine name.
    //
    // Group role IDs consist of two parts separated by a dash:
    // - The group type ID.
    // - The machine name of the group role; unique per group type.
    //
    // Therefore, the maximum length of a group role machine name is determined
    // by subtracting the group type ID length from the entity type ID length
    // and leaving room for a dash character.
    $machine_name_max_length = EntityTypeInterface::ID_MAX_LENGTH - GroupTypeInterface::ID_MAX_LENGTH - 1;

    // Generate an MD5 hash to use as the group role machine name.
    $machine_name = substr(md5('group_role_sync.' . $role_id), 0, $machine_name_max_length);

    return "$group_type_id-$machine_name";
  }

  /**
   * {@inheritdoc}
   */
  public function createGroupRoles($group_type_ids = NULL, $role_ids = NULL) {
    /** @var \Drupal\group\Entity\GroupTypeInterface[] $group_types */
    $group_types = $this->entityTypeManager->getStorage('group_type')->loadMultiple($group_type_ids);

    // Return early if there are no group types to create roles for.
    if (empty($group_types)) {
      return;
    }

    /** @var \Drupal\User\RoleInterface[] $user_roles */
    $user_roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple($role_ids);

    $definitions = [];
    foreach (array_keys($user_roles) as $role_id) {
      // We do not synchronize the 'anonymous' or 'authenticated' user role as
      // they are already taken care of by the 'anonymous' and 'outsider'
      // internal group roles.
      if ($role_id == 'anonymous' || $role_id == 'authenticated') {
        continue;
      }

      // Build a list of group role definitions but do not save them yet so we
      // can check whether they already exist in bulk instead of trying to find
      // out on an individual basis here.
      foreach (array_keys($group_types) as $group_type_id) {
        $group_role_id = $this->getGroupRoleId($group_type_id, $role_id);
        $definitions[$group_role_id] = [
          'id' => $group_role_id,
          'label' => $user_roles[$role_id]->label(),
          'weight' => $user_roles[$role_id]->getWeight(),
          'internal' => TRUE,
          'audience' => 'outsider',
          'group_type' => $group_type_id,
          'permissions_ui' => FALSE,
          // Adding our this module and the user role as enforced dependencies
          // will automatically delete the synchronized group roles when this
          // module is uninstalled or the user role is deleted.
          'dependencies' => [
            'enforced' => [
              'module' => ['grolesync'],
              'config' => [$user_roles[$role_id]->getConfigDependencyName()],
            ],
          ],
        ];
      }
    }

    // See if the roles we just defined already exist.
    $existing = $this->entityTypeManager->getStorage('group_role')->loadMultiple(array_keys($definitions));

    // Create the group roles that do not exist yet.
    foreach (array_diff_key($definitions, $existing) as $definition) {
      GroupRole::create($definition)->save();
    }
  }

  /**
   * Updates the label of all group roles for a user role.
   *
   * @param \Drupal\User\RoleInterface $role
   *   The user role to update the group role labels for.
   */
  public function updateGroupRoleLabels(RoleInterface $role) {
    /** @var \Drupal\group\Entity\GroupTypeInterface[] $group_types */
    $group_types = $this->entityTypeManager->getStorage('group_type')->loadMultiple();

    // Return early if there are no group types and, by extension, group roles.
    if (empty($group_types)) {
      return;
    }

    $group_role_ids = [];
    foreach (array_keys($group_types) as $group_type_id) {
      $group_role_ids[] = $this->getGroupRoleId($group_type_id, $role->id());
    }

    /** @var \Drupal\group\Entity\GroupRoleInterface[] $group_roles */
    $group_roles = $this->entityTypeManager->getStorage('group_role')->loadMultiple($group_role_ids);
    foreach ($group_roles as $group_role) {
      $group_role->set('label', $role->label())->save();
    }
  }

}
