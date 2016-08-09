<?php

namespace Drupal\search_api\Plugin\search_api\processor\Property;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Processor\ConfigurablePropertyBase;

/**
 * Defines a "rendered item" property.
 */
class RenderedItemProperty extends ConfigurablePropertyBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array(
      'roles' => array(AccountInterface::ANONYMOUS_ROLE),
      'view_mode' => array(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(FieldInterface $field, array $form, FormStateInterface $form_state) {
    $configuration = $field->getConfiguration();
    $index = $field->getIndex();
    $form['#tree'] = TRUE;

    $roles = user_role_names();
    $form['roles'] = array(
      '#type' => 'select',
      '#title' => $this->t('User roles'),
      '#description' => $this->t('Your item will be rendered as seen by a user with the selected roles. We recommend to just use "@anonymous" here to prevent data leaking out to unauthorized roles.', array('@anonymous' => $roles[AccountInterface::ANONYMOUS_ROLE])),
      '#options' => $roles,
      '#multiple' => TRUE,
      '#default_value' => $configuration['roles'],
      '#required' => TRUE,
    );

    $form['view_mode'] = array(
      '#type' => 'item',
      '#description' => $this->t('You can choose the view modes to use for rendering the items of different datasources and bundles. We recommend using a dedicated view mode (e.g., the "Search index" view mode available by default for content) to make sure that only relevant data (especially no field labels) will be included in the index.'),
    );

    $options_present = FALSE;
    foreach ($index->getDatasources() as $datasource_id => $datasource) {
      $bundles = $datasource->getBundles();
      foreach ($bundles as $bundle_id => $bundle_label) {
        $view_modes = $datasource->getViewModes($bundle_id);
        $view_modes[''] = $this->t("Don't include the rendered item.");
        if (count($view_modes) > 1) {
          $form['view_mode'][$datasource_id][$bundle_id] = array(
            '#type' => 'select',
            '#title' => $this->t('View mode for %datasource » %bundle', array('%datasource' => $datasource->label(), '%bundle' => $bundle_label)),
            '#options' => $view_modes,
          );
          if (isset($configuration['view_mode'][$datasource_id][$bundle_id])) {
            $form['view_mode'][$datasource_id][$bundle_id]['#default_value'] = $configuration['view_mode'][$datasource_id][$bundle_id];
          }
          $options_present = TRUE;
        }
        else {
          $form['view_mode'][$datasource_id][$bundle_id] = array(
            '#type' => 'value',
            '#value' => $view_modes ? key($view_modes) : FALSE,
          );
        }
      }
    }
    // If there are no datasources/bundles with more than one view mode, don't
    // display the description either.
    if (!$options_present) {
      unset($form['view_mode']['#type']);
      unset($form['view_mode']['#description']);
    }

    return $form;
  }

}
