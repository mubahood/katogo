# 🎬 Google Drive Video Transfer System

## 📋 Overview

A complete Laravel-based system for automatically transferring videos from any URL to Google Drive with full progress tracking, status management, and public playback URLs.

---

## ✨ Features

### Core Capabilities
- ✅ **Automatic Transfer**: Videos are processed automatically upon creation
- ✅ **Progress Tracking**: Real-time progress updates (0-100%)
- ✅ **Status Management**: pending → downloading → uploading → completed
- ✅ **Error Handling**: Automatic error capture with retry functionality
- ✅ **Public URLs**: Generate public playable URLs for app integration
- ✅ **Metadata Tracking**: File size, duration, format, quality, speed
- ✅ **Admin Interface**: Beautiful Laravel Admin dashboard
- ✅ **Self-Contained Logic**: All logic in VideoTransfer model

### Admin Panel Features
- 📊 **Statistics Dashboard**: Total, completed, failed, active transfers
- 🎯 **Status Filters**: Filter by pending, downloading, uploading, completed, failed, cancelled
- 🔄 **Retry Failed Transfers**: One-click retry for failed transfers
- ▶️ **Preview Videos**: Play completed videos directly from admin
- ❌ **Cancel Active Transfers**: Stop ongoing transfers
- 📝 **Detailed View**: Complete information about each transfer
- 🔍 **Search & Filter**: Search by title, URL, date

---

## 🚀 Installation

### Step 1: Run Migration

```bash
cd /Applications/MAMP/htdocs/katogo
php artisan migrate
```

This creates the `video_transfers` table with all necessary fields.

### Step 2: Configure Google Drive API

#### 2.1 Create Google Cloud Project
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing one
3. Enable **Google Drive API** for your project

#### 2.2 Create OAuth 2.0 Credentials
1. Go to **APIs & Services** → **Credentials**
2. Click **Create Credentials** → **OAuth 2.0 Client ID**
3. Choose **Desktop app** as application type
4. Note down your **Client ID** and **Client Secret**

