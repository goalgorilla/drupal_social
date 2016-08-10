<?php

namespace Drupal\gnode\Routing;

use Drupal\node\Entity\NodeType;
use Symfony\Component\Routing\Route;

/**
 * Provides routes for group_node group content.
 */
class GroupNodeRouteProvider {

  /**
   * Provides the shared collection route for group node plugins.
   */
  public function getRoutes() {
    $routes = $plugin_ids = $permissions_add = $permissions_create = [];

    foreach (NodeType::loadMultiple() as $name => $node_type) {
      $plugin_id = "group_node:$name";

      $plugin_ids[] = $plugin_id;
      $permissions_add[] = "create $plugin_id content";
      $permissions_create[] = "create $name node";
    }

    // If there are no node types yet, we cannot have any plugin IDs and should
    // therefore exit early because we cannot have any routes for them either.
    if (empty($plugin_ids)) {
      return $routes;
    }

    // @todo Conditionally (see above) alter GroupContent info to use this path.
    $routes['entity.group_content.group_node_relate_page'] = new Route('group/{group}/node/add');
    $routes['entity.group_content.group_node_relate_page']
      ->setDefaults([
        '_title' => 'Relate node',
        '_controller' => '\Drupal\gnode\Controller\GroupNodeController::addPage',
      ])
      ->setRequirement('_group_permission', implode('+', $permissions_add))
      ->setRequirement('_group_installed_content', implode('+', $plugin_ids))
      ->setOption('_group_operation_route', TRUE);

    // @todo Conditionally (see above) alter GroupContent info to use this path.
    $routes['entity.group_content.group_node_add_page'] = new Route('group/{group}/node/create');
    $routes['entity.group_content.group_node_add_page']
      ->setDefaults([
        '_title' => 'Create node',
        '_controller' => '\Drupal\gnode\Controller\GroupNodeWizardController::addPage',
      ])
      ->setRequirement('_group_permission', implode('+', $permissions_create))
      ->setRequirement('_group_installed_content', implode('+', $plugin_ids))
      ->setOption('_group_operation_route', TRUE);

    return $routes;
  }

}
