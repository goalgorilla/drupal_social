<?php

namespace Drupal\search_api\Task;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Defines an interface for a Search API task.
 */
interface TaskInterface extends ContentEntityInterface {

  /**
   * Retrieves the task type.
   *
   * @return string
   *   The task type.
   */
  public function getType();

  /**
   * Retrieves the ID of the search server associated with this task, if any.
   *
   * @return string|null
   *   The search server ID, or NULL if there is none.
   */
  public function getServerId();

  /**
   * Retrieves the search server associated with this task, if any.
   *
   * @return \Drupal\search_api\ServerInterface|null
   *   The search server, or NULL if there is none.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if a server was set, but it could not be loaded.
   */
  public function getServer();

  /**
   * Retrieves the ID of the search index associated with this task, if any.
   *
   * @return string|null
   *   The search index ID, or NULL if there is none.
   */
  public function getIndexId();

  /**
   * Retrieves the search index associated with this task, if any.
   *
   * @return \Drupal\search_api\IndexInterface|null
   *   The search index, or NULL if there is none.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if an index was set, but it could not be loaded.
   */
  public function getIndex();

  /**
   * Retrieves the additional data associated with this task, if any.
   *
   * @return mixed|null
   *   The additional data.
   */
  public function getData();

  /**
   * Retrieves the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  public function getEntityTypeManager();

  /**
   * Sets the entity type manager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   *
   * @return $this
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $entityTypeManager);

}
