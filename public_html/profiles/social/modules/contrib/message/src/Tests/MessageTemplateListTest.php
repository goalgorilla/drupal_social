<?php

/**
 * @file
 * Definition of Drupal\message\Tests\MessageTemplateCrudTest.
 */

namespace Drupal\message\Tests;

use Drupal\user\Entity\User;

/**
 * Testing the listing functionality for the Message template entity.
 *
 * @group Message
 */
class MessageTemplateListTest extends MessageTestBase {

  /**
   * The user object.
   *
   * @var User
   */
  protected $user;

  /**
   * Listing of messages.
   */
  public function testEntityTypeList() {
    $this->user = $this->drupalCreateUser(['administer message templates']);
    $this->drupalLogin($this->user);

    $this->drupalGet('admin/structure/message');
    $this->assertResponse(200);
  }

}
