<?php

namespace Drupal\search_api\Plugin\search_api\parse_mode;

use Drupal\search_api\ParseMode\ParseModePluginBase;

/**
 * Represents a parse mode that parses the input into multiple words.
 *
 * @SearchApiParseMode(
 *   id = "terms",
 *   label = @Translation("Multiple words"),
 *   description = @Translation("The query is interpreted as multiple keywords separated by spaces. Keywords containing spaces may be ""quoted"". Quoted keywords must still be separated by spaces."),
 * )
 */
class Terms extends ParseModePluginBase {

  /**
   * {@inheritdoc}
   */
  public function parseInput($keys) {
    $ret = explode(' ', $keys);
    $quoted = FALSE;
    $str = '';
    foreach ($ret as $k => $v) {
      if (!$v) {
        continue;
      }
      if ($quoted) {
        if (substr($v, -1) == '"') {
          $v = substr($v, 0, -1);
          $str .= ' ' . $v;
          $ret[$k] = $str;
          $quoted = FALSE;
        }
        else {
          $str .= ' ' . $v;
          unset($ret[$k]);
        }
      }
      elseif ($v[0] == '"') {
        $len = strlen($v);
        if ($len > 1 && $v[$len - 1] == '"') {
          $ret[$k] = substr($v, 1, -1);
        }
        else {
          $str = substr($v, 1);
          $quoted = TRUE;
          unset($ret[$k]);
        }
      }
    }
    if ($quoted) {
      $ret[] = $str;
    }
    $ret['#conjunction'] = $this->getConjunction();
    return array_filter($ret);
  }
}
