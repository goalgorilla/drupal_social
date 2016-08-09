<?php

namespace Drupal\search_api\Processor;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Plugin\search_api\data_type\value\TextValueInterface;
use Drupal\search_api\Query\ConditionGroupInterface;
use Drupal\search_api\Query\ConditionInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Utility;

/**
 * Provides a base class for processors that work on individual fields.
 *
 * A form element to select the fields to run on is provided, as well as easily
 * overridable methods to provide the actual functionality. Subclasses can
 * override any of these methods (or the interface methods themselves, of
 * course) to provide their specific functionality:
 * - processField()
 * - processFieldValue()
 * - processKeys()
 * - processKey()
 * - processConditions()
 * - processConditionValue()
 * - process()
 *
 * The following methods can be used for specific logic regarding the fields to
 * run on:
 * - testField()
 * - testType()
 */
abstract class FieldsProcessorPluginBase extends ProcessorPluginBase {

  // @todo Add defaultConfiguration() implementation and find a cleaner solution
  //   for all the isset($this->configuration['fields']) checks.

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $fields = $this->index->getFields();
    $field_options = array();
    $default_fields = array();
    if (isset($this->configuration['fields'])) {
      $default_fields = array_filter($this->configuration['fields']);
    }
    foreach ($fields as $name => $field) {
      if ($this->testType($field->getType())) {
        $field_options[$name] = Html::escape($field->getPrefixedLabel());
        if (!isset($this->configuration['fields']) && $this->testField($name, $field)) {
          $default_fields[$name] = $name;
        }
      }
    }