#### 2.3 Generate Refresh Token
1. Go to [OAuth 2.0 Playground](https://developers.google.com/oauthplayground/)
2. Click ⚙️ settings icon (top right)
3. Check "Use your own OAuth credentials"
4. Enter your Client ID and Client Secret
5. In Step 1: Select **Google Drive API v3** → `https://www.googleapis.com/auth/drive.file`
6. Click "Authorize APIs"
7. Sign in with your Google account
8. In Step 2: Click "Exchange authorization code for tokens"
9. Copy the **Refresh Token**

#### 2.4 Update .env File

Add these lines to your `.env` file:

```bash
# Google Drive API Configuration
GOOGLE_DRIVE_CLIENT_ID=123456789-abc123.apps.googleusercontent.com
GOOGLE_DRIVE_CLIENT_SECRET=GOCSPX-AbC123XyZ456
GOOGLE_DRIVE_REFRESH_TOKEN=1//0abcdefGHIJKLMNOPqrstuvwxyz
GOOGLE_DRIVE_FOLDER_ID=1A2B3C4D5E6F7G8H9I0J  # Optional - specific folder
```

### Step 3: Access Admin Panel

Navigate to:
```
http://your-domain.com/admin/video-transfers
```

---

## 📖 Usage

### Creating a New Transfer

1. **Go to Admin Panel**: `/admin/video-transfers`
2. **Click "New" button**
3. **Fill in the form**:
   - **Video Title**: Friendly name (optional)
   - **Source Video URL**: Direct link to video file (required)
   - **Video Description**: Description (optional)
   - **Source Type**: Direct URL, Streaming URL, etc.
   - **Video Duration**: e.g., "02:15:30" (optional)
   - **Video Quality**: e.g., "1080p" (optional)
   - **Video Format**: e.g., "mp4" (optional)
4. **Click "Submit"**

The system will automatically:
- Download the video from source URL
- Upload to Google Drive
- Make file public
- Generate playable URLs
- Track progress and status

### Transfer Status Flow

```
pending → downloading → uploading → completed
                ↓
            failed (can retry)
```

### Using Videos in Your App

Once transfer is completed, use the **Embed URL** in your mobile app:

```dart
// Example in Flutter
VideoPlayerController.network(
  'https://drive.google.com/uc?export=view&id=FILE_ID'
)
```

The `embed_url` field provides the direct streaming URL optimized for video players.

---

## 🎯 Model Methods

### Main Methods

```php
// Process transfer (called automatically on creation)
$transfer->processTransfer();

// Retry failed transfer
$transfer->retry();

// Cancel active transfer
$transfer->cancel();

// Check if playable
$transfer->isPlayable(); // Returns true/false
```

### Accessors

```php
// Get formatted progress
$transfer->progress_text; // "75%"

// Get formatted file size
$transfer->formatted_size; // "1.25 GB"

// Get formatted duration
$transfer->formatted_duration; // "2h 15m 30s"

// Get embed URL for app
$transfer->embed_url; // "https://drive.google.com/uc?export=view&id=..."

// Get status color for UI
$transfer->status_color; // "success", "danger", "info", etc.
```

### Scopes

```php
// Get completed transfers
VideoTransfer::completed()->get();

// Get failed transfers
VideoTransfer::failed()->get();

// Get active transfers
VideoTransfer::active()->get();

// Get by status
VideoTransfer::status('downloading')->get();
```

---

## 📊 Database Schema

### Key Fields

| Field | Type | Description |
|-------|------|-------------|
| `source_url` | string | Original video URL |
| `drive_file_id` | string | Google Drive file ID |
| `drive_public_url` | string | Public playable URL |
| `drive_download_url` | string | Direct download URL |
| `status` | enum | pending, downloading, uploading, completed, failed, cancelled |
| `progress` | integer | Progress percentage (0-100) |
| `bytes_transferred` | bigint | Bytes transferred so far |
| `total_bytes` | bigint | Total file size |
| `started_at` | timestamp | Transfer start time |
| `completed_at` | timestamp | Transfer completion time |
| `duration_seconds` | integer | Total transfer duration |
| `error_message` | text | Error message if failed |
| `retry_count` | integer | Number of retry attempts |
| `average_speed_mbps` | decimal | Average transfer speed |

---

## 🔧 Advanced Configuration

### Custom Folder

To upload videos to a specific Google Drive folder:

1. Create folder in Google Drive
2. Get folder ID from URL: `https://drive.google.com/drive/folders/[FOLDER_ID]`
3. Add to `.env`:
   ```bash
   GOOGLE_DRIVE_FOLDER_ID=1A2B3C4D5E6F7G8H9I0J
   ```

### Processing in Background (Production)

For production, use Laravel Queue:

```php
// In VideoTransfer model, update boot method:
static::created(function ($transfer) {
    dispatch(new ProcessVideoTransfer($transfer));
});
```

Create job:
```bash
php artisan make:job ProcessVideoTransfer
```

---

## 🐛 Troubleshooting

### Error: "Missing configuration: GOOGLE_DRIVE_CLIENT_ID"
**Solution**: Add Google Drive credentials to `.env` file

### Error: "Failed to get Google Drive access token"
**Solution**: Verify your refresh token is valid. Generate a new one if needed.

### Error: "Failed to download video"
**Solution**: 
- Check if source URL is accessible
- Verify URL points to actual video file
- Check server has enough storage space

### Transfer stuck at "downloading"
**Solution**: 
- Check server memory and storage
- Verify internet connection
- Cancel and retry transfer

### Video won't play in app
**Solution**:
- Verify file is marked as "completed"
- Check `drive_public_url` is not empty
- Use `embed_url` instead of `drive_public_url` for video players

---

## 📱 App Integration

### API Endpoint (Optional - Create if needed)

```php
// In routes/api.php
Route::get('/videos/playable', function() {
    return VideoTransfer::completed()
        ->orderBy('created_at', 'desc')
        ->get(['id', 'video_title', 'embed_url', 'video_duration']);
});
```

### Flutter Example

```dart
class VideoPlayer extends StatefulWidget {
  final String embedUrl;
  
  @override
  _VideoPlayerState createState() => _VideoPlayerState();
}

class _VideoPlayerState extends State<VideoPlayer> {
  late VideoPlayerController _controller;
  
  @override
  void initState() {
    super.initState();
    _controller = VideoPlayerController.network(widget.embedUrl)
      ..initialize().then((_) {
        setState(() {});
      });
  }
  
  @override
  Widget build(BuildContext context) {
    return _controller.value.isInitialized
      ? AspectRatio(
          aspectRatio: _controller.value.aspectRatio,
          child: VideoPlayer(_controller),
        )
      : CircularProgressIndicator();
  }
}
```

---

## 🎨 Admin Panel Screenshots

### Main Grid View
- ✅ Statistics cards (Total, Completed, Active, Failed)
- ✅ Filterable table with status badges
- ✅ Progress bars for each transfer
- ✅ Action buttons (Play, Retry, Cancel)

### Detail View
- ✅ Complete transfer information
- ✅ Source and destination URLs
- ✅ Progress tracking
- ✅ Timing information
- ✅ Error details (if failed)
- ✅ Video metadata

### Create Form
- ✅ Tabbed interface
- ✅ Required and optional fields
- ✅ Helpful tooltips
- ✅ Auto-processing on submit

---

## 🔒 Security Notes

1. **Keep credentials secure**: Never commit `.env` to version control
2. **Refresh tokens**: Store securely, they provide long-term access
3. **Public URLs**: Videos will be publicly accessible once transferred
4. **Rate limits**: Google Drive API has quotas, monitor usage
5. **Storage**: Monitor Google Drive storage limits

---

## 📈 Performance Tips

1. **Large files**: Transfers are synchronous by default. Use Laravel Queue for production
2. **Multiple transfers**: Process one at a time or use job queue
3. **Monitoring**: Check logs at `storage/logs/laravel.log`
4. **Cleanup**: Delete temp files are auto-cleaned after transfer
5. **Database indexes**: Already optimized in migration

---

## 📞 Support

### Log Files
```bash
tail -f storage/logs/laravel.log
```

### Debug Mode
```bash
# In .env
APP_DEBUG=true
LOG_LEVEL=debug
```

### Check Transfer Details
```php
$transfer = VideoTransfer::find(1);
dd([
    'status' => $transfer->status,
    'progress' => $transfer->progress,
    'error' => $transfer->error_message,
    'urls' => [
        'public' => $transfer->drive_public_url,
        'embed' => $transfer->embed_url,
    ]
]);
```

---

## 🎉 Success!

Your Google Drive Video Transfer System is ready! 

**Next Steps:**
1. ✅ Run migration
2. ✅ Configure Google Drive API
3. ✅ Update .env file
4. ✅ Create first transfer
5. ✅ Use embed URLs in your app

**Enjoy seamless video hosting with Google Drive!** 🚀
