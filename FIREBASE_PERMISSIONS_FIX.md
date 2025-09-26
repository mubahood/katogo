# Firebase Storage Permissions Fix Guide

## Issue Identified ❌
Your Firebase service account can **read bucket information** but **cannot write/upload** to Firebase Storage. This is a **permissions issue**.

## Current Status
- ✅ Firebase SDK properly configured
- ✅ Service account credentials working
- ✅ Can connect to Firebase project
- ✅ Can read bucket metadata
- ❌ **Cannot upload files (404 "bucket does not exist")**

## Solution: Grant Storage Permissions

### Method 1: Google Cloud Console (Recommended)

1. **Open Google Cloud Console**
   - Go to: https://console.cloud.google.com
   - Select project: `ugflix-71aa8`

2. **Navigate to IAM & Admin**
   - Left menu → IAM & Admin → IAM

3. **Find Your Service Account**
   - Look for: `firebase-adminsdk-fbsvc@ugflix-71aa8.iam.gserviceaccount.com`
   - Click the **pencil icon** (Edit) next to it

4. **Add Storage Role**
   - Click **"ADD ANOTHER ROLE"**
   - Search for: `Storage Admin` or `Storage Object Admin`
   - Select: **Storage Object Admin**
   - Click **SAVE**

### Method 2: Command Line (Alternative)

If you have `gcloud` CLI installed:

```bash
gcloud projects add-iam-policy-binding ugflix-71aa8 \
  --member="serviceAccount:firebase-adminsdk-fbsvc@ugflix-71aa8.iam.gserviceaccount.com" \
  --role="roles/storage.objectAdmin"
```

### Method 3: Create Firebase Storage Rules (If Method 1 doesn't work)

1. **Go to Firebase Console**
   - https://console.firebase.google.com
   - Select: ugflix-71aa8 project

2. **Go to Storage**
   - Left menu → Storage

3. **Initialize Storage** (if not done already)
   - Click "Get Started"
   - Choose "Start in production mode"
   - Select your region (closest to Uganda: europe-west3 or asia-south1)

4. **Update Rules**
   - Go to Rules tab
   - Replace with:
   ```
   rules_version = '2';
   service firebase.storage {
     match /b/{bucket}/o {
       match /{allPaths=**} {
         allow read, write: if true; // Allow all for testing
       }
     }
   }
   ```

## Test After Permissions

Once you've granted permissions, test with:

```bash
cd /Applications/MAMP/htdocs/katogo
php test_bucket_permissions.php
```

Expected output:
```
✅ Small file upload successful!
✅ Download test successful!
✅ Test file deleted
🎉 Bucket permissions are working! Ready for video upload.
```

## Then Test Video Upload

After permissions are working, test the BigBuckBunny upload:

```bash
php test_direct_firebase.php
```

## Your Video Upload Function

Once working, you can use the Utils class:

```php
$result = \App\Models\Utils::uploadVideoToFirebase(
    'http://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
    'big_buck_bunny',
    'videos'
);

if ($result['success']) {
    echo "Video uploaded! Firebase URL: " . $result['firebase_url'];
} else {
    echo "Upload failed: " . $result['error'];
}
```

## Next Steps

1. **Fix permissions** using Method 1, 2, or 3 above
2. **Test small file upload** with `test_bucket_permissions.php`
3. **Test video upload** with `test_direct_firebase.php`
4. **Start using Firebase Storage** for your movie uploads! 🎬

The technical integration is complete - only permissions need to be fixed! 🚀