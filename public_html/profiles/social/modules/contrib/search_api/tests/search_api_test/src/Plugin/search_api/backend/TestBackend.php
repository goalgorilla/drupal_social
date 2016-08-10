<?php

namespace Drupal\search_api_test\Plugin\search_api\backend;

use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Backend\BackendPluginBase;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Utility;
use Drupal\search_api_test\TestPluginTrait;

/**
 * Provides a dummy backend for testing purposes.
 *
 * @SearchApiBackend(
 *   id = "search_api_test",
 *   label = @Translation("Test backend"),
 *   description = @Translation("Dummy backend implementation")
 * )
 */
class TestBackend extends BackendPluginBase {

  use TestPluginTrait {
    checkError as traitCheckError;
  }

  /**
   * {@inheritdoc}
   */
  public function preUpdate() {
    $this->checkError(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function postUpdate() {
    $this->checkError(__FUNCTION__);
    return $this->getReturnValue(__FUNCTION__, FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function viewSettings() {
    return array(
      array(
        'label' => 'Dummy Info',
        'info' => 'Dummy Value',
        'status' => 'error',
      ),
      array(
        'label' => 'Dummy Info 2',
        'info' => 'Dummy Value 2',
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function supportsFeature($feature) {
    return $feature == 'search_api_mlt';
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDataType($type) {
    return $type == 'search_api_test' || $type == 'search_api_test_altering';
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array('test' => '');
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['test'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Test'),
      '#default_value' => $this->configuration['test'],
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function indexItems(IndexInterface $index, array $items) {
    $this->checkError(__FUNCTION__);

    $state = \Drupal::state();
    $key = 'search_api_test.backend.indexed.' . $index->id();
    $indexed_values = $state->get($key, array());
    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($items as $id => $item) {
      $indexed_values[$id] = array();
      foreach ($item->getFields() as $field_id => $field) {
        $indexed_values[$id][$field_id] = $field->getValues();
      }
    }
    $state->set($key, $indexed_values);

    return array_keys($items);
  }

  /**
   * {@inheritdoc}
   */
  public function addIndex(IndexInterface $index) {
    $this->checkError(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function updateIndex(IndexInterface $index) {
    $this->checkError(__FUNCTION__);
    $index->reindex();
  }

  /**
   * {@inheritdoc}
   */
  public function removeIndex($index) {
    $this->checkError(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteItems(IndexInterface $index, array $item_ids) {
    $this->checkError(__FUNCTION__);

    $state = \Drupal::state();
    $key = 'search_api_test.backend.indexed.' . $index->id();
    $indexed_values = $state->get($key, array());
    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($item_ids as $item_id) {
      unset($indexed_values[$item_id]);
    }
    $state->set($key, $indexed_values);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAllIndexItems(IndexInterface $index, $datasource_id = NULL) {
    $this->checkError(__FUNCTION__);

    $key = 'search_api_test.backend.indexed.' . $index->id();
    if (!$datasource_id) {
      \Drupal::state()->delete($key);
      return;
    }

    $indexed = \Drupal::state()->get($key, array());
    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach (array_keys($indexed) as $item_id) {
      list($item_datasource_id) = Utility::splitCombinedId($item_id);
      if ($item_datasource_id == $datasource_id) {
        unset($indexed[$item_id]);
      }
    }
    \Drupal::state()->set($key, $indexed);
  }

  /**
   * {@inheritdoc}
   */
  public function search(QueryInterface $query) {
    $this->checkError(__FUNCTION__);

    $results = $query->getResults();
    $result_items = array();
    $datasources = $query->getIndex()->getDatasources();
    /** @var \Drupal\search_api\Datasource\DatasourceInterface $datasource */
    $datasource = reset($datasources);
    $datasource_id = $datasource->getPluginId();
    if ($query->getKeys() && $query->getKeys()[0] == 'test') {
      $item_id = Utility::createCombinedId($datasource_id, '1');
      $item = Utility::createItem($query->getIndex(), $item_id, $datasource);
      $item->setScore(2);
      $item->setExcerpt('test');
      $result_items[$item_id] = $item;
    }
    elseif ($query->getOption('search_api_mlt')) {
      $item_id = Utility::createCombinedId($datasource_id, '2');
      $item = Utility::createItem($query->getIndex(), $item_id, $datasource);
      $item->setScore(2);
      $item->setExcerpt('test test');
      $result_items[$item_id] = $item;
    }
    else {
      $item_id = Utility::createCombinedId($datasource_id, '1');
      $item = Utility::createItem($query->getIndex(), $item_id, $datasource);
      $item->setScore(1);
      $result_items[$item_id] = $item;
      $item_id = Utility::createCombinedId($datasource_id, '2');
      $item = Utility::createItem($query->getIndex(), $item_id, $datasource);
      $item->setScore(1);
      $result_items[$item_id] = $item;
    }
    $results->setResultCount(count($result_items));
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable() {
    return $this->getReturnValue(__FUNCTION__, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getDiscouragedProcessors() {
    return $this->getReturnValue(__FUNCTION__, array());
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return !empty($this->configuration['dependencies']) ? $this->configuration['dependencies'] : array();
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies) {
    $remove = $this->getReturnValue(__FUNCTION__, FALSE);
    if ($remove) {
      unset($this->configuration['dependencies']);
    }
    return $remove;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkError($method) {
    $this->traitCheckError($method);
    $this->logMethodCall($method);
  }

}
