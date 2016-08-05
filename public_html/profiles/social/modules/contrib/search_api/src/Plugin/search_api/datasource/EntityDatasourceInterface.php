<?php

namespace Drupal\search_api\Plugin\search_api\datasource;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\search_api\Datasource\DatasourceInterface;

/**
 * Describes an interface for entity datasources.
 */
interface EntityDatasourceInterface extends DatasourceInterface {

  /**
   * Retrieves all indexes that are configured to index the given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity for which to check.
   *
   * @return \Drupal\search_api\IndexInterface[]
   *   All indexes that are configured to index the given entity (using this
   *   datasource class).
   */
  public static function getIndexesForEntity(ContentEntityInterface $entity);

  /**
   * Retrieves all item IDs of entities of the specified bundles.
   *
   * @param int|null $page
   *   The zero-based page of IDs to retrieve, for the paging mechanism
   *   implemented by this datasource; or NULL to retrieve all items at once.
   * @param string[]|null $bundles
   *   (optional) The bundles for which all item IDs should be returned; or NULL
   *   to retrieve IDs from all enabled bundles in this datasource.
   * @param string[]|null $languages
   *   (optional) The languages for which all item IDs should be returned; or
   *   NULL to retrieve IDs from all enabled languages in this datasource.
   *
   * @return string[]
   *   An array of all item IDs matching these conditions. In case both bundles
   *   and languages are specified, they are combined with OR.
   */
  public function getPartialItemIds($page = NULL, array $bundles = NULL, array $languages = NULL);

  /**
   * Returns an array of config entity dependencies.
   *
   * @param string $entity_type_id
   *   The entity type to which the fields are attached.
   * @param string[] $fields
   *   An array of property paths of fields from this entity type.
   * @param string[] $all_fields
   *   An array of property paths of all the fields from this datasource.
   *
   * @return string[]
   *   An array keyed by the IDs of entities on which this datasource depends.
   *   The values are containing list of Search API fields.
   */
  public function getFieldDependenciesForEntityType($entity_type_id, array $fields, array $all_fields);

}
