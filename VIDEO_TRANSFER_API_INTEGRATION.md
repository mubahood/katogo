# 🔌 API Integration Examples

## REST API Endpoints (Optional Implementation)

Add these to your `routes/api.php` for app integration:

```php
<?php

use App\Models\VideoTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Get all completed videos
Route::get('/videos/completed', function() {
    $videos = VideoTransfer::completed()
        ->orderBy('created_at', 'desc')
        ->select([
            'id',
            'video_title',
            'video_description',
            'video_duration',
            'video_quality',
            'video_format',
            'embed_url',
            'drive_public_url',
            'source_size',
            'created_at'
        ])
        ->paginate(20);
    
    return response()->json([
        'success' => true,
        'data' => $videos,
    ]);
});

// Get single video details
Route::get('/videos/{id}', function($id) {
    $video = VideoTransfer::completed()
        ->where('id', $id)
        ->firstOrFail();
    
    return response()->json([
        'success' => true,
        'data' => [
            'id' => $video->id,
            'title' => $video->video_title,
            'description' => $video->video_description,
            'duration' => $video->video_duration,
            'quality' => $video->video_quality,
            'format' => $video->video_format,
            'embed_url' => $video->embed_url,
            'public_url' => $video->drive_public_url,
            'download_url' => $video->drive_download_url,
            'size' => $video->formatted_size,
            'created_at' => $video->created_at->toIso8601String(),
        ],
    ]);
});

// Search videos
Route::get('/videos/search', function(Request $request) {
    $query = VideoTransfer::completed();
    
    if ($search = $request->get('q')) {
        $query->where(function($q) use ($search) {
            $q->where('video_title', 'like', "%{$search}%")
              ->orWhere('video_description', 'like', "%{$search}%");
        });
    }
    
    if ($quality = $request->get('quality')) {
        $query->where('video_quality', $quality);
    }
    
    if ($format = $request->get('format')) {
        $query->where('video_format', $format);
    }
    
    $videos = $query->orderBy('created_at', 'desc')
        ->paginate(20);
    
    return response()->json([
        'success' => true,
        'data' => $videos,
    ]);
});

// Get transfer status (for monitoring)
Route::get('/videos/transfers/{id}/status', function($id) {
    $transfer = VideoTransfer::findOrFail($id);
    
    return response()->json([
        'success' => true,
        'data' => [
            'id' => $transfer->id,
            'status' => $transfer->status,
            'progress' => $transfer->progress,
            'is_playable' => $transfer->isPlayable(),
            'embed_url' => $transfer->embed_url,
            'error_message' => $transfer->error_message,
            'started_at' => $transfer->started_at,
            'completed_at' => $transfer->completed_at,
        ],
    ]);
});
```

---

## 📱 Flutter Integration Examples

### 1. Video Model

```dart
// lib/models/drive_video.dart
class DriveVideo {
  final int id;
  final String title;
  final String? description;
  final String? duration;
  final String? quality;
  final String embedUrl;
  final String? size;
  final DateTime createdAt;

  DriveVideo({
    required this.id,
    required this.title,
    this.description,
    this.duration,
    this.quality,
    required this.embedUrl,
    this.size,
    required this.createdAt,
  });

  factory DriveVideo.fromJson(Map<String, dynamic> json) {
    return DriveVideo(
      id: json['id'],
      title: json['video_title'] ?? 'Untitled',
      description: json['video_description'],
      duration: json['video_duration'],
      quality: json['video_quality'],
      embedUrl: json['embed_url'],
      size: json['size'],
      createdAt: DateTime.parse(json['created_at']),
    );
  }
}
```

### 2. API Service

