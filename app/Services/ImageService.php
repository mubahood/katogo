<?php

namespace App\Services;

use App\Models\Utils;

/**
 * ImageService
 *
 * Centralises image upload and thumbnail generation.
 * These methods were originally on Utils (P10-05). Utils now delegates here.
 */
class ImageService
{
    /**
     * Upload one or more images from the PHP $_FILES superglobal.
     *
     * @param  array $files         Raw $_FILES array (or sub-array of it)
     * @param  bool  $isSingleFile  When true, returns a single filename string instead of an array
     * @return string|array
     */
    public static function uploadImages(array $files, bool $isSingleFile = false): string|array
    {
        ini_set('memory_limit', '-1');

        if (empty($files)) {
            return $isSingleFile ? '' : [];
        }

        $uploadedImages = [];

        foreach ($files as $file) {
            if (
                isset($file['name'], $file['type'], $file['tmp_name'], $file['error'], $file['size'])
                && $file['error'] === UPLOAD_ERR_OK
            ) {
                $ext         = pathinfo($file['name'], PATHINFO_EXTENSION);
                $fileName    = time() . '-' . rand(100000, 1000000) . '.' . $ext;
                $destination = Utils::docs_root() . '/storage/images/' . $fileName;

                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $uploadedImages[] = $fileName;
                }
            }
        }

        $singleFile = $uploadedImages[0] ?? '';

        return $isSingleFile ? $singleFile : $uploadedImages;
    }

    /**
     * Create a thumbnail from a source image path.
     *
     * @param  array{source: string, target: string, quality?: int}  $params
     * @return string|void  Returns target path on success, source path on failure
     */
    public static function createThumbnail(array $params)
    {
        ini_set('memory_limit', '-1');

        if (!isset($params['source'], $params['target'])) {
            return;
        }

        if (!file_exists($params['source'])) {
            return url('assets/images/logo.png');
        }

        $image = new \Zebra_Image();

        $image->auto_handle_exif_orientation      = true;
        $image->source_path                       = $params['source'];
        $image->target_path                       = $params['target'];
        $image->preserve_aspect_ratio             = true;
        $image->enlarge_smaller_images            = true;
        $image->preserve_time                     = true;
        $image->handle_exif_orientation_tag       = true;

        $sizeMb = filesize($params['source']) / (1024 * 1024);

        if ($sizeMb < 1) {
            copy($params['source'], $params['target']);
            return;
        }

        if (isset($params['quality'])) {
            $image->jpeg_quality = $params['quality'];
        } else {
            $image->jpeg_quality = Utils::get_jpeg_quality(filesize($params['source']));
        }

        if (!$image->resize(0, 0, ZEBRA_IMAGE_CROP_CENTER)) {
            return $image->source_path;
        }

        return $image->target_path;
    }
}
