<?php

namespace Drupal\search_api\Plugin\views\filter;

use Drupal\search_api\UncacheableDependencyTrait;
use Drupal\views\Plugin\views\filter\Date;

/**
 * Defines a filter for filtering on dates.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("search_api_date")
 */
class SearchApiDate extends Date {

  use UncacheableDependencyTrait;
  use SearchApiFilterTrait;

  /**
   * {@inheritdoc}
   */
  public function operators() {
    $operators = parent::operators();
    // @todo Enable "(not) between" again once that operator is available in
    //   the Search API.
    unset($operators['between'], $operators['not between'], $operators['regular_expression']);
    return $operators;
  }

  /**
   * {@inheritdoc}
   */
  public function acceptExposedInput($input) {
    if (empty($this->options['exposed'])) {
      return TRUE;
    }

    // Unfortunately, this is necessary due to a bug in our parent filter. See
    // #2704077.
    if (!empty($this->options['expose']['identifier'])) {
      $value = &$input[$this->options['expose']['identifier']];
      if (!is_array($value)) {
        $value = array(
          'value' => $value,
        );
      }
      $value += array(
        'min' => '',
        'max' => '',
      );
    }

    // Store this because it will get overwritten by the grandparent, and the
    // parent doesn't always restore it correctly.
    $type = $this->value['type'];
    $return = parent::acceptExposedInput($input);

    if (!$return) {
      // If the parent returns FALSE, it doesn't restore the "type" key.
      $this->value['type'] = $type;
      // Override for the "(not) empty" operators.
      $operators = $this->operators();
      if ($operators[$this->operator]['values'] == 0) {
        return TRUE;
      }
    }

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  protected function opSimple($field) {
    $value = intval(strtotime($this->value['value'], 0));
    if (!empty($this->value['type']) && $this->value['type'] == 'offset') {
      $value = strtotime($this->value['value'], REQUEST_TIME);
    }

    $this->getQuery()->addCondition($this->realField, $value, $this->operator, $this->options['group']);
  }

  /**
   * {@inheritdoc}
   */
  protected function opEmpty($field) {
    $operator = ($this->operator == 'empty') ? '=' : '<>';
    $this->getQuery()->addCondition($this->realField, NULL, $operator, $this->options['group']);
  }

}
