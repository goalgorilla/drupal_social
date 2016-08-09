<?php

/**
 * @file
 * Contains \Drupal\message\Form\MessageTemplateConfigTranslationAddForm.
 */

namespace Drupal\message\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\message\Entity\MessageTemplate;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a form for adding configuration translations.
 */
class MessageTemplateConfigTranslationAddForm extends MessageTemplateConfigTranslationBaseForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'message_template_config_translation_add_form';
  }
}
