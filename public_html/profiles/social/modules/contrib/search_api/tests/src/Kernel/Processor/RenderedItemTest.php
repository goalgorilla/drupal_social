<?php

namespace Drupal\Tests\search_api\Kernel\Processor;

use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Utility;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\search_api\Plugin\search_api\data_type\value\TextValueInterface;

/**
 * Tests the "Rendered item" processor.
 *
 * @group search_api
 *
 * @see \Drupal\search_api\Plugin\search_api\processor\RenderedItem
 */
class RenderedItemTest extends ProcessorTestBase {

  /**
   * List of nodes which are published.
   *
   * @var \Drupal\node\Entity\Node[]
   */
  protected $nodes;

  /**
   * {@inheritdoc}
   */
  public static $modules = array(
    'user',
    'node',
    'search_api',
    'search_api_db',
    'search_api_test',
    'comment',
    'system',
    'filter',
  );

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL) {
    parent::setUp('rendered_item');

    // Load additional configuration and needed schemas. (The necessary schemas
    // for using nodes are already installed by the parent method.)
    $this->installConfig(array('system', 'filter', 'node', 'comment'));
    $this->installSchema('system', array('router'));
    \Drupal::service('router.builder')->rebuild();

    // Create a node type for testing.
    $type = NodeType::create(array(
      'type' => 'page',
      'name' => 'page',
    ));
    $type->save();
    node_add_body_field($type);

    // Create anonymous user role.
    $role = Role::create(array(
      'id' => 'anonymous',
      'label' => 'anonymous',
    ));
    $role->save();

    // Insert the anonymous user into the database.
    $anonymous_user = User::create(array(
      'uid' => 0,
      'name' => '',
    ));
    $anonymous_user->save();

    // Default node values for all nodes we create below.
    $node_data = array(
      'status' => NODE_PUBLISHED,
      'type' => 'page',
      'title' => '',
      'body' => array('value' => '', 'summary' => '', 'format' => 'plain_text'),
      'uid' => $anonymous_user->id(),
    );

    // Create some test nodes with valid user on it for rendering a picture.
    $node_data['title'] = 'Title for node 1';
    $node_data['body']['value'] = 'value for node 1';
    $node_data['body']['summary'] = 'summary for node 1';
    $this->nodes[1] = Node::create($node_data);
    $this->nodes[1]->save();
    $node_data['title'] = 'Title for node 2';
    $node_data['body']['value'] = 'value for node 2';
    $node_data['body']['summary'] = 'summary for node 2';
    $this->nodes[2] = Node::create($node_data);
    $this->nodes[2]->save();

    // Add a field based on the "rendered_item" property.
    $field_info = array(
      'type' => 'text',
      'property_path' => 'rendered_item',
      'configuration' => array(
        'roles' => array($role->id()),
        'view_mode' => array(
          'entity:node' => array(
            'page' => 'full',
            'article' => 'teaser',
          ),
          'entity:user' => array(
            'user' => 'default',
          ),
          'entity:comment' => array(
            'comment' => 'full',
          ),
        ),
      ),
    );
    $field = Utility::createField($this->index, 'rendered_item', $field_info);
    $this->index->addField($field);

    $this->index->save();

    $this->index->getDatasources();

    // Enable the classy theme as the tests rely on markup from that.
    \Drupal::service('theme_handler')->install(array('classy'));
    \Drupal::theme()->setActiveTheme(\Drupal::service('theme.initialization')->initTheme('classy'));
  }

  /**
   * Tests whether the rendered_item field is correctly filled by the processor.
   */
  public function testAddFieldValues() {
    $items = array();
    foreach ($this->nodes as $node) {
      $items[] = array(
        'datasource' => 'entity:node',
        'item' => $node->getTypedData(),
        'item_id' => $node->id(),
        'text' => 'node text' . $node->id(),
      );
    }
    $items = $this->generateItems($items);

    // Add the processor's field values to the items.
    foreach ($items as $item) {
      $this->processor->addFieldValues($item);
    }

    foreach ($items as $key => $item) {
      list(, $nid) = Utility::splitCombinedId($key);
      $field = $item->getField('rendered_item');
      $this->assertEquals('text', $field->getType(), 'Node item ' . $nid . ' rendered value is identified as text.');
      /** @var \Drupal\search_api\Plugin\search_api\data_type\value\TextValueInterface[] $values */
      $values = $field->getValues();
      // Test that the value is properly wrapped in a
      // \Drupal\search_api\Plugin\search_api\data_type\value\TextValueInterface
      // object, which contains a string (not, e.g., some markup object).
      $this->assertInstanceOf('Drupal\search_api\Plugin\search_api\data_type\value\TextValueInterface', $values[0], "Node item $nid rendered value is properly wrapped in a text value object.");
      $this->assertInternalType('string', $values[0]->getText(), "Node item $nid rendered value is a string.");
      $this->assertEquals(1, count($values), 'Node item ' . $nid . ' rendered value is a single value.');
      // These tests rely on the template not changing. However, if we'd only
      // check whether the field values themselves are included, there could
      // easier be false positives. For example the title text was present even
      // when the processor was broken, because the schema metadata was also
      // adding it to the output.
      $this->assertTrue(substr_count($values[0], 'view-mode-full') > 0, 'Node item ' . $nid . ' rendered in view-mode "full".');
      $this->assertTrue(substr_count($values[0], 'field--name-title') > 0, 'Node item ' . $nid . ' has a rendered title field.');
      $this->assertTrue(substr_count($values[0], '>' . $this->nodes[$nid]->label() . '<') > 0, 'Node item ' . $nid . ' has a rendered title inside HTML-Tags.');
      $this->assertTrue(substr_count($values[0], '>Member for<') > 0, 'Node item ' . $nid . ' has rendered member information HTML-Tags.');
      $this->assertTrue(substr_count($values[0], '>' . $this->nodes[$nid]->get('body')->getValue()[0]['value'] . '<') > 0, 'Node item ' . $nid . ' has rendered content inside HTML-Tags.');
    }
  }

  /**
   * Tests that hiding a rendered item works.
   */
  public function testHideRenderedItem() {
    // Change the processor configuration to make sure that that the rendered
    // item content will be empty.
    $field = $this->index->getField('rendered_item');
    $config = $field->getConfiguration();
    $config['view_mode'] = array(
      'entity:node' => array(
        'page' => '',
        'article' => '',
      ),
    );
    $field->setConfiguration($config);

    // Create items that we can index.
    $items = array();
    foreach ($this->nodes as $node) {
      $items[] = array(
        'datasource' => 'entity:node',
        'item' => $node->getTypedData(),
        'item_id' => $node->id(),
        'text' => 'text for ' . $node->id(),
      );
    }
    $items = $this->generateItems($items);

    // Add the processor's field values to the items.
    foreach ($items as $item) {
      $this->processor->addFieldValues($item);
    }

    // Verify that no field values were added.
    foreach ($items as $key => $item) {
      $rendered_item = $item->getField('rendered_item');
      $this->assertFalse($rendered_item->getValues(), 'No rendered_item field value added when disabled for content type.');
    }
  }

  /**
   * Tests whether the property is correctly added by the processor.
   */
  public function testAlterPropertyDefinitions() {
    // Check for added properties when no datasource is given.
    $properties = $this->processor->getPropertyDefinitions(NULL);
    $this->assertTrue(array_key_exists('rendered_item', $properties), 'The Properties where modified with the "rendered_item".');
    $this->assertInstanceOf('Drupal\search_api\Plugin\search_api\processor\Property\RenderedItemProperty', $properties['rendered_item'], 'Added property has the correct class.');
    $this->assertTrue(($properties['rendered_item'] instanceof DataDefinitionInterface), 'The "rendered_item" contains a valid DataDefinition instance.');
    $this->assertEquals('text', $properties['rendered_item']->getDataType(), 'Correct DataType set in the DataDefinition.');

    // Verify that there are no properties if a datasource is given.
    $properties = $this->processor->getPropertyDefinitions($this->index->getDatasource('entity:node'));
    $this->assertEquals(array(), $properties, '"render_item" property not added when data source is given.');
  }

  /**
   * Tests whether the processor reacts correctly to removed dependencies.
   */
  public function testDependencyRemoval() {
    $expected = array(
      'config' => array(
        'core.entity_view_mode.comment.full',
        'core.entity_view_mode.node.full',
        'core.entity_view_mode.node.teaser',
      ),
    );
    $this->assertEquals($expected, $this->processor->calculateDependencies());

    EntityViewMode::load('node.teaser')->delete();
    $expected = array(
      'entity:node' => array(
        'page' => 'full',
      ),
      'entity:user' => array(
        'user' => 'default',
      ),
      'entity:comment' => array(
        'comment' => 'full',
      ),
    );
    // We need to reload the index.
    $index = Index::load($this->index->id());
    $field_config = $index->getField('rendered_item')->getConfiguration();
    $this->assertEquals($expected, $field_config['view_mode']);
  }

}
