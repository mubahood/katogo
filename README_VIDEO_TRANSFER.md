# 🎬 Google Drive Video Transfer System

> **Automatically transfer videos from any URL to Google Drive with full progress tracking and admin management**

[![Laravel](https://img.shields.io/badge/Laravel-10.x-red.svg)](https://laravel.com)
[![Google Drive API](https://img.shields.io/badge/Google%20Drive-API%20v3-blue.svg)](https://developers.google.com/drive)
[![Status](https://img.shields.io/badge/Status-Production%20Ready-success.svg)]()

---

## 🌟 Features at a Glance

- ✅ **One-Click Transfer** - Paste URL and go
- ✅ **Automatic Processing** - Downloads, uploads, makes public
- ✅ **Progress Tracking** - Real-time 0-100% progress
- ✅ **Error Handling** - Auto-capture with retry
- ✅ **Beautiful Admin UI** - Laravel Admin integration
- ✅ **App Ready** - Direct streaming URLs for mobile
- ✅ **Self-Contained** - All logic in one model

---

## 🚀 Quick Start

### 1. Install (2 minutes)

```bash
# Run migration
php artisan migrate

# Make test script executable
chmod +x test-video-transfer.sh

# Run test script
./test-video-transfer.sh
```

### 2. Configure (3 minutes)

Add to `.env`:

```bash
GOOGLE_DRIVE_CLIENT_ID=your-client-id
GOOGLE_DRIVE_CLIENT_SECRET=your-secret
GOOGLE_DRIVE_REFRESH_TOKEN=your-token
GOOGLE_DRIVE_FOLDER_ID=optional-folder
```

[Get credentials →](https://console.cloud.google.com/)

### 3. Use It! (30 seconds)

1. Visit: `http://your-domain.com/admin/video-transfers`
2. Click "New"
3. Paste video URL
4. Submit → Done! ✨

---

## 📱 Mobile App Integration

```dart
// Flutter example
VideoPlayerController.network(
  'https://drive.google.com/uc?export=view&id=FILE_ID'
)
```

[See full integration examples →](VIDEO_TRANSFER_API_INTEGRATION.md)

---

## 📚 Documentation

| Document | Description |
|----------|-------------|
| [**Complete Guide**](GOOGLE_DRIVE_VIDEO_TRANSFER_GUIDE.md) | Full setup, usage, troubleshooting |
| [**Quick Start**](VIDEO_TRANSFER_QUICK_START.md) | 5-minute setup guide |
| [**API Integration**](VIDEO_TRANSFER_API_INTEGRATION.md) | Flutter examples, REST API |
| [**System Summary**](VIDEO_TRANSFER_SYSTEM_SUMMARY.md) | Architecture overview |

---

## 🎯 How It Works

```
User Creates Transfer
        ↓
Automatic Download (0-50%)
        ↓
Upload to Drive (50-100%)
        ↓
Make Public & Generate URLs
        ↓
Ready to Play! ✅
```

---

## 📁 What's Included

```
database/migrations/
  └── 2025_10_19_000001_create_video_transfers_table.php  ← Database schema

app/Models/
  └── VideoTransfer.php  ← All transfer logic (650 lines)

app/Admin/Controllers/
  └── VideoTransferController.php  ← Admin interface (450 lines)

resources/views/admin/video-transfer/
  └── stats.blade.php  ← Statistics dashboard

app/Admin/
  └── routes.php  ← Routes (updated)

.env.example  ← Configuration template

Documentation/
  ├── GOOGLE_DRIVE_VIDEO_TRANSFER_GUIDE.md
  ├── VIDEO_TRANSFER_QUICK_START.md
  ├── VIDEO_TRANSFER_API_INTEGRATION.md
  └── VIDEO_TRANSFER_SYSTEM_SUMMARY.md

test-video-transfer.sh  ← Test script
```

---

## 🎨 Admin Panel Preview

### Dashboard
```
┌─────────────────────────────────────────┐
│  📊 Statistics                          │
│  Total: 100 | Completed: 85 | Active: 5│
├─────────────────────────────────────────┤
│  🎬 Video Transfers                     │
│  ┌───────────────────────────────────┐  │
│  │ ID  Title     Status    Progress  │  │
│  │ 1   Movie 1   ✅ DONE   [████] 100%│  │
│  │ 2   Movie 2   ⏳ UPLOD  [███░]  75%│  │
│  │ 3   Movie 3   ❌ FAIL   [░░░░]   0%│  │
│  └───────────────────────────────────┘  │
│  [New] [Filter] [Search]                │
└─────────────────────────────────────────┘
```

---

## 💡 Key Features

### Automatic Everything
- Auto-download from URL
- Auto-upload to Drive
- Auto-make public
- Auto-generate URLs
- Auto-cleanup temp files

### Smart Error Handling
- Captures all errors
- Stores error details
- One-click retry
- Unlimited retries

### Progress Tracking
- Real-time percentage
- Bytes transferred
- Transfer speed
- Time elapsed

### Admin Tools
- Status filtering
- Search by title/URL
- Play videos directly
- Cancel active transfers
- Detailed view

---

## 🔧 Technical Details

### Requirements
- PHP 8.1+
- Laravel 10.x
- MySQL/PostgreSQL
- Google Drive API access
- Internet connection

### API Used
- Google Drive API v3
- OAuth 2.0 authentication
- Refresh token flow

### Transfer Process
1. Validate credentials
2. Download via HTTP streaming
3. Upload via multipart upload
4. Set public permissions
5. Generate URLs
6. Cleanup

---

## 🎯 Use Cases

✅ Movie/video hosting  
✅ Content migration  
✅ Video backup system  
✅ Educational content  
✅ Course videos  
✅ App video hosting  

---

## 📊 Status Options

| Status | Description | Progress | Actions |
|--------|-------------|----------|---------|
| `pending` | Waiting to start | 0% | Cancel |
| `downloading` | Downloading from source | 0-50% | Cancel |
| `uploading` | Uploading to Drive | 50-100% | Cancel |
| `completed` | Successfully transferred | 100% | Play |
| `failed` | Transfer failed | 0-100% | Retry |
| `cancelled` | User cancelled | 0-100% | - |

---

## 🔐 Security

- ✅ Credentials in .env only
- ✅ No password storage
- ✅ OAuth refresh tokens
- ✅ Admin-only access
- ✅ Input validation
- ✅ Error logging

---

## 🆘 Troubleshooting

### Common Issues

**"Missing configuration"**
→ Add Google Drive credentials to `.env`

**Transfer stuck**
→ Cancel and retry, or check logs

**Video won't play**
→ Use `embed_url` field, not `public_url`

**High memory usage**
→ Files are streamed, not loaded to memory

### Debug

```bash
# Check logs
tail -f storage/logs/laravel.log

# Test credentials
php artisan tinker
>>> VideoTransfer::first()->processTransfer()
```

---

## 📈 Performance

- **Streaming**: Files streamed, not loaded to memory
- **Async Ready**: Can use Laravel Queue
- **Indexed**: Database properly indexed
- **Cached**: Config values cached
- **Optimized**: Minimal database writes

---

## 🎉 Benefits

### For You
- ✅ No bandwidth costs
- ✅ Unlimited storage (Google Drive)
- ✅ Fast delivery (Google CDN)
- ✅ Reliable hosting
- ✅ Easy management

### For Users
- ✅ Fast playback
- ✅ No buffering
- ✅ High quality
- ✅ Always available

---

## 📝 Example Usage

### Create Transfer

```php
VideoTransfer::create([
    'source_url' => 'https://example.com/video.mp4',
    'video_title' => 'My Awesome Video',
    'video_description' => 'This is amazing!',
]);
// → Automatically processes!
```

### Check Status

```php
$transfer = VideoTransfer::find(1);
echo $transfer->status; // "completed"
echo $transfer->progress; // 100
echo $transfer->embed_url; // Ready for app!
```

### Retry Failed

```php
$transfer = VideoTransfer::find(1);
$transfer->retry(); // Try again!
```

---

## 🌐 API Endpoints (Optional)

Add to `routes/api.php`:

```php
Route::get('/videos/completed', function() {
    return VideoTransfer::completed()->get();
});

Route::get('/videos/{id}', function($id) {
    return VideoTransfer::findOrFail($id);
});
```

[See full API examples →](VIDEO_TRANSFER_API_INTEGRATION.md)

---

## 🔄 Updates & Maintenance

### Check for updates
```bash
git pull origin main
php artisan migrate
php artisan cache:clear
```

### Backup
```bash
php artisan backup:run
mysqldump database_name video_transfers > backup.sql
```

---

## 💬 Support

### Need Help?

1. Check documentation files
2. Review code comments
3. Check Laravel logs
4. Test with small video first

### Found a Bug?

1. Check logs: `storage/logs/laravel.log`
2. Verify credentials in `.env`
3. Test Google Drive API access
4. Review error message in admin

---

## 📜 License

This code is provided as-is for the UGFLIX/Katogo project.

---

## 🙏 Credits

- **Laravel**: Web framework
- **Google Drive API**: Video hosting
- **Laravel Admin**: Admin interface
- **Created**: October 2025
- **Version**: 1.0

---

## ✨ Final Words

This system is:
- ✅ **Complete** - Everything included
- ✅ **Simple** - Easy to use
- ✅ **Powerful** - Production-ready
- ✅ **Creative** - Smart automation
- ✅ **Documented** - Thoroughly explained

**Ready to transfer videos to Google Drive!** 🚀

---

**Quick Links:**
- [Complete Guide](GOOGLE_DRIVE_VIDEO_TRANSFER_GUIDE.md)
- [Quick Start](VIDEO_TRANSFER_QUICK_START.md)
- [API Integration](VIDEO_TRANSFER_API_INTEGRATION.md)
- [System Summary](VIDEO_TRANSFER_SYSTEM_SUMMARY.md)

**Admin Panel:** `/admin/video-transfers`

---

Made with ❤️ for UGFLIX
