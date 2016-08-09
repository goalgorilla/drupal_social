<?php

namespace Drupal\search_api\Plugin\search_api\datasource;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\Task\TaskEvent;
use Drupal\search_api\Task\TaskManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a service for managing pending tracking tasks for datasources.
 */
class ContentEntityTaskManager implements EventSubscriberInterface {

  /**
   * The Search API task type used by this service for "insert items" tasks.
   */
  const INSERT_ITEMS_TASK_TYPE = 'search_api.entity_datasource.trackItemsInserted';

  /**
   * The Search API task type used by this service for "delete items" tasks.
   */
  const DELETE_ITEMS_TASK_TYPE = 'search_api.entity_datasource.trackItemsDeleted';

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
   * Constructs a ContentEntityTaskManager object.
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
    $events['search_api.task.' . static::INSERT_ITEMS_TASK_TYPE][] = array('processEvent');
    $events['search_api.task.' . static::DELETE_ITEMS_TASK_TYPE][] = array('processEvent');

    return $events;
  }

  /**
   * Processes a datasource tracking event.
   *
   * @param \Drupal\search_api\Task\TaskEvent $event
   *   The task event.
   * @param string $event_name
   *   The name of the event.
   */
  public function processEvent(TaskEvent $event, $event_name) {
    $event->stopPropagation();

    // The complete event name prefix in front of the method name is 45
    // characters long: "search_api.task.search_api.entity_datasource.".
    $method = substr($event_name, 45);

    $task = $event->getTask();
    $index = $task->getIndex();
    $data = $task->getData();

    if (!$index->hasValidTracker()) {
      $args['%index'] = $index->label();
      $message = new FormattableMarkup('Index %index does not have a valid tracker set.', $args);
      $event->setException(new SearchApiException($message));
      return;
    }

    $datasource_id = $data['datasource'];
    $reschedule = FALSE;
    if ($index->isValidDatasource($datasource_id)) {
      $datasource = $index->getDatasource($datasource_id);
      if ($datasource instanceof EntityDatasourceInterface) {
        $raw_ids = $datasource->getPartialItemIds($data['page'], $data['bundles'], $data['languages']);
        if ($raw_ids !== NULL) {
          $reschedule = TRUE;
          if ($raw_ids) {
            $index->startBatchTracking();
            $index->$method($datasource_id, $raw_ids);
            $index->stopBatchTracking();
          }
        }
      }
    }

    if ($reschedule) {
      ++$data['page'];
      $this->taskManager->addTask($task->getType(), NULL, $index, $data);
    }
  }

}
