<?php

namespace Drupal\Tests\search_api\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\ServerInterface;
use Drupal\search_api\Task\TaskInterface;

/**
 * Tests whether the Search API task system works correctly.
 *
 * @group search_api
 */
class TaskTest extends KernelTestBase {

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
   * {@inheritdoc}
   */
  public static $modules = array(
    'user',
    'search_api',
    'search_api_test',
    'search_api_test_tasks',
  );

  /**
   * The task manager to use for the tests.
   *
   * @var \Drupal\search_api\Task\TaskManagerInterface
   */
  protected $taskManager;

  /**
   * The test task worker service.
   *
   * @var \Drupal\search_api_test_tasks\TestTaskWorker
   */
  protected $taskWorker;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->installSchema('search_api', array('search_api_task'));

    $this->taskManager = $this->container->get('search_api.task_manager');
    $this->taskWorker = $this->container->get('search_api_test_tasks.test_task_worker');

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
      'status' => 0,
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
    ));
    $this->index->save();
  }

  /**
   * Tests successful task execution.
   */
  public function testTaskSuccess() {
    $task = $this->addTask('success');
    $this->assertEquals(1, $this->taskManager->getTasksCount());
    $this->taskManager->executeSingleTask();
    $this->assertEquals(0, $this->taskManager->getTasksCount());
    $this->assertEquals($task->toArray(), $this->taskWorker->getEventLog()[0]);
  }

  /**
   * Tests failed task execution.
   */
  public function testTaskFail() {
    $task = $this->addTask('fail', $this->server);
    $this->assertEquals(1, $this->taskManager->getTasksCount());
    try {
      $this->taskManager->executeAllTasks(array(
        'server_id' => $this->server->id(),
      ));
      $this->fail('Exception expected');
    }
    catch (SearchApiException $e) {
      $this->assertEquals('fail', $e->getMessage());
    }
    $this->assertEquals(1, $this->taskManager->getTasksCount());
    $this->assertEquals($task->toArray(), $this->taskWorker->getEventLog()[0]);
  }

  /**
   * Tests ignored task execution.
   */
  public function testTaskIgnored() {
    $task = $this->addTask('ignore', NULL, $this->index, 'foobar');
    $type = $task->getType();
    $this->assertEquals(1, $this->taskManager->getTasksCount());
    try {
      $this->taskManager->executeAllTasks(array(
        'type' => array($type, 'unknown'),
        'index_id' => $this->index->id(),
      ));
      $this->fail('Exception expected');
    }
    catch (SearchApiException $e) {
      $id = $task->id();
      $this->assertEquals("Could not execute task #$id of type '$type'. Type seems to be unknown.", $e->getMessage());
    }
    $this->assertEquals(1, $this->taskManager->getTasksCount());
    $this->assertEquals($task->toArray(), $this->taskWorker->getEventLog()[0]);
  }

  /**
   * Tests unknown task execution.
   */
  public function testTaskUnknown() {
    $task = $this->addTask('unknown');
    $this->assertEquals(1, $this->taskManager->getTasksCount());
    try {
      $this->taskManager->executeAllTasks();
      $this->fail('Exception expected');
    }
    catch (SearchApiException $e) {
      $id = $task->id();
      $type = $task->getType();
      $this->assertEquals("Could not execute task #$id of type '$type'. Type seems to be unknown.", $e->getMessage());
    }
    $this->assertEquals(1, $this->taskManager->getTasksCount());
    $this->assertEquals(array(), $this->taskWorker->getEventLog());
  }

  /**
   * Tests that multiple pending tasks are treated correctly.
   */
  public function testMultipleTasks() {
    // Add some tasks to the system. We use explicit indexes since we want to
    // verify that the tasks are executed in a different order than the one they
    // were added, if appropriate $conditions parameters are given.
    $tasks = array();
    $tasks[0] = $this->addTask('success', $this->server, $this->index, array('foo' => 1, 'bar'));
    $tasks[6] = $this->addTask('fail');
    $tasks[1] = $this->addTask('success', $this->server, NULL, TRUE);
    $tasks[4] = $this->addTask('success', NULL, NULL, 1);
    $tasks[2] = $this->addTask('fail', $this->server, $this->index);
    $tasks[5] = $this->addTask('success');
    $tasks[3] = $this->addTask('success', NULL, $this->index);

    $num = count($tasks);
    $this->assertEquals($num, $this->taskManager->getTasksCount());

    $this->taskManager->executeSingleTask();
    $this->assertEquals(--$num, $this->taskManager->getTasksCount());

    $this->taskManager->executeSingleTask(array(
      'server_id' => $this->server->id(),
    ));
    $this->assertEquals(--$num, $this->taskManager->getTasksCount());

    try {
      $this->taskManager->executeAllTasks(array(
        'server_id' => $this->server->id(),
      ));
      $this->fail('Exception expected');
    }
    catch (SearchApiException $e) {
      $this->assertEquals('fail', $e->getMessage());
    }
    $this->assertEquals($num, $this->taskManager->getTasksCount());

    $tasks[2]->delete();
    $this->assertEquals(--$num, $this->taskManager->getTasksCount());

    $this->taskManager->executeSingleTask(array(
      'index_id' => $this->index->id(),
    ));
    $this->assertEquals(--$num, $this->taskManager->getTasksCount());

    $this->taskManager->executeAllTasks(array(
      'type' => array('search_api_test_tasks.success', 'foobar'),
    ));
    $this->assertEquals($num -= 2, $this->taskManager->getTasksCount());

    $tasks[7] = $this->addTask('success');
    $tasks[8] = $this->addTask('success');
    $tasks[9] = $this->addTask('fail');
    $tasks[10] = $this->addTask('success');
    $num += 4;

    try {
      $this->taskManager->executeAllTasks();
      $this->fail('Exception expected');
    }
    catch (SearchApiException $e) {
      $this->assertEquals('fail', $e->getMessage());
    }
    $this->assertEquals($num, $this->taskManager->getTasksCount());

    $tasks[6]->delete();
    $this->assertEquals(--$num, $this->taskManager->getTasksCount());

    try {
      $this->taskManager->executeAllTasks();
      $this->fail('Exception expected');
    }
    catch (SearchApiException $e) {
      $this->assertEquals('fail', $e->getMessage());
    }
    $this->assertEquals($num -= 2, $this->taskManager->getTasksCount());

    $tasks[9]->delete();
    $this->assertEquals(--$num, $this->taskManager->getTasksCount());

    $this->taskManager->executeAllTasks();
    $this->assertEquals(0, $this->taskManager->getTasksCount());

    $to_array = function (TaskInterface $task) {
      return $task->toArray();
    };
    $tasks = array_map($to_array, $tasks);
    $this->assertEquals($tasks, $this->taskWorker->getEventLog());
  }

  /**
   * Adds a new pending task.
   *
   * @param string $type
   *   The type of task, without "search_api_test_tasks." prefix.
   * @param \Drupal\search_api\ServerInterface|null $server
   *   (optional) The search server associated with the task, if any.
   * @param \Drupal\search_api\IndexInterface|null $index
   *   (optional) The search index associated with the task, if any.
   * @param mixed|null $data
   *   (optional) Additional, type-specific data to save with the task.
   *
   * @return \Drupal\search_api\Task\TaskInterface
   *   The new task.
   */
  protected function addTask($type, ServerInterface $server = NULL, IndexInterface $index = NULL, $data = NULL) {
    $type = "search_api_test_tasks.$type";
    $count_before = $this->taskManager->getTasksCount();
    $conditions = array(
      'type' => $type,
      'server_id' => $server ? $server->id() : NULL,
      'index_id' => $index ? $index->id() : NULL,
    );
    $conditions = array_filter($conditions);
    $count_before_conditions = $this->taskManager->getTasksCount($conditions);

    $task = $this->taskManager->addTask($type, $server, $index, $data);

    $count_after = $this->taskManager->getTasksCount();
    $this->assertEquals($count_before + 1, $count_after);
    $count_after_conditions = $this->taskManager->getTasksCount($conditions);
    $this->assertEquals($count_before_conditions + 1, $count_after_conditions);

    return $task;
  }

}
