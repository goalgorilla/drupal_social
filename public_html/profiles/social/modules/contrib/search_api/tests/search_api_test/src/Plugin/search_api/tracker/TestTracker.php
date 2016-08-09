<?php

namespace Drupal\search_api_test\Plugin\search_api\tracker;

use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Tracker\TrackerPluginBase;
use Drupal\search_api_test\TestPluginTrait;

/**
 * Provides a tracker implementation which uses a FIFO-like processing order.
 *
 * @SearchApiTracker(
 *   id = "search_api_test",
 *   label = @Translation("Test tracker"),
 * )
 */
class TestTracker extends TrackerPluginBase {

  use TestPluginTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array(
      'foo' => 'test',
      'dependencies' => array(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return array(
      'foo' => array(
        '#type' => 'textfield',
        '#title' => 'Foo',
        '#default_value' => $this->configuration['foo'],
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function trackItemsInserted(array $ids) {
    $this->logMethodCall(__FUNCTION__, func_get_args());
  }

  /**
   * {@inheritdoc}
   */
  public function trackItemsUpdated(array $ids) {
    $this->logMethodCall(__FUNCTION__, func_get_args());
  }

  /**
   * {@inheritdoc}
   */
  public function trackAllItemsUpdated($datasource_id = NULL) {
    $this->logMethodCall(__FUNCTION__, func_get_args());
  }

  /**
   * {@inheritdoc}
   */
  public function trackItemsIndexed(array $ids) {
    $this->logMethodCall(__FUNCTION__, func_get_args());
  }

  /**
   * {@inheritdoc}
   */
  public function trackItemsDeleted(array $ids = NULL) {
    $this->logMethodCall(__FUNCTION__, func_get_args());
  }

  /**
   * {@inheritdoc}
   */
  public function trackAllItemsDeleted($datasource_id = NULL) {
    $this->logMethodCall(__FUNCTION__, func_get_args());
  }

  /**
   * {@inheritdoc}
   */
  public function getRemainingItems($limit = -1, $datasource_id = NULL) {
    $this->logMethodCall(__FUNCTION__, func_get_args());
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function getTotalItemsCount($datasource_id = NULL) {
    $this->logMethodCall(__FUNCTION__, func_get_args());
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getIndexedItemsCount($datasource_id = NULL) {
    $this->logMethodCall(__FUNCTION__, func_get_args());
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getRemainingItemsCount($datasource_id = NULL) {
    $this->logMethodCall(__FUNCTION__, func_get_args());
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return $this->configuration['dependencies'];
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies) {
    $remove = $this->getReturnValue(__FUNCTION__, FALSE);
    if ($remove) {
      $this->configuration['dependencies'] = array();
    }
    return $remove;
  }

}
