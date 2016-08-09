<?php

namespace Drupal\search_api\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Processor\ConfigurablePropertyInterface;

/**
 * Defines a form for changing a field's configuration.
 */
class FieldConfigurationForm extends EntityForm {

  /**
   * The index for which the fields are configured.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $entity;

  /**
   * The field whose configuration is edited.
   *
   * @var \Drupal\search_api\Item\FieldInterface
   */
  protected $field;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'search_api_field_config';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $field = $this->getField();

    $args['%field'] = $field->getLabel();
    $form['#title'] = $this->t('Edit field %field', $args);

    if (!$field) {
      $args['@id'] = $this->getRequest()->attributes->get('field_id');
      $form['message'] = array(
        '#markup' => $this->t('Unknown field with ID "@id".', $args),
      );
      return $form;
    }

    $property = $field->getDataDefinition();
    if (!($property instanceof ConfigurablePropertyInterface)) {
      $args['%field'] = $field->getLabel();
      $form['message'] = array(
        '#markup' => $this->t('Field %field is not configurable.', $args),
      );
      return $form;
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $field = $this->getField();
    /** @var \Drupal\search_api\Processor\ConfigurablePropertyInterface $property */
    $property = $field->getDataDefinition();

    $form = $property->buildConfigurationForm($field, $form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    unset($actions['delete']);

    $actions['cancel'] = array(
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => $this->entity->toUrl('fields'),
    );

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $field = $this->getField();
    /** @var \Drupal\search_api\Processor\ConfigurablePropertyInterface $property */
    $property = $field->getDataDefinition();
    $property->validateConfigurationForm($field, $form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $field = $this->getField();
    /** @var \Drupal\search_api\Processor\ConfigurablePropertyInterface $property */
    $property = $field->getDataDefinition();
    $property->submitConfigurationForm($field, $form, $form_state);

    drupal_set_message($this->t('The field configuration was successfully saved.'));
    $form_state->setRedirectUrl($this->entity->toUrl('fields'));
  }

  /**
   * Retrieves the field that is being edited.
   *
   * @return \Drupal\search_api\Item\FieldInterface|null
   *   The field, if it exists.
   */
  protected function getField() {
    if (!isset($this->field)) {
      $field_id = $this->getRequest()->attributes->get('field_id');
      $this->field = $this->entity->getField($field_id);
    }

    return $this->field;
  }

}
