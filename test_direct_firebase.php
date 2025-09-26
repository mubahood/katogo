<?php

require_once 'vendor/autoload.php';

echo "=== Direct Firebase Upload Test ===\n";
echo "Testing direct Firebase connection without Laravel service provider\n\n";

$videoUrl = 'http://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4';

try {
    // Create Firebase factory directly
    $factory = (new \Kreait\Firebase\Factory)
        ->withServiceAccount(storage_path('app/firebase-credentials.json'))
        ->withProjectId('ugflix-71aa8');

    echo "✅ Firebase factory created\n";

    // Get storage service
    $storage = $factory->createStorage();
    echo "✅ Storage service created\n";

    // Try to get the default bucket
    $bucket = $storage->getBucket();
    echo "✅ Default bucket accessed: " . $bucket->name() . "\n";

    // Create a temporary file for streaming download
    echo "📥 Downloading video...\n";
    $tempFile = tempnam(sys_get_temp_dir(), 'firebase_video_');
    $fp = fopen($tempFile, 'w+');

    // Download video content to temporary file (streaming)
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $videoUrl);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 600);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; Laravel Firebase Uploader)');
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    fclose($fp);

    if ($result === false || !empty($error) || $httpCode !== 200) {
        echo "❌ Download failed: HTTP $httpCode, Error: $error\n";
        unlink($tempFile);
        exit;
    }

    $fileSize = filesize($tempFile);
    echo "✅ Video downloaded: " . round($fileSize / 1024 / 1024, 2) . " MB\n";

    // Upload to Firebase Storage
    echo "📤 Uploading to Firebase Storage...\n";
    $firebasePath = 'test_videos/big_buck_bunny_direct_' . time() . '.mp4';
    
    $fileStream = fopen($tempFile, 'r');
    $object = $bucket->upload($fileStream, [
        'name' => $firebasePath,
        'metadata' => [
            'contentType' => 'video/mp4',
        ]
    ]);
    fclose($fileStream);

    echo "✅ Upload successful!\n";
    echo "📁 Firebase Path: $firebasePath\n";

    // Generate download URL
    $downloadUrl = $object->signedUrl(new \DateTime('+1 hour'));
    echo "🔗 Download URL: $downloadUrl\n";

    // Clean up
    unlink($tempFile);

    echo "\n🎉 SUCCESS! Video successfully uploaded to Firebase Storage!\n";

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    if (isset($tempFile) && file_exists($tempFile)) {
        unlink($tempFile);
    }
}

function storage_path($path = '') {
    return __DIR__ . '/storage/' . ltrim($path, '/');
}

echo "\n=== Test Complete ===\n";