<?php

namespace Drupal\search_api\Plugin\views\argument;

use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\UncacheableDependencyTrait;
use Drupal\views\Plugin\views\argument\ArgumentPluginBase;

/**
 * Defines a contextual filter for applying Search API conditions.
 *
 * @ingroup views_argument_handlers
 *
 * @ViewsArgument("search_api")
 */
class SearchApiStandard extends ArgumentPluginBase {

  use UncacheableDependencyTrait;

  /**
   * The Views query object used by this contextual filter.
   *
   * @var \Drupal\search_api\Plugin\views\query\SearchApiQuery
   */
  public $query;

  /**
   * The operator to use for multiple arguments.
   *
   * Either "and" or "or".
   *
   * @var string
   *
   * @see \Drupal\views\Plugin\views\argument\ArgumentPluginBase::unpackArgumentValue()
   */
  public $operator;

  /**
   * {@inheritdoc}
   */
  public function defaultActions($which = NULL) {
    $defaults = parent::defaultActions();
    unset($defaults['summary']);

    if ($which) {
      return isset($defaults[$which]) ? $defaults[$which] : NULL;
    }
    return $defaults;
  }

  /**
   * {@inheritdoc}
   */
  public function defineOptions() {
    $options = parent::defineOptions();

    $options['break_phrase'] = array('default' => FALSE);
    $options['not'] = array('default' => FALSE);

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    if (empty($this->definition['disable_break_phrase'])) {
      // Allow passing multiple values.
      $form['break_phrase'] = array(
        '#type' => 'checkbox',
        '#title' => $this->t('Allow multiple values'),
        '#description' => $this->t('If selected, users can enter multiple values in the form of 1+2+3 (for OR) or 1,2,3 (for AND).'),
        '#default_value' => !empty($this->options['break_phrase']),
        '#group' => 'options][more',
      );
    }

    $form['not'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Exclude'),
      '#description' => $this->t('If selected, the values entered for the filter will be excluded rather than limiting the view to those values.'),
      '#default_value' => !empty($this->options['not']),
      '#group' => 'options][more',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE) {
    $this->fillValue();

    if (count($this->value) > 1) {
      $operator = empty($this->options['not']) ? 'IN' : 'NOT IN';
      $this->query->addCondition($this->realField, $this->value, $operator);
    }
    elseif ($this->value) {
      $operator = empty($this->options['not']) ? '=' : '<>';
      $this->query->addCondition($this->realField, reset($this->value), $operator);
    }
  }

  /**
   * Fills $this->value and $this->operator with data from the argument.
   */
  protected function fillValue() {
    if (isset($this->value)) {
      return;
    }

    $filter = '';
    if (!empty($this->definition['filter'])) {
      $filter = $this->definition['filter'];
    }

    if (!empty($this->options['break_phrase']) && empty($this->definition['disable_break_phrase'])) {
      $force_int = FALSE;
      if ($filter == 'intval') {
        $force_int = TRUE;
        $filter = '';
      }
      $this->unpackArgumentValue($force_int);
    }
    else {
      $this->value = array($this->argument);
      $this->operator = 'and';
    }

    if (is_callable($filter)) {
      $this->value = array_map($filter, $this->value);
    }
  }

}
