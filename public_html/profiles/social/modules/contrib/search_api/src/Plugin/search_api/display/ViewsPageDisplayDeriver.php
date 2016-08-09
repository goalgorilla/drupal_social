<?php

namespace Drupal\search_api\Plugin\search_api\display;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Plugin\PluginBase;
use Drupal\search_api\Display\DisplayDeriverBase;
use Drupal\search_api\Plugin\views\query\SearchApiQuery;

/**
 * Derives a display plugin definition for every Search API views page.
 *
 * @see \Drupal\search_api\Plugin\search_api\display\ViewsPageDisplay
 */
class ViewsPageDisplayDeriver extends DisplayDeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $base_plugin_id = $base_plugin_definition['id'];

    try {
      /** @var \Drupal\Core\Entity\EntityStorageInterface $views_storage */
      $views_storage = $this->entityTypeManager->getStorage('view');
      $all_views = $views_storage->loadMultiple();
    }
    catch (PluginNotFoundException $e) {
      return array();
    }

    if (!isset($this->derivatives[$base_plugin_id])) {
      $plugin_derivatives = array();

      /** @var \Drupal\views\Entity\View $view */
      foreach ($all_views as $view) {
        $index = SearchApiQuery::getIndexFromTable($view->get('base_table'));
        if ($index) {
          $displays = $view->get('display');
          foreach ($displays as $name => $display_info) {
            if ($display_info['display_plugin'] == "page") {
              $machine_name = $base = $view->id() . '__' . $name;
              // Make sure the machine name is unique. (Will almost always be
              // the case, unless a view or page ID contains two consecutive
              // underscores.)
              $i = 0;
              while (isset($plugin_derivatives[$machine_name])) {
                $machine_name = $base . '_' . ++$i;
              }

              $label_arguments = array(
                '%view_name' => $view->label(),
                '%display_title' => $display_info['display_title'],
              );
              $label = $this->t('View %view_name, display %display_title', $label_arguments);

              $executable = $view->getExecutable();
              $executable->setDisplay($name);
              $display = $executable->getDisplay();
              $plugin_derivatives[$machine_name] = array(
                'id' => $base_plugin_id . PluginBase::DERIVATIVE_SEPARATOR . $machine_name,
                'label' => $label,
                'description' => $view->get('description') ? $this->t('%view_description - Represents the page display %display_title of view %view_name.', array('%view_name' => $view->label(), '%view_description' => $view->get('description'), '%display_title' => $display_info['display_title'])) : $this->t('Represents the page display %display_title of view %view_name.', array('%view_name' => $view->label(), '%display_title' => $display_info['display_title'])),
                'view_id' => $view->id(),
                'view_display' => $name,
                'path' => '/' . $display->getPath(),
                'index' => $index->id(),
              ) + $base_plugin_definition;

              $arguments = array(
                '%view' => $view->label(),
                '%display' => $display_info['display_title'],
              );
              $sources[] = $this->t('View name: %view. Display: %display', $arguments);
            }
          }
        }
      }
      uasort($plugin_derivatives, array($this, 'compareDerivatives'));

      $this->derivatives[$base_plugin_id] = $plugin_derivatives;
    }
    return $this->derivatives[$base_plugin_id];
  }

}
