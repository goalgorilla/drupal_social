<?php

namespace Drupal\search_api_test\Plugin\search_api\data_type;

use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Provides a dummy data type for testing purposes.
 *
 * @SearchApiDataType(
 *   id = "search_api_test",
 *   label = @Translation("Test data type"),
 *   description = @Translation("Dummy data type implementation")
 * )
 */
class TestDataType extends DataTypePluginBase {

}
