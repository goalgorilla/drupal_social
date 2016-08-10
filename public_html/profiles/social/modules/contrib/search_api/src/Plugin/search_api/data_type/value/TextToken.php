<?php

namespace Drupal\search_api\Plugin\search_api\data_type\value;

/**
 * Represents a single text token contained in a fulltext field's value.
 *
 * @see \Drupal\search_api\Plugin\search_api\data_type\value\TextValueInterface
 */
class TextToken implements TextTokenInterface {

  /**
   * The actual text value of this token.
   *
   * @var string
   */
  protected $text;

  /**
   * The boost value for this token.
   *
   * @var float
   */
  protected $boost = 1.0;

  /**
   * Constructs a TextToken object.
   *
   * @param string $text
   *   The text value of the token.
   * @param float $boost
   *   (optional) The boost for the token.
   */
  public function __construct($text, $boost = 1.0) {
    $this->text = $text;
    $this->boost = $boost;
  }

  /**
   * {@inheritdoc}
   */
  public function getText() {
    return $this->text;
  }

  /**
   * {@inheritdoc}
   */
  public function setText($text) {
    $this->text = $text;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getBoost() {
    return $this->boost;
  }

  /**
   * {@inheritdoc}
   */
  public function setBoost($boost) {
    $this->boost = $boost;
    return $this;
  }

  /**
   * Implements the magic __toString() method.
   */
  public function __toString() {
    return $this->getText();
  }

}
