<?php

require_once 'vendor/autoload.php';
require_once 'bootstrap/app.php';

$app = \Illuminate\Foundation\Application::getInstance();
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Firebase Storage Permanent URL Options ===\n";

try {
    $firebasePath = 'movies/big_buck_bunny_test.mp4';
    
    $storage = app('firebase.storage');
    $bucket = $storage->getBucket(config('firebase.storage.bucket'));
    $object = $bucket->object($firebasePath);
    
    echo "🔗 Testing different URL approaches for: $firebasePath\n\n";
    
    // Option 1: Very Long Expiration (Max allowed)
    echo "1️⃣ MAXIMUM EXPIRATION SIGNED URL\n";
    try {
        // Firebase allows up to 7 days for signed URLs, but we can use a very far future date
        $maxExpiry = new DateTime('+10 years'); // Try 10 years
        $longUrl = $object->signedUrl($maxExpiry);
        echo "✅ 10-year expiry URL: " . substr($longUrl, 0, 80) . "...\n";
        echo "   Expires: " . $maxExpiry->format('Y-m-d H:i:s') . "\n";
    } catch (\Exception $e) {
        echo "❌ 10-year failed, trying 7 days: " . $e->getMessage() . "\n";
        
        // Try 7 days (Google's recommended max)
        $sevenDays = new DateTime('+7 days');
        $weekUrl = $object->signedUrl($sevenDays);
        echo "✅ 7-day expiry URL: " . substr($weekUrl, 0, 80) . "...\n";
        echo "   Expires: " . $sevenDays->format('Y-m-d H:i:s') . "\n";
    }
    
    // Option 2: Public Access URL (if possible)
    echo "\n2️⃣ PUBLIC ACCESS URL\n";
    echo "ℹ️  Firebase Storage files are private by default.\n";
    echo "   To make them public, you need to:\n";
    echo "   1. Update Firebase Storage Rules\n";
    echo "   2. Or make the object public via ACL\n\n";
    
    try {
        // Try to make the object public
        echo "🧪 Attempting to make object public...\n";
        
        // This would make the object publicly readable
        $object->update([
            'acl' => [
                [
                    'entity' => 'allUsers',
                    'role' => 'READER'
                ]
            ]
        ]);
        
        // Generate public URL
        $publicUrl = "https://storage.googleapis.com/" . config('firebase.storage.bucket') . "/" . $firebasePath;
        echo "✅ Public URL created: $publicUrl\n";
        echo "   This URL never expires! 🎉\n";
        
        // Test if it's accessible
        $headers = get_headers($publicUrl, 1);
        if ($headers && strpos($headers[0], '200') !== false) {
            echo "✅ Public URL is accessible without authentication!\n";
        } else {
            echo "⚠️  Public URL might need Firebase Storage Rules update\n";
        }
        
    } catch (\Exception $e) {
        echo "❌ Making object public failed: " . $e->getMessage() . "\n";
        echo "   This requires Firebase Storage Rules configuration.\n\n";
    }
    
    // Option 3: Firebase Storage Rules Approach
    echo "3️⃣ FIREBASE STORAGE RULES APPROACH\n";
    echo "📋 To create permanent public URLs:\n";
    echo "\n1. Go to Firebase Console -> Storage -> Rules\n";
    echo "2. Update rules to allow public read:\n\n";
    echo "rules_version = '2';\n";
    echo "service firebase.storage {\n";
    echo "  match /b/{bucket}/o {\n";
    echo "    // Make movies folder publicly readable\n";
    echo "    match /movies/{filename} {\n";
    echo "      allow read; // Anyone can read\n";
    echo "      allow write: if request.auth != null; // Only authenticated users can write\n";
    echo "    }\n";
    echo "  }\n";
    echo "}\n\n";
    
    // Option 4: Laravel Proxy URL (Recommended)
    echo "4️⃣ LARAVEL PROXY APPROACH (RECOMMENDED)\n";
    echo "📋 Create a Laravel route that generates fresh URLs on demand:\n\n";
    
    $proxyExample = <<<'PHP'
// In routes/web.php or routes/api.php
Route::get('/video/{filename}', function ($filename) {
    $firebasePath = "movies/{$filename}";
    
    // Generate fresh signed URL (24 hours)
    $result = Utils::getFirebaseDownloadUrl($firebasePath, 24);
    
    if ($result['success']) {
        // Redirect to Firebase URL
        return redirect($result['url']);
    }
    
    return abort(404, 'Video not found');
})->name('video.stream');

// Usage in Blade templates:
// <video src="{{ route('video.stream', ['filename' => 'big_buck_bunny_test.mp4']) }}" controls>
PHP;
    
    echo $proxyExample . "\n\n";
    
    // Option 5: Download Token Approach
    echo "5️⃣ DOWNLOAD TOKEN APPROACH\n";
    echo "📋 Firebase can generate permanent download tokens:\n";
    
    try {
        // Get object metadata to see if download token exists
        $metadata = $object->info();
        
        if (isset($metadata['metadata']['firebaseStorageDownloadTokens'])) {
            $downloadToken = $metadata['metadata']['firebaseStorageDownloadTokens'];
            $tokenUrl = "https://firebasestorage.googleapis.com/v0/b/" . 
                       config('firebase.storage.bucket') . "/o/" . 
                       urlencode($firebasePath) . "?alt=media&token=" . $downloadToken;
            
            echo "✅ Download token URL found:\n";
            echo "   $tokenUrl\n";
            echo "   This URL never expires! 🎉\n";
            
            // Test the token URL
            $headers = get_headers($tokenUrl, 1);
            if ($headers && strpos($headers[0], '200') !== false) {
                echo "✅ Download token URL is accessible!\n";
            }
        } else {
            echo "ℹ️  No download token found. Upload via Firebase Admin SDK to get tokens.\n";
        }
        
    } catch (\Exception $e) {
        echo "❌ Download token check failed: " . $e->getMessage() . "\n";
    }

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== RECOMMENDATIONS ===\n";
echo "🥇 BEST OPTION: Laravel Proxy URLs\n";
echo "   - Always work\n";
echo "   - No expiration from user perspective\n";
echo "   - You control access\n";
echo "   - Fresh Firebase URLs generated on demand\n\n";

echo "🥈 ALTERNATIVE: Firebase Storage Rules + Public URLs\n";
echo "   - Truly permanent URLs\n";
echo "   - Requires Firebase Console configuration\n";
echo "   - Files become publicly accessible\n\n";

echo "=== Permanent URL Test Complete ===\n";