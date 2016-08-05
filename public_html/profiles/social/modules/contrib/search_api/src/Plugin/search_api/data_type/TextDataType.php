<?php

namespace Drupal\search_api\Plugin\search_api\data_type;

use Drupal\search_api\DataType\DataTypePluginBase;
use Drupal\search_api\Plugin\search_api\data_type\value\TextValue;

/**
 * Provides a full text data type.
 *
 * This data type uses objects of type
 * \Drupal\search_api\Plugin\search_api\data_type\value\TextValueInterface for
 * its values.
 *
 * The same is expected of all data types that specify this type as their
 * fallback.
 *
 * @see \Drupal\search_api\Plugin\search_api\data_type\value\TextValueInterface
 *
 * @SearchApiDataType(
 *   id = "text",
 *   label = @Translation("Fulltext"),
 *   description = @Translation("Fulltext fields are analyzed fields which are made available for fulltext search. This data type should be used for any fields (usually with free text input by users) which you want to search for individual words."),
 *   default = "true"
 * )
 */
class TextDataType extends DataTypePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getValue($value) {
    return new TextValue((string) $value);
  }

}
