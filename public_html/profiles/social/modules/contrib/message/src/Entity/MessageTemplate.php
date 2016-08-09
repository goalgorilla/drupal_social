<?php

/**
 * @file
 * Contains \Drupal\message\Entity\MessageTemplate.
 */

namespace Drupal\message\Entity;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Language\Language;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Drupal\message\MessageException;
use Drupal\message\MessageTemplateInterface;


/**
 * Defines the Message template entity class.
 *
 * @ConfigEntityType(
 *   id = "message_template",
 *   label = @Translation("Message template"),
 *   config_prefix = "template",
 *   bundle_of = "message",
 *   entity_keys = {
 *     "id" = "template",
 *     "label" = "label",
 *     "langcode" = "langcode",
 *   },
 *   admin_permission = "administer message templates",
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\message\Form\MessageTemplateForm",
 *       "edit" = "Drupal\message\Form\MessageTemplateForm",
 *       "delete" = "Drupal\message\Form\MessageTemplateDeleteConfirm"
 *     },
 *     "list_builder" = "Drupal\message\MessageTemplateListBuilder",
 *     "view_builder" = "Drupal\message\MessageViewBuilder",
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/message/template/add",
 *     "edit-form" = "/admin/structure/message/manage/{message_template}",
 *     "delete-form" = "/admin/structure/message/delete/{message_template}"
 *   }
 * )
 */
class MessageTemplate extends ConfigEntityBundleBase implements MessageTemplateInterface {

  /**
   * The ID of this message template.
   *
   * @var string
   */
  protected $template;

  /**
   * The UUID of the message template.
   *
   * @var string
   */
  protected $uuid;

  /**
   * The human-readable name of the message template.
   *
   * @var string
   */
  protected $label;

  /**
   * A brief description of this message template.
   *
   * @var string
   */
  protected $description;

  /**
   * The serialised text of the message template.
   *
   * @var array
   */
  protected $text = [];

  /**
   * Array with the arguments and their replacement value, or callacbks.
   *
   * The argument keys will be replaced when rendering the message, and it
   * should be prefixed by @, %, ! - similar to way it's done in Drupal
   * core's t() function.
   *
   * @code
   *
   * // Assuming out message-text is:
   * // %user-name created <a href="@message-url">@message-title</a>
   *
   * $message_template->arguments = [
   *   // Hard code the argument.
   *   '%user-name' => 'foo',
   *
   *   // Use a callback, and provide callbacks arguments.
   *   // The following example will call Drupal core's url() function to
   *   // get the most up-to-date path of message ID 1.
   *   '@message-url' => [
   *      'callback' => 'url',
   *      'callback arguments' => ['message/1'],
   *    ],
   *
   *   // Use callback, but instead of passing callback argument, we will
   *   // pass the Message entity itself.
   *   '@message-title' => [
   *      'callback' => 'example_bar',
   *      'pass message' => TRUE,
   *    ],
   * ];
   * @endcode
   *
   * Arguments assigned to message-template can be overridden by the ones
   * assigned to the message.
   *
   * @var array
   */
  public $arguments = [];

  /**
   * Serialized array with misc options.
   *
   * Purge settings:
   * - 'purge_override': TRUE or FALSE override the global behavior.
   *    "Message settings" will apply. Defaults to FALSE.
   * - 'purge_methods': An array of purge method plugin configuration, keyed by
   *   the plugin ID. An empty array indicates no purge is enabled (although
   *   global settings will be used unless 'purge_override' is TRUE).
   *
   * Token settings:
   * - 'token replace': Indicate if message's text should be passed
   *    through token_replace(). defaults to TRUE.
   * - 'token options': Array with options to be passed to
   *    token_replace().
   *
   * Tokens settings assigned to message-template can be overriden by the ones
   * assigned to the message.
   *
   * @var array
   */
  protected $settings = [];

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->template;
  }

  /**
   * {@inheritdoc}
   */
  public function setSettings(array $settings) {
    $this->settings = $settings;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings() {
    return $this->settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getSetting($key, $default_value = NULL) {
    if (isset($this->settings[$key])) {
      return $this->settings[$key];
    }

    return $default_value;
  }

  /**
   * {@inheritdoc}
   */
  public function setDescription($description) {
    $this->description = $description;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function setLabel($label) {
    $this->label = $label;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->label;
  }

  /**
   * {@inheritdoc}
   */
  public function setTemplate($template) {
    $this->template = $template;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTemplate() {
    return $this->template;
  }

  /**
   * {@inheritdoc}
   */
  public function setUuid($uuid) {
    $this->uuid = $uuid;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getUuid() {
    return $this->uuid;
  }

  /**
   * {@inheritdoc}
   */
  public function getText($langcode = Language::LANGCODE_NOT_SPECIFIED, $delta = NULL) {
    $text = $this->text;

    $language_manager = \Drupal::languageManager();
    if ($language_manager instanceof ConfigurableLanguageManagerInterface) {

      if ($langcode == Language::LANGCODE_NOT_SPECIFIED) {
        // Get the default language code when not specified.
        $langcode = $language_manager->getDefaultLanguage()->getId();
      }

      $config_translation = $language_manager->getLanguageConfigOverride($langcode, 'message.template.' . $this->id());
      $translated_text = $config_translation->get('text');

      // If there was no translated text, we return nothing instead of falling
      // back to the default language.
      $text = $translated_text ?: [];
    }

    if ($delta) {
      // Return just the delta if it exists.
      return !empty($text[$delta]) ?: '';
    }

    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function isLocked() {
    return !$this->isNew();
  }

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\message\MessageTemplateInterface
   *   A message template object ready to be save.
   */
  public static function create(array $values = []) {
    return parent::create($values);
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    $this->text = array_filter($this->text);

    $language_manager = \Drupal::languageManager();

    if ($language_manager instanceof ConfigurableLanguageManagerInterface) {
      // Set the values for the default site language.
      $config_translation = $language_manager->getLanguageConfigOverride($language_manager->getDefaultLanguage()->getId(), 'message.template.' . $this->id());
      $config_translation->set('text', $this->text);
      $config_translation->save();
    }

    parent::preSave($storage);
  }

}
