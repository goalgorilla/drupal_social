<?php

namespace Drupal\Tests\search_api\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api_test\PluginTestTrait;

/**
 * Tests whether loading items works correctly.
 *
 * @group search_api
 */
class IndexLoadItemsTest extends KernelTestBase {

  use PluginTestTrait;

  /**
   * The test index object.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * {@inheritdoc}
   */
  public static $modules = array(
    'search_api',
    'search_api_test',
    'user',
  );

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installSchema('search_api', array('search_api_task'));

    $server = Server::create(array(
      'id' => 'test',
      'backend' => 'search_api_test',
    ));
    $this->index = Index::create(array(
      'tracker_settings' => array(
        'search_api_test' => array(
          'plugin_id' => 'search_api_test',
          'settings' => array(),
        ),
      ),
      'datasource_settings' => array(
        'search_api_test' => array(
          'plugin_id' => 'search_api_test',
          'settings' => array(),
        ),
      ),
    ));
    $this->index->setServer($server);
  }

  /**
   * Verifies that missing items are correctly detected and removed.
   */
  public function testMissingItems() {
    $state = \Drupal::state();

    $item_ids = array(
      'search_api_test/1',
      'search_api_test/2',
    );
    $items = $this->index->loadItemsMultiple($item_ids);
    $this->assertEquals(array(), $items, 'No items loaded from test datasource.');
    $methods = $this->getCalledMethods('tracker');
    $this->assertContains('trackItemsDeleted', $methods, 'Unknown items deleted from tracker.');
    $args = $this->getMethodArguments('tracker', 'trackItemsDeleted');
    $this->assertEquals(array($item_ids), $args, 'Correct items deleted from tracker.');
    $methods = $this->getCalledMethods('backend');
    $this->assertContains('deleteItems', $methods, 'Unknown items deleted from server.');

    // If an error occurs while retrieving the datasource (which will happen for
    // "unknown/1"), the items should not be deleted from tracking and the
    // server.
    $expected_deletions = $item_ids;
    $item_ids = array(
      'search_api_test/1',
      'search_api_test/2',
      'search_api_test/3',
      'unknown/1',
    );
    $this->setReturnValue('datasource', 'loadMultiple', array('3' => ''));
    $items = $this->index->loadItemsMultiple($item_ids);
    $expected_items = array('search_api_test/3' => '');
    $this->assertEquals($expected_items, $items, 'Expected items loaded from test datasource.');
    $methods = $this->getCalledMethods('tracker');
    $this->assertContains('trackItemsDeleted', $methods, 'Unknown items deleted from tracker.');
    $args = $this->getMethodArguments('tracker', 'trackItemsDeleted');
    $this->assertEquals(array($expected_deletions), $args, 'Correct items deleted from tracker.');
    $methods = $this->getCalledMethods('backend');
    $this->assertContains('deleteItems', $methods, 'Unknown items deleted from server.');
  }

}
