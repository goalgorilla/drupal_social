<?php

namespace Drupal\search_api\Plugin\search_api\data_type\value;

/**
 * Provides an interface for fulltext field values.
 */
interface TextValueInterface {

  /**
   * Retrieves the currently stored text value.
   *
   * @return string
   *   The currently stored text value.
   *
   * @see \Drupal\search_api\Plugin\search_api\data_type\value\TextValue::toText()
   */
  public function getText();

  /**
   * Retrieves the current effective text value.
   *
   * This will be a concatenation of all tokens' text values, if tokens have
   * been set, or the currently stored text value otherwise.
   *
   * @return string
   *   The effective text value for this object.
   */
  public function toText();

  /**
   * Sets the currently stored text value.
   *
   * @param string $text
   *   The new text value.
   *
   * @return $this
   */
  public function setText($text);

  /**
   * Retrieves the original text value.
   *
   * @return string
   *   The original text value.
   */
  public function getOriginalText();

  /**
   * Sets the original text value.
   *
   * @param string $originalText
   *   The new original text value.
   *
   * @return $this
   */
  public function setOriginalText($originalText);

  /**
   * Retrieves the text tokens this text value was split into, if any.
   *
   * @return \Drupal\search_api\Plugin\search_api\data_type\value\TextTokenInterface[]|null
   *   The text tokens this text value was split into, or NULL if the value has
   *   not been tokenized in any way yet.
   */
  public function getTokens();

  /**
   * Sets the text tokens for the text value.
   *
   * @param \Drupal\search_api\Plugin\search_api\data_type\value\TextTokenInterface[]|null $tokens
   *   The new text tokens, or NULL to remove them.
   *
   * @return $this
   */
  public function setTokens(array $tokens = NULL);

  /**
   * Retrieves the properties set for this text value.
   *
   * @return array
   *   An associative array of properties. Known properties include:
   *   - lowercase: Whether the value has been lowercased (type: bool)
   *   - tokenized: Whether the value has been tokenized into individual words
   *     (type: bool)
   *   - strip_html: Whether HTML has been stripped from this value (type: bool)
   */
  public function getProperties();

  /**
   * Retrieves a specific property of this text value.
   *
   * @param string $name
   *   The property's name.
   * @param mixed $default
   *   (optional) The default to return if the property wasn't set yet.
   *
   * @return mixed
   *   Either the property's value, or the given $default if it wasn't set yet.
   */
  public function getProperty($name, $default = NULL);

  /**
   * Sets the properties of this text value.
   *
   * @param array $properties
   *   An associative array of properties.
   *
   * @return $this
   */
  public function setProperties($properties);

  /**
   * Sets the properties of this text value.
   *
   * @param string $name
   *   The property's name.
   * @param mixed $value
   *   (optional) The value to set for the property.
   *
   * @return $this
   */
  public function setProperty($name, $value = TRUE);

}
