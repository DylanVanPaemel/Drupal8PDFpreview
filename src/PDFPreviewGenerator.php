<?php
/**
 * @file
 * Contains Drupal\pdfpreview\PDFPreviewGenerator.
 */

namespace Drupal\pdfpreview;

use Drupal\Core\ImageToolkit\ImageToolkitManager;
use Drupal\file\Entity\File;
use Drupal\imagemagick\Plugin\ImageToolkit\ImagemagickToolkit;

class PDFPreviewGenerator {

  /**
   * The config for this module.
   *
   * @var
   */
  protected $config;

  /**
   * The toolkit to generate previews.
   *
   * @var ImagemagickToolkit
   */
  protected $toolkit;

  /**
   * Constructs a PDFPreviewGenerator object.
   */
  public function __construct() {
    $this->config = \Drupal::config('pdfpreview.settings');
    $this->toolkit = \Drupal::service('image.toolkit.manager')->createInstance('imagemagick');
  }

  /**
   * Gets the preview image if it exists, or creates it if it doesnt.
   *
   * @param \Drupal\file\Entity\File $file
   *   The file to generate a preview for.
   */
  public function getPDFPreview(File $file) {
    $destination_uri = $this->getDestinationURI($file);
    // Check if a preview already exists.
    if (file_exists($destination_uri)) {
      // Check if the existing preview is older than the file itself.
      if (filemtime($file->getFileUri()) <= filemtime($destination_uri)) {
        // The existing preview can be used, nothing to do.
        return $destination_uri;
      }
      else {
        // Delete the existing but out-of-date preview.
        $this->deletePDFPreview($file);
      }
    }
    if ($this->createPDFPreview($file, $destination_uri)) {
      return $destination_uri;
    }
  }

  /**
   * Deletes the preview image for a file.
   *
   * @param \Drupal\file\Entity\File $file
   *    The file to delete the preview for.
   */
  public function deletePDFPreview(File $file) {
    $uri = $this->getDestinationURI($file);
    file_unmanaged_delete($uri);
    image_path_flush($uri);
  }

  /**
   * Deletes the preview image for a file when the file is updated.
   *
   * @param \Drupal\file\Entity\File $file
   *    The file to delete the preview for.
   */
  public function updatePDFPreview(File $file) {
    $original = $file->original;
    if ($file->getFileUri() != $original->getFileUri()
      || filesize($file->getFileUri()) != filesize($original->getFileUri())) {
      $this->deletePDFPreview($original);
    }
  }

  /**
   * Creates a preview image of the first page of a PDF file.
   *
   * @param \Drupal\file\Entity\File $file
   *    The file to generate a preview for.
   * @param string $destination
   *    The URI where the preview should be created.
   *
   * @return bool
   *    TRUE if the preview was successfully generated, FALSE is otherwise.
   */
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

  /**
   * Gets the destination URI of the file.
   *
   * @param \Drupal\file\Entity\File $file
   *   The file that is being converted.
   *
   * @return string
   *   The destination URI.
   */
  protected function getDestinationURI(File $file) {
    // TODO: Check if we should use human-readable filenames or md5 filenames.
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
