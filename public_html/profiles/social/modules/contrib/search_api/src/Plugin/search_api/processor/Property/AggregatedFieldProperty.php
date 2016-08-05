<?php

namespace Drupal\search_api\Plugin\search_api\processor\Property;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Processor\ConfigurablePropertyBase;
use Drupal\search_api\Utility;

/**
 * Defines an "aggregated field" property.
 */
class AggregatedFieldProperty extends ConfigurablePropertyBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array(
      'type' => 'union',
      'fields' => array(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(FieldInterface $field, array $form, FormStateInterface $form_state) {
    $index = $field->getIndex();
    $configuration = $field->getConfiguration();

    $form['#attached']['library'][] = 'search_api/drupal.search_api.admin_css';
    $form['#tree'] = TRUE;

    $form['type'] = array(
      '#type' => 'select',
      '#title' => $this->t('Aggregation type'),
      '#options' => $this->getTypes(),
      '#default_value' => $configuration['type'],
      '#required' => TRUE,
    );

    foreach ($this->getTypes('description') as $type => $description) {
      $form['type_descriptions'][$type] = array(
        '#type' => 'item',
        '#description' => $description,
      );
      $form['type_descriptions'][$type]['#states']['visible'][':input[name="type"]']['value'] = $type;
    }

    $form['fields'] = array(
      '#type' => 'checkboxes',
      '#title' => $this->t('Contained fields'),
      '#options' => array(),
      '#attributes' => array('class' => array('search-api-checkboxes-list')),
      '#default_value' => $configuration['fields'],
      '#required' => TRUE,
    );
    $datasource_labels = $this->getDatasourceLabelPrefixes($index);
    $properties = $this->getAvailableProperties($index);
    ksort($properties);
    foreach ($properties as $combined_id => $property) {
      list($datasource_id, $name) = Utility::splitCombinedId($combined_id);
      $form['fields']['#options'][$combined_id] = $datasource_labels[$datasource_id] . $property->getLabel();
      $form['fields'][$combined_id] = array(
        '#attributes' => array('title' => $this->t('Machine name: @name', array('@name' => $name))),
        '#description' => $property->getDescription(),
      );
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(FieldInterface $field, array &$form, FormStateInterface $form_state) {
    $values = array(
      'type' => $form_state->getValue('type'),
      'fields' => array_keys(array_filter($form_state->getValue('fields'))),
    );
    $field->setConfiguration($values);
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDescription(FieldInterface $field) {
    $index = $field->getIndex();
    $available_properties = $this->getAvailableProperties($index);
    $datasource_label_prefixes = $this->getDatasourceLabelPrefixes($index);
    $configuration = $field->getConfiguration();

    $fields = array();
    foreach ($configuration['fields'] as $combined_id) {
      list($datasource_id, $property_path) = Utility::splitCombinedId($combined_id);
      $label = $property_path;
      if (isset($available_properties[$combined_id])) {
        $label = $available_properties[$combined_id]->getLabel();
      }
      $fields[] = $datasource_label_prefixes[$datasource_id] . $label;
    }
    $type = $this->getTypes()[$configuration['type']];

    $arguments = array('@type' => $type, '@fields' => implode(', ', $fields));

    return $this->t('A @type aggregation of the following fields: @fields.', $arguments);
  }

  /**
   * Retrieves information about available aggregation types.
   *
   * @param string $info
   *   (optional) One of "label" or "description", to indicate what values
   *   should be returned for the types.
   *
   * @return array
   *   An array of the identifiers of the available types mapped to, depending
   *   on $info, their labels, their data types or their descriptions.
   */
  protected function getTypes($info = 'label') {
    switch ($info) {
      case 'label':
        return array(
          'union' => $this->t('Union'),
          'concat' => $this->t('Concatenation'),
          'sum' => $this->t('Sum'),
          'count' => $this->t('Count'),
          'max' => $this->t('Maximum'),
          'min' => $this->t('Minimum'),
          'first' => $this->t('First'),
        );

      case 'description':
        return array(
          'union' => $this->t('The Union aggregation does an union operation of all the values of the field. 2 fields with 2 values each become 1 field with 4 values.'),
          'concat' => $this->t('The Concatenation aggregation concatenates the text data of all contained fields.'),
          'sum' => $this->t('The Sum aggregation adds the values of all contained fields numerically.'),
          'count' => $this->t('The Count aggregation takes the total number of contained field values as the aggregated field value.'),
          'max' => $this->t('The Maximum aggregation computes the numerically largest contained field value.'),
          'min' => $this->t('The Minimum aggregation computes the numerically smallest contained field value.'),
          'first' => $this->t('The First aggregation will simply keep the first encountered field value.'),
        );

    }
    return array();
  }

  /**
   * Retrieves label prefixes for an index's datasources.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The search index.
   *
   * @return string[]
   *   An associative array mapping datasource IDs (and an empty string for
   *   datasource-independent properties) to their label prefixes.
   */
  protected function getDatasourceLabelPrefixes(IndexInterface $index) {
    $prefixes = array(
      NULL => $this->t('General') . ' » ',
    );

    foreach ($index->getDatasources() as $datasource_id => $datasource) {
      $prefixes[$datasource_id] = $datasource->label() . ' » ';
    }

    return $prefixes;
  }

  /**
   * Retrieve all properties available on the index.
   *
   * The properties will be keyed by combined ID, which is a combination of the
   * datasource ID and the property path. This is used internally in this class
   * to easily identify any property on the index.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The search index.
   *
   * @return \Drupal\Core\TypedData\DataDefinitionInterface[]
   *   All the properties available on the index, keyed by combined ID.
   *
   * @see \Drupal\search_api\Utility::createCombinedId()
   */
  protected function getAvailableProperties(IndexInterface $index) {
    $properties = array();

    $datasource_ids = $index->getDatasourceIds();
    $datasource_ids[] = NULL;
    foreach ($datasource_ids as $datasource_id) {
      foreach ($index->getPropertyDefinitions($datasource_id) as $property_path => $property) {
        $properties[Utility::createCombinedId($datasource_id, $property_path)] = $property;
      }
    }

    return $properties;
  }

}
