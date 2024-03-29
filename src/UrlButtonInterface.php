<?php

/**
 * @file
 * Contains \Drupal\url_embed\UrlButtonInterface.
 */

namespace Drupal\url_embed;

#use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a url button entity.
 */
interface UrlButtonInterface{# extends ConfigEntityInterface {

  /**
   * Returns the sources of url for which this button is enabled.
   *
   * @return string
   *   Machine name of the sources .
   */
  public function getSourceMachineName();

  /**
   * Returns the label of the sources of url for which this button is enabled.
   *
   * @return string
   *   Human readable label of the source.
   */
  public function getSourceLabel();

  /**
   * Returns the label for the button to be shown in CKEditor toolbar.
   *
   * @return string
   *   Label for the button.
   */
  public function getButtonLabel();

  /**
   * Returns the URL of the button's icon.
   *
   * @return string
   *   URL for the button'icon.
   */
  public function getButtonImage();

  /**
   * Returns the list of Ombed providers selected for the entity type.
   *
   * @return array
   *   List of allowed bundles.
   */
  public function getSourceBundles();

  /**
   * Returns the list of display plugins allowed for the entity type.
   *
   * @return array
   *   List of allowed display plugins.
   */
  public function getAllowedDisplayPlugins();

}
