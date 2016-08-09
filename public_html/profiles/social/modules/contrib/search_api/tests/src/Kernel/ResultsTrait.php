<?php

namespace Drupal\Tests\search_api\Kernel;

use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api\Utility;

/**
 * Defines a trait for testing results.
 */
trait ResultsTrait {

  /**
   * Asserts that the search results contain the expected IDs.
   *
   * @param \Drupal\search_api\Query\ResultSetInterface $result
   *   The search results.
   * @param int[][] $expected
   *   The expected entity IDs, grouped by entity type and with their indexes in
   *   this object's respective array properties as the values.
   */
  protected function assertResults(ResultSetInterface $result, array $expected) {
    $results = array_keys($result->getResultItems());
    sort($results);

    $ids = array();
    foreach ($expected as $entity_type => $items) {
      $datasource_id = "entity:$entity_type";
      foreach ($items as $i) {
        if ($entity_type == 'user') {
          $id = $i . ':en';
        }
        else {
          /** @var \Drupal\Core\Entity\EntityInterface $entity */
          $entity = $this->{"{$entity_type}s"}[$i];
          $id = $entity->id() . ':en';
        }
        $ids[] = Utility::createCombinedId($datasource_id, $id);
      }
    }
    sort($ids);

    $this->assertEquals($ids, $results);
  }

}
