<?php

/**
 * @file
 * Contains \Drupal\message\MessageTemplateInterface.
 */

namespace Drupal\message;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Language\Language;

/**
 * Provides an interface defining a Message template entity.
 */
interface MessageTemplateInterface extends ConfigEntityInterface {


  /**
   * Set the message template description.
   *
   * @param string $description
   *   Description for the message template.
   *
   * @return \Drupal\message\MessageTemplateInterface
   *   Returns the message template instance.
   */
  public function setDescription($description);

  /**
   * Get the message template description.
   *
   * @return string
   *   Returns the message template description.
   */
  public function getDescription();

  /**
   * Set the message template label.
   *
   * @param string $label
   *   The message template label.
   *
   * @return \Drupal\message\MessageTemplateInterface
   *   Returns the message template instance.
   */
  public function setLabel($label);

  /**
   * Get the message template label.
   *
   * @return string
   *   Returns the message template label.
   */
  public function getLabel();

  /**
   * Set the message template.
   *
   * @param string $template
   *   The message template.
   *
   * @return \Drupal\message\MessageTemplateInterface
   *   Returns the message template instance.
   */
  public function setTemplate($template);

  /**
   * Get the message template.
   *
   * @return string
   *   Returns the message template.
   */
  public function getTemplate();

  /**
   * Set the UUID.
   *
   * @param string $uuid
   *   The UUID.
   *
   * @return \Drupal\message\MessageTemplateInterface
   *   Returns the message template instance.
   */
  public function setUuid($uuid);

  /**
   * Get the UUID.
   *
   * @return string
   *   Returns the UUID.
   */
  public function getUuid();

  /**
   * Retrieves the configured message text in a certain language.
   *
   * @param string $langcode
   *   The language code of the Message text field, the text should be
   *   extracted from.
   * @param int $delta
   *   Optional; Represents the partial number. If not provided - all partials
   *   will be returned.
   *
   * @return array
   *   An array of the text field values.
   */
  public function getText($langcode = Language::LANGCODE_NOT_SPECIFIED, $delta = NULL);

  /**
   * Set additional settings for the message template.
   */
  public function setSettings(array $settings);

  /**
   * Return the message template settings.
   *
   * @return array
   *   Array of the message template settings.
   */
  public function getSettings();

  /**
   * Return a single setting by key.
   *
   * @param string $key
   *   The key to return.
   * @param mixed $default_value
   *   The default value to use in case the key is missing. Defaults to NULL.
   *
   * @return mixed
   *   The value of the setting or the default value if none found.
   */
  public function getSetting($key, $default_value = NULL);

  /**
   * Check if the message is new.
   *
   * @return bool
   *   Returns TRUE is the message is new.
   */
  public function isLocked();

}
