<?php
/**
 * @file
 * Contains \Drupal\message\Tests\MessageTemplateCreateTrait.
 */

namespace Drupal\message\Tests;

use Drupal\Core\Language\Language;
use Drupal\message\Entity\MessageTemplate;
use Drupal\message\MessageTemplateInterface;

/**
 * Trait to assist message template creation for tests.
 */
trait MessageTemplateCreateTrait {

  /**
   * Helper function to create and save a message template entity.
   *
   * @param string $template
   *   The message template.
   * @param string $label
   *   The message template label.
   * @param string $description
   *   The message template description.
   * @param array $text
   *   The text array for the message template.
   * @param array $settings
   *   Data overrides.
   * @param string $langcode
   *   The language to use.
   *
   * @return \Drupal\message\MessageTemplateInterface
   *   A saved message template entity.
   */
  protected function createMessageTemplate($template, $label, $description, array $text, array $settings = [], $langcode = Language::LANGCODE_NOT_SPECIFIED) {
    $settings += [
      'token options' => [
        'clear' => FALSE,
      ],
    ];
    $message_template = MessageTemplate::Create([
      'template' => $template,
      'label' => $label,
      'description' => $description,
      'text' => $text,
      'settings' => $settings,
      'langcode' => $langcode,
    ]);
    $message_template->save();

    return $message_template;
  }

}
