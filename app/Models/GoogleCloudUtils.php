<?php

namespace App\Models;

use Google\Cloud\Storage\StorageClient;

class GoogleCloudUtils
{
    /**
     * Upload video to Google Cloud Storage (alternative to Firebase Storage)
     */
    public static function uploadVideoToGoogleCloud($videoUrl, $fileName = null, $folder = null)
    {
        try {
            // Initialize Google Cloud Storage
            $storage = new StorageClient([
                'keyFilePath' => storage_path('app/firebase-credentials.json'),
                'projectId' => 'ugflix-71aa8'
            ]);

            $bucket = $storage->bucket('ugflix-71aa8');

            // Generate filename
            if (!$fileName) {
                $fileName = 'video_' . time() . '_' . rand(1000, 9999);
            }

            // Set folder path
            $folderPath = $folder ?: 'movies';
            $objectName = $folderPath . '/' . $fileName . '.mp4';

            // Download video content
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $videoUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300);
            
            $videoContent = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                return [
                    'success' => false,
                    'error' => 'Failed to download video. HTTP code: ' . $httpCode
                ];
            }

            // Upload to Google Cloud Storage
            $object = $bucket->upload($videoContent, [
                'name' => $objectName,
                'metadata' => [
                    'contentType' => 'video/mp4'
                ]
            ]);

            // Generate public URL
            $publicUrl = sprintf(
                'https://storage.googleapis.com/%s/%s',
                'ugflix-71aa8',
                $objectName
            );

            return [
                'success' => true,
                'error' => null,
                'firebase_url' => $publicUrl,
                'firebase_path' => $objectName,
                'file_size' => strlen($videoContent)
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Upload failed: ' . $e->getMessage()
            ];
        }
    }
}