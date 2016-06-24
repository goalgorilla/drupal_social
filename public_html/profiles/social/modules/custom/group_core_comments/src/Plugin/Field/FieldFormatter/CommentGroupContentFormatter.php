<?php

namespace Drupal\group_core_comments\Plugin\Field\FieldFormatter;

use Drupal\comment\Plugin\Field\FieldFormatter\CommentDefaultFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\group\Entity\GroupContent;


/**
 * Plugin implementation of the 'comment_group_content' formatter.
 *
 * @FieldFormatter(
 *   id = "comment_group_content",
 *   label = @Translation("Comment on group content"),
 *   field_types = {
 *     "comment"
 *   }
 * )
 */
class CommentGroupContentFormatter extends CommentDefaultFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $parent = parent::viewElements($items, $langcode);
    $entity = $items->getEntity();
    $group_contents = GroupContent::loadByEntity($entity);

    if (!empty($group_contents)) {
      // Add cache contexts.
      $parent['#cache']['contexts'][] = 'group.type';
      $parent['#cache']['contexts'][] = 'group_membership';

      $account = \Drupal::currentUser();
      $renderer = \Drupal::service('renderer');

      foreach ($group_contents as $group_content) {
        $group = $group_content->getGroup();
        // Add cacheable dependency.
        $membership = $group->getMember($account);
        $renderer->addCacheableDependency($parent, $membership);
        // Remove comments from output if user don't have access.
        if (!$group->hasPermission('post comments', $account)) {
          unset($parent[0]['comment_form']);
        }
        if (!$group->hasPermission('access comments', $account)) {
          unset($parent[0]);
        }
      }
    }
    return $parent;
  }

}
