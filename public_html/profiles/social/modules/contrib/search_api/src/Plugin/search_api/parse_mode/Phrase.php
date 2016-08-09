<?php

namespace Drupal\search_api\Plugin\search_api\parse_mode;

use Drupal\search_api\ParseMode\ParseModePluginBase;

/**
 * Represents a parse mode that interprets the input as a single phrase.
 *
 * @SearchApiParseMode(
 *   id = "phrase",
 *   label = @Translation("Single phrase"),
 *   description = @Translation("The query is interpreted as a single phrase, possibly containing spaces or special characters, that should appear exactly like this in the results."),
 * )
 */
class Phrase extends ParseModePluginBase {

  /**
   * {@inheritdoc}
   */
  public function parseInput($keys) {
    return array(
      '#conjunction' => $this->getConjunction(),
      $keys,
    );
  }
}
