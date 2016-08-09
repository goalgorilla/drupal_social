<?php

namespace Drupal\Tests\search_api_db\Kernel;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Database\Database;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api_db\Plugin\search_api\backend\Database as BackendDatabase;
use Drupal\Tests\search_api\Kernel\BackendTestBase;

/**
 * Tests index and search capabilities using the Database search backend.
 *
 * @see \Drupal\search_api_db\Plugin\search_api\backend\Database
 *
 * @group search_api
 */
class BackendTest extends BackendTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = array(
    'search_api_db',
    'search_api_test_db',
  );

  /**
   * {@inheritdoc}
   */
  protected $serverId = 'database_search_server';

  /**
   * {@inheritdoc}
   */
  protected $indexId = 'database_search_index';

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    //
    \Drupal::database()->schema()->createTable('search_api_db_database_search_index', array(
      'fields' => array(
        'id' => array(
          'type' => 'int',
        ),
      ),
    ));

    $this->installConfig(array('search_api_test_db'));
  }

  /**
   * {@inheritdoc}
   */
  protected function checkBackendSpecificFeatures() {
    $this->checkMultiValuedInfo();
    $this->editServerPartial();
    $this->searchSuccessPartial();
    $this->editServerMinChars();
    $this->searchSuccessMinChars();
    $this->checkUnknownOperator();
  }

  /**
   * {@inheritdoc}
   */
  protected function backendSpecificRegressionTests() {
    $this->regressionTest2557291();
    $this->regressionTest2511860();
  }

  /**
   * Tests that all tables and all columns have been created.
   */
  protected function checkServerBackend() {
    $db_info = $this->getIndexDbInfo();
    $normalized_storage_table = $db_info['index_table'];
    $field_infos = $db_info['field_tables'];

    $expected_fields = array(
      'body',
      'category',
      'created',
      'id',
      'keywords',
      'name',
      'search_api_datasource',
      'search_api_language',
      'type',
      'width',
    );
    $actual_fields = array_keys($field_infos);
    sort($actual_fields);
    $this->assertEquals($expected_fields, $actual_fields, 'All expected field tables were created.');

    $this->assertTrue(\Drupal::database()->schema()->tableExists($normalized_storage_table), 'Normalized storage table exists');
    foreach ($field_infos as $field_id => $field_info) {
      if ($field_id != 'search_api_id') {
        $this->assertTrue(\Drupal::database()
          ->schema()
          ->tableExists($field_info['table']));
      }
      else {
        $this->assertEmpty($field_info['table']);
      }
      $this->assertTrue(\Drupal::database()->schema()->fieldExists($normalized_storage_table, $field_info['column']), new FormattableMarkup('Field column %column exists', array('%column' => $field_info['column'])));
    }
  }

  /**
   * Checks whether changes to the index's fields are picked up by the server.
   */
  protected function updateIndex() {
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = $this->getIndex();

    // Remove a field from the index and check if the change is matched in the
    // server configuration.
    $field = $index->getField('keywords');
    if (!$field) {
      throw new \Exception();
    }
    $index->removeField('keywords');
    $index->save();

    $index_fields = array_keys($index->getFields());
    // Include the three "magic" fields we're indexing with the DB backend.
    $index_fields[] = 'search_api_datasource';
    $index_fields[] = 'search_api_language';

    $db_info = $this->getIndexDbInfo();
    $server_fields = array_keys($db_info['field_tables']);

    sort($index_fields);
    sort($server_fields);
    $this->assertEquals($index_fields, $server_fields);

    // Add the field back for the next assertions.
    $index->addField($field)->save();
  }

  /**
   * Verifies that the generated table names are correct.
   */
  protected function checkTableNames() {
    $this->assertEquals('search_api_db_database_search_index_1', $this->getIndexDbInfo()['index_table']);
    $this->assertEquals('search_api_db_database_search_index_text', $this->getIndexDbInfo()['field_tables']['body']['table']);
  }

  /**
   * Verifies that the stored information about multi-valued fields is correct.
   */
  protected function checkMultiValuedInfo() {
    $db_info = $this->getIndexDbInfo();
    $field_info = $db_info['field_tables'];

    $fields = array(
      'name',
      'body',
      'type',
      'keywords',
      'category',
      'width',
      'search_api_datasource',
      'search_api_language',
    );
    $multi_valued = array(
      'name',
      'body',
      'keywords',
    );
    foreach ($fields as $field_id) {
      $this->assertArrayHasKey($field_id, $field_info, "Field info saved for field $field_id.");
      if (in_array($field_id, $multi_valued)) {
        $this->assertFalse(empty($field_info[$field_id]['multi-valued']), "Field $field_id is stored as multi-value.");
      }
      else {
        $this->assertTrue(empty($field_info[$field_id]['multi-valued']), "Field $field_id is not stored as multi-value.");
      }
    }
  }

  /**
   * Edits the server to enable partial matches.
   *
   * @param bool $enable
   *   (optional) Whether partial matching should be enabled or disabled.
   */
  protected function editServerPartial($enable = TRUE) {
    $server = $this->getServer();
    $backend_config = $server->getBackendConfig();
    $backend_config['partial_matches'] = $enable;
    $server->setBackendConfig($backend_config);
    $this->assertTrue((bool) $server->save(), 'The server was successfully edited.');
    $this->resetEntityCache();
  }

  /**
   * Tests whether partial searches work.
   */
  protected function searchSuccessPartial() {
    $results = $this->buildSearch('foobaz')->range(0, 1)->execute();
    $this->assertResults(array(1), $results, 'Partial search for »foobaz«');

    $results = $this->buildSearch('foo', array(), array(), FALSE)
      ->sort('search_api_relevance', QueryInterface::SORT_DESC)
      ->sort('id')
      ->execute();
    $this->assertResults(array(1, 2, 4, 3, 5), $results, 'Partial search for »foo«');

    $results = $this->buildSearch('foo tes')->execute();
    $this->assertResults(array(1, 2, 3, 4), $results, 'Partial search for »foo tes«');

    $results = $this->buildSearch('oob est')->execute();
    $this->assertResults(array(1, 2, 3), $results, 'Partial search for »oob est«');

    $results = $this->buildSearch('foo nonexistent')->execute();
    $this->assertResults(array(), $results, 'Partial search for »foo nonexistent«');

    $results = $this->buildSearch('bar nonexistent')->execute();
    $this->assertResults(array(), $results, 'Partial search for »foo nonexistent«');

    $keys = array(
      '#conjunction' => 'AND',
      'oob',
      array(
        '#conjunction' => 'OR',
        'est',
        'nonexistent',
      ),
    );
    $results = $this->buildSearch($keys)->execute();
    $this->assertResults(array(1, 2, 3), $results, 'Partial search for complex keys');

    $results = $this->buildSearch('foo', array('category,item_category'), array(), FALSE)
      ->sort('id', QueryInterface::SORT_DESC)
      ->execute();
    $this->assertResults(array(2, 1), $results, 'Partial search for »foo« with additional filter');

    $query = $this->buildSearch();
    $conditions = $query->createConditionGroup('OR');
    $conditions->addCondition('name', 'test');
    $conditions->addCondition('body', 'test');
    $query->addConditionGroup($conditions);
    $results = $query->execute();
    $this->assertResults(array(1, 2, 3, 4), $results, 'Partial search with multi-field fulltext filter');
  }

  /**
   * Edits the server to change the "Minimum word length" setting.
   */
  protected function editServerMinChars() {
    $server = $this->getServer();
    $backend_config = $server->getBackendConfig();
    $backend_config['min_chars'] = 4;
    $backend_config['partial_matches'] = FALSE;
    $server->setBackendConfig($backend_config);
    $success = (bool) $server->save();
    $this->assertTrue($success, 'The server was successfully edited.');

    $this->clearIndex();
    $this->indexItems($this->indexId);

    $this->resetEntityCache();
  }

  /**
   * Tests the results of some test searches with minimum word length of 4.
   */
  protected function searchSuccessMinChars() {
    $results = $this->getIndex()->query()->keys('test')->range(1, 2)->execute();
    $this->assertEquals(4, $results->getResultCount(), 'Search for »test« returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(4, 1)), array_keys($results->getResultItems()), 'Search for »test« returned correct result.');
    $this->assertEmpty($results->getIgnoredSearchKeys());
    $this->assertEmpty($results->getWarnings());

    $query = $this->buildSearch();
    $conditions = $query->createConditionGroup('OR');
    $conditions->addCondition('name', 'test');
    $conditions->addCondition('body', 'test');
    $query->addConditionGroup($conditions);
    $results = $query->execute();
    $this->assertResults(array(1, 2, 3, 4), $results, 'Search with multi-field fulltext filter');

    $results = $this->buildSearch(NULL, array('body,test foobar'))->execute();
    $this->assertResults(array(3), $results, 'Search with multi-term fulltext filter');

    $results = $this->getIndex()->query()->keys('test foo')->execute();
    $this->assertResults(array(2, 4, 1, 3), $results, 'Search for »test foo«', array('foo'));

    $results = $this->buildSearch('foo', array('type,item'))->execute();
    $this->assertResults(array(1, 2, 3), $results, 'Search for »foo«', array('foo'), array($this->t('No valid search keys were present in the query.')));

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
    $this->assertResults(array(3), $results, 'Complex search 1', array('baz', 'bar'));

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
    $this->assertResults(array(3), $results, 'Complex search 2', array('baz', 'bar'));

    $results = $this->buildSearch(NULL, array('keywords,orange'))->execute();
    $this->assertResults(array(1, 2, 5), $results, 'Filter query 1 on multi-valued field');

    $conditions = array(
      'keywords,orange',
      'keywords,apple',
    );
    $results = $this->buildSearch(NULL, $conditions)->execute();
    $this->assertResults(array(2), $results, 'Filter query 2 on multi-valued field');

    $results = $this->buildSearch()->addCondition('keywords', 'orange', '<>')->execute();
    $this->assertResults(array(3, 4), $results, 'Negated filter on multi-valued field');

    $results = $this->buildSearch()->addCondition('keywords', NULL)->execute();
    $this->assertResults(array(3), $results, 'Query with NULL filter');

    $results = $this->buildSearch()->addCondition('keywords', NULL, '<>')->execute();
    $this->assertResults(array(1, 2, 4, 5), $results, 'Query with NOT NULL filter');
  }

  /**
   * Checks that an unknown operator throws an exception.
   */
  protected function checkUnknownOperator() {
    try {
      $this->buildSearch()
        ->addCondition('id', 1, '!=')
        ->execute();
      $this->fail('Unknown operator "!=" did not throw an exception.');
    }
    catch (SearchApiException $e) {
      $this->assertTrue(TRUE, 'Unknown operator "!=" threw an exception.');
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkSecondServer() {
    /** @var \Drupal\search_api\ServerInterface $second_server */
    $second_server = Server::create(array(
      'id' => 'test2',
      'backend' => 'search_api_db',
      'backend_config' => array(
        'database' => 'default:default',
      ),
    ));
    $second_server->save();
    $query = $this->buildSearch();
    try {
      $second_server->search($query);
      $this->fail('Could execute a query for an index on a different server.');
    }
    catch (SearchApiException $e) {
      $this->assertTrue(TRUE, 'Executing a query for an index on a different server throws an exception.');
    }
    $second_server->delete();
  }

  /**
   * Tests the case-sensitivity of fulltext searches.
   *
   * @see https://www.drupal.org/node/2557291
   */
  protected function regressionTest2557291() {
    $results = $this->buildSearch('case')->execute();
    $this->assertResults(array(1), $results, 'Search for lowercase "case"');

    $results = $this->buildSearch('Case')->execute();
    $this->assertResults(array(1, 3), $results, 'Search for capitalized "Case"');

    $results = $this->buildSearch('CASE')->execute();
    $this->assertResults(array(), $results, 'Search for non-existent uppercase version of "CASE"');

    $results = $this->buildSearch('föö')->execute();
    $this->assertResults(array(1), $results, 'Search for keywords with umlauts');

    $results = $this->buildSearch('smile' . json_decode('"\u1F601"'))->execute();
    $this->assertResults(array(1), $results, 'Search for keywords with umlauts');

    $results = $this->buildSearch()->addCondition('keywords', 'grape', '<>')->execute();
    $this->assertResults(array(1, 3), $results, 'Negated filter on multi-valued field');
  }

  /**
   * Tests searching for multiple two-letter words.
   *
   * @see https://www.drupal.org/node/2511860
   */
  protected function regressionTest2511860() {
    $query = $this->buildSearch();
    $query->addCondition('body', 'ab xy');
    $results = $query->execute();
    $this->assertEquals(5, $results->getResultCount(), 'Fulltext filters on short words do not change the result.');

    $query = $this->buildSearch();
    $query->addCondition('body', 'ab ab');
    $results = $query->execute();
    $this->assertEquals(5, $results->getResultCount(), 'Fulltext filters on duplicate short words do not change the result.');
  }

  /**
   * {@inheritdoc}
   */
  protected function checkIndexWithoutFields() {
    $index = parent::checkIndexWithoutFields();

    $expected = array(
      'search_api_datasource',
      'search_api_language',
    );
    $db_info = $this->getIndexDbInfo($index->id());
    $info_fields = array_keys($db_info['field_tables']);
    sort($info_fields);
    $this->assertEquals($expected, $info_fields);

    return $index;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkModuleUninstall() {
    $db_info = $this->getIndexDbInfo();
    $normalized_storage_table = $db_info['index_table'];
    $field_tables = $db_info['field_tables'];

    // See whether clearing the server works.
    // Regression test for #2156151.
    $server = $this->getServer();
    $index = $this->getIndex();
    $server->deleteAllIndexItems($index);
    $query = $this->buildSearch();
    $results = $query->execute();
    $this->assertEquals(0, $results->getResultCount(), 'Clearing the server worked correctly.');
    $schema = Database::getConnection()->schema();
    $table_exists = $schema->tableExists($normalized_storage_table);
    $this->assertTrue($table_exists, 'The index tables were left in place.');

    // See whether disabling the index correctly removes all of its tables.
    $index->disable()->save();
    $db_info = $this->getIndexDbInfo();
    $this->assertNull($db_info, 'The index was successfully removed from the server.');
    $table_exists = $schema->tableExists($normalized_storage_table);
    $this->assertFalse($table_exists, 'The index tables were deleted.');
    foreach ($field_tables as $field_table) {
      $table_exists = $schema->tableExists($field_table['table']);
      $this->assertFalse($table_exists, "Field table {$field_table['table']} was successfully deleted.");
    }
    $index->enable()->save();

    // Remove first the index and then the server.
    $index->setServer();
    $index->save();

    $db_info = $this->getIndexDbInfo();
    $this->assertNull($db_info, 'The index was successfully removed from the server.');
    $table_exists = $schema->tableExists($normalized_storage_table);
    $this->assertFalse($table_exists, 'The index tables were deleted.');
    foreach ($field_tables as $field_table) {
      $table_exists = $schema->tableExists($field_table['table']);
      $this->assertFalse($table_exists, "Field table {$field_table['table']} was successfully deleted.");
    }

    // Re-add the index to see if the associated tables are also properly
    // removed when the server is deleted.
    $index->setServer($server);
    $index->save();
    $server->delete();

    $db_info = $this->getIndexDbInfo();
    $this->assertNull($db_info, 'The index was successfully removed from the server.');
    $table_exists = $schema->tableExists($normalized_storage_table);
    $this->assertFalse($table_exists, 'The index tables were deleted.');
    foreach ($field_tables as $field_table) {
      $table_exists = $schema->tableExists($field_table['table']);
      $this->assertFalse($table_exists, "Field table {$field_table['table']} was successfully deleted.");
    }

    // Uninstall the module.
    \Drupal::service('module_installer')->uninstall(array('search_api_db'), FALSE);
    $this->assertFalse(\Drupal::moduleHandler()->moduleExists('search_api_db'), 'The Database Search module was successfully uninstalled.');

    $tables = $schema->findTables('search_api_db_%');
    $expected = array(
      'search_api_db_database_search_index' => 'search_api_db_database_search_index',
    );
    $this->assertEquals($expected, $tables, 'All the tables of the the Database Search module have been removed.');
  }

  /**
   * Retrieves the database information for the test index.
   *
   * @param string|null $index_id
   *   (optional) The ID of the index whose database information should be
   *   retrieved.
   *
   * @return array
   *   The database information stored by the backend for the test index.
   */
  protected function getIndexDbInfo($index_id = NULL) {
    $index_id = $index_id ?: $this->indexId;
    return \Drupal::keyValue(BackendDatabase::INDEXES_KEY_VALUE_STORE_ID)
      ->get($index_id);
  }

}
