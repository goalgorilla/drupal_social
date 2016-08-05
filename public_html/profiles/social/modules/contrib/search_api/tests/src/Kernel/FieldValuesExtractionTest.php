<?php

namespace Drupal\Tests\search_api\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Utility;

/**
 * Tests extraction of field values, as used during indexing.
 *
 * @group search_api
 */
class FieldValuesExtractionTest extends KernelTestBase {

  /**
   * The search index used for testing.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * The test entities used in this test.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
   */
  protected $entities = array();

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  public static $modules = array(
    'entity_test',
    'field',
    'search_api',
    'search_api_test_extraction',
    'user',
  );

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->installEntitySchema('entity_test_mulrev_changed');
    $this->installConfig(array('search_api_test_extraction'));
    $entity_storage = \Drupal::entityTypeManager()->getStorage('entity_test_mulrev_changed');

    $this->entities[0] = $entity_storage->create(array(
      'type' => 'article',
      'name' => 'Article 1',
      'links' => array(),
    ));
    $this->entities[0]->save();
    $this->entities[1] = $entity_storage->create(array(
      'type' => 'article',
      'name' => 'Article 2',
      'links' => array(),
    ));
    $this->entities[1]->save();
    $this->entities[2] = $entity_storage->create(array(
      'type' => 'article',
      'name' => 'Article 3',
      'links' => array(
        array('target_id' => $this->entities[0]->id()),
        array('target_id' => $this->entities[1]->id()),
      ),
    ));
    $this->entities[2]->save();
    $this->entities[3] = $entity_storage->create(array(
      'type' => 'article',
      'name' => 'Article 4',
      'links' => array(
        array('target_id' => $this->entities[0]->id()),
        array('target_id' => $this->entities[2]->id()),
      ),
    ));
    $this->entities[2]->save();

    $this->index = $this->getMock('Drupal\search_api\IndexInterface');
  }

  /**
   * Tests extraction of field values, as used during indexing.
   */
  public function testFieldValuesExtraction() {
    $object = $this->entities[3]->getTypedData();
    /** @var \Drupal\search_api\Item\FieldInterface[][] $fields */
    $fields = array(
      'type' => array(Utility::createField($this->index, 'type')),
      'name' => array(Utility::createField($this->index, 'name')),
      'links:entity:name' => array(
        Utility::createField($this->index, 'links'),
        Utility::createField($this->index, 'links_1'),
      ),
      'links:entity:links:entity:name' => array(
        Utility::createField($this->index, 'links_links'),
      ),
    );
    Utility::extractFields($object, $fields);

    $values = array();
    foreach ($fields as $property_path => $property_fields) {
      foreach ($property_fields as $field) {
        $field_values = $field->getValues();
        sort($field_values);
        if (!isset($values[$property_path])) {
          $values[$property_path] = $field_values;
        }
        else {
          $this->assertEquals($field_values, $values[$property_path], 'Second extraction provided the same results as the first.');
        }
      }
    }

    $expected = array(
      'type' => array('article'),
      'name' => array('Article 4'),
      'links:entity:name' => array(
        'Article 1',
        'Article 3',
      ),
      'links:entity:links:entity:name' => array(
        'Article 1',
        'Article 2',
      ),
    );
    $this->assertEquals($expected, $values, 'Field values were correctly extracted');
  }

}
