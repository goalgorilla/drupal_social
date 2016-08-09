<?php

namespace Drupal\search_api\Tests;

use Drupal\search_api\Entity\Index;
use Drupal\search_api_test\PluginTestTrait;

/**
 * Tests integration of hooks.
 *
 * @group search_api
 */
class HooksTest extends WebTestBase {

  use PluginTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = array(
    'node',
    'search_api',
    'search_api_test',
    'search_api_test_views',
    'search_api_test_hooks',
  );

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Create some nodes.
    $this->drupalCreateNode(array('type' => 'page', 'title' => 'node - 1'));
    $this->drupalCreateNode(array('type' => 'page', 'title' => 'node - 2'));
    $this->drupalCreateNode(array('type' => 'page', 'title' => 'node - 3'));
    $this->drupalCreateNode(array('type' => 'page', 'title' => 'node - 4'));

    // Do not use a batch for tracking the initial items after creating an
    // index when running the tests via the GUI. Otherwise, it seems Drupal's
    // Batch API gets confused and the test fails.
    if (php_sapi_name() != 'cli') {
      \Drupal::state()->set('search_api_use_tracking_batch', FALSE);
    }

    // Create an index and server to work with.
    $this->getTestServer();
    $index = $this->getTestIndex();

    // Add the test processor to the index so we can make sure that all expected
    // processor methods are called, too.
    /** @var \Drupal\search_api\Processor\ProcessorInterface $processor */
    $processor = $index->createPlugin('processor', 'search_api_test');
    $index->addProcessor($processor)->save();

    // Parts of this test actually use the "database_search_index" from the
    // search_api_test_db module (via the test view). Set the processor there,
    // too.
    $index = Index::load('database_search_index');
    $processor = $index->createPlugin('processor', 'search_api_test');
    $index->addProcessor($processor)->save();

    // Reset the called methods on the processor.
    $this->getCalledMethods('processor');

    // Log in, so we can test all the things.
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests various operations via the Search API's admin UI.
   */
  public function testHooks() {
    // hook_search_api_backend_info_alter() was invoked.
    $this->drupalGet('admin/config/search/search-api/add-server');
    $this->assertText('Slims return');

    // hook_search_api_datasource_info_alter() was invoked.
    $this->drupalGet('admin/config/search/search-api/add-index');
    $this->assertText('Distant land');

    // hook_search_api_processor_info_alter() was invoked.
    $this->drupalGet($this->getIndexPath('processors'));
    $this->assertText('Mystic bounce');

    // hook_search_api_parse_mode_info_alter was invoked.
    $definition = \Drupal::getContainer()
      ->get('plugin.manager.search_api.parse_mode')
      ->getDefinition('direct');
    $this->assertEqual('Song for My Father', $definition['label']);

    // Saving the index should trigger the processor's preIndexSave() method.
    $this->drupalPostForm(NULL, array(), $this->t('Save'));
    $processor_methods = $this->getCalledMethods('processor');
    $this->assertEqual(array('preIndexSave'), $processor_methods);

    $this->drupalGet($this->getIndexPath());
    $this->drupalPostForm(NULL, array(), $this->t('Index now'));

    // During indexing, alterIndexedItems() and preprocessIndexItems() should be
    // called on the processor.
    $processor_methods = $this->getCalledMethods('processor');
    $expected = array('alterIndexedItems', 'preprocessIndexItems');
    $this->assertEqual($expected, $processor_methods);

    // hook_search_api_index_items_alter() was invoked, this removed node:1.
    // hook_search_api_query_TAG_alter() was invoked, this removed node:3.
    $this->assertText('There are 2 items indexed on the server for this index.');
    $this->assertText('Successfully indexed 4 items.');
    $this->assertText('Stormy');

    // hook_search_api_items_indexed() was invoked.
    $this->assertText('Please set me at ease');

    // hook_search_api_index_reindex() was invoked.
    $this->drupalGet($this->getIndexPath('reindex'));
    $this->drupalPostForm(NULL, array(), $this->t('Confirm'));
    $this->assertText('Montara');

    // hook_search_api_data_type_info_alter() was invoked.
    $this->drupalGet($this->getIndexPath('fields'));
    $this->assertText('Peace/Dolphin dance');
    // The implementation of hook_search_api_field_type_mapping_alter() has
    // removed all dates, so we can't see any timestamp anymore in the page.
    $url_options['query']['datasource'] = 'entity:node';
    $this->drupalGet($this->getIndexPath('fields/add'), $url_options);
    $this->assertNoText('timestamp');

    $this->drupalGet('search-api-test');
    // hook_search_api_query_alter() was invoked.
    $this->assertText('Funky blue note');
    // hook_search_api_results_alter() was invoked.
    $this->assertText('Stepping into tomorrow');
    // hook_search_api_results_TAG_alter() was invoked.
    $this->assertText('Llama');

    // The query alter methods of the processor were called.
    $processor_methods = $this->getCalledMethods('processor');
    $expected = array('preprocessSearchQuery', 'postprocessSearchResults');
    $this->assertEqual($expected, $processor_methods);
  }

}