```dart
// lib/services/drive_video_service.dart
import 'package:http/http.dart' as http;
import 'dart:convert';
import '../models/drive_video.dart';

class DriveVideoService {
  static const String baseUrl = 'https://your-domain.com/api';

  // Get all completed videos
  static Future<List<DriveVideo>> getCompletedVideos({int page = 1}) async {
    final response = await http.get(
      Uri.parse('$baseUrl/videos/completed?page=$page'),
    );

    if (response.statusCode == 200) {
      final json = jsonDecode(response.body);
      final data = json['data']['data'] as List;
      return data.map((video) => DriveVideo.fromJson(video)).toList();
    }
    throw Exception('Failed to load videos');
  }

  // Get single video
  static Future<DriveVideo> getVideo(int id) async {
    final response = await http.get(
      Uri.parse('$baseUrl/videos/$id'),
    );

    if (response.statusCode == 200) {
      final json = jsonDecode(response.body);
      return DriveVideo.fromJson(json['data']);
    }
    throw Exception('Failed to load video');
  }

  // Search videos
  static Future<List<DriveVideo>> searchVideos({
    required String query,
    String? quality,
    String? format,
  }) async {
    var url = '$baseUrl/videos/search?q=$query';
    if (quality != null) url += '&quality=$quality';
    if (format != null) url += '&format=$format';

    final response = await http.get(Uri.parse(url));

    if (response.statusCode == 200) {
      final json = jsonDecode(response.body);
      final data = json['data']['data'] as List;
      return data.map((video) => DriveVideo.fromJson(video)).toList();
    }
    throw Exception('Failed to search videos');
  }

  // Check transfer status
  static Future<Map<String, dynamic>> getTransferStatus(int id) async {
    final response = await http.get(
      Uri.parse('$baseUrl/videos/transfers/$id/status'),
    );

    if (response.statusCode == 200) {
      final json = jsonDecode(response.body);
      return json['data'];
    }
    throw Exception('Failed to get status');
  }
}
```

### 3. Video List Screen

```dart
// lib/screens/drive_videos_screen.dart
import 'package:flutter/material.dart';
import '../services/drive_video_service.dart';
import '../models/drive_video.dart';
import 'video_player_screen.dart';

class DriveVideosScreen extends StatefulWidget {
  @override
  _DriveVideosScreenState createState() => _DriveVideosScreenState();
}

class _DriveVideosScreenState extends State<DriveVideosScreen> {
  List<DriveVideo> videos = [];
  bool isLoading = true;

  @override
  void initState() {
    super.initState();
    loadVideos();
  }

  Future<void> loadVideos() async {
    try {
      final loadedVideos = await DriveVideoService.getCompletedVideos();
      setState(() {
        videos = loadedVideos;
        isLoading = false;
      });
    } catch (e) {
      setState(() => isLoading = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error loading videos: $e')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Google Drive Videos'),
      ),
      body: isLoading
          ? Center(child: CircularProgressIndicator())
          : ListView.builder(
              itemCount: videos.length,
              itemBuilder: (context, index) {
                final video = videos[index];
                return ListTile(
                  leading: Icon(Icons.video_library, size: 40),
                  title: Text(video.title),
                  subtitle: Text(
                    video.quality ?? 'Unknown quality',
                    style: TextStyle(fontSize: 12),
                  ),
                  trailing: Icon(Icons.play_arrow),
                  onTap: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (context) => VideoPlayerScreen(video: video),
                      ),
                    );
                  },
                );
              },
            ),
    );
  }
}
```

### 4. Video Player Screen

```dart
// lib/screens/video_player_screen.dart
import 'package:flutter/material.dart';
import 'package:video_player/video_player.dart';
import '../models/drive_video.dart';

class VideoPlayerScreen extends StatefulWidget {
  final DriveVideo video;

  VideoPlayerScreen({required this.video});

  @override
  _VideoPlayerScreenState createState() => _VideoPlayerScreenState();
}

class _VideoPlayerScreenState extends State<VideoPlayerScreen> {
  late VideoPlayerController _controller;
  bool _isInitialized = false;

  @override
  void initState() {
    super.initState();
    _initializePlayer();
  }

  Future<void> _initializePlayer() async {
    _controller = VideoPlayerController.network(widget.video.embedUrl);
    
    try {
      await _controller.initialize();
      setState(() => _isInitialized = true);
      _controller.play();
    } catch (e) {
      print('Error initializing video: $e');
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error loading video: $e')),
      );
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.video.title),
      ),
      body: Column(
        children: [
          _isInitialized
              ? AspectRatio(
                  aspectRatio: _controller.value.aspectRatio,
                  child: VideoPlayer(_controller),
                )
              : Container(
                  height: 200,
                  child: Center(
                    child: CircularProgressIndicator(),
                  ),
                ),
          SizedBox(height: 16),
          // Video controls
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              IconButton(
                icon: Icon(
                  _controller.value.isPlaying
                      ? Icons.pause
                      : Icons.play_arrow,
                  size: 40,
                ),
                onPressed: () {
                  setState(() {
                    _controller.value.isPlaying
                        ? _controller.pause()
                        : _controller.play();
                  });
                },
              ),
            ],
          ),
          // Video info
          Padding(
            padding: EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  widget.video.title,
                  style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                SizedBox(height: 8),
                if (widget.video.description != null)
                  Text(widget.video.description!),
                SizedBox(height: 8),
                Row(
                  children: [
                    if (widget.video.quality != null)
                      Chip(label: Text(widget.video.quality!)),
                    SizedBox(width: 8),
                    if (widget.video.duration != null)
                      Chip(label: Text(widget.video.duration!)),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
```

