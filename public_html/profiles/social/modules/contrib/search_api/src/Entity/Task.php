<?php

namespace Drupal\search_api\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\Task\TaskInterface;

/**
 * Defines the Search API task entity class.
 *
 * @ContentEntityType(
 *   id = "search_api_task",
 *   label = @Translation("Search task"),
 *   label_singular = @Translation("search task"),
 *   label_plural = @Translation("search tasks"),
 *   label_count = @PluralTranslation(
 *     singular = "@count search task",
 *     plural = "@count search tasks"
 *   ),
 *   base_table = "search_api_task",
 *   translatable = FALSE,
 *   entity_keys = {
 *     "id" = "id",
 *   },
 * )
 */
class Task extends ContentEntityBase implements TaskInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|null
   */
  protected $entityTypeManager;

  /**
   * The search server, if this task is associated with a server.
   *
   * @var \Drupal\search_api\ServerInterface|null
   */
  protected $serverInstance;

  /**
   * The search index, if this task is associated with a index.
   *
   * @var \Drupal\search_api\IndexInterface|null
   */
  protected $indexInstance;

  /**
   * Additional data associated with this task, if any.
   *
   * @var mixed|null
   */
  protected $unserializedData;

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeManager() {
    return $this->entityTypeManager ?: \Drupal::entityTypeManager();
  }

  /**
   * {@inheritdoc}
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return $this->get('type')[0]->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getServerId() {
    $field = $this->get('server_id')[0];
    return $field ? $field->value : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getServer() {
    $server_id = $this->getServerId();
    if ($server_id && !isset($this->serverInstance)) {
      $this->serverInstance = $this->getEntityTypeManager()
        ->getStorage('search_api_server')
        ->load($server_id);
      if (!$this->serverInstance) {
        $args['%server'] = $server_id;
        throw new SearchApiException("Could not load server with ID '$server_id'.");
      }
    }

    return $this->serverInstance;
  }

  /**
   * {@inheritdoc}
   */
  public function getIndexId() {
    $field = $this->get('index_id')[0];
    return $field ? $field->value : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getIndex() {
    $index_id = $this->getIndexId();
    if ($index_id && !isset($this->indexInstance)) {
      $this->indexInstance = $this->getEntityTypeManager()
        ->getStorage('search_api_index')
        ->load($index_id);
      if (!$this->indexInstance) {
        throw new SearchApiException("Could not load index with ID '$index_id'.");
      }
    }

    return $this->indexInstance;
  }

  /**
   * {@inheritdoc}
   */
  public function getData() {
    if (!isset($this->unserializedData)) {
      $data = $this->get('data')[0];
      if ($data) {
        $this->unserializedData = unserialize($data->value);
      }
    }

    return $this->unserializedData;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    /** @var \Drupal\Core\Field\BaseFieldDefinition[] $fields */
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Task type'))
      ->setSetting('max_length', 50)
      ->setRequired(TRUE)
      ->setReadOnly(TRUE);

    $fields['server_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Server ID'))
      ->setSetting('max_length', 50)
      ->setReadOnly(TRUE);

    $fields['index_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Index ID'))
      ->setSetting('max_length', 50)
      ->setReadOnly(TRUE);

    $fields['data'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Task data'))
      ->setReadOnly(TRUE);

    return $fields;
  }

}
