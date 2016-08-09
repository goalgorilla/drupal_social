<?php

/**
 * @file
 * Definition of Drupal\message\Tests\MessageTemplateCrudTest.
 */

namespace Drupal\message\Tests;

/**
 * Testing the CRUD functionallity for the Message template entity.
 *
 * @group Message
 */
class MessageTemplateCrudTest extends MessageTestBase {

  /**
   * Creating/reading/updating/deleting the message template entity and test it.
   */
  public function testCrudEntityType() {
    // Create the message template.
    $created_message_template = $this->createMessageTemplate('dummy_message', 'Dummy test', 'This is a dummy message with a dummy message', ['Dummy message']);

    // Reset any static cache.
    drupal_static_reset();

    // Load the message and verify the message template structure.
    $template = $this->loadMessageTemplate('dummy_message');

    foreach (['template' => 'Template', 'label' => 'Label', 'description' => 'Description', 'text' => 'Text'] as $key => $label) {
      $param = [
        '@label' => $label,
      ];

      $this->assertEqual(call_user_func([$template, 'get' . $key]), call_user_func([$created_message_template, 'get' . $key]), format_string('The @label between the message we created an loaded are equal', $param));
    }

    // Verifying updating action.
    $template->setLabel('New label');
    $template->save();

    // Reset any static cache.
    drupal_static_reset();

    $template = $this->loadMessageTemplate('dummy_message');
    $this->assertEqual($template->getLabel(), 'New label', 'The message was updated successfully');

    // Delete the message any try to load it from the DB.
    $template->delete();

    // Reset any static cache.
    drupal_static_reset();

    $this->assertFalse($this->loadMessageTemplate('dummy_message'), 'The message was not found in the DB');
  }

}
