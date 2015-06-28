<?php

/**
 * @file
 * Contains \Drupal\url_embed\UrlButtonListBuilder.
 */

namespace Drupal\url_embed;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of UrlButton.
 */
class UrlButtonListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Url button');
    $header['entity_type'] = $this->t('Entity Type');
    $header['button_label'] = $this->t('Button Label');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $this->getLabel($entity);
    $row['entity_type'] = $entity->getEntityTypeLabel();
    $row['button_label'] = $entity->getButtonLabel();
    return $row + parent::buildRow($entity);
  }
}
