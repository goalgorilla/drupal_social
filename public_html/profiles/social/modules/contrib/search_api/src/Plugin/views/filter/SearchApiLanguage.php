<?php

namespace Drupal\search_api\Plugin\views\filter;

use Drupal\search_api\UncacheableDependencyTrait;
use Drupal\views\Plugin\views\filter\LanguageFilter;

/**
 * Defines a filter for filtering on the language of items.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("search_api_language")
 */
class SearchApiLanguage extends LanguageFilter {

  use UncacheableDependencyTrait;
  use SearchApiFilterTrait;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $substitutions = self::queryLanguageSubstitutions();
    foreach ($this->value as $i => $value) {
      if (isset($substitutions[$value])) {
        $this->value[$i] = $substitutions[$value];
      }
    }

    // Only set the languages using $query->setLanguages() if the condition
    // would be placed directly on the query, as an AND condition.
    $query = $this->getQuery();
    $direct_condition = $this->operator == 'in'
      && $query->getGroupType($this->options['group'])
      && $query->getGroupOperator() == 'AND';
    if ($direct_condition) {
      $query->setLanguages($this->value);
    }
    else {
      parent::query();
    }
  }

}
