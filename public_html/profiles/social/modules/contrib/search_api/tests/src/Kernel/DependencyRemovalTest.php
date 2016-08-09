<?php

namespace Drupal\Tests\search_api\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\Utility;
use Drupal\search_api_test\PluginTestTrait;

/**
 * Tests what happens when an index's dependencies are removed.
 *
 * @group search_api
 */
class DependencyRemovalTest extends KernelTestBase {

  use PluginTestTrait;

  /**
   * A search index.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * A config entity, to be used as a dependency in the tests.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityInterface
   */
  protected $dependency;

  /**
   * {@inheritdoc}
   */
  public static $modules = array(
    'user',
    'system',
    'field',
    'search_api',
    'search_api_test',
  );

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->installSchema('search_api', 'search_api_task');

    \Drupal::configFactory()->getEditable('search_api.settings')
      ->set('default_tracker', 'default')
      ->save();

    // Create the index object, but don't save it yet since we want to change
    // its settings anyways in every test.
    $this->index = Index::create(array(
      'id' => 'test_index',
      'name' => 'Test index',
      'tracker_settings' => array(
        'default' => array(
          'plugin_id' => 'default',
          'settings' => array(),
        ),
      ),
      'datasource_settings' => array(
        'entity:user' => array(
          'plugin_id' => 'entity:user',
          'settings' => array(),
        ),
      ),
    ));

