<?php

/**
 * @file
 * Contains \Drupal\url_embed\URL\UrlButton.
 */

namespace Drupal\url_embed\URL;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\url_embed\UrlButtonInterface;
use Drupal\url_embed\EntityHelperTrait;

/**
 * Defines the UrlButton entity.
 *
 * @ConfigEntityType(
 *   id = "url_button",
 *   label = @Translation("Url Button"),
 *   handlers = {
 *     "list_builder" = "Drupal\entity_embed\UrlButtonListBuilder",
 *     "form" = {
 *       "add" = "Drupal\entity_embed\Form\UrlButtonForm",
 *       "edit" = "Drupal\entity_embed\Form\UrlButtonForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "url_button",
 *   admin_permission = "administer embed buttons",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *   },
 *   config_export = {
 *     "label",
 *     "id",
 *     "button_label",
 *     "entity_type",
 *     "Oembed_type",
 *     "button_icon_uuid",
 *     "display_plugins",
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/content/embed-button/{url_button}",
 *     "delete-form" = "/admin/config/content/embed-button/{url_button}/delete"
 *   }
 * )
 */
class UrlButton extends ConfigEntityBase implements UrlButtonInterface {
  use UrlHelperTrait;

  /**
   * The UrlButton ID.
   *
   * @var string
   */
  public $id;

  /**
   * Label of UrlButton.
   *
   * @var string
   */
  public $label;

  /**
   * Label of the button shown in CKEditor toolbar.
   *
   * @var string
   */
  public $button_label;
  
  /**
   * Array of Ombed providers allowed for the entity type.
   *
   * An empty array signifies that all are allowed.
   *
   * @var array
   */
  public $Oembed_type;

  /**
   * UUID of the button's icon file.
   *
   * @var string
   */
  public $button_icon_uuid;

  /**
   * Array of allowed display plugins for the entity type.
   *
   * An empty array signifies that all are allowed.
   *
   * @var array
   */
  public $display_plugins;

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeMachineName() {
    return $this->entity_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeLabel() {
    return $this->entityManager()->getDefinition($this->entity_type)->getLabel();
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeBundles() {
    return $this->entity_type_bundles;
  }

  /**
   * {@inheritdoc}
   */
  public function getButtonLabel() {
    return $this->button_label;
  }

  /**
   * {@inheritdoc}
   */
  public function getButtonImage() {
    if ($this->button_icon_uuid && $image = $this->entityManager()->loadEntityByUuid('file', $this->button_icon_uuid)) {
      return $image->url();
    }
    else {
      return file_create_url(drupal_get_path('module', 'entity_embed') . '/js/plugins/drupalentity/entity.png');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedDisplayPlugins() {
    $allowed_display_plugins = array();
    // Include only those plugin ids in result whose value is set.
    foreach ($this->display_plugins as $key => $value) {
      if ($value) {
        $allowed_display_plugins[$key] = $value;
      }
    }
    return $allowed_display_plugins;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();

    // Add the file icon entity as dependency if an UUID was specified.
    if ($this->button_icon_uuid && $file_icon = $this->entityManager()->loadEntityByUuid('file', $this->button_icon_uuid)) {
      $this->addDependency($file_icon->getConfigDependencyKey(), $file_icon->getConfigDependencyName());
    }

    return $this->dependencies;
  }

}
