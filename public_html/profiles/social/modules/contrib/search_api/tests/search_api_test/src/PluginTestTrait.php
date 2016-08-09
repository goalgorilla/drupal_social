<?php

namespace Drupal\search_api_test;

/**
 * Provides functionality for tests that deal with test plugins.
 *
 * @see \Drupal\search_api_test\TestPluginTrait
 */
trait PluginTestTrait {

  /**
   * Sets an exception to be thrown on calls to the given method.
   *
   * @param string $plugin_type
   *   The "short" plugin type.
   * @param string $method
   *   The method on the plugin object which should throw an exception.
   * @param bool $error
   *   (optional) If TRUE, further calls to the method will throw exceptions,
   *   otherwise they won't.
   */
  protected function setError($plugin_type, $method, $error = TRUE) {
    $key = "search_api_test.$plugin_type.exception.$method";
    \Drupal::state()->set($key, $error);
  }

  /**
   * Sets the return value for a certain method on a test plugin.
   *
   * @param string $plugin_type
   *   The "short" plugin type.
   * @param string $method
   *   The method name.
   * @param mixed $value
   *   The value that should be returned.
   */
  protected function setReturnValue($plugin_type, $method, $value) {
    $key = "search_api_test.$plugin_type.return.$method";
    \Drupal::state()->set($key, $value);
  }

  /**
   * Retrieves the methods called on a given plugin.
   *
   * @param string $plugin_type
   *   The "short" plugin type.
   * @param bool $reset
   *   (optional) If TRUE, also clear the list of called methods for that type.
   *
   * @return string[]
   *   The methods called on the given plugin.
   */
  protected function getCalledMethods($plugin_type, $reset = TRUE) {
    $key = "search_api_test.$plugin_type.methods_called";
    $methods = \Drupal::state()->get($key, array());
    if ($reset) {
      \Drupal::state()->delete($key);
    }
    return $methods;
  }

  /**
   * Retrieves the arguments of a certain method called on the given plugin.
   *
   * @param string $plugin_type
   *   The "short" plugin type.
   * @param string $method
   *   The method name.
   *
   * @return array
   *   The arguments of the last call to the method.
   */
  protected function getMethodArguments($plugin_type, $method) {
    $key = "search_api_test.$plugin_type.method_arguments.$method";
    return \Drupal::state()->get($key);
  }

}
