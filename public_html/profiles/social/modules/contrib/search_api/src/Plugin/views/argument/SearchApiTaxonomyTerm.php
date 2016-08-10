<?php

namespace Drupal\search_api\Plugin\views\argument;

use Drupal\Component\Utility\Html;
use Drupal\search_api\UncacheableDependencyTrait;
use Drupal\taxonomy\Entity\Term;

/**
 * Defines a contextual filter searching through all indexed taxonomy fields.
 *
 * @ingroup views_argument_handlers
 *
 * @ViewsArgument("search_api_taxonomy_term")
 */
class SearchApiTaxonomyTerm extends SearchApiStandard {

  use UncacheableDependencyTrait;

  /**
   * {@inheritdoc}
   */
  public function title() {
    if (!empty($this->argument)) {
      $this->fillValue();
      $terms = array();
      foreach ($this->value as $tid) {
        $taxonomy_term = Term::load($tid);
        if ($taxonomy_term) {
          $terms[] = Html::escape($taxonomy_term->label());
        }
      }

      return $terms ? implode(', ', $terms) : Html::escape($this->argument);
    }
    else {
      return Html::escape($this->argument);
    }
  }

}
