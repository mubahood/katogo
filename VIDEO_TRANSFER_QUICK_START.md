# 🚀 Quick Start: Google Drive Video Transfer

## ⚡ 5-Minute Setup

### 1️⃣ Run Migration (30 seconds)
```bash
cd /Applications/MAMP/htdocs/katogo
php artisan migrate
```

### 2️⃣ Get Google Drive Credentials (3 minutes)

**Quick OAuth Playground Method:**
1. Visit: https://developers.google.com/oauthplayground/
2. Click ⚙️ → Check "Use your own OAuth credentials"
3. Enter Client ID/Secret from Google Cloud Console
4. Select: `https://www.googleapis.com/auth/drive.file`
5. Authorize → Get Refresh Token

### 3️⃣ Update .env (1 minute)
```bash
GOOGLE_DRIVE_CLIENT_ID=your-client-id
GOOGLE_DRIVE_CLIENT_SECRET=your-secret
GOOGLE_DRIVE_REFRESH_TOKEN=your-token
GOOGLE_DRIVE_FOLDER_ID=optional-folder-id
```

### 4️⃣ Access Admin (30 seconds)
```
http://your-domain.com/admin/video-transfers
```

---

## 🎯 Common Tasks

### Create New Transfer
1. Click "New" button
2. Paste video URL
3. Add title (optional)
4. Submit → Auto-processing starts!

### Retry Failed Transfer
1. Find failed transfer in list
2. Click yellow "Retry" button
3. Done!

### Get Video URL for App
1. Open completed transfer
2. Copy "Embed URL" field
3. Use in your video player:
   ```dart
   VideoPlayerController.network(embedUrl)
   ```

### Check Transfer Status
- **Green "COMPLETED"** = Ready to use ✅
- **Blue "DOWNLOADING"** = In progress... ⏳
- **Red "FAILED"** = Click retry 🔄

---

## 📱 Use in Flutter App

```dart
// Simple example
VideoPlayerController.network(
  'https://drive.google.com/uc?export=view&id=FILE_ID'
)
```

---

## 🔧 Troubleshooting

### Can't create transfer?
- Check .env credentials ✅
- Verify Google Drive API enabled ✅
- Check logs: `tail -f storage/logs/laravel.log`

### Transfer stuck?
- Click "Cancel" and retry
- Check internet connection
- Verify source URL is valid

### Video won't play?
- Use `embed_url` not `drive_public_url`
- Check video is marked "completed"
- Test URL in browser first

---

## 💡 Pro Tips

- ✨ Videos process automatically on creation
- 🎯 Use embed_url for best app compatibility
- 📊 Monitor statistics dashboard for insights
- 🔄 Failed transfers can be retried unlimited times
- 📝 Add notes for team collaboration

---

## 🎬 That's It!

You're ready to transfer videos to Google Drive! 🚀

**Full documentation:** See `GOOGLE_DRIVE_VIDEO_TRANSFER_GUIDE.md`
