<?php

/**
 * @file
 * Contains \Drupal\url_embed\UrlButtonListBuilder.
 */

namespace Drupal\url_embed;

#use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
#use Drupal\Core\Entity\UrlInterface;

/**
 * Provides a listing of UrlButton.
 */
class UrlButtonListBuilder{# extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Url button');
    $header['source'] = $this->t('Entity Type');
    $header['button_label'] = $this->t('Button Label');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(UrlInterface $url) {
    $row['label'] = $this->getLabel($url);
    $row['source'] = $url->getEntityTypeLabel();
    $row['button_label'] = $url->getButtonLabel();
    return $row + parent::buildRow($url);
  
}
