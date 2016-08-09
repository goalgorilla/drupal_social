<?php

/**
 * @file
 * Contains \Drupal\message\MessageTemplateForm.
 */

namespace Drupal\message\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\message\FormElement\MessageTemplateMultipleTextField;
use Drupal\message\MessagePurgePluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for node type forms.
 */
class MessageTemplateForm extends EntityForm {

  /**
   * The entity being used by this form.
   *
   * @var \Drupal\message\Entity\MessageTemplate
   */
  protected $entity;

  /**
   * The purge plugin manager.
   *
   * @var \Drupal\message\MessagePurgePluginManager
   */
  protected $purgeManager;

  /**
   * Constructs the message template form.
   *
   * @param \Drupal\message\MessagePurgePluginManager $purge_manager
   *   The message purge plugin manager service.
   */
  public function __construct(MessagePurgePluginManager $purge_manager) {
    $this->purgeManager = $purge_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.message.purge')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\message\Entity\MessageTemplate $template */
    $template = $this->entity;

    $form['label'] = [
      '#title' => $this->t('Label'),
      '#type' => 'textfield',
      '#default_value' => $template->label(),
      '#description' => $this->t('The human-readable name of this message template. This text will be displayed as part of the list on the <em>Add message</em> page. It is recommended that this name begin with a capital letter and contain only letters, numbers, and spaces. This name must be unique.'),
      '#required' => TRUE,
      '#size' => 30,
    ];

    $form['template'] = [
      '#type' => 'machine_name',
      '#default_value' => $template->id(),
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      '#disabled' => $template->isLocked(),
      '#machine_name' => [
        'exists' => '\Drupal\message\Entity\MessageTemplate::load',
        'source' => ['label'],
      ],
      '#description' => $this->t('A unique machine-readable name for this message template. It must only contain lowercase letters, numbers, and underscores. This name will be used for constructing the URL of the %message-add page, in which underscores will be converted into hyphens.', [
        '%message-add' => $this->t('Add message'),
      ]),
    ];

    $form['description'] = [
      '#title' => $this->t('Description'),
      '#type' => 'textfield',
      '#default_value' => $this->entity->getDescription(),
      '#description' => $this->t('The human-readable description of this message template.'),
    ];

    $multiple = new MessageTemplateMultipleTextField($this->entity, [get_class($this), 'addMoreAjax']);
    $multiple->textField($form, $form_state);

    $settings = $this->entity->getSettings();

    $form['settings'] = [
      // Placeholder for other module to add their settings, that should be
      // added to the settings column.
      '#tree' => TRUE,
    ];

    $form['settings']['token options']['clear'] = [
      '#title' => $this->t('Clear empty tokens'),
      '#type' => 'checkbox',
      '#description' => $this->t('When this option is selected, empty tokens will be removed from display.'),
      '#default_value' => isset($settings['token options']['clear']) ? $settings['token options']['clear'] : FALSE,
    ];

    $form['settings']['token options']['token replace'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Token replace'),
      '#description' => $this->t('When this option is selected, token processing will happen.'),
      '#default_value' => !isset($settings['token options']['token replace']) || !empty($settings['token options']['token replace']),
    );

    $form['settings']['purge_override'] = [
      '#title' => $this->t('Override global purge settings'),
      '#type' => 'checkbox',
      '#description' => $this->t('Override <a href=":settings">global purge settings</a> for messages using this template.', [':settings' => Url::fromRoute('message.settings')->toString()]),
      '#default_value' => $this->entity->getSetting('purge_override'),
    ];

    // Add the purge method settings form.
    $settings = $this->entity->getSetting('purge_methods', []);
    $this->purgeManager->purgeSettingsForm($form, $form_state, $settings);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = t('Save message template');
    $actions['delete']['#value'] = t('Delete message template');
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function validate(array $form, FormStateInterface $form_state) {
    parent::validate($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    // Save only the enabled purge methods if overriding the global settings.
    $override = $form_state->getValue(['settings', 'purge_override']);
    $settings = $this->entity->getSettings();
    $settings['purge_methods'] = $override ? $this->purgeManager->getPurgeConfiguration($form, $form_state) : [];
    $this->entity->setSettings($settings);
  }

  /**
   * Ajax callback for the "Add another item" button.
   *
   * This returns the new page content to replace the page content made obsolete
   * by the form submission.
   */
  public static function addMoreAjax(array $form, FormStateInterface $form_state) {
    return $form['text'];
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $values = $form_state->getValue('text');
    usort($values, 'message_order_text_weight');

    // Saving the message text values.
    foreach ($values as $key => $value) {
      $values[$key] = $value['value'];
    }

    $this->entity->set('text', $values);
    $this->entity->save();

    $params = [
      '@template' => $form_state->getValue('label'),
    ];

    drupal_set_message(t('The message template @template created successfully.', $params));
    $form_state->setRedirect('message.overview_templates');
    return $this->entity;
  }

}
