<?php

namespace Drupal\group\Entity;

use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;

/**
 * Provides an interface defining a Group entity.
 *
 * @ingroup group
 */
interface GroupInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Gets the group creation timestamp.
   *
   * @return int
   *   Creation timestamp of the group.
   */
  public function getCreatedTime();

  /**
   * Returns the group type entity the group uses.
   *
   * @return \Drupal\group\Entity\GroupTypeInterface
   */
  public function getGroupType();

  /**
   * Adds a content entity as a group content entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity to add to the group.
   * @param string $plugin_id
   *   The ID of the content enabler plugin to add the entity with.
   * @param array $values
   *   (optional) Extra values to add to the group content relationship. You
   *   cannot overwrite the group ID (gid) or entity ID (entity_id).
   */
  public function addContent(ContentEntityInterface $entity, $plugin_id, $values = []);

  /**
   * Retrieves all GroupContent entities for the group.
   *
   * @param string $plugin_id
   *   (optional) A content enabler plugin ID to filter on.
   * @param array $filters
   *   (optional) An associative array of extra filters where the keys are
   *   property or field names and the values are the value to filter on.
   *
   * @return \Drupal\group\Entity\GroupContentInterface[]
   *   A list of GroupContent entities matching the criteria.
   */
  public function getContent($plugin_id = NULL, $filters = []);

  /**
   * Retrieves all GroupContent entities for a specific entity.
   *
   * @param string $plugin_id
   *   A content enabler plugin ID to filter on.
   * @param int $id
   *   The ID of the entity to retrieve the GroupContent entities for.
   *
   * @return \Drupal\group\Entity\GroupContentInterface[]
   *   A list of GroupContent entities matching the criteria.
   */
  public function getContentByEntityId($plugin_id, $id);

  /**
   * Retrieves all group content for the group.
   *
   * Unlike GroupInterface::getContent(), this function actually returns the
   * entities that were added to the group through GroupContent entities.
   *
   * @param string $plugin_id
   *   (optional) A content enabler plugin ID to filter on.
   * @param array $filters
   *   (optional) An associative array of extra filters where the keys are
   *   property or field names and the values are the value to filter on.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   A list of entities matching the criteria. This list does not have keys
   *   that represent the entity IDs as we could have collisions that way.
   *
   * @see \Drupal\group\Entity\GroupInterface::getContent()
   */
  public function getContentEntities($plugin_id = NULL, $filters = []);

  /**
   * Adds a user as a member of the group.
   *
   * Does nothing if the user is already a member of the group.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user entity to add as a member.
   * @param array $values
   *   (optional) Extra values to add to the group membership, like the
   *   'group_roles' field. You cannot overwrite the group ID (gid) or user ID
   *   (entity_id) with this method. Leave blank to make the user just a member.
   */
  public function addMember(UserInterface $account, $values = []);

  /**
   * Retrieves a user's membership for the group.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user to load the membership for.
   *
   * @return \Drupal\group\GroupMembership|false
   *   The loaded GroupMembership or FALSE if none was found.
   */
  public function getMember(AccountInterface $account);

  /**
   * Retrieves all group memberships for the group.
   *
   * @param string|array $roles
   *   (optional) A group role machine name or a list of group role machine
   *   names to filter on. Results only need to match on one role (IN query).
   *
   * @return \Drupal\group\GroupMembership[]
   *   A list of GroupMembership objects representing the memberships.
   */
  public function getMembers($roles = NULL);

  /**
   * Checks whether a user has the requested permission.
   *
   * @param string $permission
   *   The permission to check for.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account for which to check a permission.
   *
   * @return bool
   *   Whether the user has the requested permission.
   */
  public function hasPermission($permission, AccountInterface $account);

}
