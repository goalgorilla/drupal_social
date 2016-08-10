<?php

namespace Drupal\search_api\Display;

use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Defines an interface for display plugins.
 *
 * @see \Drupal\search_api\Annotation\SearchApiDisplay
 * @see \Drupal\search_api\Display\DisplayPluginManager
 * @see \Drupal\search_api\Display\DisplayPluginBase
 * @see plugin_api
 */
interface DisplayInterface extends PluginInspectionInterface, DerivativeInspectionInterface, ContainerFactoryPluginInterface {

  /**
   * Returns the display label.
   *
   * @return string
   *   A human-readable label for the display.
   */
  public function label();

  /**
   * Returns the display description.
   *
   * @return string
   *   A human-readable description for the display.
   */
  public function getDescription();

  /**
   * Returns the index used by this display.
   *
   * @return \Drupal\search_api\IndexInterface
   *   The search index used by this display.
   */
  public function getIndex();

  /**
   * Returns the path used for this display.
   *
   * @return \Drupal\Core\Url
   *   The path of the display.
   */
  public function getPath();

  /**
   * Returns true if the display is being rendered in the current request.
   *
   * @return bool
   *   True when the display is rendered in the current request.
   */
  public function isRenderedInCurrentRequest();

}
