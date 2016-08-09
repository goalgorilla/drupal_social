<?php
/**
 * @file
 *
 * Contains \Drupal\message\Form\ConfigTranslationDeleteForm.
 */

namespace Drupal\message\Form;

use Drupal\config_translation\Form\ConfigTranslationDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\message\Entity\MessageTemplate;
/**
 * Builds a form to delete configuration translation.
 */
class MessageTemplateConfigTranslationDeleteForm extends ConfigTranslationDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'message_template_config_translation_delete_form';
  }
}
