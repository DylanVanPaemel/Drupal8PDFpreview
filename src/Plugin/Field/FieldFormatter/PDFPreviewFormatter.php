<?php

namespace Drupal\pdfpreview\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatter;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Url;


//mogelijkheid om het veld te selecteren binnen een contenttype + instellingen
class PDFPreviewFormatter extends ImageFormatter {


  public static function defaultSettings() {
    $config = \Drupal::config('pdfpreview.settings');
    return array(
      'show_description' => $config->get('show_description'),
      'tag' => $config->get('tag'),
      'fallback_formatter' => $config->get('fallback_formatter'),
    ) + parent::defaultSettings();
  }


  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);
    $form['show_description'] = array(
      '#type' => 'checkbox',
      '#title' => t('Description'),
      '#description' => t('Show file description beside image'),
      '#options' => array(0 => t('No'), 1 => t('Yes')),
      '#default_value' => $this->getSetting('show_description'),
    );
    $form['tag'] = array(
      '#type' => 'radios',
      '#title' => t('HTML tag'),
      '#description' => t('Select which kind of HTML element will be used to theme elements'),
      '#options' => array('span' => 'span', 'div' => 'div'),
      '#default_value' => $this->getSetting('tag'),
    );
    $form['fallback_formatter'] = array(
      '#type' => 'checkbox',
      '#title' => t('Fallback to default file formatter'),
      '#description' => t('When enabled, non-PDF files will be formatted using a default file formatter.'),
      '#default_value' => (boolean) $this->getSetting('fallback_formatter'),
      '#return_value' => \Drupal::config('pdfpreview.settings')->get('fallback_formatter'),
    );
    return $form;
  }


  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $summary[] = t('Separator tag: @tag', array(
      '@tag' => $this->getSetting('tag'),
    ));
    $summary[] = t('Descriptions: @visibility', array(
      '@visibility' => $this->getSetting('show_description') ? t('Visible') : t('Hidden'),
    ));
    if ($this->getSetting('fallback_formatter')) {
      $summary[] = t('Using the default file formatter for non-PDF files');
    }
    return $summary;
  }

  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = array();
    $files = $this->getEntitiesToView($items, $langcode);

    if (empty($files)) {
      return $elements;
    }

    $url = NULL;
    $image_link_setting = $this->getSetting('image_link');
    if ($image_link_setting == 'content') {
      $entity = $items->getEntity();
      if (!$entity->isNew()) {
        $url = $entity->urlInfo();
      }
    }
    elseif ($image_link_setting == 'file') {
      $link_file = TRUE;
    }

    $image_style_setting = $this->getSetting('image_style');

    $cache_tags = array();
    if (!empty($image_style_setting)) {
      $image_style = $this->imageStyleStorage->load($image_style_setting);
      $cache_tags = $image_style->getCacheTags();
    }

    foreach ($files as $delta => $file) {
      $cache_contexts = array();
      if (isset($link_file)) {
        $image_uri = $file->getFileUri();
        $url = Url::fromUri(file_create_url($image_uri));
        $cache_contexts[] = 'url.site';
      }
      $cache_tags = Cache::mergeTags($cache_tags, $file->getCacheTags());
      $item = $file->_referringItem;
      $item_attributes = $item->_attributes;
      unset($item->_attributes);

      if (isset($item->description)) {
        $item_attributes['alt'] = $item->description;
        $item_attributes['title'] = $item->description;
      }
      $item_attributes['class'][] = 'pdfpreview-file';


      $show_preview = FALSE;
      if ($file->getMimeType() == 'application/pdf') {
        $preview_uri = \Drupal::service('pdfpreview.generator')->getPDFPreview($file);
        $preview = \Drupal::service('image.factory')->get($preview_uri);
        if ($preview->isValid()) {
          $show_preview = TRUE;
          $item->uri = $preview_uri;
          $item->width = $preview->getWidth();
          $item->height = $preview->getHeight();
          $elements[$delta] = array(
            '#theme' => 'image_formatter',
            '#item' => $item,
            '#item_attributes' => $item_attributes,
            '#image_style' => $image_style_setting,
            '#url' => $url,
            '#cache' => array(
              'tags' => $cache_tags,
              'contexts' => $cache_contexts,
            ),
          );
        }
      }
      if (!$show_preview) {
        $elements[$delta] = array(
          '#theme' => 'file_link',
          '#file' => $file,
          '#cache' => array(
            'tags' => $file->getCacheTags(),
          ),
        );
      }

      $elements[$delta]['#description'] = $item->description;
      $elements[$delta]['#theme_wrappers'][] = 'pdfpreview_formatter';
      $elements[$delta]['#settings'] = $this->getSettings();
      $elements[$delta]['#fid'] = $file->id();
    }

    return $elements;
  }

}
