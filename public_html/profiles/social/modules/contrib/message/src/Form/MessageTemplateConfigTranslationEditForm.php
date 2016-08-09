<?php
/**
 * @file
 *
 * Contains \Drupal\message\Form\MessageTemplateConfigTranslationEditForm.
 */

namespace Drupal\message\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Defines a form for editing message template configuration translations.
 */
class MessageTemplateConfigTranslationEditForm extends MessageTemplateConfigTranslationBaseForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'message_template_config_translation_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, RouteMatchInterface $route_match = NULL, $plugin_id = NULL, $langcode = NULL) {
    $form = parent::buildForm($form, $form_state, $route_match, $plugin_id, $langcode);
    $form['#title'] = $this->t('Edit @language translation for %label', [
      '%label' => $this->mapper->getTitle(),
      '@language' => $this->language->getName(),
    ]);
    return $form;
  }

}
