<?php

/**
 * @file
 * Contains \Drupal\url_embed\Plugin\Filter\UrlEmbedFilter.
 */

namespace Drupal\url_embed\Plugin\Filter;

use Drupal\Core\Annotation\Translation;
use Drupal\Component\Utility\Html;
use Drupal\filter\Annotation\Filter;
#use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\filter\Plugin\FilterBase;
#use Symfony\Component\DependencyInjection\ContainerInterface;

include '../../Embed/src/autoloader.php';

/**
 * Provides a filter to display embedded URLs based on data attributes.
 *
 * @Filter(
 *   id = "url_embed",
 *   title = @Translation("Display embedded URLs"),
 *   description = @Translation("Embeds URLs using data attributes: data-entity-type, data-entity-uuid or data-entity-id, and data-view-mode."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_REVERSIBLE
 * )
 */
class UrlEmbedFilter extends FilterBase{

  /**
   * Replace the contents of a DOMNode.
   *
   * @param \DOMNode $node
   *   A DOMNode or DOMElement object.
   * @param string $content
   *   The text or HTML that will replace the contents of $node.
   */
  protected function setDomNodeContent(\DOMNode $node, $content) {
    // Load the contents into a new DOMDocument and retrieve the element.
    $replacement_node = Html::load($content)->getElementsByTagName('body')
      ->item(0)
      ->childNodes
      ->item(0);

    // Import the updated DOMNode from the new DOMDocument into the original
    // one, importing also the child nodes of the replacment DOMNode.
    $replacement_node = $node->ownerDocument->importNode($replacement_node, TRUE);

    // Remove all children of the DOMNode.
    while ($node->hasChildNodes()) {
      $node->removeChild($node->firstChild);
    }
    // Rename tag of container elemet to 'div' if it was 'drupal-entity'.
    if ($node->tagName == 'data-url') {
      $new_node = $node->ownerDocument->createElement('div');

      // Copy all attributes of original node to new node.
      if ($node->attributes->length) {
        foreach ($node->attributes as $attribute) {
          $new_node->setAttribute($attribute->nodeName, $attribute->nodeValue);
        }
      }

      $node->parentNode->replaceChild($new_node, $node);

      $node = $new_node;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    if (strpos($text, 'data-embed-url') !== FALSE &&  strpos($text, 'data-view-mode') !== FALSE) {
      $dom = Html::load($text);
      $xpath = new \DOMXPath($dom);

      foreach ($xpath->query('//*[@data-embed-url and @data-view-mode]') as $node) {
        $url = $node->getAttribute('data-url');
        $info = Embed\Embed::create($url);
        // $placeholder = $this->buildPlaceholder($entity, $result, $context);
        $this->setDomNodeContent($node, $info->code);
      }
      $result->setProcessedText(Html::serialize($dom));
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
  }

}
