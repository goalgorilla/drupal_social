<?php

namespace Drupal\search_api\Tests;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\simpletest\WebTestBase as SimpletestWebTestBase;

/**
 * Provides the base class for web tests for Search API.
 */
abstract class WebTestBase extends SimpletestWebTestBase {

  use StringTranslationTrait;

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  public static $modules = array(
    'node',
    'search_api',
    'search_api_test',
  );

  /**
   * An admin user used for this test.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser;

  /**
   * The permissions of the admin user.
   *
   * @var string[]
   */
  protected $adminUserPermissions = array(
    'administer search_api',
    'access administration pages',
  );

  /**
   * A user without Search API admin permission.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $unauthorizedUser;

  /**
   * The anonymous user used for this test.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $anonymousUser;

  /**
   * The URL generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * The ID of the search index used for this test.
   *
   * @var string
   */
  protected $indexId;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Create the users used for the tests.
    $this->adminUser = $this->drupalCreateUser($this->adminUserPermissions);
    $this->unauthorizedUser = $this->drupalCreateUser(array('access administration pages'));
    $this->anonymousUser = $this->drupalCreateUser();

    // Get the URL generator.
    $this->urlGenerator = $this->container->get('url_generator');

    // Create a node article type.
    $this->drupalCreateContentType(array(
      'type' => 'article',
      'name' => 'Article',
    ));

    // Create a node page type.
    $this->drupalCreateContentType(array(
      'type' => 'page',
      'name' => 'Page',
    ));
  }

  /**
   * Creates or loads a server.
   *
   * @return \Drupal\search_api\ServerInterface
   *   A search server.
   */
  public function getTestServer() {
    $server = Server::load('webtest_server');
    if (!$server) {
      $server = Server::create(array(
        'id' => 'webtest_server',
        'name' => 'WebTest server',
        'description' => 'WebTest server' . ' description',
        'backend' => 'search_api_test',
        'backend_config' => array(),
      ));
      $server->save();
    }

    return $server;
  }

  /**
   * Creates or loads an index.
   *
   * @return \Drupal\search_api\IndexInterface
   *   A search index.
   */
  public function getTestIndex() {
    $this->indexId = 'webtest_index';
    $index = Index::load($this->indexId);
    if (!$index) {
      $index = Index::create(array(
        'id' => $this->indexId,
        'name' => 'WebTest index',
        'description' => 'WebTest index' . ' description',
        'server' => 'webtest_server',
        'datasource_settings' => array(
          'entity:node' => array(
            'plugin_id' => 'entity:node',
            'settings' => array(),
          ),
        ),
      ));
      $index->save();
    }

    return $index;
  }

  /**
   * Returns the system path for the test index.
   *
   * @param string|null $tab
   *   (optional) If set, the path suffix for a specific index tab.
   *
   * @return string
   *   A system path.
   */
  protected function getIndexPath($tab = NULL) {
    $path = 'admin/config/search/search-api/index/' . $this->indexId;
    if ($tab) {
      $path .= "/$tab";
    }
    return $path;
  }

  /**
   * Executes all pending Search API tasks.
   */
  protected function executeTasks() {
    $task_manager = \Drupal::getContainer()->get('search_api.task_manager');
    $task_manager->executeAllTasks();
    $this->assertEqual(0, $task_manager->getTasksCount(), 'No more pending tasks.');
  }

}
