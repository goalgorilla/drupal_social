<?php

namespace Drupal\gnode\Plugin\GroupContentEnabler;

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
 *   id = "group_node",
 *   label = @Translation("Group node"),
 *   description = @Translation("Adds nodes to groups both publicly and privately."),
 *   entity_type_id = "node",
 *   pretty_path_key = "node",
 *   deriver = "Drupal\gnode\Plugin\GroupContentEnabler\GroupNodeDeriver"
 * )
 */
class GroupNode extends GroupContentEnablerBase {

  /**
   * Retrieves the node type this plugin supports.
   *
   * @return \Drupal\node\NodeTypeInterface
   *   The node type this plugin supports.
   */
  protected function getNodeType() {
    return NodeType::load($this->getEntityBundle());
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupOperations(GroupInterface $group) {
    $account = \Drupal::currentUser();
    $type = $this->getEntityBundle();
    $operations = [];

    if ($group->hasPermission("create $type node", $account)) {
      $route_params = ['group' => $group->id(), 'node_type' => $this->getEntityBundle()];
      $operations["gnode-create-$type"] = [
        'title' => $this->t('Create @type', ['@type' => $this->getNodeType()->label()]),
        'url' => new Url('entity.group_content.group_node_add_form', $route_params),
        'weight' => 30,
      ];
    }

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function getPermissions() {
    $permissions = parent::getPermissions();

    // Unset unwanted permissions defined by the base plugin.
    $plugin_id = $this->getPluginId();
    unset($permissions["access $plugin_id overview"]);

    // Add our own permissions for managing the actual nodes.
    $type = $this->getEntityBundle();
    $type_arg = ['%node_type' => $this->getNodeType()->label()];
    $defaults = [
      'title_args' => $type_arg,
      'description' => 'Only applies to %node_type nodes that belong to this group.',
      'description_args' => $type_arg,
    ];

    $permissions["view $type node"] = [
      'title' => '%node_type: View content',
    ] + $defaults;

    $permissions["create $type node"] = [
      'title' => '%node_type: Create new content',
      'description' => 'Allows you to create %node_type nodes that immediately belong to this group.',
      'description_args' => $type_arg,
    ] + $defaults;

    $permissions["edit own $type node"] = [
      'title' => '%node_type: Edit own content',
    ] + $defaults;

    $permissions["edit any $type node"] = [
      'title' => '%node_type: Edit any content',
    ] + $defaults;

    $permissions["delete own $type node"] = [
      'title' => '%node_type: Delete own content',
    ] + $defaults;

    $permissions["delete any $type node"] = [
      'title' => '%node_type: Delete any content',
    ] + $defaults;

    return $permissions;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $config = parent::defaultConfiguration();
    $config['entity_cardinality'] = 1;

    // This string will be saved as part of the group type config entity. We do
    // not use a t() function here as it needs to be stored untranslated.
    $config['info_text']['value'] = '<p>By submitting this form you will add this content to the group.<br />It will then be subject to the access control settings that were configured for the group.<br/>Please fill out any available fields to describe the relation between the content and the group.</p>';
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Disable the entity cardinality field as the functionality of this module
    // relies on a cardinality of 1. We don't just hide it, though, to keep a UI
    // that's consistent with other content enabler plugins.
    $info = $this->t("This field has been disabled by the plugin to guarantee the functionality that's expected of it.");
    $form['entity_cardinality']['#disabled'] = TRUE;
    $form['entity_cardinality']['#description'] .= '<br /><em>' . $info . '</em>';

    return $form;
  }

}
