<?php

namespace Drupal\search_api\Processor;

use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Item\FieldInterface;

/**
 * Represents a processor-defined property with additional configuration.
 */
interface ConfigurablePropertyInterface extends ProcessorPropertyInterface {

  /**
   * Gets the default configuration for this property.
   *
   * @return array
   *   An associative array with the default configuration.
   */
  public function defaultConfiguration();

  /**
   * Constructs a configuration form for a field based on this property.
   *
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   The field for which the configuration form is constructed.
   * @param array $form
   *   An associative array containing the initial structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the complete form.
   *
   * @return array
   *   The form structure.
   */
  public function buildConfigurationForm(FieldInterface $field, array $form, FormStateInterface $form_state);

  /**
   * Validates a configuration form for a field based on this property.
   *
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   The field for which the configuration form is validated.
   * @param array $form
   *   An associative array containing the structure of the plugin form as built
   *   by static::buildConfigurationForm().
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the complete form.
   */
  public function validateConfigurationForm(FieldInterface $field, array &$form, FormStateInterface $form_state);

  /**
   * Submits a configuration form for a field based on this property.
   *
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   The field for which the configuration form is submitted.
   * @param array $form
   *   An associative array containing the structure of the plugin form as built
   *   by static::buildConfigurationForm().
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the complete form.
   */
  public function submitConfigurationForm(FieldInterface $field, array &$form, FormStateInterface $form_state);

  /**
   * Retrieves the description for a field based on this property.
   *
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   The field.
   *
   * @return string|null
   *   A human-readable description for the field, or NULL if the field has no
   *   description.
   */
  public function getFieldDescription(FieldInterface $field);

}
