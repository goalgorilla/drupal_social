<?php

namespace Drupal\search_api\Processor;

use Drupal\Core\TypedData\DataDefinitionInterface;

/**
 * Provides an interface for processor-defined properties.
 */
interface ProcessorPropertyInterface extends DataDefinitionInterface {

  /**
   * Retrieves the ID of the processor which defines this property.
   *
   * @return string
   *   The defining processor's plugin ID.
   */
  public function getProcessorId();

  /**
   * Determines whether this property should be hidden from the UI.
   *
   * @return bool
   *   TRUE if this property should not be displayed in the UI, FALSE otherwise.
   */
  public function isHidden();

}
