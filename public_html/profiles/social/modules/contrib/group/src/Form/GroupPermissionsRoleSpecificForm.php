<?php

namespace Drupal\group\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\group\Entity\GroupRoleInterface;

/**
 * Provides the user permissions administration form for a specific group role.
 */
class GroupPermissionsRoleSpecificForm extends GroupPermissionsForm {

  /**
   * The specific group role for this form.
   *
   * @var \Drupal\group\Entity\GroupRoleInterface
   */
  protected $groupRole;

  /**
   * {@inheritdoc}
   */
  protected function getGroupType() {
    return $this->groupRole->getGroupType();
  }

  /**
   * {@inheritdoc}
   */
  protected function getGroupRoles() {
    return [$this->groupRole->id() => $this->groupRole];
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\group\Entity\GroupRoleInterface $group_role
   *   The group role used for this form.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state, GroupRoleInterface $group_role = NULL) {
    if ($group_role->isInternal()) {
      return [
        '#title' => t('Error'),
        'description' => [
          '#prefix' => '<p>',
          '#suffix' => '</p>',
          '#markup' => t('Cannot edit an internal group role directly.'),
        ],
      ];
    }

    $this->groupRole = $group_role;
    return parent::buildForm($form, $form_state);
  }

}
