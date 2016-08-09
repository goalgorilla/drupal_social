<?php

namespace Drupal\features;

use Drupal\Core\Config\Entity\ConfigDependencyManager;
use Drupal\Core\Config\Entity\ConfigEntityDependency;

/**
 * Class FeaturesConfigDependencyManager
 * @package Drupal\features
 */
class FeaturesConfigDependencyManager extends ConfigDependencyManager{

  protected $sorted_graph;

  /**
   * {@inheritdoc}
   */
  public function getDependentEntities($type, $name) {
    $dependent_entities = array();

    $entities_to_check = array();
    if ($type == 'config') {
      $entities_to_check[] = $name;
    }
    else {
      if ($type == 'module' || $type == 'theme' || $type == 'content') {
        $dependent_entities = array_filter($this->data, function (ConfigEntityDependency $entity) use ($type, $name) {
          return $entity->hasDependency($type, $name);
        });
      }
      // If checking content, module, or theme dependencies, discover which
      // entities are dependent on the entities that have a direct dependency.
      foreach ($dependent_entities as $entity) {
        $entities_to_check[] = $entity->getConfigDependencyName();
      }
    }
    $dependencies = array_merge($this->createGraphConfigEntityDependencies($entities_to_check), $dependent_entities);
    if (!$this->sorted_graph) {
      // Sort dependencies in the reverse order of the graph. So the least
      // dependent is at the top. For example, this ensures that fields are
      // always after field storages. This is because field storages need to be
      // created before a field.
      $this->sorted_graph = $this->getGraph();
      uasort($this->sorted_graph, array($this, 'sortGraph'));
    }
    return array_replace(array_intersect_key($this->sorted_graph, $dependencies), $dependencies);
  }

  /**
   * {@inheritdoc}
   */
  public function setData(array $data) {
    parent::setData($data);
    $this->sorted_graph = NULL;
    return $this;
  }

}
