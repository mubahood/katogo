# ✅ Google Drive Video Transfer - Setup Checklist

Use this checklist to ensure everything is configured correctly.

---

## 📋 Pre-Installation Checklist

- [ ] PHP 8.1 or higher installed
- [ ] Laravel 10.x project running
- [ ] MySQL/PostgreSQL database configured
- [ ] Internet connection available
- [ ] Admin access to Laravel Admin panel

---

## 🔧 Installation Steps

### Step 1: Database Setup
- [ ] Migration file exists: `database/migrations/2025_10_19_000001_create_video_transfers_table.php`
- [ ] Run migration: `php artisan migrate`
- [ ] Verify table created: Check `video_transfers` table in database
- [ ] Test query: `SELECT * FROM video_transfers;` (should be empty)

### Step 2: Files Verification
- [ ] Model exists: `app/Models/VideoTransfer.php`
- [ ] Controller exists: `app/Admin/Controllers/VideoTransferController.php`
- [ ] View exists: `resources/views/admin/video-transfer/stats.blade.php`
- [ ] Routes added: Check `app/Admin/routes.php` for `video-transfers` routes

### Step 3: Google Drive API Setup

#### 3.1 Google Cloud Console
- [ ] Go to: https://console.cloud.google.com/
- [ ] Create new project OR select existing project
- [ ] Project name: ________________
- [ ] Project ID: ________________

#### 3.2 Enable API
- [ ] Navigate to: APIs & Services → Library
- [ ] Search for: "Google Drive API"
- [ ] Click "Enable"
- [ ] Wait for confirmation

#### 3.3 Create Credentials
- [ ] Go to: APIs & Services → Credentials
- [ ] Click: Create Credentials → OAuth 2.0 Client ID
- [ ] Application type: Desktop app
- [ ] Name: ________________
- [ ] Click "Create"
- [ ] Copy Client ID: ________________
- [ ] Copy Client Secret: ________________

#### 3.4 Generate Refresh Token
- [ ] Go to: https://developers.google.com/oauthplayground/
- [ ] Click ⚙️ (settings icon, top right)
- [ ] Check: "Use your own OAuth credentials"
- [ ] Paste Client ID: ________________
- [ ] Paste Client Secret: ________________
- [ ] Close settings

- [ ] Step 1: Select API
  - [ ] Expand "Drive API v3"
  - [ ] Check: `https://www.googleapis.com/auth/drive.file`
  - [ ] Click "Authorize APIs"
  
- [ ] Step 2: Sign in
  - [ ] Sign in with Google account
  - [ ] Allow permissions
  - [ ] Click "Exchange authorization code for tokens"
  - [ ] Copy Refresh token: ________________

#### 3.5 Optional: Create Upload Folder
- [ ] Go to: https://drive.google.com/
- [ ] Create new folder: ________________
- [ ] Open folder
- [ ] Get Folder ID from URL: `https://drive.google.com/drive/folders/[FOLDER_ID]`
- [ ] Copy Folder ID: ________________

### Step 4: Environment Configuration
- [ ] Open `.env` file
- [ ] Add the following lines:

```bash
# Google Drive API Configuration
GOOGLE_DRIVE_CLIENT_ID=your-client-id-here
GOOGLE_DRIVE_CLIENT_SECRET=your-client-secret-here
GOOGLE_DRIVE_REFRESH_TOKEN=your-refresh-token-here
GOOGLE_DRIVE_FOLDER_ID=your-folder-id-here (optional)
```

- [ ] Replace placeholders with actual values
- [ ] Save `.env` file

### Step 5: Clear Cache
- [ ] Run: `php artisan cache:clear`
- [ ] Run: `php artisan config:clear`
- [ ] Run: `php artisan route:clear`

### Step 6: Test Installation
- [ ] Run test script: `./test-video-transfer.sh`
- [ ] All tests pass: ✅
- [ ] Review any warnings or errors

---

## 🎯 First Transfer Test

### Step 1: Access Admin Panel
- [ ] Open browser
- [ ] Navigate to: `http://your-domain.com/admin/video-transfers`
- [ ] Login if needed
- [ ] See "Video Transfer to Google Drive" page

### Step 2: Create Test Transfer
- [ ] Click "New" button
- [ ] Fill in form:
  - [ ] Video Title: "Test Video"
  - [ ] Source URL: (use a small test video URL)
  - [ ] Video Description: "This is a test"
  - [ ] Source Type: "Direct URL"
- [ ] Click "Submit"

### Step 3: Monitor Progress
- [ ] Return to list view
- [ ] See new transfer with "Pending" or "Downloading" status
- [ ] Refresh page periodically
- [ ] Wait for status to change to "Completed" ✅

### Step 4: Verify Success
- [ ] Status shows: "COMPLETED" (green badge)
- [ ] Progress bar shows: 100%
- [ ] "Play" button appears
- [ ] Click "View" to see details
- [ ] Verify fields are filled:
  - [ ] Drive File ID: ________________
  - [ ] Drive Public URL: ________________
  - [ ] Embed URL: ________________

