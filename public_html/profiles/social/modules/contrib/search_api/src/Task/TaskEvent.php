<?php

namespace Drupal\search_api\Task;

use Drupal\search_api\SearchApiException;
use Symfony\Component\EventDispatcher\Event;

/**
 * Represents an event that was fired to execute a pending task.
 *
 * A module receiving and properly handling such an event should call its
 * stopPropagation() method so the task manager will know that it was
 * successfully executed and can be deleted.
 *
 * If the code handling the task wants to flag an error during execution, it
 * should set an exception on the task.
 */
class TaskEvent extends Event {

  /**
   * The task being executed.
   *
   * @var \Drupal\search_api\Task\TaskInterface
   */
  protected $task;

  /**
   * The exception that stopped execution of the task, if any.
   *
   * @var \Drupal\search_api\SearchApiException|null
   */
  protected $exception;

  /**
   * Constructs a TaskEvent object.
   *
   * @param \Drupal\search_api\Task\TaskInterface $task
   *   The task being executed.
   */
  public function __construct(TaskInterface $task) {
    $this->task = $task;
  }

  /**
   * Retrieves the executed task.
   *
   * @return \Drupal\search_api\Task\TaskInterface
   *   The task being executed.
   */
  public function getTask() {
    return $this->task;
  }

  /**
   * Retrieves any exception that stopped the execution of the task.
   *
   * @return \Drupal\search_api\SearchApiException|null
   *   The exception, if any.
   */
  public function getException() {
    return $this->exception;
  }

  /**
   * Sets the exception that stopped the execution of the task.
   *
   * @param \Drupal\search_api\SearchApiException|null $exception
   *   The exception that occurred.
   *
   * @return $this
   */
  public function setException(SearchApiException $exception = NULL) {
    $this->exception = $exception;
    return $this;
  }

}
