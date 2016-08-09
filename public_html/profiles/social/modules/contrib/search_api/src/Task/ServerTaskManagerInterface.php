<?php

namespace Drupal\search_api\Task;

use Drupal\search_api\ServerInterface;

/**
 * Defines the interface for the server task manager.
 */
interface ServerTaskManagerInterface {

  /**
   * Retrieves the number of pending server tasks.
   *
   * @param \Drupal\search_api\ServerInterface|null $server
   *   (optional) The server whose tasks should be counted, or NULL to count all
   *   tasks.
   *
   * @return int
   *   The number of tasks pending for this server, or in total.
   */
  public function getCount(ServerInterface $server = NULL);

  /**
   * Checks for pending tasks on one or all enabled search servers.
   *
   * @param \Drupal\search_api\ServerInterface|null $server
   *   (optional) The server whose tasks should be executed. If not given, the
   *   tasks for all enabled servers are executed.
   *
   * @return bool
   *   TRUE if all tasks (for the specific server, if $server was given) were
   *   executed successfully, or if there were no tasks. FALSE if there are
   *   still pending tasks.
   */
  public function execute(ServerInterface $server = NULL);

  /**
   * Sets a batch for executing server tasks.
   *
   * @param \Drupal\search_api\ServerInterface|null $server
   *   (optional) The server whose tasks should be executed. If not given, the
   *   tasks for all enabled servers are executed.
   */
  public function setExecuteBatch(ServerInterface $server = NULL);

  /**
   * Removes pending server tasks from the list.
   *
   * @param \Drupal\search_api\ServerInterface|null $server
   *   (optional) A server for which the tasks should be deleted. Set to NULL to
   *   delete tasks from all servers.
   * @param \Drupal\search_api\IndexInterface|string|null $index
   *   (optional) An index (or its ID) for which the tasks should be deleted.
   *   Set to NULL to delete tasks for all indexes.
   * @param string[]|null $types
   *   (optional) The types of tasks that should be deleted, or NULL to delete
   *   tasks regardless of type.
   */
  public function delete(ServerInterface $server = NULL, $index = NULL, array $types = NULL);

}
