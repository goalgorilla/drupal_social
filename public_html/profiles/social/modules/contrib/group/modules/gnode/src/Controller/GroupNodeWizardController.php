<?php

namespace Drupal\gnode\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Render\RendererInterface;
use Drupal\group\Entity\GroupContent;
use Drupal\group\Entity\GroupInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeTypeInterface;
use Drupal\user\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for 'group_node' GroupContent routes.
 */
class GroupNodeWizardController extends ControllerBase {

  /**
   * The private store for temporary group nodes.
   *
   * @var \Drupal\user\PrivateTempStore
   */
  protected $privateTempStore;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity form builder.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface
   */
  protected $entityFormBuilder;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a new GroupNodeWizardController.
   *
   * @param \Drupal\user\PrivateTempStoreFactory $temp_store_factory
   *   The factory for the temp store object.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFormBuilderInterface $entity_form_builder
   *   The entity form builder.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory, EntityTypeManagerInterface $entity_type_manager, EntityFormBuilderInterface $entity_form_builder, RendererInterface $renderer) {
    $this->privateTempStore = $temp_store_factory->get('gnode_add_temp');
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFormBuilder = $entity_form_builder;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user.private_tempstore'),
      $container->get('entity_type.manager'),
      $container->get('entity.form_builder'),
      $container->get('renderer')
    );
  }

  /**
   * Provides the form for creating a node in a group.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group to create a node in.
   * @param \Drupal\node\NodeTypeInterface $node_type
   *   The node type to create.
   *
   * @return array
   *   The form array for either step 1 or 2 of the group node creation wizard.
   */
  public function addForm(GroupInterface $group, NodeTypeInterface $node_type) {
    $plugin_id = 'group_node:' . $node_type->id();
    $storage_id = $plugin_id . ':' . $group->id();

    // If we are on step one, we need to build a node form.
    if ($this->privateTempStore->get("$storage_id:step") !== 2) {
      $this->privateTempStore->set("$storage_id:step", 1);

      // Only create a new node if we have nothing stored.
      if (!$entity = $this->privateTempStore->get("$storage_id:node")) {
        $entity = Node::create(['type' => $node_type->id()]);
      }
    }
    // If we are on step two, we need to build a group content form.
    else {
      /** @var \Drupal\group\Plugin\GroupContentEnablerInterface $plugin */
      $plugin = $group->getGroupType()->getContentPlugin($plugin_id);
      $entity = GroupContent::create([
        'type' => $plugin->getContentTypeConfigId(),
        'gid' => $group->id(),
      ]);
    }

    // Return the form with the group and storage ID added to the form state.
    $extra = ['group' => $group, 'storage_id' => $storage_id];
    return $this->entityFormBuilder()->getForm($entity, 'gnode-form', $extra);
  }

  /**
   * The _title_callback for the add node form route.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group to create a node in.
   * @param \Drupal\node\NodeTypeInterface $node_type
   *   The node type to create.
   *
   * @return string
   *   The page title.
   */
  public function addFormTitle(GroupInterface $group, NodeTypeInterface $node_type) {
    return $this->t('Create %type in %label', ['%type' => $node_type->label(), '%label' => $group->label()]);
  }

  /**
   * Provides the group node creation overview page.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group to add the group node to.
   *
   * @return array
   *   The group node creation overview page.
   */
  public function addPage(GroupInterface $group) {
    // We do not set the "entity_add_list" template's "#add_bundle_message" key
    // because we deny access to the page if no bundle is available.
    $build = ['#theme' => 'entity_add_list', '#bundles' => []];
    $add_form_route = 'entity.group_content.group_node_add_form';

    // Build a list of available bundles.
    $bundles = [];
    $access_control_handler = $this->entityTypeManager->getAccessControlHandler('group_content');
    foreach ($group->getGroupType()->getInstalledContentPlugins() as $plugin_id => $plugin) {
      /** @var \Drupal\group\Plugin\GroupContentEnablerInterface $plugin */
      // Only select the group_node plugins.
      if ($plugin->getBaseId() == 'group_node') {
        $bundle = $plugin->getContentTypeConfigId();

        // Add the user's access rights as cacheable dependencies.
        $access = $access_control_handler->createAccess($bundle, NULL, ['group' => $group], TRUE);
        $this->renderer->addCacheableDependency($build, $access);

        // Filter out the bundles the user doesn't have access to.
        if ($access->isAllowed()) {
          $bundles[$plugin_id] = $bundle;
        }
      }
    }

    // Redirect if there's only one bundle available.
    if (count($bundles) == 1) {
      $plugin = $group->getGroupType()->getContentPlugin(key($bundles));
      $route_params = ['group' => $group->id(), 'node_type' => $plugin->getEntityBundle()];
      $url = Url::fromRoute($add_form_route, $route_params, ['absolute' => TRUE]);
      return new RedirectResponse($url->toString());
    }

    // Get the node type storage handler.
    $storage_handler = $this->entityTypeManager->getStorage('node_type');

    // Set the info for all of the remaining bundles.
    foreach ($bundles as $plugin_id => $bundle) {
      $plugin = $group->getGroupType()->getContentPlugin($plugin_id);
      $bundle_label = $storage_handler->load($plugin->getEntityBundle())->label();
      $route_params = ['group' => $group->id(), 'node_type' => $plugin->getEntityBundle()];

      $build['#bundles'][$bundle] = [
        'label' => $bundle_label,
        'description' => $this->t('Create a node of type %node_type for the group.', ['%node_type' => $bundle_label]),
        'add_link' => Link::createFromRoute($bundle_label, $add_form_route, $route_params),
      ];
    }

    // Add the list cache tags for the GroupContentType entity type.
    $bundle_entity_type = $this->entityTypeManager->getDefinition('group_content_type');
    $build['#cache']['tags'] = $bundle_entity_type->getListCacheTags();

    return $build;
  }

}
