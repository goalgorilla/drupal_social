<?php

namespace Drupal\search_api\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\ServerInterface;
use Drupal\search_api\Task\ServerTaskManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for performing common actions on a server.
 */
class ServerStatusForm extends FormBase {

  /**
   * The server task manager.
   *
   * @var \Drupal\search_api\Task\ServerTaskManagerInterface|null
   */
  protected $serverTaskManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var static $form */
    $form = parent::create($container);

    $form->setServerTaskManager($container->get('search_api.server_task_manager'));

    return $form;
  }

  /**
   * Retrieves the server task manager.
   *
   * @return \Drupal\search_api\Task\ServerTaskManagerInterface
   *   The server task manager.
   */
  public function getServerTaskManager() {
    return $this->serverTaskManager ?: \Drupal::service('search_api.server_task_manager');
  }

  /**
   * Sets the server task manager.
   *
   * @param \Drupal\search_api\Task\ServerTaskManagerInterface $server_task_manager
   *   The new server task manager.
   *
   * @return $this
   */
  public function setServerTaskManager(ServerTaskManagerInterface $server_task_manager) {
    $this->serverTaskManager = $server_task_manager;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'search_api_server_status';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ServerInterface $server = NULL) {
    $form['#server'] = $server;

    $pending_tasks = $this->getServerTaskManager()->getCount($server);
    if ($pending_tasks) {
      $status = $this->formatPlural(
        $pending_tasks,
        'There is currently @count task pending for this server.',
        'There are currently @count tasks pending for this server.'
      );
      $form['tasks'] = array(
        '#type' => 'fieldset',
        '#title' => $this->t('Pending server tasks'),
      );
      $form['tasks']['help'] = array(
        '#type' => 'item',
        '#title' => $status,
        '#description' => $this->t('Pending tasks are created when operations on the server, such as deleting one or more items, cannot be executed because the server is currently unavailable (which will usually also create an entry in the Drupal logs). They are automatically tried again before any other operation is executed and the operation is aborted if the tasks could still not be executed, or if there are too many pending tasks to be executed in a single page request. In the latter case, you can use this form to manually execute all tasks and thus unblock the server again.'),
      );
      $form['tasks']['execute'] = array(
        '#type' => 'submit',
        '#value' => $this->t('Execute tasks now'),
        '#disabled' => !$server->isAvailable(),
        '#submit' => array('::executeTasks'),
      );
    }

    $form['actions']['#type'] = 'actions';
    $form['actions']['clear'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Delete all indexed data on this server'),
      '#button_type' => 'danger',
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function executeTasks(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\search_api\ServerInterface $server */
    $server = $form['#server'];
    $this->getServerTaskManager()->setExecuteBatch($server);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Redirect to the "Clear server" confirmation form.
    /** @var \Drupal\search_api\ServerInterface $server */
    $server = $form['#server'];
    $form_state->setRedirect('entity.search_api_server.clear', array('search_api_server' => $server->id()));
  }

}
