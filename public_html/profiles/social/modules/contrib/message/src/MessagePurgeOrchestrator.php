<?php

namespace Drupal\message;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Purges messages from the system based on global and template configurations.
 */
class MessagePurgeOrchestrator {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The global message configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $globalConfig;

  /**
   * The purge method plugin manager.
   *
   * @var \Drupal\message\MessagePurgePluginManager
   */
  protected $purgeManager;

  /**
   * Constructs the purging service.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\message\MessagePurgePluginManager $purge_manager
   *   The purge plugin manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, MessagePurgePluginManager $purge_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->globalConfig = $config_factory->get('message.settings');
    $this->purgeManager = $purge_manager;
  }

  /**
   * Purgers all messages for all templates as configured.
   *
   * Messages set to ignore global purge settings will use their own
   * configuration, otherwise global settings will be used.
   */
  public function purgeAllTemplateMessages() {
    // The maximal amount of messages to purge per cron run.
    $purge_limit = $this->globalConfig->get('delete_cron_limit');

    // Names of non global-purge-settings overriding message templates.
    /** @var \Drupal\message\MessageTemplateInterface[] $no_override_templates */
    $no_override_templates = [];
    // Message templates that override global purge settings.
    /** @var \Drupal\message\MessageTemplateInterface[] $override_templates */
    $override_templates = [];

    // Iterate all message templates to distinguish between overriding and non-
    // overriding templates.
    /** @var \Drupal\message\MessageTemplateInterface[] $message_templates */
    $message_templates = $this->entityTypeManager->getStorage('message_template')->loadMultiple();
    foreach ($message_templates as $message_template) {
      if (!$message_template->getSetting('purge_override')) {
        $no_override_templates[] = $message_template;
      }
      else {
        $override_templates[] = $message_template;
      }
    }

    // Purge messages of templates overriding the global settings.
    foreach ($override_templates as $message_template) {
      $settings = $message_template->getSetting('purge_methods');
      if (empty($settings)) {
        // Ignore messages that have no enabled purge methods.
        continue;
      }
      $this->purgeMessagesByTemplate($purge_limit, $message_template, $settings);
    }

    // Purge messages for templates that are not overriding global settings.
    if (!empty($no_override_templates)) {
      // Only purge if globally enabled.
      if ($this->globalConfig->get('purge_enable')) {
        foreach ($no_override_templates as $message_template) {
          $this->purgeMessagesByTemplate($purge_limit, $message_template, $this->globalConfig->get('purge_methods'));
        }
      }
    }
  }

  /**
   * Find and purge messages according to template and purge settings.
   *
   * @param $purge_limit
   *   The maximal amount of messages to fetch. Decremented each time messages
   *   are fetched.
   * @param \Drupal\message\MessageTemplateInterface $message_template
   *   The message template for which to retrieve message IDs.
   * @param $purge_plugins
   *   Array of purge plugin configurations, keyed by plugin ID.
   */
  protected function purgeMessagesByTemplate(&$purge_limit, MessageTemplateInterface $message_template, array $purge_plugins) {
    foreach ($purge_plugins as $plugin_id => $configuration) {
      // Return early if limit has been hit.
      if ($purge_limit <= 0) {
        return;
      }

      /** @var \Drupal\message\MessagePurgeInterface $plugin */
      $plugin = $this->purgeManager->createInstance($plugin_id, $configuration);
      $message_ids = $plugin->fetch($message_template, $purge_limit);

      // Decrease the limit by the number of messages found.
      $purge_limit -= count($message_ids);
      $plugin->process($message_ids);
    }
  }

}
