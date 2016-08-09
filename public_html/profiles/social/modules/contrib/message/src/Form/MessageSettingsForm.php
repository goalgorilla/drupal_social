<?php

/**
 * @file
 *
 * Contains \Drupal\system\Form\FileSystemForm.
 */

namespace Drupal\message\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\message\MessagePurgePluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure file system settings for this site.
 */
class MessageSettingsForm extends ConfigFormBase {

  /**
   * The entity manager object.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   *
   * @todo Use the entity type manager service.
   */
  protected $entityManager;

  /**
   * The message purge plugin manager.
   *
   * @var \Drupal\message\MessagePurgePluginManager
   */
  protected $purgeManager;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'message_system_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['message.settings'];
  }

  /**
   * Holds the name of the keys we holds in the variable.
   */
  public function defaultKeys() {
    return [
      'delete_on_entity_delete',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity.manager'),
      $container->get('plugin.manager.message.purge')
    );
  }

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager object.
   * @param \Drupal\message\MessagePurgePluginManager $purge_manager
   *   The message purge plugin manager service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityManagerInterface $entity_manager, MessagePurgePluginManager $purge_manager) {
    parent::__construct($config_factory);
    $this->entityManager = $entity_manager;
    $this->purgeManager = $purge_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('message.settings');

    // Uses the same form keys as the MessageTemplateForm so that the purge
    // plugins form can be re-used.
    $form['settings'] = [
      '#type' => 'fieldset',
      '#title' => t('Purge settings'),
      '#tree' => TRUE,
    ];

    $form['settings']['purge_enable'] = [
      '#type' => 'checkbox',
      '#title' => t('Purge messages'),
      '#description' => t('Configure how messages will be deleted.'),
      '#default_value' => $config->get('purge_enable'),
    ];

    // Add the purge method settings form.
    $this->purgeManager->purgeSettingsForm($form, $form_state, $config->get('purge_methods'));

    $form['delete_on_entity_delete'] = [
      '#title' => t('Auto delete messages referencing the following entities'),
      '#type' => 'select',
      '#multiple' => TRUE,
      '#options' => $this->getContentEntityTypes(),
      '#default_value' => $config->get('delete_on_entity_delete'),
      '#description' => t('Messages that reference entities of these types will be deleted when the referenced entity gets deleted.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $config = $this->config('message.settings');

    foreach ($this->defaultKeys() as $key) {
      $config->set($key, $form_state->getValue($key));
    }

    $purge_enable = $form_state->getValue(['settings', 'purge_enable']);
    $config->set('purge_enable', $purge_enable);
    $config->set('purge_methods', $purge_enable ? $this->purgeManager->getPurgeConfiguration($form, $form_state) : []);

    $config->save();
  }

  /**
   * Get content entity types keyed by id.
   *
   * @return array
   *   Returns array of content entity types.
   */
  protected function getContentEntityTypes() {
    $options = [];
    foreach ($this->entityManager->getDefinitions() as $entity_id => $entity_type) {
      if ($entity_type instanceof ContentEntityTypeInterface) {
        $options[$entity_type->id()] = $entity_type->getLabel();
      }
    }
    return $options;
  }

}
