<?php

/**
 * @file
 * Contains \Drupal\group_core_comments\Plugin\GroupContentEnabler\GroupCoreComments.
 */

namespace Drupal\group_core_comments\Plugin\GroupContentEnabler;

use Drupal\group\Entity\GroupInterface;
use Drupal\group\Plugin\GroupContentEnablerBase;
use Drupal\node\Entity\NodeType;
use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\Routing\Route;

/**
 * Provides a content enabler for nodes.
 *
 * @GroupContentEnabler(
 *   id = "group_core_comments",
 *   label = @Translation("Group core comments"),
 *   description = @Translation("Adds support for comments on entities in groups."),
 *   entity_type_id = "comment",
 *   path_key = "node",
 * )
 */
class GroupCoreComments extends GroupContentEnablerBase {

//  /**
//   * Retrieves the node type this plugin supports.
//   *
//   * @return \Drupal\node\NodeTypeInterface
//   *   The node type this plugin supports.
//   */
//  protected function getNodeType() {
//    return NodeType::load($this->getEntityBundle());
//  }

//  /**
//   * {@inheritdoc}
//   */
//  public function getGroupOperations(GroupInterface $group) {
//    $account = \Drupal::currentUser();
//    $type = $this->getEntityBundle();
//    $operations = [];
//
//    if ($group->hasPermission("create $type node", $account)) {
//      $operations["group_core_comments-create-$type"] = [
//        'title' => $this->t('Create @type', ['@type' => $this->getNodeType()->label()]),
//        'url' => new Url($this->getRouteName('create-form'), ['group' => $group->id()]),
//        'weight' => 30,
//      ];
//    }
//
//    return $operations;
//  }

  /**
   * {@inheritdoc}
   */
//  public function getPermissions() {
//    $permissions = parent::getPermissions();
//
//    // Unset unwanted permissions defined by the base plugin.
//    $plugin_id = $this->getPluginId();
//    unset($permissions["access $plugin_id overview"]);
//
//    // Add our own permissions for managing the actual nodes.
//    $type = $this->getEntityBundle();
//    $type_arg = ['%node_type' => $this->getNodeType()->label()];
//    $defaults = [
//      'title_args' => $type_arg,
//      'description' => 'Only applies to %node_type nodes that belong to this group.',
//      'description_args' => $type_arg,
//    ];
//
//    $permissions["view $type node"] = [
//      'title' => '%node_type: View content',
//    ] + $defaults;
//
//    $permissions["create $type node"] = [
//      'title' => '%node_type: Create new content',
//      'description' => 'Allows you to create %node_type nodes that immediately belong to this group.',
//      'description_args' => $type_arg,
//    ] + $defaults;
//
//    $permissions["edit own $type node"] = [
//      'title' => '%node_type: Edit own content',
//    ] + $defaults;
//
//    $permissions["edit any $type node"] = [
//      'title' => '%node_type: Edit any content',
//    ] + $defaults;
//
//    $permissions["delete own $type node"] = [
//      'title' => '%node_type: Delete own content',
//    ] + $defaults;
//
//    $permissions["delete any $type node"] = [
//      'title' => '%node_type: Delete any content',
//    ] + $defaults;
//
//    return $permissions;
//  }

}
