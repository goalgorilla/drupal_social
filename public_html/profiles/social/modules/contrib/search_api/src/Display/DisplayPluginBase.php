<?php

namespace Drupal\search_api\Display;

use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Url;
use Drupal\search_api\Entity\Index;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a base class from which other display classes may extend.
 *
 * Plugins extending this class need to define a plugin definition array through
 * annotation. The definition includes the following keys:
 * - id: The unique, system-wide identifier of the display class.
 * - label: Human-readable name of the display class, translated.
 *
 * A complete plugin definition should be written as in this example:
 *
 * @code
 * @SearchApiDisplay(
 *   id = "my_display",
 *   label = @Translation("My display"),
 *   description = @Translation("A few words about this search display"),
 * )
 * @endcode
 *
 * @see \Drupal\search_api\Annotation\SearchApiDisplay
 * @see \Drupal\search_api\Display\DisplayPluginManager
 * @see \Drupal\search_api\Display\DisplayInterface
 * @see plugin_api
 */
abstract class DisplayPluginBase extends PluginBase implements DisplayInterface {

  /**
   * The current path service.
   *
   * @var \Drupal\Core\Path\CurrentPathStack|null
   */
  protected $currentPath;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $display = new static($configuration, $plugin_id, $plugin_definition);

    $display->setCurrentPath($container->get('path.current'));

    return $display;
  }

  /**
   * Retrieves the current path service.
   *
   * @return \Drupal\Core\Path\CurrentPathStack
   *   The current path service.
   */
  public function getCurrentPath() {
    return $this->currentPath ?: \Drupal::service('path.current');
  }

  /**
   * Sets the current path service.
   *
   * @param \Drupal\Core\Path\CurrentPathStack $current_path
   *   The new current path service.
   *
   * @return $this
   */
  public function setCurrentPath(CurrentPathStack $current_path) {
    $this->currentPath = $current_path;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    $plugin_definition = $this->getPluginDefinition();
    return $plugin_definition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $plugin_definition = $this->getPluginDefinition();
    return $plugin_definition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function getIndex() {
    $plugin_definition = $this->getPluginDefinition();
    return Index::load($plugin_definition['index']);
  }

  /**
   * {@inheritdoc}
   */
  public function getPath() {
    $plugin_definition = $this->getPluginDefinition();
    return Url::fromUserInput($plugin_definition['path']);
  }

  /**
   * {@inheritdoc}
   */
  public function isRenderedInCurrentRequest() {
    $plugin_definition = $this->getPluginDefinition();
    if (isset($plugin_definition['path'])) {
      $current_path = $this->getCurrentPath()->getPath();
      return $current_path == $plugin_definition['path'];
    }
    return FALSE;
  }

}