### 5. Transfer Status Monitor

```dart
// lib/widgets/transfer_status_widget.dart
import 'package:flutter/material.dart';
import 'dart:async';
import '../services/drive_video_service.dart';

class TransferStatusWidget extends StatefulWidget {
  final int transferId;

  TransferStatusWidget({required this.transferId});

  @override
  _TransferStatusWidgetState createState() => _TransferStatusWidgetState();
}

class _TransferStatusWidgetState extends State<TransferStatusWidget> {
  Timer? _timer;
  Map<String, dynamic>? _status;

  @override
  void initState() {
    super.initState();
    _startPolling();
  }

  void _startPolling() {
    _checkStatus();
    _timer = Timer.periodic(Duration(seconds: 5), (_) => _checkStatus());
  }

  Future<void> _checkStatus() async {
    try {
      final status = await DriveVideoService.getTransferStatus(widget.transferId);
      setState(() => _status = status);
      
      // Stop polling if completed or failed
      if (status['status'] == 'completed' || status['status'] == 'failed') {
        _timer?.cancel();
      }
    } catch (e) {
      print('Error checking status: $e');
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (_status == null) {
      return CircularProgressIndicator();
    }

    final status = _status!['status'];
    final progress = _status!['progress'] ?? 0;
    final isPlayable = _status!['is_playable'] ?? false;

    return Card(
      child: Padding(
        padding: EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Transfer Status: ${status.toUpperCase()}',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
            ),
            SizedBox(height: 8),
            LinearProgressIndicator(value: progress / 100),
            SizedBox(height: 4),
            Text('$progress%'),
            if (isPlayable)
              ElevatedButton(
                onPressed: () {
                  // Navigate to video player
                },
                child: Text('Play Video'),
              ),
          ],
        ),
      ),
    );
  }
}
```

---

## 🔐 Authentication Example

If you need to protect the API:

```php
// In routes/api.php
Route::middleware('auth:sanctum')->group(function() {
    Route::get('/videos/completed', [VideoApiController::class, 'index']);
    Route::get('/videos/{id}', [VideoApiController::class, 'show']);
});
```

---

## 📊 Response Examples

### Completed Videos List
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "video_title": "My Awesome Video",
        "video_description": "This is a great video",
        "video_duration": "02:15:30",
        "video_quality": "1080p",
        "video_format": "mp4",
        "embed_url": "https://drive.google.com/uc?export=view&id=ABC123",
        "drive_public_url": "https://drive.google.com/file/d/ABC123/preview",
        "source_size": 1500000000,
        "created_at": "2025-10-19T10:30:00Z"
      }
    ],
    "total": 50,
    "per_page": 20
  }
}
```

### Transfer Status
```json
{
  "success": true,
  "data": {
    "id": 1,
    "status": "completed",
    "progress": 100,
    "is_playable": true,
    "embed_url": "https://drive.google.com/uc?export=view&id=ABC123",
    "error_message": null,
    "started_at": "2025-10-19T10:25:00Z",
    "completed_at": "2025-10-19T10:30:00Z"
  }
}
```

---

## 🎉 That's It!

You now have complete API integration examples for your Google Drive video system!
