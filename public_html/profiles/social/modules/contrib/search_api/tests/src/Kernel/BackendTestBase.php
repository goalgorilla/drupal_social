<?php

namespace Drupal\Tests\search_api\Kernel;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api\Tests\ExampleContentTrait;
use Drupal\search_api\Utility;

/**
 * Provides a base class for backend tests.
 */
abstract class BackendTestBase extends KernelTestBase {

  use ExampleContentTrait;
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = array(
    'field',
    'search_api',
    'user',
    'system',
    'entity_test',
    'text',
    'search_api_test_example_content',
  );

  /**
   * A search server ID.
   *
   * @var string
   */
  protected $serverId = 'search_server';

  /**
   * A search index ID.
   *
   * @var string
   */
  protected $indexId = 'search_index';

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->installSchema('search_api', array('search_api_item', 'search_api_task'));
    $this->installSchema('system', array('router'));
    $this->installSchema('user', array('users_data'));
    $this->installEntitySchema('entity_test_mulrev_changed');
    $this->installConfig('search_api_test_example_content');

    // Set the tracking page size so tracking will work properly.
    \Drupal::configFactory()
      ->getEditable('search_api.settings')
      ->set('tracking_page_size', 100)
      ->save();

    // Do not use a batch for tracking the initial items after creating an
    // index when running the tests via the GUI. Otherwise, it seems Drupal's
    // Batch API gets confused and the test fails.
    if (php_sapi_name() != 'cli') {
      \Drupal::state()->set('search_api_use_tracking_batch', FALSE);
    }

    // Set tracking page size so tracking will work properly.
    \Drupal::configFactory()
      ->getEditable('search_api.settings')
      ->set('tracking_page_size', 100)
      ->save();

