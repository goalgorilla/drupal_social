<?php

namespace Drupal\message\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a MessagePurge annotation object.
 *
 * @Annotation
 */
class MessagePurge extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the plugin.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $label;

  /**
   * A short description of the plugin.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $description;

}
