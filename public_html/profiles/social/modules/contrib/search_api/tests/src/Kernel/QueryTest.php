<?php

namespace Drupal\Tests\search_api\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\Query\Query;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api_test\PluginTestTrait;

/**
 * Tests query functionality.
 *
 * @group search_api
 */
class QueryTest extends KernelTestBase {

  use PluginTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = array(
    'search_api',
    'search_api_test',
    'search_api_test_hooks',
    'language',
    'user',
    'system',
    'entity_test',
  );

  /**
   * The search index used for testing.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installSchema('search_api', array('search_api_item', 'search_api_task'));
    $this->installEntitySchema('entity_test_mulrev_changed');

    // Set tracking page size so tracking will work properly.
    \Drupal::configFactory()
      ->getEditable('search_api.settings')
      ->set('tracking_page_size', 100)
      ->save();

    // Create a test server.
    $server = Server::create(array(
      'name' => 'Test Server',
      'id' => 'test_server',
      'status' => 1,
      'backend' => 'search_api_test',
    ));
    $server->save();

    // Create a test index.
    Index::create(array(
      'name' => 'Test Index',
      'id' => 'test_index',
      'status' => 1,
      'datasource_settings' => array(
        'search_api_test' => array(
          'plugin_id' => 'search_api_test',
          'settings' => array(),
        ),
      ),
      'processor_settings' => array(
        'search_api_test' => array(
          'plugin_id' => 'search_api_test',
          'settings' => array(),
        ),
      ),
      'tracker_settings' => array(
        'default' => array(
          'plugin_id' => 'default',
          'settings' => array(),
        ),
      ),
      'server' => $server->id(),
      'options' => array('index_directly' => FALSE),
    ))->save();
    $this->index = Index::load('test_index');
  }

  /**
   * Tests that processing levels are working correctly.
   *
   * @param int $level
   *   The processing level to test.
   * @param bool $hooks_and_processors_invoked
   *   (optional) Whether hooks and processors should be invoked with this
   *   processing level.
   *
   * @dataProvider testProcessingLevelDataProvider
   */
  public function testProcessingLevel($level, $hooks_and_processors_invoked = TRUE) {
    /** @var \Drupal\search_api\Processor\ProcessorInterface $processor */
    $processor = $this->container->get('plugin.manager.search_api.processor')
      ->createInstance('search_api_test', array('index' => $this->index));
    $this->index->addProcessor($processor)->save();

    $query = $this->index->query();
    if ($level != QueryInterface::PROCESSING_FULL) {
      $query->setProcessingLevel($level);
    }
    $this->assertEquals($level, $query->getProcessingLevel());
    $query->addTag('andrew_hill');

    $_SESSION['messages']['status'] = array();
    $query->execute();
    $messages = $_SESSION['messages']['status'];
    $_SESSION['messages']['status'] = array();

    $methods = $this->getCalledMethods('processor');
    if ($hooks_and_processors_invoked) {
      $expected = array(
        'Funky blue note',
        'Stepping into tomorrow',
        'Llama',
      );
      $this->assertEquals($expected, $messages);
      $this->assertTrue($query->getOption('tag query alter hook'));
      $this->assertContains('preprocessSearchQuery', $methods);
      $this->assertContains('postprocessSearchResults', $methods);
    }
    else {
      $this->assertEmpty($messages);
      $this->assertFalse($query->getOption('tag query alter hook'));
      $this->assertNotContains('preprocessSearchQuery', $methods);
      $this->assertNotContains('postprocessSearchResults', $methods);
    }
  }

  /**
   * Provides test data for the testProcessingLevel() method.
   *
   * @return array[]
   *   Arrays of method arguments for the
   *   \Drupal\Tests\search_api\Kernel\QueryTest::testProcessingLevel() method.
   */
  public function testProcessingLevelDataProvider() {
    return array(
      'none' => array(QueryInterface::PROCESSING_NONE, FALSE),
      'basic' => array(QueryInterface::PROCESSING_BASIC),
      'full' => array(QueryInterface::PROCESSING_FULL),
    );
  }

  /**
   * Tests that queries can be cloned.
   */
  public function testQueryCloning() {
    $query = $this->index->query();
    $this->assertEquals(0, $query->getResults()->getResultCount());
    $cloned_query = clone $query;
    $cloned_query->getResults()->setResultCount(1);
    $this->assertEquals(0, $query->getResults()->getResultCount());
    $this->assertEquals(1, $cloned_query->getResults()->getResultCount());
  }

  /**
   * Tests that serialization of queries works correctly.
   */
  public function testQuerySerialization() {
    $results_cache = $this->container->get('search_api.results_static_cache');
    $query = Query::create($this->index, $results_cache);
    $tags = array('tag1', 'tag2');
    $query->keys('foo bar')
      ->addCondition('field1', 'value', '<')
      ->addCondition('field2', array(15, 25), 'BETWEEN')
      ->addConditionGroup($query->createConditionGroup('OR', $tags)
        ->addCondition('field2', 'foo')
        ->addCondition('field3', 1, '<>')
      )
      ->sort('field1', Query::SORT_DESC)
      ->sort('field2');
    $query->setOption('option1', array('foo' => 'bar'));
    $translation = $this->container->get('string_translation');
    $query->setStringTranslation($translation);

    $cloned_query = clone $query;
    $unserialized_query = unserialize(serialize($query));
    $this->assertEquals($cloned_query, $unserialized_query);
  }

}
