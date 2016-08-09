<?php

namespace Drupal\search_api\Backend;

use Drupal\search_api\Entity\Server;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Plugin\ConfigurablePluginBase;
use Drupal\search_api\ServerInterface;
use Drupal\search_api\Utility;

/**
 * Defines a base class for backend plugins.
 *
 * Plugins extending this class need to define a plugin definition array through
 * annotation. These definition arrays may be altered through
 * hook_search_api_backend_info_alter(). The definition includes the following
 * keys:
 * - id: The unique, system-wide identifier of the backend class.
 * - label: The human-readable name of the backend class, translated.
 * - description: A human-readable description for the backend class,
 *   translated.
 *
 * A complete plugin definition should be written as in this example:
 *
 * @code
 * @SearchApiBackend(
 *   id = "my_backend",
 *   label = @Translation("My backend"),
 *   description = @Translation("Searches with SuperSearch™.")
 * )
 * @endcode
 *
 * @see \Drupal\search_api\Annotation\SearchApiBackend
 * @see \Drupal\search_api\Backend\BackendPluginManager
 * @see \Drupal\search_api\Backend\BackendInterface
 * @see plugin_api
 */
abstract class BackendPluginBase extends ConfigurablePluginBase implements BackendInterface {

  /**
   * The server this backend is configured for.
   *
   * @var \Drupal\search_api\ServerInterface
   */
  protected $server;

  /**
   * The backend's server's ID.
   *
   * Used for serialization.
   *
   * @var string
   */
  protected $serverId;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition) {
    if (!empty($configuration['server']) && $configuration['server'] instanceof ServerInterface) {
      $this->setServer($configuration['server']);
      unset($configuration['server']);
    }
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getServer() {
    return $this->server;
  }

  /**
   * {@inheritdoc}
   */
  public function setServer(ServerInterface $server) {
    $this->server = $server;
  }

  /**
   * {@inheritdoc}
   */
  public function viewSettings() {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsFeature($feature) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDataType($type) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function postInsert() {}

  /**
   * {@inheritdoc}
   */
  public function preUpdate() {}

  /**
   * {@inheritdoc}
   */
  public function postUpdate() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function preDelete() {
    try {
      $this->getServer()->deleteAllItems();
    }
    catch (SearchApiException $e) {
      $vars = array(
        '%server' => $this->getServer()->label(),
      );
      watchdog_exception('search_api', $e, '%type while deleting items from server %server: @message in %function (line %line of %file).', $vars);
      drupal_set_message($this->t('Deleting some of the items on the server failed. Check the logs for details. The server was still removed.'), 'error');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getBackendDefinedFields(IndexInterface $index) {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function addIndex(IndexInterface $index) {}

  /**
   * {@inheritdoc}
   */
  public function updateIndex(IndexInterface $index) {}

  /**
   * {@inheritdoc}
   */
  public function removeIndex($index) {
    // Only delete the index's data if the index isn't read-only. (If only the
    // ID is given, we assume the index was read-only, to be on the safe side.)
    if ($index instanceof IndexInterface && !$index->isReadOnly()) {
      $this->deleteAllIndexItems($index);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDiscouragedProcessors() {
    return array();
  }

  /**
   * Creates dummy field objects for the "magic" fields present for every index.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index for which to create the fields. (Needed since field objects
   *   always need an index set.)
   * @param \Drupal\search_api\Item\ItemInterface|null $item
   *   (optional) If given, an item whose data should be used for the fields'
   *   values.
   *
   * @return \Drupal\search_api\Item\FieldInterface[]
   *   An array of field objects for all "magic" fields, keyed by field IDs.
   */
  protected function getSpecialFields(IndexInterface $index, ItemInterface $item = NULL) {
    $field_info = array(
      'type' => 'string',
      'original type' => 'string',
    );
    $fields['search_api_id'] = Utility::createField($index, 'search_api_id', $field_info);
    $fields['search_api_datasource'] = Utility::createField($index, 'search_api_datasource', $field_info);
    $fields['search_api_language'] = Utility::createField($index, 'search_api_language', $field_info);

    if ($item) {
      $fields['search_api_id']->setValues(array($item->getId()));
      $fields['search_api_datasource']->setValues(array($item->getDatasourceId()));
      $fields['search_api_language']->setValues(array($item->getLanguage()));
    }

    return $fields;
  }

  /**
   * Verifies that the given condition operator is valid for this backend.
   *
   * @param string $operator
   *   The operator in question.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if the operator is not known.
   *
   * @see \Drupal\search_api\Query\ConditionSetInterface::addCondition()
   */
  protected function validateOperator($operator) {
    switch ($operator) {
      case '=':
      case '<>':
      case '<':
      case '<=':
      case '>=':
      case '>':
      case 'IN':
      case 'NOT IN':
      case 'BETWEEN':
      case 'NOT BETWEEN':
        return;
    }
    throw new SearchApiException("Unknown operator '$operator' used in search query condition");
  }

  /**
   * Implements the magic __sleep() method.
   *
   * Prevents the server entity from being serialized.
   */
  public function __sleep() {
    if ($this->server) {
      $this->serverId = $this->server->id();
    }
    $properties = array_flip(parent::__sleep());
    unset($properties['server']);
    return array_keys($properties);
  }

  /**
   * Implements the magic __wakeup() method.
   *
   * Reloads the server entity.
   */
  public function __wakeup() {
    parent::__wakeup();

    if ($this->serverId) {
      $this->server = Server::load($this->serverId);
      $this->serverId = NULL;
    }
  }

  /**
   * Retrieves the effective fulltext fields from the query.
   *
   * Automatically translates a NULL value in the query object to all fulltext
   * fields in the search index.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The search query.
   *
   * @return string[]
   *   The fulltext fields in which to search for the search keys.
   *
   * @see \Drupal\search_api\Query\QueryInterface::getFulltextFields()
   */
  protected function getQueryFulltextFields(QueryInterface $query) {
    $fulltext_fields = $query->getFulltextFields();
    return $fulltext_fields === NULL ? $query->getIndex()->getFulltextFields() : $fulltext_fields;
  }

}
