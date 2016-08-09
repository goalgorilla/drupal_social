<?php

namespace Drupal\search_api\Processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Plugin\IndexPluginBase;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\Utility;

/**
 * Defines a base class from which other processors may extend.
 *
 * Plugins extending this class need to define a plugin definition array through
 * annotation. These definition arrays may be altered through
 * hook_search_api_processor_info_alter(). The definition includes the following
 * keys:
 * - id: The unique, system-wide identifier of the processor.
 * - label: The human-readable name of the processor, translated.
 * - description: A human-readable description for the processor, translated.
 *
 * A complete plugin definition should be written as in this example:
 *
 * @code
 * @SearchApiProcessor(
 *   id = "my_processor",
 *   label = @Translation("My Processor"),
 *   description = @Translation("Does … something."),
 *   stages = {
 *     "preprocess_index" = 0,
 *     "preprocess_query" = 0,
 *     "postprocess_query" = 0
 *   }
 * )
 * @endcode
 *
 * @see \Drupal\search_api\Annotation\SearchApiProcessor
 * @see \Drupal\search_api\Processor\ProcessorPluginManager
 * @see \Drupal\search_api\Processor\ProcessorInterface
 * @see plugin_api
 */
abstract class ProcessorPluginBase extends IndexPluginBase implements ProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsStage($stage) {
    $plugin_definition = $this->getPluginDefinition();
    return isset($plugin_definition['stages'][$stage]);
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight($stage) {
    if (isset($this->configuration['weights'][$stage])) {
      return $this->configuration['weights'][$stage];
    }
    $plugin_definition = $this->getPluginDefinition();
    if (isset($plugin_definition['stages'][$stage])) {
      return (int) $plugin_definition['stages'][$stage];
    }
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight($stage, $weight) {
    $this->configuration['weights'][$stage] = $weight;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isLocked() {
    return !empty($this->pluginDefinition['locked']);
  }

  /**
   * {@inheritdoc}
   */
  public function isHidden() {
    return !empty($this->pluginDefinition['hidden']);
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {}

  /**
   * {@inheritdoc}
   */
  public function preIndexSave() {}

  /**
   * Ensures that a field with certain properties is indexed on the index.
   *
   * Can be used as a helper method in preIndexSave().
   *
   * @param string|null $datasource_id
   *   The ID of the field's datasource, or NULL for a datasource-independent
   *   field.
   * @param string $property_path
   *   The field's property path on the datasource.
   * @param string|null $type
   *   (optional) If set, the field should have this type.
   *
   * @return \Drupal\search_api\Item\FieldInterface
   *   A field on the index, possibly newly added, with the specified
   *   properties.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if there is no property with the specified path, or no type is
   *   given and no default could be determined for the property.
   */
  protected function ensureField($datasource_id, $property_path, $type = NULL) {
    $field = $this->findField($datasource_id, $property_path, $type);

    if (!$field) {
      $property = Utility::retrieveNestedProperty($this->index->getPropertyDefinitions($datasource_id), $property_path);
      if (!$property) {
        $property_id = Utility::createCombinedId($datasource_id, $property_path);
        $processor_label = $this->label();
        throw new SearchApiException("Could not find property '$property_id' which is required by the '$processor_label' processor.");
      }
      $field = Utility::createFieldFromProperty($this->index, $property, $datasource_id, $property_path, NULL, $type);
      $this->index->addField($field);
    }

    $field->setIndexedLocked();
    if (isset($type)) {
      $field->setTypeLocked();
    }
    return $field;
  }

  /**
   * Finds a certain field in the index.
   *
   * @param string|null $datasource_id
   *   The ID of the field's datasource, or NULL for a datasource-independent
   *   field.
   * @param string $property_path
   *   The field's property path on the datasource.
   * @param string|null $type
   *   (optional) If set, only return a field if it has this type.
   *
   * @return \Drupal\search_api\Item\FieldInterface|null
   *   A field on the index with the desired properties, or NULL if none could
   *   be found.
   */
  protected function findField($datasource_id, $property_path, $type = NULL) {
    foreach ($this->index->getFieldsByDatasource($datasource_id) as $field) {
      if ($field->getPropertyPath() == $property_path) {
        if (!isset($type) || $field->getType() == $type) {
          return $field;
        }
      }
    }
    return NULL;
  }

  /**
   * Filters the given fields for those with the specified property path.
   *
   * Array keys will be preserved.
   *
   * @param \Drupal\search_api\Item\FieldInterface[] $fields
   *   The fields to filter.
   * @param string $property_path
   *   The searched property path on the item.
   *
   * @return \Drupal\search_api\Item\FieldInterface[]
   *   All fields with the given property path.
   */
  protected function filterForPropertyPath(array $fields, $property_path) {
    $found_fields = array();
    foreach ($fields as $field_id => $field) {
      if ($field->getPropertyPath() == $property_path) {
        $found_fields[$field_id] = $field;
      }
    }
    return $found_fields;
  }

  /**
   * Extracts property values from items.
   *
   * Values are taken from existing fields on the item, where present, and are
   * otherwise extracted from the item's underlying object.
   *
   * @param \Drupal\search_api\Item\ItemInterface[] $items
   *   The items from which properties should be extracted.
   * @param string[][] $required_properties
   *   The properties that should be extracted, keyed by datasource ID and
   *   property path, with the values being the IDs that the values should be
   *   put under in the return value.
   * @param bool $load
   *   (optional) If FALSE, only field values already present will be returned.
   *   Otherwise, fields will be extracted (and underlying objects loaded) if
   *   necessary.
   *
   * @return mixed[][][]
   *   Arrays of field values, keyed by items' indexes in $items and the given
   *   field IDs from $required_properties.
   */
  protected function extractItemValues(array $items, array $required_properties, $load = TRUE) {
    $extracted_values = array();

    foreach ($items as $i => $item) {
      $item_values = array();
      /** @var \Drupal\search_api\Item\FieldInterface[][] $missing_fields */
      $missing_fields = array();
      $processor_fields = array();
      $needed_processors = array();
      foreach (array(NULL, $item->getDatasourceId()) as $datasource_id) {
        if (empty($required_properties[$datasource_id])) {
          continue;
        }

        $properties = $this->index->getPropertyDefinitions($datasource_id);
        foreach ($required_properties[$datasource_id] as $property_path => $combined_id) {
          // If a field with the right property path is already set on the item,
          // use it. This might actually make problems in case the values have
          // already been processed in some way, or use a data type that
          // transformed their original value. But that will hopefully not be a
          // problem in most situations.
          foreach ($this->filterForPropertyPath($item->getFields(FALSE), $property_path) as $field) {
            if ($field->getDatasourceId() === $datasource_id) {
              $item_values[$combined_id] = $field->getValues();
              continue 2;
            }
          }

          // There are no values present on the item for this property. If we
          // don't want to extract any fields, skip it.
          if (!$load) {
            continue;
          }

          // If the field is not already on the item, we need to extract it. We
          // set our own combined ID as the field identifier as kind of a hack,
          // to easily be able to add the field values to $property_values
          // afterwards.
          $property = NULL;
          if (isset($properties[$property_path])) {
            $property = $properties[$property_path];
          }
          if ($property instanceof ProcessorPropertyInterface) {
            $processor_fields[] = Utility::createField($this->index, $combined_id, array(
              'datasource_id' => $datasource_id,
              'property_path' => $property_path,
            ));
            $needed_processors[$property->getProcessorId()] = TRUE;
          }
          elseif ($datasource_id) {
            $missing_fields[$property_path][] = Utility::createField($this->index, $combined_id);
          }
          else {
            // Extracting properties without a datasource is pointless.
            $item_values[$combined_id] = array();
          }
        }
      }
      if ($missing_fields) {
        Utility::extractFields($item->getOriginalObject(), $missing_fields);
        foreach ($missing_fields as $property_fields) {
          foreach ($property_fields as $field) {
            $item_values[$field->getFieldIdentifier()] = $field->getValues();
          }
        }
      }
      if ($processor_fields) {
        $dummy_item = clone $item;
        $dummy_item->setFields($processor_fields);
        $processors = $this->index->getProcessorsByStage(ProcessorInterface::STAGE_ADD_PROPERTIES);
        foreach ($processors as $processor_id => $processor) {
          // Avoid an infinite recursion.
          if (isset($needed_processors[$processor_id]) && $processor != $this) {
            $processor->addFieldValues($dummy_item);
          }
        }
        foreach ($processor_fields as $field) {
          $item_values[$field->getFieldIdentifier()] = $field->getValues();
        }
      }

      $extracted_values[$i] = $item_values;
    }

    return $extracted_values;
  }

  /**
   * {@inheritdoc}
   */
  public function alterIndexedItems(array &$items) {}

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array $items) {}

  /**
   * {@inheritdoc}
   */
  public function preprocessSearchQuery(QueryInterface $query) {}

  /**
   * {@inheritdoc}
   */
  public function postprocessSearchResults(ResultSetInterface $results) {}

  /**
   * {@inheritdoc}
   */
  public function requiresReindexing(array $old_settings = NULL, array $new_settings = NULL) {
    // Only require re-indexing for processors that actually run during the
    // indexing process.
    return $this->supportsStage(ProcessorInterface::STAGE_PREPROCESS_INDEX);
  }

}
