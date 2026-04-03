<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * VideoService
 *
 * Centralises Firebase Storage operations for video files.
 * These methods were originally on Utils (P10-04). Utils now delegates here.
 */
class VideoService
{
    /**
     * Ensure a Firebase Storage folder exists (creates a marker file if needed).
     */
    public static function ensureFirebaseFolder(string $folderPath): array
    {
        try {
            $storage = app('firebase.storage');
            $bucket  = $storage->getBucket(config('firebase.storage.bucket'));

            $objects     = $bucket->objects(['prefix' => $folderPath . '/']);
            $folderExists = false;

            foreach ($objects as $object) {
                $folderExists = true;
                break;
            }

            if (!$folderExists) {
                $bucket->upload('', [
                    'name'     => $folderPath . '/.folder_marker',
                    'metadata' => [
                        'contentType' => 'text/plain',
                        'metadata'    => [
                            'created_at' => now()->toISOString(),
                            'type'       => 'folder_marker',
                        ],
                    ],
                ]);
            }

            return [
                'success'        => true,
                'folder_exists'  => $folderExists,
                'folder_created' => !$folderExists,
                'error'          => null,
            ];
        } catch (\Exception $e) {
            return [
                'success'        => false,
                'error'          => 'Failed to ensure folder exists: ' . $e->getMessage(),
                'folder_exists'  => false,
                'folder_created' => false,
            ];
        }
    }

    /**
     * Get a permanent public URL for a file in Firebase Storage.
     */
    public static function getFirebasePermanentUrl(string $firebasePath): array
    {
        try {
            $storage = app('firebase.storage');
            $bucket  = $storage->getBucket(config('firebase.storage.bucket'));
            $object  = $bucket->object($firebasePath);

            if (!$object->exists()) {
                return ['success' => false, 'error' => 'File not found in Firebase Storage', 'url' => null];
            }

            $object->update(['acl' => [['entity' => 'allUsers', 'role' => 'READER']]]);

            $publicUrl = 'https://storage.googleapis.com/' . config('firebase.storage.bucket') . '/' . $firebasePath;

            return ['success' => true, 'error' => null, 'url' => $publicUrl, 'expires' => 'never'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to create permanent URL: ' . $e->getMessage(), 'url' => null];
        }
    }

    /**
     * Upload a video from a URL to Firebase Storage.
     *
     * @param  string      $videoUrl  Source video URL to download
     * @param  string|null $fileName  Custom filename (without extension)
     * @param  string|null $folder    Target Firebase Storage folder
     */
    public static function uploadVideoToFirebase(string $videoUrl, ?string $fileName = null, ?string $folder = null): array
    {
        try {
            if (empty($videoUrl)) {
                return ['success' => false, 'error' => 'Video URL cannot be empty', 'firebase_url' => null];
            }

            $storage = app('firebase.storage');
            $bucket  = $storage->getBucket(config('firebase.storage.bucket'));

            $fileName   = $fileName ?: ('video_' . time() . '_' . rand(1000, 9999));
            $folderPath = $folder   ?: config('firebase.storage.default_folder', 'movies');

            $folderResult = static::ensureFirebaseFolder($folderPath);
            if (!$folderResult['success']) {
                return ['success' => false, 'error' => 'Failed to ensure folder: ' . $folderResult['error'], 'firebase_url' => null];
            }

            $firebasePath = $folderPath . '/' . $fileName . '.mp4';
            $tempFile     = tempnam(sys_get_temp_dir(), 'firebase_video_');
            $fp           = fopen($tempFile, 'w+');

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $videoUrl,
                CURLOPT_FILE           => $fp,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT        => 600,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; Laravel Firebase Uploader)',
            ]);
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            fclose($fp);

            if (!empty($curlError)) {
                @unlink($tempFile);
                return ['success' => false, 'error' => 'Failed to download video: ' . $curlError, 'firebase_url' => null];
            }

            if ($httpCode !== 200) {
                @unlink($tempFile);
                return ['success' => false, 'error' => 'Failed to download video. HTTP ' . $httpCode, 'firebase_url' => null];
            }

            $actualFileSize = filesize($tempFile);
            $fileStream     = fopen($tempFile, 'r');

            if (!$fileStream) {
                @unlink($tempFile);
                return ['success' => false, 'error' => 'Failed to open temp file for upload', 'firebase_url' => null];
            }

            $object = $bucket->upload($fileStream, [
                'name'     => $firebasePath,
                'metadata' => [
                    'contentType' => 'video/mp4',
                    'metadata'    => [
                        'uploaded_at'  => now()->toISOString(),
                        'original_url' => $videoUrl,
                        'uploaded_by'  => 'laravel_app',
                    ],
                ],
            ]);

            if (is_resource($fileStream)) {
                fclose($fileStream);
            }
            @unlink($tempFile);

            $downloadUrl = $object->signedUrl(new \DateTime('+1 year'));

            return [
                'success'       => true,
                'error'         => null,
                'firebase_url'  => $downloadUrl,
                'firebase_path' => $firebasePath,
                'file_size'     => $actualFileSize,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Firebase upload failed: ' . $e->getMessage(), 'firebase_url' => null];
        }
    }

    /**
     * Get a time-limited signed download URL for a Firebase Storage file.
     */
    public static function getFirebaseDownloadUrl(string $firebasePath, int $expirationHours = 24): array
    {
        try {
            $storage = app('firebase.storage');
            $bucket  = $storage->getBucket(config('firebase.storage.bucket'));
            $object  = $bucket->object($firebasePath);

            if (!$object->exists()) {
                return ['success' => false, 'error' => 'File not found in Firebase Storage', 'url' => null];
            }

            $expirationTime = new \DateTime('+' . $expirationHours . ' hours');
            $downloadUrl    = $object->signedUrl($expirationTime);

            return [
                'success'    => true,
                'error'      => null,
                'url'        => $downloadUrl,
                'expires_at' => $expirationTime->format('Y-m-d H:i:s'),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to generate download URL: ' . $e->getMessage(), 'url' => null];
        }
    }

    /**
     * Delete a video file from Firebase Storage.
     */
    public static function deleteFirebaseVideo(string $firebasePath): array
    {
        try {
            $storage = app('firebase.storage');
            $bucket  = $storage->getBucket(config('firebase.storage.bucket'));
            $object  = $bucket->object($firebasePath);

            if (!$object->exists()) {
                return ['success' => false, 'error' => 'File not found in Firebase Storage'];
            }

            $object->delete();

            return ['success' => true, 'error' => null, 'message' => 'File deleted successfully'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to delete file: ' . $e->getMessage()];
        }
    }
}
