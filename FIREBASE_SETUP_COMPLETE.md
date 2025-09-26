# Firebase Storage Integration - Complete Setup Guide

## Current Status ✅

Your Firebase Storage integration is **successfully configured** but needs permissions setup to work with your Google Cloud Storage bucket.

### What's Working:
- ✅ Firebase PHP SDK installed
- ✅ Service account credentials properly configured
- ✅ Laravel Firebase service provider setup
- ✅ Bucket connection established (ugflix-71aa8)
- ✅ Upload utility methods created in Utils.php

### What Needs Permissions:
- ❌ Service account needs Storage Object Admin permissions

## Quick Fix - Grant Storage Permissions

You need to grant your Firebase service account permissions to access your Google Cloud Storage bucket. Here are two approaches:

### Option 1: Use Firebase Default Bucket (Recommended)

1. **Update your .env to use the Firebase default bucket:**
   ```
   FIREBASE_STORAGE_BUCKET=ugflix-71aa8.appspot.com
   ```

2. **The Firebase default bucket should work immediately** because Firebase service accounts have automatic access to their default bucket.

### Option 2: Grant Permissions to Custom Bucket

If you want to keep using `ugflix-71aa8` (your custom bucket):

1. **Go to Google Cloud Console:** https://console.cloud.google.com
2. **Navigate to:** IAM & Admin > IAM
3. **Find your service account:** `firebase-adminsdk-fbsvc@ugflix-71aa8.iam.gserviceaccount.com`
4. **Click Edit (pencil icon)**
5. **Add these roles:**
   - Storage Object Admin
   - Storage Legacy Bucket Reader
6. **Save changes**

Alternatively, use the gcloud command:
```bash
gcloud projects add-iam-policy-binding ugflix-71aa8 \
  --member="serviceAccount:firebase-adminsdk-fbsvc@ugflix-71aa8.iam.gserviceaccount.com" \
  --role="roles/storage.objectAdmin"
```

## Test Your Setup

After configuring permissions, test with this command:
```bash
cd /Applications/MAMP/htdocs/katogo
php test_firebase_upload.php
```

## Using Firebase Storage in Your App

### Upload a Video File
```php
use App\Models\Utils;

// Upload video from URL
$result = Utils::uploadVideoToFirebase($videoUrl, 'my_movie', 'movies');

if ($result['success']) {
    echo "Video uploaded! URL: " . $result['firebase_url'];
    
    // Save to database
    $movie = new MovieModel();
    $movie->firebase_url = $result['firebase_url'];
    $movie->firebase_path = $result['firebase_path'];
    $movie->save();
} else {
    echo "Upload failed: " . $result['error'];
}
```

### Get Download URL
```php
// Generate a new download URL (expires in 24 hours)
$result = Utils::getFirebaseDownloadUrl($movie->firebase_path, 24);

if ($result['success']) {
    echo "Download URL: " . $result['url'];
}
```

### Delete Video
```php
// Delete video from Firebase Storage
$result = Utils::deleteFirebaseVideo($movie->firebase_path);

if ($result['success']) {
    echo "Video deleted successfully";
}
```

## Example Integration with Movie Upload

```php
// In your movie controller or service
public function uploadMovieToFirebase($movieId)
{
    $movie = MovieModel::find($movieId);
    
    if (!$movie || !$movie->external_url) {
        return ['success' => false, 'message' => 'Movie not found'];
    }
    
    // Upload to Firebase
    $result = Utils::uploadVideoToFirebase(
        $movie->external_url, 
        'movie_' . $movie->id, 
        'movies'
    );
    
    if ($result['success']) {
        // Update movie record
        $movie->firebase_url = $result['firebase_url'];
        $movie->firebase_path = $result['firebase_path'];
        $movie->uploaded_to_firebase = 'Yes';
        $movie->save();
        
        return ['success' => true, 'message' => 'Movie uploaded to Firebase'];
    }
    
    return ['success' => false, 'message' => $result['error']];
}
```

## Next Steps

1. **Choose Option 1 or 2 above** to fix permissions
2. **Test the upload functionality** with the test script
3. **Integrate Firebase uploads** into your movie management workflow
4. **Consider adding progress tracking** for large video uploads
5. **Set up CDN/download URL management** for video streaming

## Configuration Files Modified

- ✅ `composer.json` - Added Firebase SDK
- ✅ `config/firebase.php` - Firebase configuration
- ✅ `app/Providers/FirebaseServiceProvider.php` - Service provider
- ✅ `.env` - Environment variables
- ✅ `config/app.php` - Registered service provider
- ✅ `app/Models/Utils.php` - Upload utility methods

Your Firebase Storage integration is ready to use once the permissions are configured! 🚀