<?php

namespace Drupal\search_api\Processor;

use Drupal\Core\TypedData\DataDefinition;

/**
 * Provides a base class for normal processor-defined properties.
 */
class ProcessorProperty extends DataDefinition implements ProcessorPropertyInterface {

  /**
   * {@inheritdoc}
   */
  public function getProcessorId() {
    return $this->definition['processor_id'];
  }

  /**
   * {@inheritdoc}
   */
  public function isHidden() {
    return !empty($this->definition['hidden']);
  }

}
