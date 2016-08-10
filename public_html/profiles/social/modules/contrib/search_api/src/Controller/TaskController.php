<?php

namespace Drupal\search_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\search_api\Task\TaskManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for task-related routes.
 */
class TaskController extends ControllerBase {

  /**
   * The server task manager.
   *
   * @var \Drupal\search_api\Task\TaskManagerInterface|null
   */
  protected $taskManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var static $controller */
    $controller = parent::create($container);

    $controller->setTaskManager($container->get('search_api.task_manager'));

    return $controller;
  }

  /**
   * Retrieves the task manager.
   *
   * @return \Drupal\search_api\Task\TaskManagerInterface
   *   The task manager.
   */
  public function getTaskManager() {
    return $this->taskManager ?: \Drupal::service('search_api._task_manager');
  }

  /**
   * Sets the task manager.
   *
   * @param \Drupal\search_api\Task\TaskManagerInterface $task_manager
   *   The new task manager.
   *
   * @return $this
   */
  public function setTaskManager(TaskManagerInterface $task_manager) {
    $this->taskManager = $task_manager;
    return $this;
  }

  /**
   * Executes all pending tasks.
   */
  public function executeTasks() {
    $this->getTaskManager()->setTasksBatch();
    return batch_process(Url::fromRoute('search_api.overview'));
  }

}
