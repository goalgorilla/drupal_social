<?php

namespace Drupal\search_api\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Adds the item's URL to the indexed data.
 *
 * @SearchApiProcessor(
 *   id = "add_url",
 *   label = @Translation("URL field"),
 *   description = @Translation("Adds the item's URL to the indexed data."),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class AddURL extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = array();

    if (!$datasource) {
      $definition = array(
        'label' => $this->t('URI'),
        'description' => $this->t('A URI where the item can be accessed'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      );
      $properties['search_api_url'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $url = $item->getDatasource()->getItemUrl($item->getOriginalObject());
    if ($url) {
      foreach ($this->filterForPropertyPath($item->getFields(), 'search_api_url') as $field) {
        if (!$field->getDatasourceId()) {
          $field->addValue($url->toString());
        }
      }
    }
  }

}
