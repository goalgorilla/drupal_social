<?php

namespace Drupal\search_api\ParseMode;

use Drupal\Core\Plugin\PluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a base class from which other parse mode classes may extend.
 *
 * Plugins extending this class need to define a plugin definition array through
 * annotation. These definition arrays may be altered through
 * hook_search_api_parse_mode_info_alter(). The definition includes the following
 * keys:
 * - id: The unique, system-wide identifier of the parse mode.
 * - label: The human-readable name of the parse mode class, translated.
 * - description: A human-readable description for the parse mode, translated.
 *
 * A complete plugin definition should be written as in this example:
 *
 * @code
 * @SearchApiParseMode(
 *   id = "my_parse_mode",
 *   label = @Translation("My parse mode"),
 *   description = @Translation("Some information about my parse mode"),
 * )
 * @endcode
 *
 * @see \Drupal\search_api\Annotation\SearchApiParseMode
 * @see \Drupal\search_api\ParseMode\ParseModePluginManager
 * @see \Drupal\search_api\ParseMode\ParseModeInterface
 * @see plugin_api
 */
abstract class ParseModePluginBase extends PluginBase implements ParseModeInterface {

  /**
   * The default conjunction to use when parsing keywords.
   *
   * @var string
   */
  protected $conjunction = 'AND';

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition);
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
  public function getConjunction() {
    return $this->conjunction;
  }

  /**
   * {@inheritdoc}
   */
  public function setConjunction($conjunction) {
    $this->conjunction = $conjunction;
    return $this;
  }

}
