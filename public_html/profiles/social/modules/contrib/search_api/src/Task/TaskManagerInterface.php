<?php

namespace Drupal\search_api\Task;

use Drupal\search_api\IndexInterface;
use Drupal\search_api\ServerInterface;

/**
 * Defines the interface of the Search API task manager service.
 */
interface TaskManagerInterface {

  /**
   * Retrieves the number of pending tasks for the given conditions.
   *
   * @param array $conditions
   *   (optional) An array of conditions to be matched for the tasks, with
   *   property names mapped to the value (or values, for multiple
   *   possibilities) that the property should have.
   *
   * @return int
   *   The number of pending tasks matching the conditions.
   */
  public function getTasksCount(array $conditions = array());

  /**
   * Adds a new pending task.
   *
   * @param string $type
   *   The type of task.
   * @param \Drupal\search_api\ServerInterface|null $server
   *   (optional) The search server associated with the task, if any.
   * @param \Drupal\search_api\IndexInterface|null $index
   *   (optional) The search index associated with the task, if any.
   * @param mixed|null $data
   *   (optional) Additional, type-specific data to save with the task.
   *
   * @return \Drupal\search_api\Task\TaskInterface
   *   The new task.
   */
  public function addTask($type, ServerInterface $server = NULL, IndexInterface $index = NULL, $data = NULL);

  /**
   * Load all tasks matching the given conditions.
   *
   * @param array $conditions
   *   (optional) An array of conditions to be matched for the tasks, with
   *   property names mapped to the value (or values, for multiple
   *   possibilities) that the property should have.
   *
   * @return \Drupal\search_api\Task\TaskInterface[]
   *   The loaded tasks, keyed by task ID.
   */
  public function loadTasks(array $conditions = array());

  /**
   * Deletes the task with the given ID.
   *
   * @param int $task_id
   *   The task's ID.
   */
  public function deleteTask($task_id);

  /**
   * Deletes all tasks that fulfil a certain set of conditions.
   *
   * @param array $conditions
   *   (optional) An array of conditions defining which tasks should be deleted.
   *   The structure is an associative array with property names mapped to the
   *   value (or values, for multiple possibilities) that the property should
   *   have. Leave empty to delete all pending tasks.
   */
  public function deleteTasks(array $conditions = array());

  /**
   * Executes and deletes the given task.
   *
   * @param \Drupal\search_api\Task\TaskInterface $task
   *   The task to execute.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if any error occurred while processing the task.
   */
  public function executeSpecificTask(TaskInterface $task);

  /**
   * Retrieves and executes a single task.
   *
   * @param array $conditions
   *   (optional) An array of conditions to be matched for the task, with
   *   property names mapped to the value (or values, for multiple
   *   possibilities) that the property should have.
   *
   * @return bool
   *   TRUE if a task was successfully executed, FALSE if there was no matching
   *   task.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if any error occurred while processing the task.
   */
  public function executeSingleTask(array $conditions = array());

  /**
   * Executes all (or some) pending tasks.
   *
   * @param array $conditions
   *   (optional) An array of conditions to be matched for the tasks, with
   *   property names mapped to the value (or values, for multiple
   *   possibilities) that the property should have.
   * @param int|null $limit
   *   (optional) If given, only this number of tasks will be executed.
   *
   * @return bool
   *   TRUE if all tasks matching the conditions have been executed, FALSE if
   *   $limit was given and lower than the total count of pending tasks matching
   *   the conditions.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if any error occurred while processing a task.
   */
  public function executeAllTasks(array $conditions = array(), $limit = NULL);

  /**
   * Sets a batch for executing all pending tasks.
   *
   * @param array $conditions
   *   (optional) An array of conditions to be matched for the tasks, with
   *   property names mapped to the value (or values, for multiple
   *   possibilities) that the property should have.
   */
  public function setTasksBatch(array $conditions = array());

}
