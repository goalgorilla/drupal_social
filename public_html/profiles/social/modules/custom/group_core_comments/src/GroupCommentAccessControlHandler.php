<?php

namespace Drupal\group_core_comments;

use Drupal\Core\Access\AccessResult;
use Drupal\comment\CommentAccessControlHandler;
use Drupal\group\Entity\GroupContent;
//use Drupal\group\Plugin\GroupContentEnablerHelper;
//use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the comment entity type.
 *
 * @see \Drupal\comment\Entity\Comment
 */
class GroupCommentAccessControlHandler extends CommentAccessControlHandler {

  // @TODO implement setting to make it possible overridden on per-group basis.

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\comment\CommentInterface|\Drupal\user\EntityOwnerInterface $entity */

    $parent = parent::checkAccess($entity, $operation, $account);

    // @TODO only react on if $parent === allowed Is this good/safe enough?
    if ($parent) {

      $commented_entity = $entity->getCommentedEntity();
      $groupcontents = GroupContent::loadByEntity($commented_entity);

      // Only react if it is actually posted inside a group.
      if (!empty($groupcontents)) {
        switch ($operation) {
          case 'view':
            $perm = 'access comments';
            return $this->getPermissionInGroups($perm, $account, $groupcontents);

          default:
            // No opinion.
            return AccessResult::neutral()->cachePerPermissions();
        }
      }
    }
    // Fallback.
    return $parent;
  }

  protected function getPermissionInGroups($perm, AccountInterface $account, $groupcontents) {

    // Only when you have permission to view the comments.
    foreach ($groupcontents as $groupcontent) {
      /** @var \Drupal\group\Entity\GroupContent $groupcontent */
      $group = $groupcontent->getGroup();
      /** @var \Drupal\group\Entity\Group $group */
      if ($group->hasPermission($perm, $account)) {
        return AccessResult::allowed()->cachePerUser();
      }
    }
    // Fallback.
    return AccessResult::forbidden()->cachePerUser();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {

    $parent = parent::checkCreateAccess($account, $context, $entity_bundle);
    if ($parent) {
//      $commented_entity = $entity->getCommentedEntity();
//      $groupcontents = GroupContent::loadByEntity($commented_entity);
//
//      // Only react if it is actually posted inside a group.
//      if (!empty($groupcontents)) {
//        // Only when you have permission to post the comments.
//        foreach ($groupcontents as $groupcontent) {
//          /** @var \Drupal\group\Entity\GroupContent $groupcontent */
//          $group = $groupcontent->getGroup();
//          /** @var \Drupal\group\Entity\Group $group */
//          if ($group->hasPermission($perm, $account)) {
//            return AccessResult::allowed()->cachePerUser();
//          }
//        }
//        // Fallback.
//        return AccessResult::forbidden()->cachePerUser();
//      }
    }

    return $parent;
  }

}
