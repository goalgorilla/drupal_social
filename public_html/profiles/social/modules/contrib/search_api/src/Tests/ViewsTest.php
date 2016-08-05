<?php

namespace Drupal\search_api\Tests;

use Drupal\Component\Utility\Html;
use Drupal\Core\Url;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Utility;

/**
 * Tests the Views integration of the Search API.
 *
 * @group search_api
 */
class ViewsTest extends WebTestBase {

  use ExampleContentTrait;

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  public static $modules = array('search_api_test_views', 'views_ui');

  /**
   * A search index ID.
   *
   * @var string
   */
  protected $indexId = 'database_search_index';

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->setUpExampleStructure();
    \Drupal::getContainer()
      ->get('search_api.index_task_manager')
      ->addItemsAll(Index::load($this->indexId));
    $this->insertExampleContent();
    $this->indexItems($this->indexId);
  }

  /**
   * Tests a view with exposed filters.
   */
  public function testView() {
    $this->checkResults(array(), array_keys($this->entities), 'Unfiltered search');

    $this->checkResults(
      array('search_api_fulltext' => 'foobar'),
      array(3),
      'Search for a single word'
    );
    $this->checkResults(
      array('search_api_fulltext' => 'foo test'),
      array(1, 2, 4),
      'Search for multiple words'
    );
    $query = array(
      'search_api_fulltext' => 'foo test',
      'search_api_fulltext_op' => 'or',
    );
    $this->checkResults($query, array(1, 2, 3, 4, 5), 'OR search for multiple words');
    $query = array(
      'search_api_fulltext' => 'foobar',
      'search_api_fulltext_op' => 'not',
    );
    $this->checkResults($query, array(1, 2, 4, 5), 'Negated search');
    $query = array(
      'search_api_fulltext' => 'foo test',
      'search_api_fulltext_op' => 'not',
    );
    $this->checkResults($query, array(), 'Negated search for multiple words');
    $query = array(
      'search_api_fulltext' => 'fo',
    );
    $label = 'Search for short word';
    $this->checkResults($query, array(), $label);
    $this->assertText('You must include at least one positive keyword with 3 characters or more', "$label displayed the correct warning.");
    $query = array(
      'search_api_fulltext' => 'foo to test',
    );
    $label = 'Fulltext search including short word';
    $this->checkResults($query, array(1, 2, 4), $label);
    $this->assertNoText('You must include at least one positive keyword with 3 characters or more', "$label didn't display a warning.");

    $this->checkResults(array('id[value]' => 2), array(2), 'Search with ID filter');
    // @todo Enable "between" again. See #2624870.
//    $query = array(
//      'id[min]' => 2,
//      'id[max]' => 4,
//      'id_op' => 'between',
//    );
//    $this->checkResults($query, array(2, 3, 4), 'Search with ID "in between" filter');
    $query = array(
      'id[value]' => 2,
      'id_op' => '>',
    );
    $this->checkResults($query, array(3, 4, 5), 'Search with ID "greater than" filter');
    $query = array(
      'id[value]' => 2,
      'id_op' => '!=',
    );
    $this->checkResults($query, array(1, 3, 4, 5), 'Search with ID "not equal" filter');
    $query = array(
      'id_op' => 'empty',
    );
    $this->checkResults($query, array(), 'Search with ID "empty" filter');
    $query = array(
      'id_op' => 'not empty',
    );
    $this->checkResults($query, array(1, 2, 3, 4, 5), 'Search with ID "not empty" filter');

    $yesterday = strtotime('-1DAY');
    $query = array(
      'created[value]' => date('Y-m-d', $yesterday),
      'created_op' => '>',
    );
    $this->checkResults($query, array(1, 2, 3, 4, 5), 'Search with "Created after" filter');
    $query = array(
      'created[value]' => date('Y-m-d', $yesterday),
      'created_op' => '<',
    );
    $this->checkResults($query, array(), 'Search with "Created before" filter');
    $query = array(
      'created_op' => 'empty',
    );
    $this->checkResults($query, array(), 'Search with "empty creation date" filter');
    $query = array(
      'created_op' => 'not empty',
    );
    $this->checkResults($query, array(1, 2, 3, 4, 5), 'Search with "not empty creation date" filter');

    $this->checkResults(array('keywords[value]' => 'apple'), array(2, 4), 'Search with Keywords filter');
    // @todo Enable "between" again. See #2695627.
//    $query = array(
//      'keywords[min]' => 'aardvark',
//      'keywords[max]' => 'calypso',
//      'keywords_op' => 'between',
//    );
//    $this->checkResults($query, array(2, 4, 5), 'Search with Keywords "in between" filter');
    // For the keywords filters with comparison operators, exclude entity 1
    // since that contains all the uppercase and special characters weirdness.
    $query = array(
      'id[value]' => 1,
      'id_op' => '!=',
      'keywords[value]' => 'melon',
      'keywords_op' => '>=',
    );
    $this->checkResults($query, array(2, 4, 5), 'Search with Keywords "greater than or equal" filter');
    $query = array(
      'id[value]' => 1,
      'id_op' => '!=',
      'keywords[value]' => 'banana',
      'keywords_op' => '<',
    );
    $this->checkResults($query, array(2, 4), 'Search with Keywords "less than" filter');
    $query = array(
      'keywords[value]' => 'orange',
      'keywords_op' => '!=',
    );
    $this->checkResults($query, array(3, 4), 'Search with Keywords "not equal" filter');
    $query = array(
      'keywords_op' => 'empty',
    );
    $label = 'Search with Keywords "empty" filter';
    $this->checkResults($query, array(3), $label, 'all/all/all');
    $query = array(
      'keywords_op' => 'not empty',
    );
    $this->checkResults($query, array(1, 2, 4, 5), 'Search with Keywords "not empty" filter');

    $query = array(
      'language' => array('***LANGUAGE_language_content***'),
    );
    $this->checkResults($query, array(1, 2, 3, 4, 5), 'Search with "Page content language" filter');
    $query = array(
      'language' => array('en'),
    );
    $this->checkResults($query, array(1, 2, 3, 4, 5), 'Search with "English" language filter');
    $query = array(
      'language' => array('und'),
    );
    $this->checkResults($query, array(), 'Search with "Not specified" language filter');
    $query = array(
      'language' => array(
        '***LANGUAGE_language_interface***',
        'zxx',
      ),
    );
    $this->checkResults($query, array(1, 2, 3, 4, 5), 'Search with multiple languages filter');

    $query = array(
      'search_api_fulltext' => 'foo to test',
      'id[value]' => 2,
      'id_op' => '>',
      'keywords_op' => 'not empty',
    );
    $this->checkResults($query, array(4), 'Search with multiple filters');

    // Test contextual filters. Configured contextual filters are:
    // 1: datasource
    // 2: type (not = true)
    // 3: keywords (break_phrase = true)
    $this->checkResults(array(), array(4, 5), 'Search with arguments', 'entity:entity_test_mulrev_changed/item/grape');

    // "Type" doesn't have "break_phrase" enabled, so the second argument won't
    // have any effect.
    $this->checkResults(array(), array(2, 4, 5), 'Search with arguments', 'all/item+article/strawberry+apple');

    $this->checkResults(array(), array(), 'Search with unknown datasource argument', 'entity:foobar/all/all');

    $query = array(
      'id[value]' => 1,
      'id_op' => '!=',
      'keywords[value]' => 'melon',
      'keywords_op' => '>=',
    );
    $this->checkResults($query, array(2, 5), 'Search with arguments and filters', 'entity:entity_test_mulrev_changed/all/orange');

    // Make sure there was a display plugin created for this view.
    $displays = \Drupal::getContainer()->get('plugin.manager.search_api.display')->getInstances();
    $display_id = 'views_page:search_api_test_view__page_1';
    $this->assertEqual(array($display_id), array_keys($displays), 'A display plugin was created for the test view.');
    $view_url = Url::fromUserInput('/search-api-test')->toString();
    $this->assertEqual($view_url, $displays[$display_id]->getPath()->toString(), 'Display returns the correct path.');
    $this->assertEqual('database_search_index', $displays[$display_id]->getIndex()->id(), 'Display returns the correct search index.');
  }

  /**
   * Checks the Views results for a certain set of parameters.
   *
   * @param array $query
   *   The GET parameters to set for the view.
   * @param int[]|null $expected_results
   *   (optional) The IDs of the expected results; or NULL to skip checking the
   *   results.
   * @param string $label
   *   (optional) A label for this search, to include in assert messages.
   * @param string $arguments
   *   (optional) A string to append to the search path.
   */
  protected function checkResults(array $query, array $expected_results = NULL, $label = 'Search', $arguments = '') {
    $this->drupalGet('search-api-test/' . $arguments, array('query' => $query));

    if (isset($expected_results)) {
      $count = count($expected_results);
      $count_assert_message = "$label returned correct number of results.";
      if ($count) {
        $this->assertText("Displaying $count search results", $count_assert_message);
      }
      else {
        $this->assertNoText('search results', $count_assert_message);
      }

      $expected_results = array_combine($expected_results, $expected_results);
      $actual_results = array();
      foreach ($this->entities as $id => $entity) {
        $entity_label = Html::escape($entity->label());
        if (strpos($this->getRawContent(), ">$entity_label<") !== FALSE) {
          $actual_results[$id] = $id;
        }
      }
      $this->assertEqual($expected_results, $actual_results, "$label returned correct results.");
    }
  }

  /**
   * Test Views admin UI and field handlers.
   */
  public function testViewsAdmin() {
    // For viewing the user name and roles of the user associated with test
    // entities, the logged-in user needs to have the permission to administer
    // both users and permissions.
    $permissions = array(
      'administer search_api',
      'access administration pages',
      'administer views',
      'administer users',
      'administer permissions',
    );
    $this->drupalLogin($this->drupalCreateUser($permissions));

    $this->drupalGet('admin/structure/views/view/search_api_test_view');
    $this->assertResponse(200);

    // Set the user IDs associated with our test entities.
    $users[$this->adminUser->id()] = $this->adminUser;
    $users[$this->unauthorizedUser->id()] = $this->unauthorizedUser;
    $users[$this->anonymousUser->id()] = $this->anonymousUser;
    $this->entities[1]->setOwnerId($this->adminUser->id())->save();
    $this->entities[2]->setOwnerId($this->adminUser->id())->save();
    $this->entities[3]->setOwnerId($this->unauthorizedUser->id())->save();
    $this->entities[4]->setOwnerId($this->unauthorizedUser->id())->save();
    $this->entities[5]->setOwnerId($this->anonymousUser->id())->save();

    // Switch to "Fields" row style.
    $this->clickLink($this->t('Rendered entity'));
    $this->assertResponse(200);
    $edit = array(
      'row[type]' => 'fields',
    );
    $this->drupalPostForm(NULL, $edit, $this->t('Apply'));
    $this->assertResponse(200);
    $this->drupalPostForm(NULL, array(), $this->t('Apply'));
    $this->assertResponse(200);

    // Add the "User ID" relationship.
    $this->clickLink($this->t('Add relationships'));
    $edit = array(
      'name[search_api_datasource_database_search_index_entity_entity_test_mulrev_changed.user_id]' => 'search_api_datasource_database_search_index_entity_entity_test_mulrev_changed.user_id',
    );
    $this->drupalPostForm(NULL, $edit, $this->t('Add and configure relationships'));
    $this->drupalPostForm(NULL, array(), $this->t('Apply'));

    // Add new fields. First check that the listing seems correct.
    $this->clickLink($this->t('Add fields'));
    $this->assertResponse(200);
    $this->assertText($this->t('Test entity - revisions and data table datasource'));
    $this->assertText($this->t('Authored on'));
    $this->assertText($this->t('Body (indexed field)'));
    $this->assertText($this->t('Index Test index'));
    $this->assertText($this->t('Item ID'));
    $this->assertText($this->t('Excerpt'));
    $this->assertText($this->t('The search result excerpted to show found search terms'));
    $this->assertText($this->t('Relevance'));
    $this->assertText($this->t('The relevance of this search result with respect to the query'));
    $this->assertText($this->t('Language code'));
    $this->assertText($this->t('The user language code.'));
    $this->assertText($this->t('(No description available)'));
    $this->assertNoText($this->t('Error: missing help'));

    // Then add some fields.
    $fields = array(
      'views.counter',
      'search_api_datasource_database_search_index_entity_entity_test_mulrev_changed.id',
      'search_api_index_database_search_index.search_api_datasource',
      'search_api_datasource_database_search_index_entity_entity_test_mulrev_changed.body',
      'search_api_index_database_search_index.category',
      'search_api_index_database_search_index.keywords',
      'search_api_datasource_database_search_index_entity_entity_test_mulrev_changed.user_id',
      'search_api_entity_user.name',
      'search_api_entity_user.roles',
    );
    $edit = array();
    foreach ($fields as $field) {
      $edit["name[$field]"] = $field;
    }
    $this->drupalPostForm(NULL, $edit, $this->t('Add and configure fields'));
    $this->assertResponse(200);

    // @todo For some strange reason, the "roles" field form is not included
    //   automatically in the series of field forms shown to us by Views. Deal
    //   with this graciously (since it's not really our fault, I hope), but it
    //   would be great to have this working normally.
    $get_field_id = function ($key) {
      return Utility::splitPropertyPath($key, TRUE, '.')[1];
    };
    $fields = array_map($get_field_id, $fields);
    $fields = array_combine($fields, $fields);
    for ($i = 0; $i < count($fields); ++$i) {
      $field = $this->submitFieldsForm();
      if (!$field) {
        break;
      }
      unset($fields[$field]);
    }
    foreach ($fields as $field) {
      $this->drupalGet('admin/structure/views/nojs/handler/search_api_test_view/page_1/field/' . $field);
      $this->submitFieldsForm();
    }

    $this->clickLink($this->t('Add filter criteria'));
    $edit = array(
      'name[search_api_index_database_search_index.name]' => 'search_api_index_database_search_index.name',
    );
    $this->drupalPostForm(NULL, $edit, $this->t('Add and configure filter criteria'));
    $this->submitPluginForm(array());

    // Save the view.
    $this->drupalPostForm(NULL, array(), $this->t('Save'));
    $this->assertResponse(200);

    // Check the results.
    $this->drupalGet('search-api-test');
    $this->assertResponse(200);

    foreach ($this->entities as $id => $entity) {
      $fields = array(
        'search_api_datasource',
        'id',
        'body',
        'category',
        'keywords',
        'user_id',
        'user_id:name',
        'user_id:roles',
      );
      foreach ($fields as $field) {
        $field_entity = $entity;
        while (strpos($field, ':')) {
          list($direct_property, $field) = Utility::splitPropertyPath($field, FALSE);
          if (empty($field_entity->{$direct_property}[0]->entity)) {
            continue 2;
          }
          $field_entity = $field_entity->{$direct_property}[0]->entity;
        }
        if ($field != 'search_api_datasource') {
          $data = Utility::extractFieldValues($field_entity->get($field));
          if (!$data) {
            $data = array('[EMPTY]');
          }
        }
        else {
          $data = array('entity:entity_test_mulrev_changed');
        }
        $prefix = "#$id [$field] ";
        $text = $prefix . implode("|$prefix", $data);
        $this->assertText($text, "Correct value displayed for field $field on entity #$id (\"$text\")");
      }
    }
  }

  /**
   * Submits the field handler config form currently displayed.
   *
   * @return string|null
   *   The field ID of the field whose form was submitted. Or NULL if the
   *   current page is no field form.
   */
  protected function submitFieldsForm() {
    $url_parts = explode('/', $this->getUrl());
    $field = array_pop($url_parts);
    if (array_pop($url_parts) != 'field') {
      return NULL;
    }

    $edit['options[fallback_options][multi_separator]'] = '|';
    $edit['options[alter][alter_text]'] = TRUE;
    $edit['options[alter][text]'] = "#{{counter}} [$field] {{ $field }}";
    $edit['options[empty]'] = "#{{counter}} [$field] [EMPTY]";

    switch ($field) {
      case 'counter':
        $edit = array(
          'options[exclude]' => TRUE,
        );
        break;

      case 'id':
        $edit['options[field_rendering]'] = FALSE;
        break;

      case 'search_api_datasource':
        unset($edit['options[fallback_options][multi_separator]']);
        break;

      case 'body':
        break;

      case 'category':
        break;

      case 'keywords':
        $edit['options[field_rendering]'] = FALSE;
        break;

      case 'user_id':
        $edit['options[field_rendering]'] = FALSE;
        $edit['options[fallback_options][display_methods][user][display_method]'] = 'id';
        break;

      case 'name':
        break;

      case 'roles':
        $edit['options[field_rendering]'] = FALSE;
        $edit['options[fallback_options][display_methods][user_role][display_method]'] = 'id';
        break;
    }

    $this->submitPluginForm($edit);

    return $field;
  }

  /**
   * Submits a Views plugin's configuration form.
   *
   * @param array $edit
   *   The values to set in the form.
   */
  protected function submitPluginForm(array $edit) {
    $button_label = $this->t('Apply');
    $buttons = $this->xpath('//input[starts-with(@value, :label)]', array(':label' => $button_label));
    if ($buttons) {
      $button_label = $buttons[0]['value'];
    }

    $this->drupalPostForm(NULL, $edit, $button_label);
    $this->assertResponse(200);
  }

}
