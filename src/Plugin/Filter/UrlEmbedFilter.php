<?php

/**
 * @file
 * Contains \Drupal\url_embed\Plugin\Filter\urlEmbedFilter.
 */

namespace Drupal\url_embed\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;


class urlEmbedFilter extends FilterBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityManagerInterface $entity_manager, ModuleHandlerInterface $module_handler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {  
  }
  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE){
  }

}

