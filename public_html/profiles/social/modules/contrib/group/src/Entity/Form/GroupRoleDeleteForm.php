<?php

namespace Drupal\group\Entity\Form;

use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for group role deletion.
 */
class GroupRoleDeleteForm extends EntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if ($this->entity->isInternal()) {
      return [
        '#title' => t('Error'),
        'description' => [
          '#prefix' => '<p>',
          '#suffix' => '</p>',
          '#markup' => t('Cannot edit an internal group role directly.'),
        ],
      ];
    }

    return parent::buildForm($form, $form_state);
  }

}
