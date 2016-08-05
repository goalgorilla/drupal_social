<?php

namespace Drupal\search_api\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\search_api\Task\ServerTaskManagerInterface;

/**
 * Provides an access check for the "Execute server tasks" route.
 */
class ExecuteTasksAccessCheck implements AccessInterface {

  /**
   * The server tasks manager service.
   *
   * @var \Drupal\search_api\Task\ServerTaskManagerInterface
   */
  protected $serverTasksManager;

  /**
   * Creates an ExecuteTasksAccessCheck object.
   *
   * @param \Drupal\search_api\Task\ServerTaskManagerInterface $serverTasksManager
   */
  public function __construct(ServerTaskManagerInterface $serverTasksManager) {
    $this->serverTasksManager = $serverTasksManager;
  }

  /**
   * Checks access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access() {
    // @todo Once #2722237 is fixed, see whether this can't just use the
    //   "search_api_task_list" cache tag instead.
    if ($this->serverTasksManager->getCount()) {
      return AccessResult::allowed()->setCacheMaxAge(0);
    }
    return AccessResult::forbidden()->setCacheMaxAge(0);
  }

}
