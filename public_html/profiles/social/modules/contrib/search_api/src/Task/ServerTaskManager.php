<?php

namespace Drupal\search_api\Task;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\ServerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a service for managing pending server tasks.
 */
class ServerTaskManager implements ServerTaskManagerInterface, EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The Search API task manager.
   *
   * @var \Drupal\search_api\Task\TaskManagerInterface
   */
  protected $taskManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a ServerTaskManager object.
   *
   * @param \Drupal\search_api\Task\TaskManagerInterface $task_manager
   *   The Search API task manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(TaskManagerInterface $task_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->taskManager = $task_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = array();

    foreach (static::getSupportedTypes() as $type) {
      $events['search_api.task.' . $type][] = array('processEvent');
    }

    return $events;
  }

  /**
   * Retrieves the task types supported by this task manager.
   *
   * @return string[]
   *   The task types supported by this task manager.
   */
  protected static function getSupportedTypes() {
    return array(
      'addIndex',
      'updateIndex',
      'removeIndex',
      'deleteItems',
      'deleteAllIndexItems',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCount(ServerInterface $server = NULL) {
    return $this->taskManager->getTasksCount($this->getTaskConditions($server));
  }

  /**
   * {@inheritdoc}
   */
  public function execute(ServerInterface $server = NULL) {
    if ($server && !$server->status()) {
      return FALSE;
    }

    $conditions = $this->getTaskConditions($server);
    try {
      return $this->taskManager->executeAllTasks($conditions, 100);
    }
    catch (SearchApiException $e) {
      watchdog_exception('search_api', $e);
      return FALSE;
    }
  }

  /**
   * Processes a single server task.
   *
   * @param \Drupal\search_api\Task\TaskEvent $event
   *   The task event.
   */
  public function processEvent(TaskEvent $event) {
    $event->stopPropagation();

    $task = $event->getTask();

    try {
      if (!$this->executeTask($task)) {
        $type = $task->getType();
        throw new SearchApiException("Task of unknown type '$type' passed to server task manager.");
      }
    }
    catch (SearchApiException $e) {
      $event->setException($e);
    }
  }

  /**
   * Executes a single server task.
   *
   * @param \Drupal\search_api\Task\TaskInterface $task
   *   The task to execute.
   *
   * @return bool
   *   TRUE if the task was successfully executed, FALSE if the task type was
   *   unknown.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   If any error occurred while executing the task.
   */
  protected function executeTask(TaskInterface $task) {
    $server = $task->getServer();
    $index = $task->getIndex();
    $data = $task->getData();

    switch ($task->getType()) {
      case 'addIndex':
        if ($index) {
          $server->getBackend()->addIndex($index);
        }
        return TRUE;

      case 'updateIndex':
        if ($index) {
          if ($data) {
            $index->original = $data;
          }
          $server->getBackend()->updateIndex($index);
        }
        return TRUE;

      case 'removeIndex':
        $index = $index ?: $data;
        if ($index) {
          $server->getBackend()->removeIndex($index);
        }
        return TRUE;

      case 'deleteItems':
        if ($index && !$index->isReadOnly()) {
          $server->getBackend()->deleteItems($index, $data);
        }
        return TRUE;

      case 'deleteAllIndexItems':
        if ($index && !$index->isReadOnly()) {
          $server->getBackend()->deleteAllIndexItems($index, $data);
        }
        return TRUE;
    }

    // We didn't know that type of task.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function setExecuteBatch(ServerInterface $server = NULL) {
    $this->taskManager->setTasksBatch($this->getTaskConditions($server));
  }

  /**
   * {@inheritdoc}
   */
  public function delete(ServerInterface $server = NULL, $index = NULL, array $types = NULL) {
    $conditions = $this->getTaskConditions($server);
    if ($index !== NULL) {
      $conditions['index_id'] = $index instanceof IndexInterface ? $index->id() : $index;
    }
    if ($types !== NULL) {
      $conditions['type'] = $types;
    }
    $this->taskManager->deleteTasks($conditions);
  }

  /**
   * Gets a set of conditions for finding the tracking tasks of the given index.
   *
   * @param \Drupal\search_api\ServerInterface $server
   *   The server for which to retrieve tasks.
   *
   * @return array
   *   An array of conditions to pass to the Search API task manager.
   */
  protected function getTaskConditions(ServerInterface $server = NULL) {
    $conditions['type'] = static::getSupportedTypes();
    if ($server) {
      $conditions['server_id'] = $server->id();
    }
    return $conditions;
  }

}
