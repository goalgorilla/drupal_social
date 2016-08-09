<?php

namespace Drupal\message;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Plugin type manager for MessagePurge plugins.
 */
class MessagePurgePluginManager extends DefaultPluginManager {

  use StringTranslationTrait;

  /**
   * Constructs an MessagePurgePluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/MessagePurge', $namespaces, $module_handler, 'Drupal\message\MessagePurgeInterface', 'Drupal\message\Annotation\MessagePurge');
    $this->setCacheBackend($cache_backend, 'message_purge');
    $this->alterInfo('message_purge');
  }

  /**
   * Construct the purge method form.
   *
   * This can be used on either the message template form, or the global
   * message settings form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $purge_settings
   *   The default purge plugin settings to use.
   */
  public function purgeSettingsForm(array &$form, FormStateInterface $form_state, array $purge_settings) {

    // Loop through all purge plugins and add to form.
    $form['settings']['purge_methods'] = [
      '#type' => 'table',
      '#states' => [
        'visible' => [
          // Configure visibility for both the template form and the global
          // settings form.
          [
            [':input[name="settings[purge_override]"]' => ['checked' => TRUE]],
            'or',
            [':input[name="settings[purge_enable]"]' => ['checked' => TRUE]],
          ],
        ],
      ],
      '#theme_wrappers' => ['form_element'],
      '#header' => [
        $this->t('Purge method'),
        $this->t('Weight'),
        $this->t('Enabled'),
        $this->t('Settings'),
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'message-purge-order-weight',
        ],
      ],
    ];
    $user_input = $form_state->getUserInput();
    $definitions = $this->getDefinitions();
    $this->sortDefinitions($definitions, $purge_settings);
    foreach ($definitions as $plugin_id => $definition) {

      /** @var \Drupal\message\MessagePurgeInterface $plugin */
      $plugin = $this->createInstance($plugin_id, isset($purge_settings[$plugin_id]) ? $purge_settings[$plugin_id] : []);

      // Create the table row.
      $form['settings']['purge_methods'][$plugin_id]['#attributes']['class'][] = 'draggable';
      $form['settings']['purge_methods'][$plugin_id]['#weight'] = isset($user_input['settings']['purge_methods']) ? $user_input['settings']['purge_methods'][$plugin_id]['weight'] : $plugin->getWeight();
      $form['settings']['purge_methods'][$plugin_id]['plugin'] = [
        '#tree' => FALSE,
        'data' => [
          'label' => [
            '#plain_text' => $plugin->label(),
          ],
        ],
      ];

      // Purge weight.
      $form['settings']['purge_methods'][$plugin_id]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight for @title', ['@title' => $plugin->label()]),
        '#title_display' => 'invisible',
        '#default_value' => $plugin->getWeight(),
        '#attributes' => [
          'class' => ['message-purge-order-weight'],
        ],
      ];

      // Plugin enabled.
      $form['settings']['purge_methods'][$plugin_id]['enabled'] = [
        '#type' => 'checkbox',
        '#title' => $plugin->description(),
        '#default_value' => isset($purge_settings[$plugin_id]),
      ];

      // Purge plugin-specific settings.
      $element = [];
      $form['settings']['purge_methods'][$plugin_id]['data'] = $plugin->buildConfigurationForm($element, $form_state);
    }
  }

  /**
   * Gather the purge plugin settings on form submission.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The purge plugin configurations that are enabled, keyed by plugin ID.
   */
  public function getPurgeConfiguration(array $form, FormStateInterface $form_state) {
    $purge_plugins = [];
    foreach ($form_state->getValue(['settings', 'purge_methods']) as $plugin_id => $configuration) {
      if ($configuration['enabled']) {
        /** @var \Drupal\message\MessagePurgeInterface $plugin */
        $plugin = $this->createInstance($plugin_id, $configuration);
        $plugin->submitConfigurationForm($form, $form_state);
        $purge_plugins[$plugin_id] = $plugin->getConfiguration();
      }
    }
    return $purge_plugins;
  }

  /**
   * Sort plugin definitions based on plugin settings.
   */
  protected function sortDefinitions(array &$definitions, array $settings) {
    uasort($definitions, function ($a, $b) use ($settings) {
      $weight_a = isset($settings[$a['id']]) ? $settings[$a['id']]['weight'] : 0;
      $weight_b = isset($settings[$b['id']]) ? $settings[$b['id']]['weight'] : 0;
      if ($weight_a == $weight_b) {
        return 0;
      }
      return ($weight_a < $weight_b) ? -1 : 1;
    });
  }

}
