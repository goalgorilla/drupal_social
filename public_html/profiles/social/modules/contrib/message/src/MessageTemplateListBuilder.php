<?php

/**
 * @file
 * Contains \Drupal\message\MessageTemplateListBuilder.
 */

namespace Drupal\message;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Component\Utility\Xss;

/**
 * Defines a class to build a listing of message template entities.
 *
 * @see \Drupal\message\Entity\MessageTemplate
 */
class MessageTemplateListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['title'] = t('Name');
    $header['description'] = [
      'data' => t('Description'),
      'class' => [RESPONSIVE_PRIORITY_MEDIUM],
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['title'] = [
      'data' => $this->getLabel($entity),
      'class' => ['menu-label'],
    ];
    $row['description'] = Xss::filterAdmin($entity->getDescription());
    return $row + parent::buildRow($entity);
  }

}
