<?php

namespace Drupal\search_api_db\DatabaseCompatibility;

use Drupal\search_api\SearchApiException;

/**
 * Represents a MySQL-based database.
 */
class MySql extends GenericDatabase {

  /**
   * {@inheritdoc}
   */
  public function alterNewTable($table, $type = 'text') {
    // The Drupal MySQL integration defaults to using a 4-byte-per-character
    // encoding, which would make it impossible to use our normal 255 characters
    // long varchar fields in a primary key (since that would exceed the key's
    // maximum size). Therefore, we have to convert all tables to the "utf8"
    // character set – but we only want to make fulltext tables case-sensitive.
    $collation = $type == 'text' ? 'utf8_bin' : 'utf8_general_ci';
    try {
      $this->database->query("ALTER TABLE {{$table}} CONVERT TO CHARACTER SET 'utf8' COLLATE '$collation'");
    }
    catch (\PDOException $e) {
      $class = get_class($e);
      $message = $e->getMessage();
      throw new SearchApiException("$class while trying to change collation of $type search data table '$table': $message", 0, $e);
    }
  }

}
