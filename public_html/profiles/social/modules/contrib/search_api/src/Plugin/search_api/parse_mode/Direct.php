<?php

namespace Drupal\search_api\Plugin\search_api\parse_mode;

use Drupal\search_api\ParseMode\ParseModePluginBase;

/**
 * Represents a parse mode that just passes the user input on as-is.
 *
 * @SearchApiParseMode(
 *   id = "direct",
 *   label = @Translation("Direct query"),
 *   description = @Translation("Don't parse the query, just hand it to the search server unaltered. Might fail if the query contains syntax errors in regard to the specific server's query syntax."),
 * )
 */
class Direct extends ParseModePluginBase {

  /**
   * {@inheritdoc}
   */
  public function parseInput($keys) {
    return $keys;
  }
}
