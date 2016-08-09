<?php

namespace Drupal\search_api\Task;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\ServerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides a service for managing pending tasks.
 *
 * Tasks are executed by this service by dispatching an event with the class
 * \Drupal\search_api\Task\TaskEvent and the name "search_api.task.TYPE", where
 * TYPE is the type of task. Any module wishing to employ the Search API task
 * system can therefore just create events of any type they want as long as they
 * have a subscriber listening to events with the corresponding name.
 *
 * Contrib modules should, however, always prefix TYPE with their module short
 * name, followed by a period, to avoid collisions.
 *
 * The system is used by the Search API module itself in the following ways:
 * - Keeping track of failed method calls on search servers (or, rather, their
 *   backends). See \Drupal\search_api\Task\ServerTaskManager.
 * - Moving the adding of items to an index's tracker to a batch operation when
 *   a new index is created or a new datasource enabled for an index. See
 *   \Drupal\search_api\Task\IndexTaskManager.
 * - For content entity datasources, to similarly add/remove items to/from
 *   tracking when a datasource's configuration changes. See
 *   \Drupal\search_api\Plugin\search_api\datasource\ContentEntityTaskManager.
 *   (Since this implements functionality for just one plugin, and not for the
 *   Search API in general, it uses the proper "search_api." prefix for the task
 *   type. Also, it should not be considered part of the framework.)
 *
 * @see \Drupal\search_api\Task\TaskEvent
 */
class TaskManager implements TaskManagerInterface {