    $this->setUpExampleStructure();
  }

  /**
   * Tests various indexing scenarios for the search backend.
   *
   * Uses a single method to save time.
   */
  public function testBackend() {
    $this->insertExampleContent();
    $this->checkDefaultServer();
    $this->checkServerBackend();
    $this->checkDefaultIndex();
    $this->updateIndex();
    $this->searchNoResults();
    $this->indexItems($this->indexId);
    $this->searchSuccess();
    $this->checkFacets();
    $this->checkSecondServer();
    $this->regressionTests();
    $this->clearIndex();

    $this->indexItems($this->indexId);
    $this->backendSpecificRegressionTests();
    $this->checkBackendSpecificFeatures();
    $this->clearIndex();

    $this->enableHtmlFilter();
    $this->indexItems($this->indexId);
    $this->disableHtmlFilter();
    $this->clearIndex();

    $this->searchNoResults();
    $this->regressionTests2();

    $this->checkIndexWithoutFields();

    $this->checkModuleUninstall();
  }

  /**
   * Tests the correct setup of the server backend.
   */
  abstract protected function checkServerBackend();

  /**
   * Checks whether changes to the index's fields are picked up by the server.
   */
  abstract protected function updateIndex();

  /**
   * Tests that a second server doesn't interfere with the first.
   */
  abstract protected function checkSecondServer();

  /**
   * Tests whether removing the configuration again works as it should.
   */
  abstract protected function checkModuleUninstall();

  /**
   * Checks backend specific features.
   */
  protected function checkBackendSpecificFeatures() {}

  /**
   * Runs backend specific regression tests.
   */
  protected function backendSpecificRegressionTests() {}

  /**
   * Tests the server that was installed through default configuration files.
   */
  protected function checkDefaultServer() {
    $server = $this->getServer();
    $this->assertTrue((bool) $server, 'The server was successfully created.');
  }

  /**
   * Tests the index that was installed through default configuration files.
   */
  protected function checkDefaultIndex() {
    $index = $this->getIndex();
    $this->assertTrue((bool) $index, 'The index was successfully created.');

    $this->assertEquals(array("entity:entity_test_mulrev_changed"), $index->getDatasourceIds(), 'Datasources are set correctly.');
    $this->assertEquals('default', $index->getTrackerId(), 'Tracker is set correctly.');

    $this->assertEquals(5, $index->getTrackerInstance()->getTotalItemsCount(), 'Correct item count.');
    $this->assertEquals(0, $index->getTrackerInstance()->getIndexedItemsCount(), 'All items still need to be indexed.');
  }

  /**
   * Enables the "HTML Filter" processor for the index.
   */
  protected function enableHtmlFilter() {
    $index = $this->getIndex();

    /** @var \Drupal\search_api\Processor\ProcessorInterface $processor */
    $processor = $index->createPlugin('processor', 'html_filter');
    $index->addProcessor($processor)->save();

    $this->assertArrayHasKey('html_filter', $index->getProcessors(), 'HTML filter processor is added.');
  }

  /**
   * Disables the "HTML Filter" processor for the index.
   */
  protected function disableHtmlFilter() {
    $index = $this->getIndex();
    $index->removeField('body');
    $index->removeProcessor('html_filter');
    $index->save();

    $this->assertArrayNotHasKey('html_filter', $index->getProcessors(), 'HTML filter processor is removed.');
    $this->assertArrayNotHasKey('body', $index->getFields(), 'Body field is removed.');
  }

  /**
   * Builds a search query for testing purposes.
   *
   * Used as a helper method during testing.
   *
   * @param string|array|null $keys
   *   (optional) The search keys to set, if any.
   * @param string[] $conditions
   *   (optional) Conditions to set on the query, in the format "field,value".
   * @param string[]|null $fields
   *   (optional) Fulltext fields to search for the keys.
   * @param bool $place_id_sort
   *   (optional) Whether to place a default sort on the item ID.
   *
   * @return \Drupal\search_api\Query\QueryInterface
   *   A search query on the test index.
   */
  protected function buildSearch($keys = NULL, array $conditions = array(), array $fields = NULL, $place_id_sort = TRUE) {
    static $i = 0;

    $query = $this->getIndex()->query();
    if ($keys) {
      $query->keys($keys);
      if ($fields) {
        $query->setFulltextFields($fields);
      }
    }
    foreach ($conditions as $condition) {
      list($field, $value) = explode(',', $condition, 2);
      $query->addCondition($field, $value);
    }
    $query->range(0, 10);
    if ($place_id_sort) {
      // Use the normal "id" and the magic "search_api_id" field alternately, to
      // make sure both work as expected.
      $query->sort((++$i % 2) ? 'id' : 'search_api_id');
    }

    return $query;
  }

  /**
   * Tests that a search on the index doesn't have any results.
   */
  protected function searchNoResults() {
    $results = $this->buildSearch('test')->execute();
    $this->assertResults(array(), $results, 'Search before indexing');
  }

  /**
   * Tests whether some test searches have the correct results.
   */
  protected function searchSuccess() {
    $results = $this->buildSearch('test')->range(1, 2)->execute();
    $this->assertEquals(4, $results->getResultCount(), 'Search for »test« returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(2, 3)), array_keys($results->getResultItems()), 'Search for »test« returned correct result.');
    $this->assertEmpty($results->getIgnoredSearchKeys());
    $this->assertEmpty($results->getWarnings());

    $id = $this->getItemIds(array(2))[0];
    $this->assertEquals($id, key($results->getResultItems()));
    $this->assertEquals($id, $results->getResultItems()[$id]->getId());
    $this->assertEquals('entity:entity_test_mulrev_changed', $results->getResultItems()[$id]->getDatasourceId());

    $results = $this->buildSearch('test foo')->execute();
    $this->assertResults(array(1, 2, 4), $results, 'Search for »test foo«');

    $results = $this->buildSearch('foo', array('type,item'))->execute();
    $this->assertResults(array(1, 2), $results, 'Search for »foo«');

    $keys = array(
      '#conjunction' => 'AND',
      'test',
      array(
        '#conjunction' => 'OR',
        'baz',
        'foobar',
      ),
      array(
        '#conjunction' => 'OR',
        '#negation' => TRUE,
        'bar',
        'fooblob',
      ),
    );
    $results = $this->buildSearch($keys)->execute();
    $this->assertResults(array(4), $results, 'Complex search 1');

    $query = $this->buildSearch();
    $conditions = $query->createConditionGroup('OR');
    $conditions->addCondition('name', 'bar');
    $conditions->addCondition('body', 'bar');
    $query->addConditionGroup($conditions);
    $results = $query->execute();
    $this->assertResults(array(1, 2, 3, 5), $results, 'Search with multi-field fulltext filter');

    $results = $this->buildSearch()
      ->addCondition('keywords', array('grape', 'apple'), 'IN')
      ->execute();
    $this->assertResults(array(2, 4, 5), $results, 'Query with IN filter');

    $results = $this->buildSearch()->addCondition('keywords', array('grape', 'apple'), 'NOT IN')->execute();
    $this->assertResults(array(1, 3), $results, 'Query with NOT IN filter');

    $results = $this->buildSearch()->addCondition('width', array('0.9', '1.5'), 'BETWEEN')->execute();
    $this->assertResults(array(4), $results, 'Query with BETWEEN filter');

    $results = $this->buildSearch()
      ->addCondition('width', array('0.9', '1.5'), 'NOT BETWEEN')
      ->execute();
    $this->assertResults(array(1, 2, 3, 5), $results, 'Query with NOT BETWEEN filter');

    $results = $this->buildSearch()
      ->setLanguages(array('und', 'en'))
      ->addCondition('keywords', array('grape', 'apple'), 'IN')
      ->execute();
    $this->assertResults(array(2, 4, 5), $results, 'Query with IN filter');

    $results = $this->buildSearch()
      ->setLanguages(array('und'))
      ->execute();
    $this->assertResults(array(), $results, 'Query with languages');

    $query = $this->buildSearch();
    $conditions = $query->createConditionGroup('OR')
      ->addCondition('search_api_language', 'und')
      ->addCondition('width', array('0.9', '1.5'), 'BETWEEN');
    $query->addConditionGroup($conditions);
    $results = $query->execute();
    $this->assertResults(array(4), $results, 'Query with search_api_language filter');

    $results = $this->buildSearch()
      ->addCondition('search_api_language', 'und')
      ->addCondition('width', array('0.9', '1.5'), 'BETWEEN')
      ->execute();
    $this->assertResults(array(), $results, 'Query with search_api_language filter');

    $results = $this->buildSearch()
      ->addCondition('search_api_language', array('und', 'en'), 'IN')
      ->addCondition('width', array('0.9', '1.5'), 'BETWEEN')
      ->execute();
    $this->assertResults(array(4), $results, 'Query with search_api_language filter');

    $results = $this->buildSearch()
      ->addCondition('search_api_language', array('und', 'de'), 'NOT IN')
      ->addCondition('width', array('0.9', '1.5'), 'BETWEEN')
      ->execute();
    $this->assertResults(array(4), $results, 'Query with search_api_language "NOT IN" filter');

    $results = $this->buildSearch()
      ->addCondition('search_api_id', $this->getItemIds(array(1))[0])
      ->execute();
    $this->assertResults(array(1), $results, 'Query with search_api_id filter');

    $results = $this->buildSearch()
      ->addCondition('search_api_id', $this->getItemIds(array(2, 4)), 'NOT IN')
      ->execute();
    $this->assertResults(array(1, 3, 5), $results, 'Query with search_api_id "NOT IN" filter');

    $results = $this->buildSearch()
      ->addCondition('search_api_id', $this->getItemIds(array(3))[0], '>')
      ->execute();
    $this->assertResults(array(4, 5), $results, 'Query with search_api_id "greater than" filter');

    $results = $this->buildSearch()
      ->addCondition('search_api_datasource', 'foobar')
      ->execute();
    $this->assertResults(array(), $results, 'Query for a non-existing datasource');

    $results = $this->buildSearch()
      ->addCondition('search_api_datasource', array('foobar', 'entity:entity_test_mulrev_changed'), 'IN')
      ->execute();
    $this->assertResults(array(1, 2, 3, 4, 5), $results, 'Query with search_api_id "IN" filter');

    $results = $this->buildSearch()
      ->addCondition('search_api_datasource', array('foobar', 'entity:entity_test_mulrev_changed'), 'NOT IN')
      ->execute();
    $this->assertResults(array(), $results, 'Query with search_api_id "NOT IN" filter');

    // For a query without keys, all of these except for the last one should
    // have no effect. Therefore, we expect results with IDs in descending
    // order.
    $results = $this->buildSearch(NULL, array(), array(), FALSE)
      ->sort('search_api_relevance')
      ->sort('search_api_datasource', QueryInterface::SORT_DESC)
      ->sort('search_api_language')
      ->sort('search_api_id', QueryInterface::SORT_DESC)
      ->execute();
    $this->assertResults(array(5, 4, 3, 2, 1), $results, 'Query with magic sorts');
  }

  /**
   * Tests whether facets work correctly.
   */
  protected function checkFacets() {
    $query = $this->buildSearch();
    $conditions = $query->createConditionGroup('OR', array('facet:' . 'category'));
    $conditions->addCondition('category', 'article_category');
    $query->addConditionGroup($conditions);
    $facets['category'] = array(
      'field' => 'category',
      'limit' => 0,
      'min_count' => 1,
      'missing' => TRUE,
      'operator' => 'or',
    );
    $query->setOption('search_api_facets', $facets);
    $results = $query->execute();
    $this->assertResults(array(4, 5), $results, 'OR facets query');
    $expected = array(
      array('count' => 2, 'filter' => '"article_category"'),
      array('count' => 2, 'filter' => '"item_category"'),
      array('count' => 1, 'filter' => '!'),
    );
    $category_facets = $results->getExtraData('search_api_facets')['category'];
    usort($category_facets, array($this, 'facetCompare'));
    $this->assertEquals($expected, $category_facets, 'Correct OR facets were returned');

    $query = $this->buildSearch();
    $conditions = $query->createConditionGroup('OR', array('facet:' . 'category'));
    $conditions->addCondition('category', 'article_category');
    $query->addConditionGroup($conditions);
    $conditions = $query->createConditionGroup('AND');
    $conditions->addCondition('category', NULL, '<>');
    $query->addConditionGroup($conditions);
    $facets['category'] = array(
      'field' => 'category',
      'limit' => 0,
      'min_count' => 1,
      'missing' => TRUE,
      'operator' => 'or',
    );
    $query->setOption('search_api_facets', $facets);
    $results = $query->execute();
    $this->assertResults(array(4, 5), $results, 'OR facets query');
    $expected = array(
      array('count' => 2, 'filter' => '"article_category"'),
      array('count' => 2, 'filter' => '"item_category"'),
    );
    $category_facets = $results->getExtraData('search_api_facets')['category'];
    usort($category_facets, array($this, 'facetCompare'));
    $this->assertEquals($expected, $category_facets, 'Correct OR facets were returned');
  }

  /**
   * Executes regression tests for issues that were already fixed.
   */
  protected function regressionTests() {
    $this->regressionTest2007872();
    $this->regressionTest1863672();
    $this->regressionTest2040543();
    $this->regressionTest2111753();
    $this->regressionTest2127001();
    $this->regressionTest2136409();
    $this->regressionTest1658964();
    $this->regressionTest2469547();
    $this->regressionTest1403916();
  }

  /**
   * Regression tests for missing results when using OR filters.
   *
   * @see https://www.drupal.org/node/2007872
   */
  protected function regressionTest2007872() {
    $results = $this->buildSearch('test', array(), array(), FALSE)
      ->sort('id')
      ->sort('type')
      ->execute();
    $this->assertResults(array(1, 2, 3, 4), $results, 'Sorting on field with NULLs');

    $query = $this->buildSearch(NULL, array(), array(), FALSE);
    $conditions = $query->createConditionGroup('OR');
    $conditions->addCondition('id', 3);
    $conditions->addCondition('type', 'article');
    $query->addConditionGroup($conditions);
    $query->sort('search_api_id', QueryInterface::SORT_DESC);
    $results = $query->execute();
    $this->assertResults(array(5, 4, 3), $results, 'OR filter on field with NULLs');
  }

  /**
   * Regression tests for same content multiple times in the search result.
   *
   * Error was caused by multiple terms for filter.
   *
   * @see https://www.drupal.org/node/1863672
   */
  protected function regressionTest1863672() {
    $query = $this->buildSearch();
    $conditions = $query->createConditionGroup('OR');
    $conditions->addCondition('keywords', 'orange');
    $conditions->addCondition('keywords', 'apple');
    $query->addConditionGroup($conditions);
    $results = $query->execute();
    $this->assertResults(array(1, 2, 4, 5), $results, 'OR filter on multi-valued field');

    $query = $this->buildSearch();
    $conditions = $query->createConditionGroup('OR');
    $conditions->addCondition('keywords', 'orange');
    $conditions->addCondition('keywords', 'strawberry');
    $query->addConditionGroup($conditions);
    $conditions = $query->createConditionGroup('OR');
    $conditions->addCondition('keywords', 'apple');
    $conditions->addCondition('keywords', 'grape');
    $query->addConditionGroup($conditions);
    $results = $query->execute();
    $this->assertResults(array(2, 4, 5), $results, 'Multiple OR filters on multi-valued field');

    $query = $this->buildSearch();
    $conditions1 = $query->createConditionGroup('OR');
    $conditions = $query->createConditionGroup('AND');
    $conditions->addCondition('keywords', 'orange');
    $conditions->addCondition('keywords', 'apple');
    $conditions1->addConditionGroup($conditions);
    $conditions = $query->createConditionGroup('AND');
    $conditions->addCondition('keywords', 'strawberry');
    $conditions->addCondition('keywords', 'grape');
    $conditions1->addConditionGroup($conditions);
    $query->addConditionGroup($conditions1);
    $results = $query->execute();
    $this->assertResults(array(2, 4, 5), $results, 'Complex nested filters on multi-valued field');
  }

  /**
   * Regression tests for (none) facet shown when feature is set to "no".
   *
   * @see https://www.drupal.org/node/2040543
   */
  protected function regressionTest2040543() {
    $query = $this->buildSearch();
    $facets['category'] = array(
      'field' => 'category',
      'limit' => 0,
      'min_count' => 1,
      'missing' => TRUE,
    );
    $query->setOption('search_api_facets', $facets);
    $query->range(0, 0);
    $results = $query->execute();
    $expected = array(
      array('count' => 2, 'filter' => '"article_category"'),
      array('count' => 2, 'filter' => '"item_category"'),
      array('count' => 1, 'filter' => '!'),
    );
    $type_facets = $results->getExtraData('search_api_facets')['category'];
    usort($type_facets, array($this, 'facetCompare'));
    $this->assertEquals($expected, $type_facets, 'Correct facets were returned');

    $query = $this->buildSearch();
    $facets['category']['missing'] = FALSE;
    $query->setOption('search_api_facets', $facets);
    $query->range(0, 0);
    $results = $query->execute();
    $expected = array(
      array('count' => 2, 'filter' => '"article_category"'),
      array('count' => 2, 'filter' => '"item_category"'),
    );
    $type_facets = $results->getExtraData('search_api_facets')['category'];
    usort($type_facets, array($this, 'facetCompare'));
    $this->assertEquals($expected, $type_facets, 'Correct facets were returned');
  }

  /**
   * Regression tests for searching for multiple words using "OR" condition.
   *
   * @see https://www.drupal.org/node/2111753
   */
  protected function regressionTest2111753() {
    $keys = array(
      '#conjunction' => 'OR',
      'foo',
      'test',
    );
    $query = $this->buildSearch($keys, array(), array('name'));
    $results = $query->execute();
    $this->assertResults(array(1, 2, 4), $results, 'OR keywords');

    $query = $this->buildSearch($keys, array(), array('name', 'body'));
    $query->range(0, 0);
    $results = $query->execute();
    $this->assertEquals(5, $results->getResultCount(), 'Multi-field OR keywords returned correct number of results.');
    $this->assertFalse($results->getResultItems(), 'Multi-field OR keywords returned correct result.');
    $this->assertEmpty($results->getIgnoredSearchKeys());
    $this->assertEmpty($results->getWarnings());

    $keys = array(
      '#conjunction' => 'OR',
      'foo',
      'test',
      array(
        '#conjunction' => 'AND',
        'bar',
        'baz',
      ),
    );
    $query = $this->buildSearch($keys, array(), array('name'));
    $results = $query->execute();
    $this->assertResults(array(1, 2, 4, 5), $results, 'Nested OR keywords');

    $keys = array(
      '#conjunction' => 'OR',
      array(
        '#conjunction' => 'AND',
        'foo',
        'test',
      ),
      array(
        '#conjunction' => 'AND',
        'bar',
        'baz',
      ),
    );
    $query = $this->buildSearch($keys, array(), array('name', 'body'));
    $results = $query->execute();
    $this->assertResults(array(1, 2, 4, 5), $results, 'Nested multi-field OR keywords');
  }

  /**
   * Regression tests for non-working operator "contains none of these words".
   *
   * @see https://www.drupal.org/node/2127001
   */
  protected function regressionTest2127001() {
    $keys = array(
      '#conjunction' => 'AND',
      '#negation' => TRUE,
      'foo',
      'bar',
    );
    $results = $this->buildSearch($keys)->execute();
    $this->assertResults(array(3, 4), $results, 'Negated AND fulltext search');

    $keys = array(
      '#conjunction' => 'OR',
      '#negation' => TRUE,
      'foo',
      'baz',
    );
    $results = $this->buildSearch($keys)->execute();
    $this->assertResults(array(3), $results, 'Negated OR fulltext search');

    $keys = array(
      '#conjunction' => 'AND',
      'test',
      array(
        '#conjunction' => 'AND',
        '#negation' => TRUE,
        'foo',
        'bar',
      ),
    );
    $results = $this->buildSearch($keys)->execute();
    $this->assertResults(array(3, 4), $results, 'Nested NOT AND fulltext search');
  }

  /**
   * Regression tests for handling of NULL filters.
   *
   * @see https://www.drupal.org/node/2136409
   */
  protected function regressionTest2136409() {
    $query = $this->buildSearch();
    $query->addCondition('category', NULL);
    $results = $query->execute();
    $this->assertResults(array(3), $results, 'NULL filter');

    $query = $this->buildSearch();
    $query->addCondition('category', NULL, '<>');
    $results = $query->execute();
    $this->assertResults(array(1, 2, 4, 5), $results, 'NOT NULL filter');
  }

  /**
   * Regression tests for facets with counts of 0.
   *
   * @see https://www.drupal.org/node/1658964
   */
  protected function regressionTest1658964() {
    $query = $this->buildSearch();
    $facets['type'] = array(
      'field' => 'type',
      'limit' => 0,
      'min_count' => 0,
      'missing' => TRUE,
    );
    $query->setOption('search_api_facets', $facets);
    $query->addCondition('type', 'article');
    $query->range(0, 0);
    $results = $query->execute();
    $expected = array(
      array('count' => 2, 'filter' => '"article"'),
      array('count' => 0, 'filter' => '!'),
      array('count' => 0, 'filter' => '"item"'),
    );
    $facets = $results->getExtraData('search_api_facets', array())['type'];
    usort($facets, array($this, 'facetCompare'));
    $this->assertEquals($expected, $facets, 'Correct facets were returned');
  }

  /**
   * Regression tests for facets on fulltext fields.
   *
   * @see https://www.drupal.org/node/2469547
   */
  protected function regressionTest2469547() {
    $query = $this->buildSearch();
    $facets = array();
    $facets['body'] = array(
      'field' => 'body',
      'limit' => 0,
      'min_count' => 1,
      'missing' => FALSE,
    );
    $query->setOption('search_api_facets', $facets);
    $query->addCondition('id', 5, '<>');
    $query->range(0, 0);
    $results = $query->execute();
    $expected = array(
      array('count' => 4, 'filter' => '"test"'),
      array('count' => 2, 'filter' => '"Case"'),
      array('count' => 2, 'filter' => '"casE"'),
      array('count' => 1, 'filter' => '"bar"'),
      array('count' => 1, 'filter' => '"case"'),
      array('count' => 1, 'filter' => '"foobar"'),
    );
    // We can't guarantee the order of returned facets, since "bar" and "foobar"
    // both occur once, so we have to manually sort the returned facets first.
    $facets = $results->getExtraData('search_api_facets', array())['body'];
    usort($facets, array($this, 'facetCompare'));
    $this->assertEquals($expected, $facets, 'Correct facets were returned for a fulltext field.');
  }

  /**
   * Regression tests for multi word search results sets and wrong facet counts.
   *
   * @see https://www.drupal.org/node/1403916
   */
  protected function regressionTest1403916() {
    $query = $this->buildSearch('test foo');
    $facets = array();
    $facets['type'] = array(
      'field' => 'type',
      'limit' => 0,
      'min_count' => 1,
      'missing' => TRUE,
    );
    $query->setOption('search_api_facets', $facets);
    $query->range(0, 0);
    $results = $query->execute();
    $expected = array(
      array('count' => 2, 'filter' => '"item"'),
      array('count' => 1, 'filter' => '"article"'),
    );
    $facets = $results->getExtraData('search_api_facets', array())['type'];
    usort($facets, array($this, 'facetCompare'));
    $this->assertEquals($expected, $facets, 'Correct facets were returned');
  }

  /**
   * Compares two facet filters to determine their order.
   *
   * Used as a callback for usort() in regressionTests().
   *
   * Will first compare the counts, ranking facets with higher count first, and
   * then by filter value.
   *
   * @param array $a
   *   The first facet filter.
   * @param array $b
   *   The second facet filter.
   *
   * @return int
   *   -1 or 1 if the first filter should, respectively, come before or after
   *   the second; 0 if both facet filters are equal.
   */
  protected function facetCompare(array $a, array $b) {
    if ($a['count'] != $b['count']) {
      return $b['count'] - $a['count'];
    }
    return strcmp($a['filter'], $b['filter']);
  }

  /**
   * Clears the test index.
   */
  protected function clearIndex() {
    $this->getIndex()->clear();
  }

  /**
   * Executes regression tests which are unpractical to run in between.
   */
  protected function regressionTests2() {
    // Create a "prices" field on the test entity type.
    FieldStorageConfig::create(array(
      'field_name' => 'prices',
      'entity_type' => 'entity_test_mulrev_changed',
      'type' => 'decimal',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ))->save();
    FieldConfig::create(array(
      'field_name' => 'prices',
      'entity_type' => 'entity_test_mulrev_changed',
      'bundle' => 'item',
      'label' => 'Prices',
    ))->save();

    $this->regressionTest1916474();
    $this->regressionTest2284199();
    $this->regressionTest2471509();
  }

  /**
   * Regression tests for correctly indexing  multiple float/decimal fields.
   *
   * @see https://www.drupal.org/node/1916474
   */
  protected function regressionTest1916474() {
    $index = $this->getIndex();
    $this->addField($index, 'prices', 'decimal');
    $success = $index->save();
    $this->assertTrue($success, 'The index field settings were successfully changed.');

    // Reset the static cache so the new values will be available.
    $this->resetEntityCache('server');
    $this->resetEntityCache();

    $this->addTestEntity(6, array(
      'prices' => array('3.5', '3.25', '3.75', '3.5'),
      'type' => 'item',
    ));

    $this->indexItems($this->indexId);

    $query = $this->buildSearch(NULL, array('prices,3.25'));
    $results = $query->execute();
    $this->assertResults(array(6), $results, 'Filter on decimal field');

    $query = $this->buildSearch(NULL, array('prices,3.5'));
    $results = $query->execute();
    $this->assertResults(array(6), $results, 'Filter on decimal field');

    // Use the "prices" field, since we've added it now, to also check for
    // proper handling of (NOT) BETWEEN for multi-valued fields.
    $query = $this->buildSearch()
      ->addCondition('prices', array(3.6, 3.8), 'BETWEEN');
    $results = $query->execute();
    $this->assertResults(array(6), $results, 'BETWEEN filter on multi-valued field');

    $query = $this->buildSearch()
      ->addCondition('prices', array(3.6, 3.8), 'NOT BETWEEN');
    $results = $query->execute();
    $this->assertResults(array(1, 2, 3, 4, 5), $results, 'NOT BETWEEN filter on multi-valued field');
  }

  /**
   * Regression tests for problems with taxonomy term parent.
   *
   * @see https://www.drupal.org/node/2284199
   */
  protected function regressionTest2284199() {
    $this->addTestEntity(7, array('type' => 'item'));

    $count = $this->indexItems($this->indexId);
    $this->assertEquals(1, $count, 'Indexing an item with an empty value for a non string field worked.');
  }

  /**
   * Regression tests for strings longer than 50 chars.
   *
   * @see https://www.drupal.org/node/2471509
   * @see https://www.drupal.org/node/2616268
   */
  protected function regressionTest2471509() {
    $index = $this->getIndex();
    $this->addField($index, 'body');
    $index->save();
    $this->indexItems($this->indexId);

    $this->addTestEntity(8, array(
      'name' => 'Article with long body',
      'type' => 'article',
      'body' => 'astringlongerthanfiftycharactersthatcantbestoredbythedbbackend',
    ));
    $count = $this->indexItems($this->indexId);
    $this->assertEquals(1, $count, 'Indexing an item with a word longer than 50 characters worked.');

    $index = $this->getIndex();
    $index->getField('body')->setType('string');
    $index->save();
    $count = $this->indexItems($this->indexId);
    $this->assertEquals(count($this->entities), $count, 'Switching type from text to string worked.');

    // For a string field, 50 characters shouldn't be a problem.
    $query = $this->buildSearch(NULL, array('body,astringlongerthanfiftycharactersthatcantbestoredbythedbbackend'));
    $results = $query->execute();
    $this->assertResults(array(8), $results, 'Filter on new string field');

    $index->removeField('body');
    $index->save();
  }

  /**
   * Regression tests for multibyte characters exceeding 50 byte.
   *
   * @see https://www.drupal.org/node/2616804
   */
  protected function regressionTests2616804() {
    // The word has 28 Unicode characters but 56 bytes. Verify that it is still
    // indexed correctly.
    $mb_word = 'äöüßáŧæøðđŋħĸµäöüßáŧæøðđŋħĸµ';
    // We put the word 8 times into the body so we can also verify that the 255
    // character limit for strings counts characters, not bytes.
    $mb_body = implode(' ', array_fill(0, 8, $mb_word));
    $this->addTestEntity(9, array(
      'name' => 'Test item 9',
      'type' => 'item',
      'body' => $mb_body,
    ));
    $entity_count = count($this->entities);
    $count = $this->indexItems($this->indexId);
    $this->assertEquals($entity_count, $count, 'Indexing an item with a word with 28 multi-byte characters worked.');

    $query = $this->buildSearch($mb_word);
    $results = $query->execute();
    $this->assertResults(array(9), $results, 'Search for word with 28 multi-byte characters');

    $query = $this->buildSearch($mb_word . 'ä');
    $results = $query->execute();
    $this->assertResults(array(), $results, 'Search for unknown word with 29 multi-byte characters');

    // Test the same body when indexed as a string (255 characters limit should
    // not be reached).
    $index = $this->getIndex();
    $index->getField('body')->setType('string');
    $index->save();
    $count = $index->indexItems();
    $this->assertEquals($entity_count, $count, 'Switching type from text to string worked.');

    $query = $this->buildSearch(NULL, array("body,$mb_body"));
    $results = $query->execute();
    $this->assertResults(array(9), $results, 'Search for body with 231 multi-byte characters');

    $query = $this->buildSearch(NULL, array("body,{$mb_body}ä"));
    $results = $query->execute();
    $this->assertResults(array(), $results, 'Search for unknown body with 232 multi-byte characters');

    $index->getField('body')->setType('text');
    $index->save();
  }

  /**
   * Checks the correct handling of an index without fields.
   *
   * @return \Drupal\search_api\IndexInterface
   *   The created test index.
   */
  protected function checkIndexWithoutFields() {
    $index = Index::create(array(
      'id' => 'test_index_2',
      'name' => 'Test index 2',
      'status' => TRUE,
      'server' => $this->serverId,
      'datasource_settings' => array(
        'entity:entity_test_mulrev_changed' => array(
          'plugin_id' => 'entity:entity_test_mulrev_changed',
          'settings' => array(),
        ),
      ),
      'tracker_settings' => array(
        'default' => array(
          'plugin_id' => 'default',
          'settings' => array(),
        ),
      ),
    ));
    $index->save();

    $indexed_count = $this->indexItems($index->id());
    $this->assertEquals(count($this->entities), $indexed_count);

    $search_count = $index->query()->execute()->getResultCount();
    $this->assertEquals(count($this->entities), $search_count);

    return $index;
  }

  /**
   * Asserts that the given result set complies with expectations.
   *
   * @param int[] $result_ids
   *   The expected result item IDs, as raw entity IDs.
   * @param \Drupal\search_api\Query\ResultSetInterface $results
   *   The returned result set.
   * @param string $search_label
   *   (optional) A label for the search to include in assertion messages.
   * @param string[] $ignored
   *   (optional) The ignored keywords that should be present, if any.
   * @param string[] $warnings
   *   (optional) The ignored warnings that should be present, if any.
   */
  protected function assertResults(array $result_ids, ResultSetInterface $results, $search_label = 'Search', array $ignored = array(), array $warnings = array()) {
    $this->assertEquals(count($result_ids), $results->getResultCount(), "$search_label returned correct number of results.");
    if ($result_ids) {
      $this->assertEquals($this->getItemIds($result_ids), array_keys($results->getResultItems()), "$search_label returned correct results.");
    }
    $this->assertEquals($ignored, $results->getIgnoredSearchKeys());
    $this->assertEquals($warnings, $results->getWarnings());
  }

  /**
   * Retrieves the search server used by this test.
   *
   * @return \Drupal\search_api\ServerInterface
   *   The search server.
   */
  protected function getServer() {
    return Server::load($this->serverId);
  }

  /**
   * Retrieves the search index used by this test.
   *
   * @return \Drupal\search_api\IndexInterface
   *   The search index.
   */
  protected function getIndex() {
    return Index::load($this->indexId);
  }

  /**
   * Adds a field to a search index.
   *
   * The index will not be saved automatically.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The search index.
   * @param string $property_name
   *   The property's name.
   * @param string $type
   *   (optional) The field type.
   */
  protected function addField(IndexInterface $index, $property_name, $type = 'text') {
    $field_info = array(
      'label' => $property_name,
      'type' => $type,
      'datasource_id' => 'entity:entity_test_mulrev_changed',
      'property_path' => $property_name,
    );
    $field = Utility::createField($index, $property_name, $field_info);
    $index->addField($field);
    $index->save();
  }

  /**
   * Resets the entity cache for the specified entity.
   *
   * @param string $type
   *   (optional) The type of entity whose cache should be reset. Either "index"
   *   or "server".
   */
  protected function resetEntityCache($type = 'index') {
    $entity_type_id = 'search_api_' . $type;
    \Drupal::entityTypeManager()
      ->getStorage($entity_type_id)
      ->resetCache(array($this->{$type . 'Id'}));
  }

}
