<?php

namespace Drupal\gnode\Controller;

use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\Controller\GroupContentController;
use Drupal\group\Entity\GroupContent;
use Drupal\group\Entity\GroupInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeTypeInterface;
use Drupal\user\PrivateTempStoreFactory;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for 'group_node' GroupContent routes.
 */
class GroupNodeController extends GroupContentController {

  /**
   * {@inheritdoc}
   */
  protected function addPageBundles(GroupInterface $group) {
    $plugins = $group->getGroupType()->getInstalledContentPlugins();

    $bundle_names = [];
    foreach ($plugins as $plugin_id => $plugin) {
      /** @var \Drupal\group\Plugin\GroupContentEnablerInterface $plugin */
      list($base_plugin_id, $derivative_id) = explode(':', $plugin->getPluginId() . ':');

      // Only select the group_node plugins.
      if ($base_plugin_id == 'group_node') {
        $bundle_names[$plugin_id] = $plugin->getContentTypeConfigId();
      }
    }

    return $bundle_names;
  }

  /**
   * {@inheritdoc}
   */
  protected function addPageBundleMessage(GroupInterface $group) {
    // We do not set the 'add_bundle_message' variable because we deny access to
    // the add page if no bundle is available.
    return FALSE;
  }

}
