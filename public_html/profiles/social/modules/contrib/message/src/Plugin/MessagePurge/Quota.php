<?php

namespace Drupal\message\Plugin\MessagePurge;

use Drupal\Core\Form\FormStateInterface;
use Drupal\message\MessagePurgeBase;
use Drupal\message\MessageTemplateInterface;

/**
 * Maximal (approximate) amount of messages.
 *
 * @MessagePurge(
 *   id = "quota",
 *   label = @Translation("Quota", context = "MessagePurge"),
 *   description = @Translation("Maximal (approximate) amount of messages to keep."),
 * )
 */
class Quota extends MessagePurgeBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['quota'] = [
      '#type' => 'number',
      '#min' => 1,
      '#title' => t('Messages quota'),
      '#description' => t('Maximal (approximate) amount of messages.'),
      '#default_value' => $this->configuration['quota'],
      '#tree' => FALSE,
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['quota'] = $form_state->getValue('quota');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'quota' => 1000,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function fetch(MessageTemplateInterface $template, $limit) {
    $query = $this->baseQuery($template);
    $result = $query
      ->range($this->configuration['quota'], $limit)
      ->execute();
    return $result;
  }

}
