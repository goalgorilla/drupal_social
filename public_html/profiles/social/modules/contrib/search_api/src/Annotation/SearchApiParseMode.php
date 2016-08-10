<?php

namespace Drupal\search_api\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Search API parse mode annotation object.
 *
 * @see \Drupal\search_api\ParseMode\ParseModePluginManager
 * @see \Drupal\search_api\ParseMode\ParseModeInterface
 * @see \Drupal\search_api\ParseMode\ParseModePluginBase
 * @see plugin_api
 *
 * @Annotation
 */
class SearchApiParseMode extends Plugin {

  /**
   * The parse mode plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the parse mode.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $label;

  /**
   * The description of the parse mode.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $description;

}
