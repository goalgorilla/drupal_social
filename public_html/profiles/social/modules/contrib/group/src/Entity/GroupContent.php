<?php

namespace Drupal\group\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\UserInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines the Group content entity.
 *
 * @ingroup group
 *
 * @ContentEntityType(
 *   id = "group_content",
 *   label = @Translation("Group content"),
 *   label_singular = @Translation("group content item"),
 *   label_plural = @Translation("group content items"),
 *   label_count = @PluralTranslation(
 *     singular = "@count group content item",
 *     plural = "@count group content items"
 *   ),
 *   bundle_label = @Translation("Group content type"),
 *   handlers = {
 *     "storage" = "Drupal\group\Entity\Storage\GroupContentStorage",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "views_data" = "Drupal\group\Entity\Views\GroupContentViewsData",
 *     "list_builder" = "Drupal\group\Entity\Controller\GroupContentListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\group\Entity\Routing\GroupContentRouteProvider",
 *     },
 *     "form" = {
 *       "add" = "Drupal\group\Entity\Form\GroupContentForm",
 *       "edit" = "Drupal\group\Entity\Form\GroupContentForm",
 *       "delete" = "Drupal\group\Entity\Form\GroupContentDeleteForm",
 *       "group-join" = "Drupal\group\Form\GroupJoinForm",
 *       "group-leave" = "Drupal\group\Form\GroupLeaveForm",
 *     },
 *     "access" = "Drupal\group\Entity\Access\GroupContentAccessControlHandler",
 *   },
 *   base_table = "group_content",
 *   data_table = "group_content_field_data",
 *   translatable = TRUE,
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "langcode" = "langcode",
 *     "bundle" = "type",
 *     "label" = "label"
 *   },
 *   links = {
 *     "add-form" = "/group/{group}/content/add/{plugin_id}",
 *     "add-page" = "/group/{group}/content/add",
 *     "canonical" = "/group/{group}/content/{group_content}",
 *     "collection" = "/group/{group}/content",
 *     "delete-form" = "/group/{group}/content/{group_content}/delete",
 *     "edit-form" = "/group/{group}/content/{group_content}/edit"
 *   },
 *   bundle_entity_type = "group_content_type",
 *   field_ui_base_route = "entity.group_content_type.edit_form",
 *   permission_granularity = "bundle"
 * )
 */
class GroupContent extends ContentEntityBase implements GroupContentInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public function getGroupContentType() {
    return $this->type->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroup() {
    return $this->gid->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity() {
    return $this->entity_id->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getContentPlugin() {
    return $this->getGroupContentType()->getContentPlugin();
  }

  /**
   * {@inheritdoc}
   */
  public static function loadByContentPluginId($plugin_id) {
    $group_content_types = GroupContentType::loadByContentPluginId($plugin_id);

    if (empty($group_content_types)) {
      return [];
    }

    return \Drupal::entityTypeManager()
      ->getStorage('group_content')
      ->loadByProperties(['type' => array_keys($group_content_types)]);
  }

  /**
   * {@inheritdoc}
   */
  public static function loadByEntity(ContentEntityInterface $entity) {
    $group_content_types = GroupContentType::loadByEntityTypeId($entity->getEntityTypeId());

    // If no responsible group content types were found, we return nothing.
    if (empty($group_content_types)) {
      return [];
    }

    return \Drupal::entityTypeManager()
      ->getStorage('group_content')
      ->loadByProperties([
        'type' => array_keys($group_content_types),
        'entity_id' => $entity->id(),
      ]);
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->getContentPlugin()->getContentLabel($this);
  }

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    $uri_route_parameters = parent::urlRouteParameters($rel);
    $uri_route_parameters['group'] = $this->getGroup()->id();
    return $uri_route_parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getChangedTime() {
    return $this->get('changed')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('uid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('uid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('uid', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Set the label so the DB also reflects it.
    $this->set('label', $this->label());
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['gid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Parent group'))
      ->setDescription(t('The group containing the entity.'))
      ->setSetting('target_type', 'group')
      ->setReadOnly(TRUE);

    // Borrowed this logic from the Comment module.
    // Warning! May change in the future: https://www.drupal.org/node/2346347
    $fields['entity_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Content'))
      ->setDescription(t('The entity to add to the group.'))
      ->addConstraint('GroupContentCardinality')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setRequired(TRUE);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setReadOnly(TRUE)
      ->setTranslatable(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ]);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Group content creator'))
      ->setDescription(t('The username of the group content creator.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDefaultValueCallback('Drupal\group\Entity\GroupContent::getCurrentUserId')
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created on'))
      ->setDescription(t('The time that the group content was created.'))
      ->setTranslatable(TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed on'))
      ->setDescription(t('The time that the group content was last edited.'))
      ->setTranslatable(TRUE);

    return $fields;
  }

  /**
   * Default value callback for 'uid' base field definition.
   *
   * @see ::baseFieldDefinitions()
   *
   * @return array
   *   An array of default values.
   */
  public static function getCurrentUserId() {
    return [\Drupal::currentUser()->id()];
  }

  /**
   * {@inheritdoc}
   */
  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {
    // Borrowed this logic from the Comment module.
    // Warning! May change in the future: https://www.drupal.org/node/2346347
    if ($group_content_type = GroupContentType::load($bundle)) {
      $plugin = $group_content_type->getContentPlugin();

      /** @var \Drupal\Core\Field\BaseFieldDefinition $original */
      $original = $base_field_definitions['entity_id'];

      // Recreated the original entity_id field so that it does not contain any
      // data in its "propertyDefinitions" or "schema" properties because those
      // were set based on the base field which had no clue what bundle to serve
      // up until now. This is a bug in core because we can't simply unset those
      // two properties, see: https://www.drupal.org/node/2346329
      $fields['entity_id'] = BaseFieldDefinition::create('entity_reference')
        ->setLabel($original->getLabel())
        ->setDescription($original->getDescription())
        ->setConstraints($original->getConstraints())
        ->setDisplayOptions('view', $original->getDisplayOptions('view'))
        ->setDisplayOptions('form', $original->getDisplayOptions('form'))
        ->setDisplayConfigurable('view', $original->isDisplayConfigurable('view'))
        ->setDisplayConfigurable('form', $original->isDisplayConfigurable('form'))
        ->setRequired($original->isRequired());

      foreach ($plugin->getEntityReferenceSettings() as $name => $setting) {
        $fields['entity_id']->setSetting($name, $setting);
      }

      return $fields;
    }

    return [];
  }

}
