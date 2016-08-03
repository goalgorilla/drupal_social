<?php

namespace Drupal\group\Entity\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the group content edit forms.
 *
 * @ingroup group
 */
class GroupContentForm extends ContentEntityForm {

  /**
   * Returns the plugin responsible for this piece of group content.
   *
   * @return \Drupal\group\Plugin\GroupContentEnablerInterface
   *   The responsible group content enabler plugin.
   */
  protected function getContentPlugin() {
    /** @var \Drupal\group\Entity\GroupContent $group_content */
    $group_content = $this->getEntity();
    return $group_content->getContentPlugin();
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $config = $this->getContentPlugin()->getConfiguration();
    if (!empty($config['data']['info_text']['value'])) {
      $form['info_text'] = [
        '#markup' => $config['data']['info_text']['value'],
        '#weight' => -99,
      ];
    }

    // Do not allow to edit the group content subject through the UI.
    // @todo Perhaps make this configurable per plugin.
    if ($this->operation !== 'add') {
      $form['entity_id']['#access'] = FALSE;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $return = parent::save($form, $form_state);

    /** @var \Drupal\group\Entity\GroupContent $group_content */
    $group_content = $this->getEntity();

    // The below redirect ensures the user will be redirected to the entity this
    // form was for. But only if there was no destination set in the URL.
    $route_params = ['group' => $group_content->getGroup()->id(), 'group_content' => $group_content->id()];
    $form_state->setRedirect('entity.group_content.canonical', $route_params);

    return $return;
  }

}