### Step 5: Test Playback
- [ ] Click "Play" button in list view
- [ ] OR click public URL in detail view
- [ ] Video opens in new tab
- [ ] Video plays successfully ✅

---

## 🔍 Verification Checklist

### Database Verification
- [ ] Table exists: `video_transfers`
- [ ] Test record exists with status "completed"
- [ ] All URLs populated in record
- [ ] No errors in error_message field

### Google Drive Verification
- [ ] Log into Google Drive
- [ ] Find uploaded video file
- [ ] File is visible
- [ ] File has public permissions (anyone with link)
- [ ] File is in correct folder (if folder specified)

### Admin Panel Verification
- [ ] Statistics cards show correct numbers
- [ ] Filters work (try filtering by status)
- [ ] Search works (try searching by title)
- [ ] Actions work:
  - [ ] "View" button shows details
  - [ ] "Edit" button opens form
  - [ ] "Play" button opens video
  - [ ] "Retry" button works (for failed transfers)

---

## 🐛 Troubleshooting Checklist

### If Transfer Fails

- [ ] Check error message in admin panel
- [ ] Check Laravel logs: `tail -f storage/logs/laravel.log`
- [ ] Verify .env credentials are correct
- [ ] Test Google Drive API access:
  ```bash
  php artisan tinker
  >>> $transfer = VideoTransfer::find(1);
  >>> $transfer->retry();
  ```

### If Video Won't Download

- [ ] Verify source URL is accessible in browser
- [ ] Check if URL requires authentication
- [ ] Test with smaller video file first
- [ ] Check server storage space: `df -h`
- [ ] Check server memory: `free -m`

### If Upload Fails

- [ ] Verify Google Drive credentials in .env
- [ ] Test credentials with OAuth Playground
- [ ] Check Google Drive API is enabled
- [ ] Verify refresh token is valid
- [ ] Check Google Drive storage quota

### If Video Won't Play

- [ ] Use `embed_url` field, not `public_url`
- [ ] Test URL directly in browser
- [ ] Check file permissions in Google Drive
- [ ] Verify file completed upload (check file size)

---

## 📱 App Integration Checklist

### API Setup (Optional)
- [ ] Add routes to `routes/api.php`
- [ ] Test API endpoints with Postman/Insomnia
- [ ] Verify JSON responses
- [ ] Add authentication if needed

### Flutter Integration
- [ ] Add `video_player` package to `pubspec.yaml`
- [ ] Create video model class
- [ ] Create API service class
- [ ] Implement video player screen
- [ ] Test playback in app
- [ ] Test on real device (iOS/Android)

---

## 🎉 Success Criteria

Your system is ready when:

- ✅ Migration completed successfully
- ✅ Admin panel accessible
- ✅ Test transfer completes successfully
- ✅ Video plays in browser
- ✅ Embed URL works in video player
- ✅ No errors in logs
- ✅ Statistics display correctly
- ✅ All action buttons work

---

## 📝 Production Checklist

Before going live:

- [ ] Test with multiple video formats (mp4, mkv, avi)
- [ ] Test with large files (> 1GB)
- [ ] Test with slow internet connection
- [ ] Test retry functionality
- [ ] Test cancel functionality
- [ ] Set up Laravel Queue for background processing
- [ ] Configure proper logging
- [ ] Set up monitoring/alerts
- [ ] Backup database
- [ ] Document any custom changes
- [ ] Train admin users

---

## 🔒 Security Checklist

- [ ] .env file NOT in version control
- [ ] .gitignore includes .env
- [ ] Google Drive credentials secure
- [ ] Admin panel requires authentication
- [ ] HTTPS enabled in production
- [ ] Database credentials secure
- [ ] Regular backups configured

---

## 📊 Monitoring Checklist

- [ ] Set up log monitoring
- [ ] Monitor disk space usage
- [ ] Monitor Google Drive quota
- [ ] Track transfer success rate
- [ ] Monitor average transfer time
- [ ] Set up alerts for failures

---

## 🎯 Your Configuration

Fill this out for reference:

**Project Details:**
- Laravel Version: ________________
- PHP Version: ________________
- Database: ________________
- Domain: ________________

**Google Drive API:**
- Project Name: ________________
- Project ID: ________________
- Client ID: ________________
- Client Secret: ________________
- Refresh Token: ________________
- Folder ID: ________________

**Admin Access:**
- Admin URL: ________________
- Username: ________________

**First Test Transfer:**
- Transfer ID: ________________
- Status: ________________
- Drive File ID: ________________
- Embed URL: ________________

---

## ✅ Final Verification

- [ ] All checkboxes above are complete
- [ ] Documentation reviewed
- [ ] System tested end-to-end
- [ ] Team members trained
- [ ] Backup plan in place
- [ ] Ready for production! 🚀

---

**Date Completed:** ________________  
**Completed By:** ________________  
**Notes:** ________________

---

**🎉 Congratulations! Your Google Drive Video Transfer System is ready!**

Keep this checklist for future reference and troubleshooting.
