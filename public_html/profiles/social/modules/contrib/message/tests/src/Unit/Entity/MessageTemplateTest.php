<?php
/**
 * @file
 * Contains \Drupal\Tests\message\Unit\Entity\MessageTemplateTest.
 */

namespace Drupal\Tests\message\Unit\Entity;

use Drupal\message\Entity\MessageTemplate;
use Drupal\message\MessageTemplateInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for the message template entity.
 *
 * @coversDefaultClass \Drupal\message\Entity\MessageTemplate
 *
 * @group Message
 */
class MessageTemplateTest extends UnitTestCase {

  /**
   * A message template entity.
   *
   * @var \Drupal\message\MessageTemplateInterface
   */
  protected $messageTemplate;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->messageTemplate = new \Drupal\message\Entity\MessageTemplate([], 'message_template');
  }

  /**
   * Tests getting and setting the Settings array.
   *
   * @covers ::setSettings
   * @covers ::getSettings
   * @covers ::getSetting
   */
  public function testSetSettings() {
    $settings = [
      'one' => 'foo',
      'two' => 'bar',
    ];

    $this->messageTemplate->setSettings($settings);
    $this->assertArrayEquals($settings, $this->messageTemplate->getSettings());
    $this->assertEquals($this->messageTemplate->getSetting('one'), $this->messageTemplate->getSetting('one'));
    $this->assertEquals('bar', $this->messageTemplate->getSetting('two'));
  }

  /**
   * Tests getting and setting description.
   *
   * @covers ::setDescription
   * @covers ::getDescription
   */
  public function testSetDescription() {
    $description = 'A description';

    $this->messageTemplate->setDescription($description);
    $this->assertEquals($description, $this->messageTemplate->getDescription());
  }

  /**
   * Tests getting and setting label.
   *
   * @covers ::setLabel
   * @covers ::getLabel
   */
  public function testSetLabel() {
    $label = 'A label';
    $this->messageTemplate->setLabel($label);
    $this->assertEquals($label, $this->messageTemplate->getLabel());
  }

  /**
   * Tests getting and setting template.
   *
   * @covers ::setTemplate
   * @covers ::getTemplate
   */
  public function testSetTemplate() {
    $template = 'a_template';
    $this->messageTemplate->setTemplate($template);
    $this->assertEquals($template, $this->messageTemplate->getTemplate());
  }

  /**
   * Tests getting and setting uuid.
   *
   * @covers ::setUuid
   * @covers ::getUuid
   */
  public function testSetUuid() {
    $uuid = 'a-uuid-123';
    $this->messageTemplate->setUuid($uuid);
    $this->assertEquals($uuid, $this->messageTemplate->getUuid());
  }

  /**
   * Tests if the template is locked.
   *
   * @covers ::isLocked
   */
  public function testIsLocked() {
    $this->assertTrue($this->messageTemplate->isLocked());
    $this->messageTemplate->enforceIsNew(TRUE);
    $this->assertFalse($this->messageTemplate->isLocked());
  }

}
