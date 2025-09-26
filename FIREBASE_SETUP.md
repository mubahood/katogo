# Firebase Setup Instructions

## 🎉 **Firebase Integration Status: READY!**

✅ Firebase PHP SDK installed successfully  
✅ Configuration files created  
✅ Service provider registered  
✅ Upload methods created  
✅ Test routes added  

## **Final Steps to Complete Setup:**

### 1. Get Your Firebase Service Account Key

1. Go to [Firebase Console](https://console.firebase.google.com/)
2. Select your project: **ugflix-71aa8**
3. Go to **Project Settings** (gear icon) → **Service Accounts** tab
4. Click **"Generate new private key"**
5. Download the JSON file
6. Rename it to `firebase-credentials.json`
7. Place it in `storage/app/firebase-credentials.json`

### 2. Update Your .env File

Copy your `.env.example` to `.env` (if you haven't already) and add:

```bash
# Firebase Configuration (Use these exact values)
FIREBASE_PROJECT_ID=ugflix-71aa8
FIREBASE_CREDENTIALS_PATH=storage/app/firebase-credentials.json
FIREBASE_STORAGE_BUCKET=ugflix-71aa8
```

### 3. Set Bucket Permissions (Important!)

Since Firebase Storage setup failed in console, you need to set permissions manually:

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Select project **ugflix-71aa8** 
3. Go to **Cloud Storage** → **Buckets** → **ugflix-71aa8**
4. Go to **Permissions** tab
5. Add your service account email (from the JSON file) with **Storage Admin** role

### 4. Test the Setup

Once you've completed steps 1-3, test your setup:

1. **Test connection**: Visit `http://your-domain.com/test-firebase-connection`
2. **Test upload**: Visit `http://your-domain.com/test-firebase-upload`

If both tests pass, you're ready to use Firebase Storage!

## 3. Firebase Storage Setup

1. In Firebase Console, go to Storage
2. Click "Get Started"
3. Choose your security rules (start in test mode for development)
4. Select a storage location near your users

## 4. Install Dependencies

Run this command to install the Firebase PHP SDK:

```bash
composer install
```

## 5. Usage Example

```php
// Upload a video to Firebase
$result = Utils::uploadVideoToFirebase(
    'https://example.com/video.mp4',  // Video URL
    'my_movie_2024',                  // Optional: Custom filename
    'movies/action'                   // Optional: Custom folder
);

if ($result['success']) {
    echo "Video uploaded successfully!";
    echo "Firebase URL: " . $result['firebase_url'];
    echo "Firebase Path: " . $result['firebase_path'];
    echo "File Size: " . $result['file_size'] . " bytes";
} else {
    echo "Upload failed: " . $result['error'];
}
```

## 6. Security Rules (Production)

For production, update your Firebase Storage security rules:

```javascript
rules_version = '2';
service firebase.storage {
  match /b/{bucket}/o {
    match /movies/{movieId} {
      // Allow read for authenticated users
      allow read: if request.auth != null;
      // Allow write for admin users only
      allow write: if request.auth != null && request.auth.token.admin == true;
    }
  }
}
```

## 7. Firebase Storage Folder Structure

The app will organize files like this:
```
your-bucket/
├── movies/
│   ├── video_1727347200_1234.mp4
│   ├── video_1727347300_5678.mp4
│   └── action/
│       └── custom_movie_name.mp4
└── thumbnails/
    ├── thumb_1727347200_1234.jpg
    └── thumb_1727347300_5678.jpg
```

## 8. Methods Available

- `Utils::uploadVideoToFirebase($url, $fileName, $folder)` - Upload video from URL
- `Utils::getFirebaseDownloadUrl($path, $hours)` - Get signed download URL
- `Utils::deleteFirebaseVideo($path)` - Delete video from Firebase

## 9. Error Handling

All methods return an array with:
- `success` (boolean)
- `error` (string|null) 
- Additional data based on the method

Always check the `success` field before using other returned values.