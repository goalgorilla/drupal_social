<?php

namespace Drupal\search_api\Plugin\search_api\data_type\value;

/**
 * Represents a single value of a fulltext field.
 */
class TextValue implements TextValueInterface {

  /**
   * The current text value.
   *
   * @var string
   */
  protected $text;

  /**
   * The original text value.
   *
   * @var string
   */
  protected $originalText;

  /**
   * The tokens created for this text value (if any).
   *
   * @var \Drupal\search_api\Plugin\search_api\data_type\value\TextTokenInterface[]|null
   */
  protected $tokens;

  /**
   * An array of properties for this text value.
   *
   * @var array
   */
  protected $properties = array();

  /**
   * Constructs a TextValue object.
   *
   * @param string $text
   *   The original text value.
   */
  public function __construct($text) {
    $this->text = $this->originalText = $text;
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
  public function toText() {
    $tokens = $this->getTokens();
    if ($tokens !== NULL) {
      $to_string = function (TextTokenInterface $token) {
        return $token->getText();
      };
      return implode(' ', array_map($to_string, $tokens));
    }
    return $this->getText();
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
  public function getOriginalText() {
    return $this->originalText;
  }

  /**
   * {@inheritdoc}
   */
  public function setOriginalText($originalText) {
    $this->originalText = $originalText;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTokens() {
    return $this->tokens;
  }

  /**
   * {@inheritdoc}
   */
  public function setTokens(array $tokens = NULL) {
    $this->tokens = $tokens;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getProperties() {
    return $this->properties;
  }

  /**
   * {@inheritdoc}
   */
  public function getProperty($name, $default = NULL) {
    if (array_key_exists($name, $this->properties)) {
      return $this->properties[$name];
    }
    return $default;
  }

  /**
   * {@inheritdoc}
   */
  public function setProperties($properties) {
    $this->properties = $properties;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setProperty($name, $value = TRUE) {
    $this->properties[$name] = $value;
    return $this;
  }

  /**
   * Implements the magic __toString() method.
   */
  public function __toString() {
    return $this->toText();
  }

}