  use DependencySerializationTrait;
  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Constructs a TaskManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   The string translation service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EventDispatcherInterface $event_dispatcher, TranslationInterface $translation) {
    $this->entityTypeManager = $entity_type_manager;
    $this->eventDispatcher = $event_dispatcher;
    $this->setStringTranslation($translation);
  }

  /**
   * Returns the entity storage for search tasks.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   The storage handler.
   */
  protected function getTaskStorage() {
    return $this->entityTypeManager->getStorage('search_api_task');
  }

  /**
   * Creates an entity query matching the given search tasks.
   *
   * @param array $conditions
   *   (optional) An array of conditions to be matched for the tasks, with
   *   property names keyed to the value (or values, for multiple possibilities)
   *   that the property should have.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   An entity query for search tasks.
   */
  protected function getTasksQuery(array $conditions = array()) {
    $query = $this->getTaskStorage()->getQuery();
    foreach ($conditions as $property => $values) {
      $query->condition($property, $values, is_array($values) ? 'IN' : '=');
    }
    $query->sort('id');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function getTasksCount(array $conditions = array()) {
    return $this->getTasksQuery($conditions)->count()->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function addTask($type, ServerInterface $server = NULL, IndexInterface $index = NULL, $data = NULL) {
    $task = $this->getTaskStorage()->create(array(
      'type' => $type,
      'server_id' => $server ? $server->id() : NULL,
      'index_id' => $index ? $index->id() : NULL,
      'data' => isset($data) ? serialize($data) : NULL,
    ));
    $task->save();
    return $task;
  }

  /**
   * {@inheritdoc}
   */
  public function loadTasks(array $conditions = array()) {
    $task_ids = $this->getTasksQuery($conditions)->execute();
    if ($task_ids) {
      return $this->getTaskStorage()->loadMultiple($task_ids);
    }
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteTask($task_id) {
    $task = $this->getTaskStorage()->load($task_id);
    if ($task) {
      $task->delete();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteTasks(array $conditions = array()) {
    $storage = $this->getTaskStorage();
    while (TRUE) {
      $task_ids = $this->getTasksQuery($conditions)
        ->range(0, 100)
        ->execute();
      if (!$task_ids) {
        break;
      }
      $tasks = $storage->loadMultiple($task_ids);
      $storage->delete($tasks);
      if (count($task_ids) < 100) {
        break;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function executeSpecificTask(TaskInterface $task) {
    $event = new TaskEvent($task);
    $this->eventDispatcher->dispatch('search_api.task.' . $task->getType(), $event);
    if (!$event->isPropagationStopped()) {
      $id = $task->id();
      $type = $task->getType();
      throw new SearchApiException("Could not execute task #$id of type '$type'. Type seems to be unknown.");
    }
    if ($exception = $event->getException()) {
      throw $exception;
    }
    $task->delete();
  }

  /**
   * {@inheritdoc}
   */
  public function executeSingleTask(array $conditions = array()) {
    $task_id = $this->getTasksQuery($conditions)->range(0, 1)->execute();
    if ($task_id) {
      $task_id = reset($task_id);
      /** @var \Drupal\search_api\Task\TaskInterface $task */
      $task = $this->getTaskStorage()->load($task_id);
      $this->executeSpecificTask($task);
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function executeAllTasks(array $conditions = array(), $limit = NULL) {
    // We have to use this roundabout way because tasks, during their execution,
    // might create additional tasks. (E.g., see
    // \Drupal\search_api\Task\IndexTaskManager::trackItems().)
    $executed = 0;
    while (TRUE) {
      $query = $this->getTasksQuery($conditions);
      if (isset($limit)) {
        $query->range(0, $limit - $executed);
      }
      $task_ids = $query->execute();

      if (!$task_ids) {
        break;
      }

      // We can't use multi-load here as a task might delete other tasks, so we
      // have to make sure each tasks still exists right before it is executed.
      foreach ($task_ids as $task_id) {
        /** @var \Drupal\search_api\Task\TaskInterface $task */
        $task = $this->getTaskStorage()->load($task_id);
        if ($task) {
          $this->executeSpecificTask($task);
        }
        else {
          --$executed;
        }
      }

      $executed += count($task_ids);
      if (isset($limit) && $executed >= $limit) {
        break;
      }
    }

    return !$this->getTasksCount($conditions);
  }

  /**
   * {@inheritdoc}
   */
  public function setTasksBatch(array $conditions = array()) {
    $task_ids = $this->getTasksQuery($conditions)->range(0, 100)->execute();

    if (!$task_ids) {
      return;
    }

    $batch_definition = array(
      'operations' => array(
        array(array($this, 'processBatch'), array($task_ids, $conditions)),
      ),
      'finished' => array($this, 'finishBatch'),
    );
    // Schedule the batch.
    batch_set($batch_definition);
  }

  /**
   * Processes a single pending task as part of a batch operation.
   *
   * @param int[] $task_ids
   *   An array of task IDs to execute. Might not contain all task IDs.
   * @param array $conditions
   *   An array of conditions defining the tasks to be executed. Should be used
   *   to retrieve more task IDs if necessary.
   * @param array $context
   *   The current batch context, as defined in the @link batch Batch operations
   *   @endlink documentation.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if any error occurred while processing the task.
   */
  public function processBatch(array $task_ids, array $conditions, array &$context) {
    // Initialize context information.
    if (!isset($context['sandbox']['task_ids'])) {
      $context['sandbox']['task_ids'] = $task_ids;
      $context['results']['total'] = $this->getTasksCount($conditions);
    }

    $task_id = array_shift($context['sandbox']['task_ids']);
    /** @var \Drupal\search_api\Task\TaskInterface $task */
    $task = $this->getTaskStorage()->load($task_id);

    if ($task) {
      $this->executeSpecificTask($task);
    }

    if (!$context['sandbox']['task_ids']) {
      $context['sandbox']['task_ids'] = $this->getTasksQuery($conditions)
        ->range(0, 100)
        ->execute();
      if (!$context['sandbox']['task_ids']) {
        $context['finished'] = 1;
        return;
      }
    }

    $pending = $this->getTasksCount($conditions);
    $context['finished'] = 1 - $pending / $context['results']['total'];
    $executed = $context['results']['total'] - $pending;
    $context['message'] = $this->formatPlural(
      $executed,
      'Successfully executed @count pending task.',
      'Successfully executed @count pending tasks.'
    );
  }

  /**
   * Finishes an "execute tasks" batch.
   *
   * @param bool $success
   *   Indicates whether the batch process was successful.
   * @param array $results
   *   Results information passed from the processing callback.
   */
  public function finishBatch($success, array $results) {
    // Check if the batch job was successful.
    if ($success) {
      $message = $this->formatPlural(
        $results['total'],
        'Successfully executed @count pending task.',
        'Successfully executed @count pending tasks.'
      );
      drupal_set_message($message);
    }
    else {
      // Notify the user about the batch job failure.
      drupal_set_message($this->t('An error occurred while trying to execute tasks. Check the logs for details.'), 'error');
    }
  }

}
