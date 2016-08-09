<?php

namespace Drupal\search_api\Tests;

use Drupal\search_api\Entity\Index;
use Drupal\search_api\Utility;

/**
 * Contains helpers to create data that can be used by tests.
 */
trait ExampleContentTrait {

  /**
   * The generated test entities, keyed by ID.
   *
   * @var \Drupal\entity_test\Entity\EntityTestMulRevChanged[]
   */
  protected $entities = array();

  /**
   * Sets up the necessary bundles on the test entity type.
   */
  protected function setUpExampleStructure() {
    entity_test_create_bundle('item', NULL, 'entity_test_mulrev_changed');
    entity_test_create_bundle('article', NULL, 'entity_test_mulrev_changed');
  }

  /**
   * Creates several test entities.
   */
  protected function insertExampleContent() {
    // To test Unicode compliance, include all kind of strange characters here.
    $smiley = json_decode('"\u1F601"');
    $this->addTestEntity(1, array(
      'name' => 'foo bar baz foobaz föö smile' . $smiley,
      'body' => 'test test case Case casE',
      'type' => 'item',
      'keywords' => array('Orange', 'orange', 'örange', 'Orange', $smiley),
      'category' => 'item_category',
    ));
    $this->addTestEntity(2, array(
      'name' => 'foo test foobuz',
      'body' => 'bar test casE',
      'type' => 'item',
      'keywords' => array('orange', 'apple', 'grape'),
      'category' => 'item_category',
    ));
    $this->addTestEntity(3, array(
      'name' => 'bar',
      'body' => 'test foobar Case',
      'type' => 'item',
    ));
    $this->addTestEntity(4, array(
      'name' => 'foo baz',
      'body' => 'test test test',
      'type' => 'article',
      'keywords' => array('apple', 'strawberry', 'grape'),
      'category' => 'article_category',
      'width' => '1.0',
    ));
    $this->addTestEntity(5, array(
      'name' => 'bar baz',
      'body' => 'foo',
      'type' => 'article',
      'keywords' => array('orange', 'strawberry', 'grape', 'banana'),
      'category' => 'article_category',
      'width' => '2.0',
    ));
    $count = \Drupal::entityQuery('entity_test_mulrev_changed')->count()->execute();
    $this->assertEqual($count, 5, "$count items inserted.");
  }

  /**
   * Creates and saves a test entity with the given values.
   *
   * @param int $id
   *   The entity's ID.
   * @param array $values
   *   The entity's property values.
   *
   * @return \Drupal\entity_test\Entity\EntityTestMulRevChanged
   *   The created entity.
   */
  protected function addTestEntity($id, array $values) {
    $storage = \Drupal::entityTypeManager()->getStorage('entity_test_mulrev_changed');
    $values['id'] = $id;
    $this->entities[$id] = $storage->create($values);
    $this->entities[$id]->save();
    return $this->entities[$id];
  }

  /**
   * Indexes all (unindexed) items on the specified index.
   *
   * @param string $index_id
   *   The ID of the index on which items should be indexed.
   *
   * @return int
   *   The number of successfully indexed items.
   */
  protected function indexItems($index_id) {
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = Index::load($index_id);
    return $index->indexItems();
  }

  /**
   * Returns the item IDs for the given entity IDs.
   *
   * @param array $entity_ids
   *   An array of entity IDs.
   *
   * @return string[]
   *   An array of item IDs.
   */
  protected function getItemIds(array $entity_ids) {
    $translate_ids = function ($entity_id) {
      return Utility::createCombinedId('entity:entity_test_mulrev_changed', $entity_id . ':en');
    };
    return array_map($translate_ids, $entity_ids);
  }

}
