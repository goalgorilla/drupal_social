<?php

namespace Drupal\Tests\search_api\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Entity\Index;

/**
 * Tests indexing entities that use string IDs.
 *
 * The current limit for item IDs in the Search API is 50 characters. The format
 * of the generated ID is entity:<entity_type_id>/<entity_id>:<language_code>.
 *
 * @group search_api
 */
class BundlelessEntityTest extends KernelTestBase {

  /**
   * The entity type used in the test.
   *
   * @var string
   */
  protected $testEntityTypeId = 'user';

  /**
   * {@inheritdoc}
   */
  public static $modules = array(
    'search_api',
    'user',
    'system',
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
  public function setUp() {
    parent::setUp();

    $this->installSchema('search_api', array('search_api_task'));
    $this->installConfig(array('user'));

    // Create a test index.
    $this->index = Index::create(array(
      'name' => 'Test Index',
      'id' => 'test_index',
      'status' => FALSE,
      'datasource_settings' => array(
        'entity:' . $this->testEntityTypeId => array(
          'plugin_id' => 'entity:' . $this->testEntityTypeId,
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
    $this->index->save();
  }

  /**
   * Tests that view modes are returned correctly.
   */
  public function testViewModes() {
    $datasource = $this->index->getDatasource('entity:' . $this->testEntityTypeId);

    $bundles = $datasource->getBundles();
    $expected = array(
      'user' => 'User',
    );
    $this->assertEquals($expected, $bundles);

    $view_modes = $datasource->getViewModes('user');
    $expected = array(
      'compact' => 'Compact',
      'default' => 'Default',
      'full' => 'User account',
    );
    ksort($view_modes);
    $this->assertEquals($expected, $view_modes);

    $view_modes = $datasource->getViewModes();
    ksort($view_modes);
    $this->assertEquals($expected, $view_modes);
  }

}
