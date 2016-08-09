<?php

namespace Drupal\search_api\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Plugin\search_api\processor\Property\AggregatedFieldProperty;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Utility;

/**
 * Adds customized aggregations of existing fields to the index.
 *
 * @SearchApiProcessor(
 *   id = "aggregated_field",
 *   label = @Translation("Aggregated fields"),
 *   description = @Translation("Add customized aggregations of existing fields to the index."),
 *   stages = {
 *     "add_properties" = 20,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class AggregatedFields extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = array();

    if (!$datasource) {
      $definition = array(
        'label' => $this->t('Aggregated field'),
        'description' => $this->t('An aggregation of multiple other fields.'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      );
      $properties['aggregated_field'] = new AggregatedFieldProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $aggregated_fields = $this->filterForPropertyPath(
      $this->index->getFieldsByDatasource(NULL),
      'aggregated_field'
    );
    $required_properties_by_datasource = array(
      NULL => array(),
      $item->getDatasourceId() => array(),
    );
    foreach ($aggregated_fields as $field) {
      foreach ($field->getConfiguration()['fields'] as $combined_id) {
        list($datasource_id, $property_path) = Utility::splitCombinedId($combined_id);
        $required_properties_by_datasource[$datasource_id][$property_path] = $combined_id;
      }
    }

    $property_values = $this->extractItemValues(array($item), $required_properties_by_datasource)[0];

    $aggregated_fields = $this->filterForPropertyPath($item->getFields(), 'aggregated_field');
    foreach ($aggregated_fields as $aggregated_field) {
      $values = array();
      $configuration = $aggregated_field->getConfiguration();
      foreach ($configuration['fields'] as $combined_id) {
        if (!empty($property_values[$combined_id])) {
          $values = array_merge($values, $property_values[$combined_id]);
        }
      }

      switch ($configuration['type']) {
        case 'concat':
          $values = array(implode("\n\n", $values));
          break;

        case 'sum':
          $values = array(array_sum($values));
          break;

        case 'count':
          $values = array(count($values));
          break;

        case 'max':
          $values = array(max($values));
          break;

        case 'min':
          $values = array(min($values));
          break;

        case 'first':
          if ($values) {
            $values = array(reset($values));
          }
          break;
      }

      $aggregated_field->setValues($values);
    }
  }

}
