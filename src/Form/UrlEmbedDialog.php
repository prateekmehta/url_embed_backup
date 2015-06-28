<?php

/**
 * @file
 * Contains \Drupal\url_embed\Form\UrlEmbedDialog.
 */

namespace Drupal\url_embed\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\Ajax\EditorDialogSave;
use Drupal\editor\Entity\Editor;
use Drupal\url_embed\UrlButtonInterface;
use Drupal\filter\FilterFormatInterface;
use Drupal\Component\Serialization\Json;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to embed urls.
 */
class UrlEmbedDialog extends FormBase {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a UrlEmbedDialog object.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The Form Builder.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(FormBuilderInterface $form_builder, LoggerInterface $logger) {
    $this->formBuilder = $form_builder;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder'),
      $container->get('logger.factory')->get('url_embed')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'url_embed_dialog';
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\filter\Entity\FilterFormatInterface $filter_format
   *   The filter format to which this dialog corresponds.
   * @param \Drupal\url_embed\Entity\UrlButtonInterface $url_button
   *   The url button to which this dialog corresponds.
   */
  public function buildForm(array $form, FormStateInterface $form_state, FilterFormatInterface $filter_format = NULL, UrlButtonInterface $url_button = NULL) {
    $values = $form_state->getValues();
    $input = $form_state->getUserInput();
    // Set url button element in form state, so that it can be used later in
    // validateForm() function.
    $form_state->set('url_button', $url_button);
    // Initialize entity element with form attributes, if present.
    $entity_element = empty($values['attributes']) ? array() : $values['attributes'];
    // The default values are set directly from \Drupal::request()->request,
    // provided by the editor plugin opening the dialog.
    if (!$form_state->get('entity_element')) {
      $form_state->set('entity_element', isset($input['editor_object']) ? $input['editor_object'] : array());
    }
    $entity_element += $form_state->get('entity_element');
    $entity_element += array(
      'data-entity-type' => $url_button->getEntityTypeMachineName(),
      'data-entity-uuid' => '',
      'data-entity-id' => '',
      'data-entity-embed-display' => 'default',
      'data-entity-embed-settings' => array(),
      'data-align' => '',
    );

    if (!$form_state->get('step')) {
      // If an entity has been selected, then always skip to the embed options.
      if (!empty($entity_element['data-entity-type']) && (!empty($entity_element['data-entity-uuid']) || !empty($entity_element['data-entity-id']))) {
        $form_state->set('step', 'embed');
      }
      else {
        $form_state->set('step', 'select');
      }
    }

    $form['#tree'] = TRUE;
    $form['#attached']['library'][] = 'editor/drupal.editor.dialog';
    $form['#attached']['library'][] = 'url_embed/drupal.entity_embed.dialog';
    $form['#prefix'] = '<div id="entity-embed-dialog-form">';
    $form['#suffix'] = '</div>';

    switch ($form_state->get('step')) {
      case 'select':
        $form['attributes']['data-entity-type'] = array(
          '#type' => 'value',
          '#value' => $entity_element['data-entity-type'],
        );
        $entity = $this->loadEntity($entity_element['data-entity-type'], $entity_element['data-entity-uuid'] ?: $entity_element['data-entity-id']);

        $label = $this->t('Label');
        // Attempt to display a better label if we can by getting it from
        // the label field definition.
        $entity_type = $this->entityManager()->getDefinition($entity_element['data-entity-type']);
        if ($entity_type->isSubclassOf('\Drupal\Core\Entity\FieldableEntityInterface') && $entity_type->hasKey('label')) {
          $field_definitions = $this->entityManager()->getBaseFieldDefinitions($entity_type->id());
          if (isset($field_definitions[$entity_type->getKey('label')])) {
            $label = $field_definitions[$entity_type->getKey('label')]->getLabel();
          }
        }

        $form['attributes']['data-entity-id'] = array(
          '#type' => 'entity_autocomplete',
          '#target_type' => $entity_element['data-entity-type'],
          '#selection_settings' => array(
            'target_bundles' => $url_button->getEntityTypeBundles(),
          ),
          '#title' => $label,
          '#default_value' => $entity,
          '#required' => TRUE,
          '#description' => $this->t('Type label and pick the right one from suggestions. Note that the unique ID will be saved.'),
        );
        $form['attributes']['data-entity-uuid'] = array(
          '#type' => 'value',
          '#title' => $entity_element['data-entity-uuid'],
        );
        $form['actions'] = array(
          '#type' => 'actions',
        );
        $form['actions']['save_modal'] = array(
          '#type' => 'submit',
          '#value' => $this->t('Next'),
          // No regular submit-handler. This form only works via JavaScript.
          '#submit' => array(),
          '#ajax' => array(
            'callback' => array($this, 'submitSelectForm'),
            'event' => 'click',
          ),
        );
        break;

      case 'embed':
        $entity = $this->loadEntity($entity_element['data-entity-type'], $entity_element['data-entity-uuid'] ?: $entity_element['data-entity-id']);
        $entity_label = '';
        try {
          $entity_label = $entity->link();
        }
        catch(\Exception $e) {
          // Contruct markup of the link to the entity manually if link() fails.
          // @see https://www.drupal.org/node/2402533
          $entity_label = '<a href="' . $entity->url() . '">' . $entity->label() . '</a>';
        }

        $form['entity'] = array(
          '#type' => 'item',
          '#title' => $this->t('Selected entity'),
          '#markup' => $entity_label,
        );
        $form['attributes']['data-entity-type'] = array(
          '#type' => 'value',
          '#value' => $entity_element['data-entity-type'],
        );
        $form['attributes']['data-entity-id'] = array(
          '#type' => 'value',
          '#value' => $entity_element['data-entity-id'],
        );
        $form['attributes']['data-entity-uuid'] = array(
          '#type' => 'value',
          '#value' => $entity_element['data-entity-uuid'],
        );

        // Build the list of allowed display plugins.
        $allowed_plugins = $url_button->getAllowedDisplayPlugins();
        $available_plugins = $this->displayPluginManager()->getDefinitionOptionsForEntity($entity);
        // If list of allowed options is empty, it means that all plugins are
        // allowed. Else, take the intresection of allowed and available
        // plugins.
        $display_plugin_options = empty($allowed_plugins) ? $available_plugins : array_intersect_key($available_plugins, $allowed_plugins);

        // If the currently selected display is not in the available options,
        // use the first from the list instead. This can happen if an alter
        // hook customizes the list based on the entity.
        if (!isset($display_plugin_options[$entity_element['data-entity-embed-display']])) {
          $entity_element['data-entity-embed-display'] = key($display_plugin_options);
        }
        $form['attributes']['data-entity-embed-display'] = array(
          '#type' => 'select',
          '#title' => $this->t('Display as'),
          '#options' => $display_plugin_options,
          '#default_value' => $entity_element['data-entity-embed-display'],
          '#required' => TRUE,
          '#ajax' => array(
            'callback' => array($this, 'updatePluginConfigurationForm'),
            'wrapper' => 'data-entity-embed-settings-wrapper',
            'effect' => 'fade',
          ),
          // Hide the selection if only one option is available.
          '#access' => count($display_plugin_options) > 1,
        );
        $form['attributes']['data-entity-embed-settings'] = array(
          '#type' => 'container',
          '#prefix' => '<div id="data-entity-embed-settings-wrapper">',
          '#suffix' => '</div>',
        );
        $form['attributes']['data-embed-button'] = array(
          '#type' => 'value',
          '#value' => $url_button->id(),
        );
        $form['attributes']['data-entity-label'] = array(
          '#type' => 'value',
          '#value' => $url_button->getButtonLabel(),
        );
        $plugin_id = !empty($values['attributes']['data-entity-embed-display']) ? $values['attributes']['data-entity-embed-display'] : $entity_element['data-entity-embed-display'];
        if (!empty($plugin_id)) {
          if (is_string($entity_element['data-entity-embed-settings'])) {
            $entity_element['data-entity-embed-settings'] = Json::decode($entity_element['data-entity-embed-settings'], TRUE);
          }
          $display = $this->displayPluginManager()->createInstance($plugin_id, $entity_element['data-entity-embed-settings']);
          $display->setContextValue('entity', $entity);
          $display->setAttributes($entity_element);
          $form['attributes']['data-entity-embed-settings'] += $display->buildConfigurationForm($form, $form_state);
        }

        // When Drupal core's filter_align is being used, the text editor may
        // offer the ability to change the alignment.
        if (isset($entity_element['data-align']) && $filter_format->filters('filter_align')->status) {
          $form['attributes']['data-align'] = array(
            '#title' => $this->t('Align'),
            '#type' => 'radios',
            '#options' => array(
              'none' => $this->t('None'),
              'left' => $this->t('Left'),
              'center' => $this->t('Center'),
              'right' => $this->t('Right'),
            ),
            '#default_value' => $entity_element['data-align'] === '' ? 'none' : $entity_element['data-align'],
            '#wrapper_attributes' => array('class' => array('container-inline')),
            '#attributes' => array('class' => array('container-inline')),
            '#parents' => array('attributes', 'data-align'),
          );
        }

        // @todo Re-add caption attribute.
        $form['actions'] = array(
          '#type' => 'actions',
        );
        $form['actions']['back'] = array(
          '#type' => 'submit',
          '#value' => $this->t('Back'),
          // No regular submit-handler. This form only works via JavaScript.
          '#submit' => array(),
          '#ajax' => array(
            'callback' => array($this, 'goBack'),
            'event' => 'click',
          ),
        );
        $form['actions']['save_modal'] = array(
          '#type' => 'submit',
          '#value' => $this->t('Embed'),
          // No regular submit-handler. This form only works via JavaScript.
          '#submit' => array(),
          '#ajax' => array(
            'callback' => array($this, 'submitEmbedForm'),
            'event' => 'click',
          ),
        );
        break;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $values = $form_state->getValues();

    switch ($form_state->getStorage()['step']) {
      case 'select':
        if ($entity_type = $values['attributes']['data-entity-type']) {
          $id = trim($values['attributes']['data-entity-id']);
          if ($entity = $this->loadEntity($entity_type, $id)) {
            if (!$this->accessEntity($entity, 'view')) {
              $form_state->setError($form['attributes']['data-entity-id'], $this->t('Unable to access @type entity @id.', array('@type' => $entity_type, '@id' => $id)));
            }
            else {
              $form_state->setValueForElement($form['attributes']['data-entity-id'], $entity->id());
              if ($uuid = $entity->uuid()) {
                $form_state->setValueForElement($form['attributes']['data-entity-uuid'], $uuid);
              }
              else {
                $form_state->setValueForElement($form['attributes']['data-entity-uuid'], '');
              }

              // Ensure that at least one display plugin is present before
              // proceeding to the next step. Rasie an error otherwise.
              $url_button = $form_state->get('url_button');
              $allowed_plugins = $url_button->getAllowedDisplayPlugins();
              $available_plugins = $this->displayPluginManager()->getDefinitionOptionsForEntity($entity);
              $display_plugin_options = empty($allowed_plugins) ? $available_plugins : array_intersect_key($available_plugins, $allowed_plugins);
              // If no plugin is available after taking the intersection,
              // raise error. Also log an exception.
              if (empty($display_plugin_options)) {
                $form_state->setError($form['attributes']['data-entity-id'], $this->t('No display options available for the selected entity. Please select another entity.'));
                $this->logger->warning('No display options available for "@type:" entity "@id" while embedding using button "@button". Please ensure that at least one display plugin is allowed for this url button which is available for this entity.', array('@type' => $entity_type, '@id' => $entity->id(), '@button' => $url_button->id()));
              }
            }
          }
          else {
            $form_state->setError($form['attributes']['data-entity-id'], $this->t('Unable to load @type entity @id.', array('@type' => $entity_type, '@id' => $id)));
          }
        }
        break;

      case 'embed':
        // Validate configuration forms for the display plugin used.
        $entity_element = $form_state->getValue('attributes');
        $entity = $this->loadEntity($entity_element['data-entity-type'], $entity_element['data-entity-uuid']);
        $plugin_id = $entity_element['data-entity-embed-display'];
        $plugin_settings = $entity_element['data-entity-embed-settings'] ?: array();
        $display = $this->displayPluginManager()->createInstance($plugin_id, $plugin_settings);
        $display->setContextValue('entity', $entity);
        $display->setAttributes($entity_element);
        $display->validateConfigurationForm($form, $form_state);
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

  /**
   * Form submission handler to update the plugin configuration form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param FormStateInterface $form_state
   *   An associative array containing the current state of the form.
   */
  public function updatePluginConfigurationForm(array &$form, FormStateInterface $form_state) {
    return $form['attributes']['data-entity-embed-settings'];
  }

  /**
   * Form submission handler to go back to the previous step of the form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param FormStateInterface $form_state
   *   An associative array containing the current state of the form.
   */
  public function goBack(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    $form_state->setStorage(array('step' => 'select'));
    $form_state->setRebuild(TRUE);
    $rebuild_form = $this->formBuilder->rebuildForm('url_embed_dialog', $form_state, $form);
    unset($rebuild_form['#prefix'], $rebuild_form['#suffix']);
    $response->addCommand(new HtmlCommand('#entity-embed-dialog-form', $rebuild_form));

    return $response;
  }

  /**
   * Form submission handler that selects an entity and display embed settings.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param FormStateInterface $form_state
   *   An associative array containing the current state of the form.
   */
  public function submitSelectForm(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    // Display errors in form, if any.
    if ($form_state->hasAnyErrors()) {
      unset($form['#prefix'], $form['#suffix']);
      $form['status_messages'] = array(
        '#type' => 'status_messages',
        '#weight' => -10,
      );
      $response->addCommand(new HtmlCommand('#entity-embed-dialog-form', $form));
    }
    else {
      $form_state->setStorage(array('step' => 'embed'));
      $form_state->setRebuild(TRUE);
      $rebuild_form = $this->formBuilder->rebuildForm('url_embed_dialog', $form_state, $form);
      unset($rebuild_form['#prefix'], $rebuild_form['#suffix']);
      $response->addCommand(new HtmlCommand('#entity-embed-dialog-form', $rebuild_form));
    }

    return $response;
  }

  /**
   * Form submission handler embeds selected entity in WYSIWYG.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param FormStateInterface $form_state
   *   An associative array containing the current state of the form.
   */
  public function submitEmbedForm(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    // Submit configuration form the selected display plugin.
    $entity_element = $form_state->getValue('attributes');
    $entity = $this->loadEntity($entity_element['data-entity-type'], $entity_element['data-entity-uuid']);
    $plugin_id = $entity_element['data-entity-embed-display'];
    $plugin_settings = $entity_element['data-entity-embed-settings'] ?: array();
    $display = $this->displayPluginManager()->createInstance($plugin_id, $plugin_settings);
    $display->setContextValue('entity', $entity);
    $display->setAttributes($entity_element);
    $display->submitConfigurationForm($form, $form_state);

    $values = $form_state->getValues();
    // Display errors in form, if any.
    if ($form_state->hasAnyErrors()) {
      unset($form['#prefix'], $form['#suffix']);
      $form['status_messages'] = array(
        '#type' => 'status_messages',
        '#weight' => -10,
      );
      $response->addCommand(new HtmlCommand('#entity-embed-dialog-form', $form));
    }
    else {
      // Serialize entity embed settings to JSON string.
      if (!empty($values['attributes']['data-entity-embed-settings'])) {
        $values['attributes']['data-entity-embed-settings'] = Json::encode($values['attributes']['data-entity-embed-settings']);
      }

      $response->addCommand(new EditorDialogSave($values));
      $response->addCommand(new CloseModalDialogCommand());
    }

    return $response;
  }

  /**
   * Checks whether or not the url button is enabled for given text format.
   *
   * Returns allowed if the editor toolbar contains the url button and neutral
   * otherwise.
   *
   * @param \Drupal\filter\Entity\FilterFormatInterface $filter_format
   *   The filter format to which this dialog corresponds.
   * @param \Drupal\url_embed\Entity\UrlButtonInterface $url_button
   *   The url button to which this dialog corresponds.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function buttonIsEnabled(FilterFormatInterface $filter_format, UrlButtonInterface $url_button) {
    $button_id = $url_button->id();
    $editor = Editor::load($filter_format->id());
    $settings = $editor->getSettings();
    foreach ($settings['toolbar']['rows'] as $row_number => $row) {
      $button_groups[$row_number] = array();
      foreach ($row as $group) {
        if (in_array($button_id, $group['items'])) {
          return AccessResult::allowed();
        }
      }
    }

    return AccessResult::neutral();
  }
}
