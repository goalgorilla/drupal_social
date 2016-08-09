<?php

namespace Drupal\Tests\search_api\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api_test\PluginTestTrait;

/**
 * Tests whether changes for the server are processed correctly.
 *
 * @group search_api
 */
class ServerChangesTest extends KernelTestBase {

  use PluginTestTrait;

  /**
   * The test server.
   *
   * @var \Drupal\search_api\ServerInterface
   */
  protected $server;

  /**
   * The test index.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * The content entity datasource.
   *
   * @var \Drupal\search_api\Datasource\DatasourceInterface
   */
  protected $datasource;

  /**
   * {@inheritdoc}
   */
  public static $modules = array(
    'search_api',
    'search_api_test',
    'user',
    'system',
  );

  /**
   * The task manager to use for the tests.
   *
   * @var \Drupal\search_api\Task\TaskManagerInterface
   */
  protected $taskManager;

  /**
   * The server task manager to use for the tests.
   *
   * @var \Drupal\search_api\Task\ServerTaskManagerInterface
   */
  protected $serverTaskManager;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installSchema('search_api', array('search_api_item', 'search_api_task'));
    $this->installSchema('system', array('key_value_expire'));

    // Set tracking page size so tracking will work properly.
    \Drupal::configFactory()
      ->getEditable('search_api.settings')
      ->set('tracking_page_size', 100)
      ->save();

    // Create a test server.
    $this->server = Server::create(array(
      'name' => 'Test Server',
      'id' => 'test_server',
      'status' => 1,
      'backend' => 'search_api_test',
    ));
    $this->server->save();

    // Create a test index.
    $this->index = Index::create(array(
      'name' => 'Test index',
      'id' => 'test_index',
      'status' => 1,
      'datasource_settings' => array(
        'entity:user' => array(
          'plugin_id' => 'entity:user',
          'settings' => array(),
        ),
      ),
      'tracker_settings' => array(
        'default' => array(
          'plugin_id' => 'default',
          'settings' => array(),
        ),
      ),
      'server' => $this->server->id(),
      'options' => array('index_directly' => FALSE),
    ));

    // Reset the list of called backend methods.
    $this->getCalledMethods('backend');
  }

  /**
   * Tests adding and removing of indexes.
   */
  public function testAddRemoveIndex() {
    $this->index->save();
    $this->index->setServer(NULL)->save();
    $this->index->setServer($this->server)->enable()->save();
    $this->server->disable()->save();
    $this->index->setServer(NULL)->save();
    $this->server->enable()->save();
    $this->index->setServer($this->server)->enable()->save();
    $this->index->delete();

    $methods = $this->getCalledMethods('backend');
    $methods = array_intersect($methods, array('addIndex', 'removeIndex'));
    $expected = array(
      'addIndex',
      'removeIndex',
      'addIndex',
      'removeIndex',
      'addIndex',
      'removeIndex',
    );
    $this->assertEquals($expected, array_values($methods));
  }

}
