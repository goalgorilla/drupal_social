<?php

namespace Drupal\search_api\ParseMode;

use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Defines an interface for parse mode plugins.
 *
 * @see \Drupal\search_api\Annotation\SearchApiParseMode
 * @see \Drupal\search_api\ParseMode\ParseModePluginManager
 * @see \Drupal\search_api\ParseMode\ParseModePluginBase
 * @see plugin_api
 */
interface ParseModeInterface extends PluginInspectionInterface, DerivativeInspectionInterface, ContainerFactoryPluginInterface {

  /**
   * Returns the label of the parse mode.
   *
   * @return string
   *   The administration label.
   */
  public function label();

  /**
   * Returns the description of the parse mode.
   */
  public function getDescription();

  /**
   * Retrieves the default conjunction.
   *
   * @return string
   *   The default conjunction to be used when parsing keywords. Can be either
   *   "AND" or "OR".
   */
  public function getConjunction();

  /**
   * Sets the default conjunction.
   *
   * @param string $conjunction
   *   The default conjunction to be used when parsing keywords. Can be either
   *   "AND" or "OR".
   *
   * @return $this
   */
  public function setConjunction($conjunction);

  /**
   * Parses search keys input by the user.
   *
   * @param string $keys
   *   The keywords to parse.
   *
   * @return array|string|null
   *   The parsed keywords – either a string, or an array specifying a complex
   *   search expression, or NULL if no keywords should be set for this input.
   *   An array will contain a '#conjunction' key specifying the conjunction
   *   type, and search strings or nested expression arrays at numeric keys.
   *   Additionally, a '#negation' key might be present, which means – unless it
   *   maps to a FALSE value – that the search keys contained in that array
   *   should be negated, i.e. not be present in returned results. The negation
   *   works on the whole array, not on each contained term individually – i.e.,
   *   with the "AND" conjunction and negation, only results that contain all
   *   the terms in the array should be excluded; with the "OR" conjunction and
   *   negation, all results containing one or more of the terms in the array
   *   should be excluded.
   */
  public function parseInput($keys);

}
