<?php

namespace Drupal\search_api\ParseMode;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Manages parse mode plugins.
 *
 * @see \Drupal\search_api\Annotation\SearchApiParseMode
 * @see \Drupal\search_api\ParseMode\ParseModeInterface
 * @see \Drupal\search_api\ParseMode\ParseModePluginBase
 * @see plugin_api
 */
class ParseModePluginManager extends DefaultPluginManager {

  /**
   * Static cache for the parse mode definitions.
   *
   * @var \Drupal\search_api\ParseMode\ParseModeInterface[]
   *
   * @see \Drupal\search_api\ParseMode\ParseModePluginManager::createInstance()
   * @see \Drupal\search_api\ParseMode\ParseModePluginManager::getInstances()
   */
  protected $parseModes;

  /**
   * Whether all plugin instances have already been created.
   *
   * @var bool
   *
   * @see \Drupal\search_api\ParseMode\ParseModePluginManager::getInstances()
   */
  protected $allCreated = FALSE;

  /**
   * Constructs a ParseModePluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/search_api/parse_mode', $namespaces, $module_handler, 'Drupal\search_api\ParseMode\ParseModeInterface', 'Drupal\search_api\Annotation\SearchApiParseMode');

    $this->setCacheBackend($cache_backend, 'search_api_parse_mode');
    $this->alterInfo('search_api_parse_mode_info');
  }

  /**
   * Creates or retrieves a parse mode plugin.
   *
   * @param string $plugin_id
   *   The ID of the plugin being instantiated.
   * @param array $configuration
   *   (optional) An array of configuration relevant to the plugin instance.
   *   Ignored for parse mode plugins.
   *
   * @return \Drupal\search_api\ParseMode\ParseModeInterface
   *   The requested parse mode plugin.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If the instance cannot be created, such as if the ID is invalid.
   */
  public function createInstance($plugin_id, array $configuration = array()) {
    if (empty($this->parseModes[$plugin_id])) {
      $this->parseModes[$plugin_id] = parent::createInstance($plugin_id, $configuration);
    }
    return $this->parseModes[$plugin_id];
  }

  /**
   * Returns all known parse modes.
   *
   * @return \Drupal\search_api\ParseMode\ParseModeInterface[]
   *   An array of parse mode plugins, keyed by type identifier.
   */
  public function getInstances() {
    if (!$this->allCreated) {
      $this->allCreated = TRUE;
      if (!isset($this->parseModes)) {
        $this->parseModes = array();
      }

      foreach ($this->getDefinitions() as $plugin_id => $definition) {
        if (class_exists($definition['class']) && empty($this->parseModes[$plugin_id])) {
          $parse_mode = $this->createInstance($plugin_id);
          $this->parseModes[$plugin_id] = $parse_mode;
        }
      }
    }

    return $this->parseModes;
  }

  /**
   * Returns all parse modes known by the Search API as an options list.
   *
   * @return string[]
   *   An associative array with all parse mode's IDs as keys, mapped to their
   *   translated labels.
   *
   * @see \Drupal\search_api\ParseMode\ParseModePluginManager::getInstances()
   */
  public function getInstancesOptions() {
    $parse_modes = array();
    foreach ($this->getInstances() as $id => $info) {
      $parse_modes[$id] = $info->label();
    }
    return $parse_modes;
  }

}
