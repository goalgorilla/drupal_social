<?php

namespace Drupal\group\Entity\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupContent;
use Drupal\group\Entity\GroupContentType;
use Drupal\group\Entity\GroupInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for GroupContent routes.
 */
class GroupContentController extends ControllerBase {

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
   * Constructs a new GroupContentController.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFormBuilderInterface $entity_form_builder
   *   The entity form builder.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFormBuilderInterface $entity_form_builder, RendererInterface $renderer) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFormBuilder = $entity_form_builder;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity.form_builder'),
      $container->get('renderer')
    );
  }

  /**
   * Provides the group content creation overview page.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group to add the group content to.
   *
   * @return array
   *   The group content creation overview page.
   */
  public function addPage(GroupInterface $group) {
    $build = ['#theme' => 'entity_add_list', '#bundles' => []];
    $form_route = $this->addPageFormRoute($group);
    $bundle_names = $this->addPageBundles($group);

    // Set the add bundle message if available.
    $add_bundle_message = $this->addPageBundleMessage($group);
    if ($add_bundle_message !== FALSE) {
      $build['#add_bundle_message'] = $add_bundle_message;
    }

    // Filter out the bundles the user doesn't have access to.
    $access_control_handler = $this->entityTypeManager->getAccessControlHandler('group_content');
    foreach ($bundle_names as $plugin_id => $bundle_name) {
      $access = $access_control_handler->createAccess($bundle_name, NULL, ['group' => $group], TRUE);
      if (!$access->isAllowed()) {
        unset($bundle_names[$plugin_id]);
      }
      $this->renderer->addCacheableDependency($build, $access);
    }

    // Redirect if there's only one bundle available.
    if (count($bundle_names) == 1) {
      reset($bundle_names);
      $route_params = ['group' => $group->id(), 'plugin_id' => key($bundle_names)];
      $url = Url::fromRoute($form_route, $route_params, ['absolute' => TRUE]);
      return new RedirectResponse($url->toString());
    }

    // Set the info for all of the remaining bundles.
    foreach ($bundle_names as $plugin_id => $bundle_name) {
      $plugin = $group->getGroupType()->getContentPlugin($plugin_id);
      $label = $plugin->getLabel();

      $build['#bundles'][$bundle_name] = [
        'label' => $label,
        'description' => $plugin->getContentTypeDescription(),
        'add_link' => Link::createFromRoute($label, $form_route, ['group' => $group->id(), 'plugin_id' => $plugin_id]),
      ];
    }

    // Add the list cache tags for the GroupContentType entity type.
    $bundle_entity_type = $this->entityTypeManager->getDefinition('group_content_type');
    $build['#cache']['tags'] = $bundle_entity_type->getListCacheTags();

    return $build;
  }

  /**
   * Retrieves a list of available bundles for the add page.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group to add the group content to.
   *
   * @return array
   *   An array of group content type IDs, keyed by the plugin that was used to
   *   generate their respective group content types.
   */
  protected function addPageBundles(GroupInterface $group) {
    $plugins = $group->getGroupType()->getInstalledContentPlugins();

    $bundle_names = [];
    foreach ($plugins as $plugin_id => $plugin) {
      /** @var \Drupal\group\Plugin\GroupContentEnablerInterface $plugin */
      $bundle_names[$plugin_id] = $plugin->getContentTypeConfigId();
    }

    return $bundle_names;
  }

  /**
   * Returns the 'add_bundle_message' string for the add page.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group to add the group content to.
   *
   * @return string|false
   *   The translated string or FALSE if no string should be set.
   */
  protected function addPageBundleMessage(GroupInterface $group) {
    // We do not set the 'add_bundle_message' variable because we know there
    // will always be at least one bundle, namely group memberships.
    return FALSE;
  }

  /**
   * Returns the route name of the form the add page should link to.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group to add the group content to.
   *
   * @return string
   *   The route name.
   */
  protected function addPageFormRoute(GroupInterface $group) {
    return 'entity.group_content.add_form';
  }

  /**
   * Provides the group content submission form.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group to add the group content to.
   * @param string $plugin_id
   *   The group content enabler to add content with.
   *
   * @return array
   *   A group submission form.
   */
  public function addForm(GroupInterface $group, $plugin_id) {
    /** @var \Drupal\group\Plugin\GroupContentEnablerInterface $plugin */
    $plugin = $group->getGroupType()->getContentPlugin($plugin_id);

    $group_content = GroupContent::create([
      'type' => $plugin->getContentTypeConfigId(),
      'gid' => $group->id(),
    ]);

    return $this->entityFormBuilder->getForm($group_content, 'add');
  }

  /**
   * The _title_callback for the entity.group_content.add_form route.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group to add the group content to.
   * @param string $plugin_id
   *   The group content enabler to add content with.
   *
   * @return string
   *   The page title.
   */
  public function addFormTitle(GroupInterface $group, $plugin_id) {
    /** @var \Drupal\group\Plugin\GroupContentEnablerInterface $plugin */
    $plugin = $group->getGroupType()->getContentPlugin($plugin_id);
    $group_content_type = GroupContentType::load($plugin->getContentTypeConfigId());
    return $this->t('Create @name', ['@name' => $group_content_type->label()]);
  }

  /**
   * The _title_callback for the entity.group_content.collection route.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group to add the group content to.
   *
   * @return string
   *   The page title.
   *
   * @todo Revisit when 8.2.0 is released, https://www.drupal.org/node/2767853.
   */
  public function collectionTitle(GroupInterface $group) {
    return $this->t('Related entities for @group', ['@group' => $group->label()]);
  }

}
