<?php

namespace Drupal\search_api_test_tasks;

use Drupal\search_api\SearchApiException;
use Drupal\search_api\Task\TaskEvent;
use Drupal\search_api\Task\TaskManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a task worker for testing purposes.
 */
class TestTaskWorker implements EventSubscriberInterface {

  /**
   * The Search API task manager.
   *
   * @var \Drupal\search_api\Task\TaskManagerInterface
   */
  protected $taskManager;

  /**
   * Log for all received tasks.
   *
   * @var string[][]
   */
  protected $eventLog = array();

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events['search_api.task.search_api_test_tasks.success'][] = array('success');
    $events['search_api.task.search_api_test_tasks.fail'][] = array('fail');
    $events['search_api.task.search_api_test_tasks.ignore'][] = array('ignore');

    return $events;
  }

  /**
   * Constructs an IndexTaskManager object.
   *
   * @param \Drupal\search_api\Task\TaskManagerInterface $task_manager
   *   The Search API task manager.
   */
  public function __construct(TaskManagerInterface $task_manager) {
    $this->taskManager = $task_manager;
  }

  /**
   * Handles a task event successfully.
   *
   * @param \Drupal\search_api\Task\TaskEvent $event
   *   The task event.
   */
  public function success(TaskEvent $event) {
    $this->logEvent($event);
    $event->stopPropagation();
  }

  /**
   * Handles a task event with an exception.
   *
   * @param \Drupal\search_api\Task\TaskEvent $event
   *   The task event.
   */
  public function fail(TaskEvent $event) {
    $this->logEvent($event);
    $event->stopPropagation();

    $event->setException(new SearchApiException('fail'));
  }

  /**
   * Ignores a task event.
   *
   * @param \Drupal\search_api\Task\TaskEvent $event
   *   The task event.
   */
  public function ignore(TaskEvent $event) {
    $this->logEvent($event);
  }

  /**
   * Retrieves the event log.
   *
   * @return string[][]
   *   The event log, with each event represented by an associative array of the
   *   task's properties.
   */
  public function getEventLog() {
    return $this->eventLog;
  }

  /**
   * Logs an event.
   *
   * @param \Drupal\search_api\Task\TaskEvent $event
   *   The event.
   */
  protected function logEvent(TaskEvent $event) {
    $this->eventLog[] = $event->getTask()->toArray();
  }

}
