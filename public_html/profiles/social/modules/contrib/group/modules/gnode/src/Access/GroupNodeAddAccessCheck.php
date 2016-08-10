<?php

namespace Drupal\gnode\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\node\NodeTypeInterface;
use Symfony\Component\Routing\Route;

/**
 * Determines access to for group node add forms.
 */
class GroupNodeAddAccessCheck implements AccessInterface {

  /**
   * Checks access to the group node creation wizard.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group to create the node in.
   * @param \Drupal\node\NodeTypeInterface $node_type
   *   The type of node to create in the group.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, AccountInterface $account, GroupInterface $group, NodeTypeInterface $node_type) {
    $needs_access = $route->getRequirement('_group_node_add_access') === 'TRUE';

    // We can only get the group content type ID if the plugin is installed.
    $plugin_id = 'group_node:' . $node_type->id();
    if (!$group->getGroupType()->hasContentPlugin($plugin_id)) {
      return AccessResult::neutral();
    }

    // Determine whether the user can create nodes of the provided type.
    $access = $group->hasPermission('create ' . $node_type->id() . ' node', $account);

    // Only allow access if the user can create group nodes of the provided type
    // or if he doesn't need access to do so.
    return AccessResult::allowedIf($access xor !$needs_access);
  }

}