    $form['fields'] = array(
      '#type' => 'checkboxes',
      '#title' => $this->t('Enable this processor on the following fields'),
      '#options' => $field_options,
      '#default_value' => $default_fields,
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    $fields = array_filter($form_state->getValues()['fields']);
    if ($fields) {
      $fields = array_keys($fields);
    }
    $form_state->setValue('fields', $fields);
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array $items) {
    // Annoyingly, this doc comment is needed for PHPStorm. See
    // http://youtrack.jetbrains.com/issue/WI-23586
    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($items as $item) {
      foreach ($item->getFields() as $name => $field) {
        if ($this->testField($name, $field)) {
          $this->processField($field);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessSearchQuery(QueryInterface $query) {
    $keys = &$query->getKeys();
    if (isset($keys)) {
      $this->processKeys($keys);
    }
    $conditions = $query->getConditionGroup();
    $this->processConditions($conditions->getConditions());
  }

  /**
   * Processes a single field's value.
   *
   * Calls process() either for each value, or each token, depending on the
   * type. Also takes care of extracting list values and of fusing returned
   * tokens back into a one-dimensional array.
   *
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   The field to process.
   */
  protected function processField(FieldInterface $field) {
    $values = $field->getValues();
    $type = $field->getType();

    foreach ($values as $i => &$value) {
      // We restore the field's type for each run of the loop since we need the
      // unchanged one as long as the current field value hasn't been updated.
      if ($value instanceof TextValueInterface) {
        $tokens = $value->getTokens();
        if ($tokens !== NULL) {
          $new_tokens = array();
          foreach ($tokens as $token) {
            $token_text = $token->getText();
            $this->processFieldValue($token_text, $type);
            if (is_scalar($token_text)) {
              if ($token_text !== '') {
                $token->setText($token_text);
                $new_tokens[] = $token;
              }
            }
            else {
              $base_boost = $token->getBoost();
              /** @var \Drupal\search_api\Plugin\search_api\data_type\value\TextTokenInterface $new_token */
              foreach ($token_text as $new_token) {
                if ($new_token->getText() !== '') {
                  $new_token->setBoost($new_token->getBoost() * $base_boost);
                  $new_tokens[] = $new_token;
                }
              }
            }
          }
          $value->setTokens($new_tokens);
        }
        else {
          $text = $value->getText();
          if ($text !== '') {
            $this->processFieldValue($text, $type);
            if ($text === '') {
              unset($values[$i]);
            }
            elseif (is_scalar($text)) {
              $value->setText($text);
            }
            else {
              $value->setTokens($text);
            }
          }
        }
      }
      elseif ($value !== '') {
        $this->processFieldValue($value, $type);

        if ($value === '') {
          unset($values[$i]);
        }
      }
    }

    $field->setValues(array_values($values));
  }

  /**
   * Preprocesses the search keywords.
   *
   * Calls processKey() for individual strings.
   *
   * @param array|string $keys
   *   Either a parsed keys array, or a single keywords string.
   */
  protected function processKeys(&$keys) {
    if (is_array($keys)) {
      foreach ($keys as $key => &$v) {
        if (Element::child($key)) {
          $this->processKeys($v);
          if ($v === '') {
            unset($keys[$key]);
          }
        }
      }
    }
    else {
      $this->processKey($keys);
    }
  }

  /**
   * Preprocesses the query conditions.
   *
   * @param \Drupal\search_api\Query\ConditionInterface[]|\Drupal\search_api\Query\ConditionGroupInterface[] $conditions
   *   An array of conditions, as returned by
   *   \Drupal\search_api\Query\ConditionGroupInterface::getConditions(),
   *   passed by reference.
   */
  protected function processConditions(array &$conditions) {
    $fields = $this->index->getFields();
    foreach ($conditions as $key => &$condition) {
      if ($condition instanceof ConditionInterface) {
        $field = $condition->getField();
        if (isset($fields[$field]) && $this->testField($field, $fields[$field])) {
          // We want to allow processors also to easily remove complete
          // conditions. However, we can't use empty() or the like, as that
          // would sort out filters for 0 or NULL. So we specifically check only
          // for the empty string, and we also make sure the condition value was
          // actually changed by storing whether it was empty before.
          $value = $condition->getValue();
          $empty_string = $value === '';
          $this->processConditionValue($value);

          // The (NOT) BETWEEN operators deserve special attention, as it seems
          // unlikely that it makes sense to completely remove them. Processors
          // that remove values are normally indicating that this value can't be
          // in the index – but that's irrelevant for (NOT) BETWEEN conditions,
          // as any value between the two bounds could still be included. We
          // therefore never remove a (NOT) BETWEEN condition and also ignore it
          // when one of the two values got removed. (Note that this check will
          // also catch empty strings.) Processors who need different behavior
          // have to override this method.
          $between_operator = in_array($condition->getOperator(), array('BETWEEN', 'NOT BETWEEN'));
          if ($between_operator && count($value) < 2) {
            continue;
          }

          if ($value === '' && !$empty_string) {
            unset($conditions[$key]);
          }
          else {
            $condition->setValue($value);
          }
        }
      }
      elseif ($condition instanceof ConditionGroupInterface) {
        $child_conditions = &$condition->getConditions();
        $this->processConditions($child_conditions);
      }
    }
  }

  /**
   * Tests whether a certain field should be processed.
   *
   * @param string $name
   *   The field's ID.
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   The field's information.
   *
   * @return bool
   *   TRUE if the field should be processed, FALSE otherwise.
   */
  protected function testField($name, FieldInterface $field) {
    if (!isset($this->configuration['fields'])) {
      return $this->testType($field->getType());
    }
    return in_array($name, $this->configuration['fields'], TRUE);
  }

  /**
   * Determines whether a field of a certain type should be preprocessed.
   *
   * The default implementation returns TRUE for "text" and "string".
   *
   * @param string $type
   *   The type of the field (either when preprocessing the field at index time,
   *   or a condition on the field at query time).
   *
   * @return bool
   *   TRUE if fields of that type should be processed, FALSE otherwise.
   */
  protected function testType($type) {
    return Utility::isTextType($type, array('text', 'string'));
  }

  /**
   * Processes a single text element in a field.
   *
   * The default implementation just calls process().
   *
   * @param string $value
   *   The string value to preprocess, as a reference. Can be manipulated
   *   directly, nothing has to be returned. Can either be left a string, or
   *   changed into an array of
   *   \Drupal\search_api\Plugin\search_api\data_type\value\TextTokenInterface
   *   objects. Returning anything else will result in undefined behavior.
   * @param string $type
   *   The field's data type.
   */
  protected function processFieldValue(&$value, $type) {
    $this->process($value);
  }

  /**
   * Processes a single search keyword.
   *
   * The default implementation just calls process().
   *
   * @param string $value
   *   The string value to preprocess, as a reference. Can be manipulated
   *   directly, nothing has to be returned. Can either be left a string, or be
   *   changed into a nested keys array, as defined by
   *   \Drupal\search_api\ParseMode\ParseModeInterface::parseInput().
   */
  protected function processKey(&$value) {
    $this->process($value);
  }

  /**
   * Processes a single condition value.
   *
   * Called for processing a single condition value. The default implementation
   * just calls process().
   *
   * @param mixed $value
   *   The condition value to preprocess, as a reference. Can be manipulated
   *   directly, nothing has to be returned. Set to an empty string to remove
   *   the condition.
   */
  protected function processConditionValue(&$value) {
    if (is_array($value)) {
      if ($value) {
        foreach ($value as $i => $part) {
          $this->processConditionValue($value[$i]);
          if ($value[$i] !== $part && $value[$i] === '') {
            unset($value[$i]);
          }
        }
        if (!$value) {
          $value = '';
        }
      }
    }
    else {
      $this->process($value);
    }
  }

  /**
   * Processes a single string value.
   *
   * This method is ultimately called for all text by the standard
   * implementation, and does nothing by default.
   *
   * @param string $value
   *   The string value to preprocess, as a reference. Can be manipulated
   *   directly, nothing has to be returned. Since this can be called for all
   *   value types, $value has to remain a string.
   */
  protected function process(&$value) {}

}
