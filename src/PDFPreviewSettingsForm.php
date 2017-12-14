<?php

namespace Drupal\pdfpreview;

/**
 * toevoegen van de gebruikte packages
 */

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * klasse aanmaken + functies
 */
class PDFPreviewSettingsForm extends ConfigFormBase {

  /**
   * aanmaken van een formobject en returnen
   *
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    parent::__construct($config_factory);
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  public function getFormId() {
    return 'pdfpreview_settings_form';
  }

  protected function getEditableConfigNames() {
    return ['pdfpreview.settings'];
  }

  /**
   * samenstellen en opbouwen van de form met alle mogelijkheden.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('pdfpreview.settings');
    $form['path'] = array(
      '#type' => 'textfield',
      '#title' => t('Preview path'),
      '#description' => t('Path inside files directory where previews are stored. For example %default', array(
        '%default' => 'pdfpreview',
      )),
      '#default_value' => $config->get('path'),
    );
    $form['size'] = array(
      '#type' => 'textfield',
      '#title' => t('Preview size'),
      '#description' => t('Size of the preview in pixels. For example %example. You must set this to a value big enough to apply your image styles.', array(
        '%example' => '100x100',
      )),
      '#default_value' => $config->get('size'),
    );
    $form['quality'] = array(
      '#type' => 'textfield',
      '#title' => t('Image quality'),
      '#size' => 3,
      '#maxlength' => 3,
      '#field_suffix' => '%',
      '#description' => t('Image extraction quality'),
      '#default_value' => $config->get('quality'),
    );
    $form['filenames'] = array(
      '#type' => 'radios',
      '#title' => t('Generated filenames'),
      '#options' => array(
        'machine' => t('Filename hash'),
        'human' => t('From PDF filename'),
      ),
      '#description' => t('This changes how filenames will be used on generated previews. If you change this after some files were generated, you must delete them manually.'),
      '#default_value' => $config->get('filenames'),
    );
    return parent::buildForm($form, $form_state);
  }

  /**
   * functie als op opslaan geduwd wordt (alle waardes opslaan)
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('pdfpreview.settings')
      ->set('path', $form_state->getValue('path'))
      ->set('size', $form_state->getValue('size'))
      ->set('quality', $form_state->getValue('quality'))
      ->set('filenames', $form_state->getValue('filenames'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
