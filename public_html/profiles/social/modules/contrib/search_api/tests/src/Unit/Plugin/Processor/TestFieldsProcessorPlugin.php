<?php

namespace Drupal\Tests\search_api\Unit\Plugin\Processor;

use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Plugin\search_api\data_type\value\TextValue;
use Drupal\search_api\Processor\FieldsProcessorPluginBase;
use Drupal\search_api\Utility;

/**
 * Mimics a processor working on individual fields of items.
 *
 * Generally just uses the parent implementations for all methods, but also
 * allows temporary overriding of any method. Also implements process() to have
 * an easily recognizable return value.
 *
 * Used by
 * \Drupal\Tests\search_api\Plugin\Processor\FieldsProcessorPluginBaseTest to
 * test the functionality provided by
 * \Drupal\search_api\Processor\FieldsProcessorPluginBase.
 */
class TestFieldsProcessorPlugin extends FieldsProcessorPluginBase {

  /**
   * Array of method overrides, keyed by method name.
   *
   * @var callable[]
   *
   * @see setMethodOverride()
   */
  protected $methodOverrides = array();

  /**
   * Tokenizes the given string by splitting on space characters.
   *
   * @param string $value
   *   The value to be tokenized.
   * @param float $score
   *   (optional) The score to set for all tokens.
   *
   * @return \Drupal\search_api\Plugin\search_api\data_type\value\TextValueInterface
   *   A text value object containing an array of tokens.
   */
  public static function createTokenizedText($value, $score = 1.0) {
    $return = new TextValue($value);
    $tokens = array();
    foreach (explode(' ', $value) as $word) {
      $tokens[] = Utility::createTextToken($word, $score);
    }
    $return->setTokens($tokens);
    return $return;
  }

  /**
   * Overrides a method in this processor.
   *
   * @param string $method
   *   The name of the method to override.
   * @param callable|null $override
   *   The new code of the method, or NULL to use the default.
   */
  public function setMethodOverride($method, $override = NULL) {
    $this->methodOverrides[$method] = $override;
  }

  /**
   * {@inheritdoc}
   */
  protected function testField($name, FieldInterface $field) {
    if (isset($this->methodOverrides[__FUNCTION__])) {
      return $this->methodOverrides[__FUNCTION__]($name, $field);
    }
    return parent::testField($name, $field);
  }

  /**
   * {@inheritdoc}
   */
  protected function testType($type) {
    if (isset($this->methodOverrides[__FUNCTION__])) {
      return $this->methodOverrides[__FUNCTION__]($type);
    }
    return parent::testType($type);
  }

  /**
   * {@inheritdoc}
   */
  protected function processFieldValue(&$value, $type) {
    if (isset($this->methodOverrides[__FUNCTION__])) {
      $this->methodOverrides[__FUNCTION__]($value, $type);
      return;
    }
    parent::processFieldValue($value, $type);
  }

  /**
   * {@inheritdoc}
   */
  protected function processKey(&$value) {
    if (isset($this->methodOverrides[__FUNCTION__])) {
      $this->methodOverrides[__FUNCTION__]($value);
      return;
    }
    parent::processKey($value);
  }

  /**
   * {@inheritdoc}
   */
  protected function processConditionValue(&$value) {
    if (isset($this->methodOverrides[__FUNCTION__])) {
      $this->methodOverrides[__FUNCTION__]($value);
      return;
    }
    parent::processConditionValue($value);
  }

  /**
   * {@inheritdoc}
   */
  protected function process(&$value) {
    if (isset($this->methodOverrides[__FUNCTION__])) {
      $this->methodOverrides[__FUNCTION__]($value);
      return;
    }
    if (isset($value)) {
      $value = "*$value";
    }
    else {
      $value = 'undefined';
    }
  }

}
