<?php

namespace Drupal\search_api\Processor;

use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Item\FieldInterface;

/**
 * Provides a base class for configurable processor-defined properties.
 */
abstract class ConfigurablePropertyBase extends ProcessorProperty implements ConfigurablePropertyInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(FieldInterface $field, array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(FieldInterface $field, array &$form, FormStateInterface $form_state) {
    $values = array_intersect_key($form_state->getValues(), $this->defaultConfiguration());
    $field->setConfiguration($values);
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDescription(FieldInterface $field) {
    return $this->getDescription();
  }

}