    // Use a search server as the dependency, since we have that available
    // anyways. The entity type should not matter at all, though.
    $this->dependency = Server::create(array(
      'id' => 'dependency',
      'name' => 'Test dependency',
      'backend' => 'search_api_test',
    ));
    $this->dependency->save();
  }

  /**
   * Tests index with a field dependency that gets removed.
   */
  public function testFieldDependency() {
    // Add new field storage and field definitions.
    /** @var \Drupal\field\FieldStorageConfigInterface $field_storage */
    $field_storage = FieldStorageConfig::create(array(
      'field_name' => 'field_search',
      'type' => 'string',
      'entity_type' => 'user',
    ));
    $field_storage->save();
    $field_search = FieldConfig::create(array(
      'field_name' => 'field_search',
      'field_type' => 'string',
      'entity_type' => 'user',
      'bundle' => 'user',
      'label' => 'Search Field',
    ));
    $field_search->save();

    // Create a Search API field/item and add it to the current index.
    $field = Utility::createFieldFromProperty($this->index, $field_storage->getPropertyDefinition('value'), 'entity:user', 'field_search', NULL, 'string');
    $field->setLabel('Search Field');
    $this->index->addField($field);
    $this->index->save();

    // New field has been added to the list of dependencies.
    $config_dependencies = \Drupal::config('search_api.index.' . $this->index->id())->get('dependencies.config');
    $this->assertContains($field_storage->getConfigDependencyName(), $config_dependencies);

    // Remove a dependent field.
    $field_storage->delete();

    // Index has not been deleted and index dependencies were updated.
    $this->reloadIndex();
    $dependencies = \Drupal::config('search_api.index.' . $this->index->id())->get('dependencies');
    $this->assertFalse(isset($dependencies['config'][$field_storage->getConfigDependencyName()]));
  }

  /**
   * Tests a backend with a dependency that gets removed.
   *
   * If the dependency does not get removed, proper cascading to the index is
   * also verified.
   *
   * @param bool $remove_dependency
   *   Whether to remove the dependency from the backend when the object
   *   depended on is deleted.
   *
   * @dataProvider dependencyTestDataProvider
   */
  public function testBackendDependency($remove_dependency) {
    $dependency_key = $this->dependency->getConfigDependencyKey();
    $dependency_name = $this->dependency->getConfigDependencyName();

    // Create a server using the test backend, and set the dependency in the
    // configuration.
    /** @var \Drupal\search_api\ServerInterface $server */
    $server = Server::create(array(
      'id' => 'test_server',
      'name' => 'Test server',
      'backend' => 'search_api_test',
      'backend_config' => array(
        'dependencies' => array(
          $dependency_key => array(
            $dependency_name,
          ),
        ),
      ),
    ));
    $server->save();
    $server_dependency_key = $server->getConfigDependencyKey();
    $server_dependency_name = $server->getConfigDependencyName();

    // Set the server on the index and save that, too. However, we don't want
    // the index enabled, since that would lead to all kinds of overhead which
    // is completely irrelevant for this test.
    $this->index->setServer($server);
    $this->index->disable();
    $this->index->save();

    // Check that the dependencies were calculated correctly.
    $server_dependencies = $server->getDependencies();
    $this->assertContains($dependency_name, $server_dependencies[$dependency_key], 'Backend dependency correctly inserted');
    $index_dependencies = $this->index->getDependencies();
    $this->assertContains($server_dependency_name, $index_dependencies[$server_dependency_key], 'Server dependency correctly inserted');

    // Tell the backend plugin whether it should successfully remove the
    // dependency.
    $this->setReturnValue('backend', 'onDependencyRemoval', $remove_dependency);

    // Delete the backend's dependency.
    $this->dependency->delete();

    // Reload the index and check it's still there.
    $this->reloadIndex();
    $this->assertInstanceOf('Drupal\search_api\IndexInterface', $this->index, 'Index not removed');

    // Reload the server.
    $storage = \Drupal::entityTypeManager()->getStorage('search_api_server');
    $storage->resetCache();
    $server = $storage->load($server->id());

    if ($remove_dependency) {
      $this->assertInstanceOf('Drupal\search_api\ServerInterface', $server, 'Server was not removed');
      $this->assertArrayNotHasKey('dependencies', $server->get('backend_config'), 'Backend config was adapted');
      // @todo Logically, this should not be changed: if the server does not get
      //   removed, there is no need to adapt the index's configuration.
      //   However, the way this config dependency cascading is actually
      //   implemented in
      //   \Drupal\Core\Config\ConfigManager::getConfigEntitiesToChangeOnDependencyRemoval()
      //   does not seem to follow that logic, but just computes the complete
      //   tree of dependencies once and operates generally on the assumption
      //   that all of them will be deleted. See #2642374.
//      $this->assertEquals($server->id(), $this->index->getServerId(), "Index's server was not changed");
    }
    else {
      $this->assertNull($server, 'Server was removed');
      $this->assertEquals(NULL, $this->index->getServerId(), 'Index server was changed');
    }
  }

  /**
   * Tests a datasource with a dependency that gets removed.
   *
   * @param bool $remove_dependency
   *   Whether to remove the dependency from the datasource when the object
   *   depended on is deleted.
   *
   * @dataProvider dependencyTestDataProvider
   */
  public function testDatasourceDependency($remove_dependency) {
    // Add the datasource to the index and save it. The datasource configuration
    // contains the dependencies it will return – in our case, we use the test
    // server.
    $dependency_key = $this->dependency->getConfigDependencyKey();
    $dependency_name = $this->dependency->getConfigDependencyName();

    // Also index users, to verify that they are unaffected by the processor.
    $datasources = $this->index->createPlugins('datasource', array('entity:user', 'search_api_test'), array(
      'search_api_test' => array(
        $dependency_key => array($dependency_name),
      ),
    ));
    $this->index->setDatasources($datasources);

    $this->index->save();

    // Check the dependencies were calculated correctly.
    $dependencies = $this->index->getDependencies();
    $this->assertContains($dependency_name, $dependencies[$dependency_key], 'Datasource dependency correctly inserted');

    // Tell the datasource plugin whether it should successfully remove the
    // dependency.
    $this->setReturnValue('datasource', 'onDependencyRemoval', $remove_dependency);

    // Delete the datasource's dependency.
    $this->dependency->delete();

    // Reload the index and check it's still there.
    $this->reloadIndex();
    $this->assertInstanceOf('Drupal\search_api\IndexInterface', $this->index, 'Index not removed');

    // Make sure the dependency has been removed, one way or the other.
    $dependencies = $this->index->getDependencies();
    $dependencies += array($dependency_key => array());
    $this->assertNotContains($dependency_name, $dependencies[$dependency_key], 'Datasource dependency removed from index');

    // Depending on whether the plugin should have removed the dependency or
    // not, make sure the right action was taken.
    $datasources = $this->index->getDatasources();
    if ($remove_dependency) {
      $this->assertArrayHasKey('search_api_test', $datasources, 'Datasource not removed');
      $this->assertEmpty($datasources['search_api_test']->getConfiguration(), 'Datasource settings adapted');
    }
    else {
      $this->assertArrayNotHasKey('search_api_test', $datasources, 'Datasource removed');
    }
  }

  /**
   * Tests removing the (hard) dependency of the index's single datasource.
   */
  public function testSingleDatasourceDependency() {
    // Add the datasource to the index and save it. The datasource configuration
    // contains the dependencies it will return – in our case, we use the test
    // server.
    $dependency_key = $this->dependency->getConfigDependencyKey();
    $dependency_name = $this->dependency->getConfigDependencyName();
    $datasources['search_api_test'] = $this->index->createPlugin('datasource', 'search_api_test', array(
      $dependency_key => array($dependency_name),
    ));
    $this->index->setDatasources($datasources);

    $this->index->save();

    // Since in this test the index will be removed, we need a mock key/value
    // store (the index will purge any unsaved configuration of it upon
    // deletion, which uses a "user-shared temp store", which in turn uses a
    // key/value store).
    $mock = $this->getMock('Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface');
    $mock_factory = $this->getMock('Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface');
    $mock_factory->method('get')->willReturn($mock);
    $this->container->set('keyvalue.expirable', $mock_factory);

    // Delete the datasource's dependency.
    $this->dependency->delete();

    // Reload the index to ensure it was deleted.
    $this->reloadIndex();
    $this->assertNull($this->index, 'Index was removed');
  }

  /**
   * Tests a processor with a dependency that gets removed.
   *
   * @param bool $remove_dependency
   *   Whether to remove the dependency from the processor when the object
   *   depended on is deleted.
   *
   * @dataProvider dependencyTestDataProvider
   */
  public function testProcessorDependency($remove_dependency) {
    // Add the processor to the index and save it. The processor configuration
    // contains the dependencies it will return – in our case, we use the test
    // server.
    $dependency_key = $this->dependency->getConfigDependencyKey();
    $dependency_name = $this->dependency->getConfigDependencyName();

    /** @var \Drupal\search_api\Processor\ProcessorInterface $processor */
    $processor = $this->index->createPlugin('processor', 'search_api_test', array(
      $dependency_key => array($dependency_name),
    ));
    $this->index->addProcessor($processor);
    $this->index->save();

    // Check the dependencies were calculated correctly.
    $dependencies = $this->index->getDependencies();
    $this->assertContains($dependency_name, $dependencies[$dependency_key], 'Processor dependency correctly inserted');

    // Tell the processor plugin whether it should successfully remove the
    // dependency.
    $this->setReturnValue('processor', 'onDependencyRemoval', $remove_dependency);

    // Delete the processor's dependency.
    $this->dependency->delete();

    // Reload the index and check it's still there.
    $this->reloadIndex();
    $this->assertInstanceOf('Drupal\search_api\IndexInterface', $this->index, 'Index not removed');

    // Make sure the dependency has been removed, one way or the other.
    $dependencies = $this->index->getDependencies();
    $dependencies += array($dependency_key => array());
    $this->assertNotContains($dependency_name, $dependencies[$dependency_key], 'Processor dependency removed from index');

    // Depending on whether the plugin should have removed the dependency or
    // not, make sure the right action was taken.
    $processors = $this->index->getProcessors();
    if ($remove_dependency) {
      $this->assertArrayHasKey('search_api_test', $processors, 'Processor not removed');
      $this->assertEmpty($processors['search_api_test']->getConfiguration(), 'Processor settings adapted');
    }
    else {
      $this->assertArrayNotHasKey('search_api_test', $processors, 'Processor removed');
    }
  }

  /**
   * Tests a tracker with a dependency that gets removed.
   *
   * @param bool $remove_dependency
   *   Whether to remove the dependency from the tracker when the object
   *   depended on is deleted.
   *
   * @dataProvider dependencyTestDataProvider
   */
  public function testTrackerDependency($remove_dependency) {
    // Set the tracker for the index and save it. The tracker configuration
    // contains the dependencies it will return – in our case, we use the test
    // server.
    $dependency_key = $this->dependency->getConfigDependencyKey();
    $dependency_name = $this->dependency->getConfigDependencyName();

    /** @var \Drupal\search_api\Tracker\TrackerInterface $tracker */
    $tracker = $this->index->createPlugin('tracker', 'search_api_test', array(
      'dependencies' => array(
        $dependency_key => array(
          $dependency_name,
        ),
      ),
    ));
    $this->index->setTracker($tracker);
    $this->index->save();

    // Check the dependencies were calculated correctly.
    $dependencies = $this->index->getDependencies();
    $this->assertContains($dependency_name, $dependencies[$dependency_key], 'Tracker dependency correctly inserted');

    // Tell the datasource plugin whether it should successfully remove the
    // dependency.
    $this->setReturnValue('tracker', 'onDependencyRemoval', $remove_dependency);

    // Delete the tracker's dependency.
    $this->dependency->delete();

    // Reload the index and check it's still there.
    $this->reloadIndex();
    $this->assertInstanceOf('Drupal\search_api\IndexInterface', $this->index, 'Index not removed');

    // Make sure the dependency has been removed, one way or the other.
    $dependencies = $this->index->getDependencies();
    $dependencies += array($dependency_key => array());
    $this->assertNotContains($dependency_name, $dependencies[$dependency_key], 'Tracker dependency removed from index');

    // Depending on whether the plugin should have removed the dependency or
    // not, make sure the right action was taken.
    $tracker_instance = $this->index->getTrackerInstance();
    $tracker_id = $tracker_instance->getPluginId();
    $tracker_config = $tracker_instance->getConfiguration();
    if ($remove_dependency) {
      $this->assertEquals('search_api_test', $tracker_id, 'Tracker not reset');
      $this->assertEmpty($tracker_config['dependencies'], 'Tracker settings adapted');
    }
    else {
      $this->assertEquals('default', $tracker_id, 'Tracker was reset');
      $this->assertEmpty($tracker_config, 'Tracker settings were cleared');
    }
  }

  /**
   * Data provider for this class's test methods.
   *
   * If $remove_dependency is TRUE, in Plugin::onDependencyRemoval() it clears
   * its configuration (and thus its dependency, in those test plugins) and
   * returns TRUE, which the index will take as "all OK, dependency removed" and
   * leave the plugin where it is, only with updated configuration.
   *
   * If $remove_dependency is FALSE, Plugin::onDependencyRemoval() will do
   * nothing and just return FALSE, the index says "oh, that plugin still has
   * that removed dependency, so I should better remove the plugin" and the
   * plugin gets removed.
   *
   * @return array
   *   An array of argument arrays for this class's test methods.
   */
  public function dependencyTestDataProvider() {
    return array(
      'Remove dependency' => array(TRUE),
      'Keep dependency' => array(FALSE),
    );
  }

  /**
   * Tests whether module dependencies are handled correctly.
   */
  public function testModuleDependency() {
    // Test with all types of plugins at once.
    /** @var \Drupal\search_api\Datasource\DatasourceInterface $datasource */
    $datasource = $this->index->createPlugin('datasource', 'search_api_test');
    $this->index->addDatasource($datasource);
    $datasource = $this->index->createPlugin('datasource', 'entity:user');
    $this->index->addDatasource($datasource);

    /** @var \Drupal\search_api\Processor\ProcessorInterface $processor */
    $processor = $this->index->createPlugin('processor', 'search_api_test');
    $this->index->addProcessor($processor);

    /** @var \Drupal\search_api\Tracker\TrackerInterface $tracker */
    $tracker = $this->index->createPlugin('tracker', 'search_api_test');
    $this->index->setTracker($tracker);

    $this->index->save();

    // Check the dependencies were calculated correctly.
    $dependencies = $this->index->getDependencies();
    $this->assertContains('search_api_test', $dependencies['module'], 'Module dependency correctly inserted');

    // When the index resets the tracker, it needs to know the ID of the default
    // tracker.
    \Drupal::configFactory()->getEditable('search_api.settings')
      ->set('default_tracker', 'default')
      ->save();

    // Disabling modules in Kernel tests normally doesn't trigger any kind of
    // reaction, just removes it from the list of modules (e.g., to avoid
    // calling of a hook). Therefore, we have to trigger that behavior
    // ourselves.
    \Drupal::getContainer()->get('config.manager')->uninstall('module', 'search_api_test');

    // Reload the index and check it's still there.
    $this->reloadIndex();
    $this->assertInstanceOf('Drupal\search_api\IndexInterface', $this->index, 'Index not removed');

    // Make sure the dependency has been removed.
    $dependencies = $this->index->getDependencies();
    $dependencies += array('module' => array());
    $this->assertNotContains('search_api_test', $dependencies['module'], 'Module dependency removed from index');

    // Make sure all the plugins have been removed.
    $this->assertNotContains('search_api_test', $this->index->getDatasources(), 'Datasource was removed');
    $this->assertArrayNotHasKey('search_api_test', $this->index->getProcessors(), 'Processor was removed');
    $this->assertEquals('default', $this->index->getTrackerId(), 'Tracker was reset');
  }

  /**
   * Tests whether dependencies of used data types are handled correctly.
   *
   * @param string $dependency_type
   *   The type of dependency that should be set on the data type (and then
   *   removed): "module" or "config".
   *
   * @dataProvider dataTypeDependencyTestDataProvider
   */
  public function testDataTypeDependency($dependency_type) {
    switch ($dependency_type) {
      case 'module':
        $type = 'search_api_test';
        $config_dependency_key = 'module';
        $config_dependency_name = 'search_api_test';
        break;

      case 'config':
        $type = 'search_api_test_altering';
        $config_dependency_key = $this->dependency->getConfigDependencyKey();
        $config_dependency_name = $this->dependency->getConfigDependencyName();
        \Drupal::state()->set('search_api_test.data_type.dependencies', array(
          $config_dependency_key => array(
            $config_dependency_name,
          ),
        ));
        break;

      default:
        $this->fail();
        return;
    }

    // Use the "user" datasource (to not get a module dependency via that) and
    // add a field with the given data type.
    $datasources = $this->index->createPlugins('datasource', array('entity:user'));
    $this->index->setDatasources($datasources);
    $field = Utility::createField($this->index, 'uid', array(
      'label' => 'ID',
      'datasource_id' => 'entity:user',
      'property_path' => 'uid',
      'type' => $type,
    ));
    $this->index->addField($field);
    // Set the server to NULL to not have a dependency on that by default.
    $this->index->setServer(NULL);
    $this->index->save();

    // Check the dependencies were calculated correctly.
    $dependencies = $this->index->getDependencies();
    $dependencies += array($config_dependency_key => array());
    $this->assertContains($config_dependency_name, $dependencies[$config_dependency_key], 'Data type dependency correctly inserted');

    switch ($dependency_type) {
      case 'module':
        // Disabling modules in Kernel tests normally doesn't trigger any kind of
        // reaction, just removes it from the list of modules (e.g., to avoid
        // calling of a hook). Therefore, we have to trigger that behavior
        // ourselves.
        \Drupal::getContainer()
          ->get('config.manager')
          ->uninstall('module', 'search_api_test');
        break;

      case 'config':
        $this->dependency->delete();
        break;
    }

    // Reload the index and check it's still there.
    $this->reloadIndex();
    $this->assertInstanceOf('Drupal\search_api\IndexInterface', $this->index, 'Index not removed');

    // Make sure the dependency has been removed.
    $dependencies = $this->index->getDependencies();
    $dependencies += array($config_dependency_key => array());
    $this->assertNotContains($config_dependency_name, $dependencies[$config_dependency_key], 'Data type dependency correctly removed');

    // Make sure the field type has changed.
    $field = $this->index->getField('uid');
    $this->assertNotNull($field, 'Field was not removed');
    $this->assertEquals('string', $field->getType(), 'Field type was changed to fallback type');
  }

  /**
   * Data provider for testDataTypeDependency().
   *
   * @return array
   *   An array of argument arrays for
   *   \Drupal\Tests\search_api\Kernel\DependencyRemovalTest::testDataTypeDependency().
   */
  public function dataTypeDependencyTestDataProvider() {
    return array(
      'Module dependency' => array('module'),
      'Config dependency' => array('config'),
    );
  }

  /**
   * Reloads the index with the latest copy from storage.
   */
  protected function reloadIndex() {
    $storage = \Drupal::entityTypeManager()->getStorage('search_api_index');
    $storage->resetCache();
    $this->index = $storage->load($this->index->id());
  }

}
