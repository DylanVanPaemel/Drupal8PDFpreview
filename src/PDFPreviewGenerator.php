<?php


namespace Drupal\pdfpreview;

use Drupal\Core\ImageToolkit\ImageToolkitManager;
use Drupal\file\Entity\File;
use Drupal\imagemagick\Plugin\ImageToolkit\ImagemagickToolkit;

class PDFPreviewGenerator {


  protected $config;


  protected $toolkit;

  public function __construct() {
    $this->config = \Drupal::config('pdfpreview.settings');
    $this->toolkit = \Drupal::service('image.toolkit.manager')->createInstance('imagemagick');
  }

  public function getPDFPreview(File $file) {
    $destination_uri = $this->getDestinationURI($file);

    if (file_exists($destination_uri)) {

      if (filemtime($file->getFileUri()) <= filemtime($destination_uri)) {
        return $destination_uri;
      }
      else {

        $this->deletePDFPreview($file);
      }
    }
    if ($this->createPDFPreview($file, $destination_uri)) {
      return $destination_uri;
    }
  }


  public function deletePDFPreview(File $file) {
    $uri = $this->getDestinationURI($file);
    file_unmanaged_delete($uri);
    image_path_flush($uri);
  }

  public function updatePDFPreview(File $file) {
    $original = $file->original;
    if ($file->getFileUri() != $original->getFileUri()
      || filesize($file->getFileUri()) != filesize($original->getFileUri())) {
      $this->deletePDFPreview($original);
    }
  }

  protected function createPDFPreview(File $file, $destination) {
    $file_uri = $file->getFileUri();
    $local_path = \Drupal::service('file_system')->realpath($file_uri);

    $directory = \Drupal::service('file_system')->dirname($destination);
    file_prepare_directory($directory, FILE_CREATE_DIRECTORY);
    $this->toolkit->addArgument('-background white');
    $this->toolkit->addArgument('-flatten');
    $this->toolkit->addArgument('-resize ' . escapeshellarg($this->config->get('size')));
    $this->toolkit->addArgument('-quality ' . escapeshellarg($this->config->get('quality')));
    $this->toolkit->addArgument('-pdfpreview');
    $this->toolkit->setDestinationFormat('JPG');
    $this->toolkit->setSourceFormat('PDF');
    $this->toolkit->setSourceLocalPath($local_path);

    return $this->toolkit->save($destination);
  }

  protected function getDestinationURI(File $file) {

    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $output_path = file_default_scheme() . '://' . $this->config->get('path');
    if ($this->config->get('filenames') == 'human') {
      $filename = \Drupal::service('file_system')->basename($file->getFileUri(), '.pdf');
      $filename = \Drupal::service('transliteration')->transliterate($filename, $langcode);
      $filename = $file->id() . '-' . $filename;
    }
    else {
      $filename = md5('pdfpreview' . $file->id());
    }
    return $output_path . '/' . $filename . '.jpg';
  }

}
