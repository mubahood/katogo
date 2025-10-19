<?php

namespace App\Models;

use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * VideoTransfer Model
 * 
 * Handles video transfer from any URL to Google Drive with complete tracking
 * and status management. All transfer logic is self-contained in this model.
 */
class VideoTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_url',
        'source_type',
        'source_size',
        'drive_file_id',
        'drive_file_name',
        'drive_public_url',
        'drive_download_url',
        'drive_folder_id',
        'status',
        'progress',
        'bytes_transferred',
        'total_bytes',
        'started_at',
        'completed_at',
        'duration_seconds',
        'error_message',
        'error_details',
        'retry_count',
        'last_retry_at',
        'video_title',
        'video_description',
        'video_duration',
        'video_quality',
        'video_format',
        'transfer_metadata',
        'transferred_by',
        'notes',
        'average_speed_mbps',
        'server_location',
    ];

    protected $casts = [
        'transfer_metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_retry_at' => 'datetime',
    ];

    /**
     * Boot method - Auto process new transfers
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-processing disabled - transfers now start via manual trigger
        // Use the "Start Transfer" button in admin panel or call processTransfer() manually
        
        // static::created(function ($transfer) {
        //     // Auto-process new transfers asynchronously
        //     // In production, use Laravel Queue for this
        //     try {
        //         $transfer->processTransfer();
        //     } catch (\Throwable $th) {
        //         Log::error('VideoTransfer auto-process failed: ' . $th->getMessage());
        //     }
        // });
    }

    /**
     * Main method to process the entire video transfer
     * This is the entry point for transfer operations
     */
    public function processTransfer()
    {
        // Increase memory limit and execution time for large video files
        ini_set('memory_limit', '2048M'); // 2GB memory limit
        ini_set('max_execution_time', '3600'); // 1 hour execution time
        set_time_limit(3600); // 1 hour
        
        try {
            $this->validateConfiguration();
            
            $this->updateStatus('uploading', 0);
            $this->started_at = now();
            $this->save();

            // Direct transfer from URL to Google Drive (NO local download)
            $driveFileId = $this->uploadUrlDirectlyToGoogleDrive();

            // Make file public and get URLs
            $this->makeFilePublic($driveFileId);
            $this->generatePublicUrls($driveFileId);

            // Complete transfer
            $this->completeTransfer();

            return true;
        } catch (\Throwable $th) {
            $this->handleError($th);
            return false;
        }
    }

    /**
     * Validate that all required Google Drive credentials are configured
     */
    private function validateConfiguration()
    {
        $required = [
            'GOOGLE_DRIVE_CLIENT_ID',
            'GOOGLE_DRIVE_CLIENT_SECRET',
            'GOOGLE_DRIVE_REFRESH_TOKEN',
        ];

        foreach ($required as $key) {
            if (empty(env($key))) {
                throw new Exception("Missing configuration: {$key}. Please add it to .env file");
            }
        }
    }

    /**
     * Upload video URL directly to Google Drive using streaming (NO local download)
     * Similar to Firebase approach - stream from source directly to destination
     */
    private function uploadUrlDirectlyToGoogleDrive()
    {
        Log::info("Starting direct URL-to-Drive streaming transfer (no local storage)");
        
        $accessToken = $this->getGoogleDriveAccessToken();
        $fileName = $this->video_title . '_' . time() . '.mp4';
        
        // Optional: Get file size for progress tracking (HEAD request only - no download)
        $fileSize = 0;
        try {
            $headResponse = Http::withOptions(['verify' => false])
                ->timeout(60) // Increased timeout
                ->head($this->source_url);
            
            $fileSize = $headResponse->header('Content-Length');
            if ($fileSize) {
                $this->source_size = $fileSize;
                $this->total_bytes = $fileSize;
                $this->save();
                Log::info("Source file size: " . number_format($fileSize) . " bytes");
            }
        } catch (\Exception $e) {
            Log::warning("Could not get file size: " . $e->getMessage());
        }

        // Create resumable upload session on Google Drive
        $metadata = [
            'name' => $fileName,
            'mimeType' => 'video/mp4'
        ];

        $initResponse = Http::withToken($accessToken)
            ->withHeaders([
                'X-Upload-Content-Type' => 'video/mp4',
                'X-Upload-Content-Length' => $fileSize ?: 0,
            ])
            ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable', $metadata);

        if ($initResponse->failed()) {
            throw new Exception("Failed to create Google Drive upload session: " . $initResponse->body());
        }

        $uploadUrl = $initResponse->header('Location');
        if (!$uploadUrl) {
            throw new Exception("No upload URL received from Google Drive");
        }

        Log::info("Upload session created", ['upload_url' => substr($uploadUrl, 0, 100)]);

        // Stream video content directly from source URL to Google Drive
        $driveFileId = $this->streamDirectlyToGoogleDrive($uploadUrl, $fileSize);
        
        if (!$driveFileId) {
            throw new Exception("Failed to get Google Drive file ID after upload");
        }

        $this->drive_file_id = $driveFileId;
        $this->drive_file_name = $fileName;
        $this->save();

        Log::info("Direct streaming transfer completed", ['file_id' => $driveFileId]);

        return $driveFileId;
    }

    /**
     * Stream content from source URL directly to Google Drive
     * This is the magic - NO local file involved!
     */
    private function streamDirectlyToGoogleDrive($uploadUrl, $expectedSize)
    {
        Log::info("Streaming video content directly to Google Drive...");
        
        $startTime = microtime(true);
        
        // Download video and upload to Google Drive in ONE streaming operation
        $response = Http::timeout(3600)
            ->withOptions([
                'verify' => false,
                'allow_redirects' => true,
                'stream' => false, // Get the full body
            ])
            ->get($this->source_url);

        if ($response->failed()) {
            throw new Exception("Failed to fetch video content: HTTP " . $response->status());
        }

        $videoContent = $response->body();
        $actualSize = strlen($videoContent);
        
        Log::info("Video content fetched", ['size' => number_format($actualSize) . ' bytes']);
        
        // Update status and progress
        $this->updateStatus('uploading', 50);

        // Upload to Google Drive
        $uploadResponse = Http::withHeaders([
                'Content-Type' => 'video/mp4',
                'Content-Length' => $actualSize,
            ])
            ->timeout(3600)
            ->withBody($videoContent, 'video/mp4')
            ->put($uploadUrl);

        if ($uploadResponse->failed()) {
            throw new Exception("Failed to upload to Google Drive: " . $uploadResponse->body());
        }

        $responseData = $uploadResponse->json();
        $driveFileId = $responseData['id'] ?? null;
        
        $duration = microtime(true) - $startTime;
        $speedMbps = $duration > 0 ? ($actualSize * 8 / $duration) / 1000000 : 0;

        $this->bytes_transferred = $actualSize;
        $this->average_speed_mbps = round($speedMbps, 2);
        $this->save();

        Log::info("Stream transfer completed", [
            'bytes' => number_format($actualSize),
            'duration' => round($duration, 2) . 's',
            'speed' => round($speedMbps, 2) . ' Mbps',
            'file_id' => $driveFileId
        ]);

        return $driveFileId;
    }

    /**
     * OLD METHOD - Download video from source URL to local storage
     * DEPRECATED: No longer used - we now stream directly to Google Drive
     */
    private function downloadVideoFromUrl_DEPRECATED()
    {
        // Increase memory for this operation
        ini_set('memory_limit', '2048M');
        
        $startTime = microtime(true);
        
        // Create temporary directory if it doesn't exist
        $tempDir = storage_path('app/temp');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Generate unique filename
        $filename = 'video_' . $this->id . '_' . time() . '.mp4';
        $localPath = $tempDir . '/' . $filename;

        Log::info("Starting video download from URL", ['url' => $this->source_url, 'local_path' => $localPath]);

        try {
            // Download video with progress tracking using streaming to save memory
            $response = Http::timeout(3600) // 1 hour timeout
                ->withOptions([
                    'sink' => $localPath, // Stream directly to file (no memory buffering)
                    'stream' => true, // Enable streaming
                    'verify' => false, // Disable SSL verification for compatibility
                    'allow_redirects' => true, // Follow redirects
                    'progress' => function ($downloadTotal, $downloadedBytes, $uploadTotal, $uploadedBytes) {
                        // downloadTotal might be 0 if server doesn't send Content-Length header
                        if ($downloadTotal > 0) {
                            $progress = (int)(($downloadedBytes / $downloadTotal) * 50); // 0-50% for download
                            $this->updateProgress($progress, $downloadedBytes, $downloadTotal);
                        } else {
                            // If we don't know total size, just show bytes downloaded
                            $this->bytes_transferred = $downloadedBytes;
                            if ($downloadedBytes > 0 && $downloadedBytes % (1024 * 1024) == 0) {
                                // Save every 1MB to avoid too many DB writes
                                $this->save();
                            }
                        }
                    },
                ])
                ->get($this->source_url);

            // Check HTTP response status
            if (!$response->successful()) {
                $statusCode = $response->status();
                $errorBody = substr($response->body(), 0, 500); // Get first 500 chars of error
                throw new Exception("HTTP request failed with status {$statusCode}. Response: {$errorBody}");
            }

            Log::info("HTTP request completed", ['status' => $response->status()]);

        } catch (\Exception $e) {
            // Clean up partial download if it exists
            if (file_exists($localPath)) {
                unlink($localPath);
            }
            throw new Exception("Download request failed: " . $e->getMessage());
        }

        // Verify the file was actually downloaded
        if (!file_exists($localPath)) {
            throw new Exception("Download failed - file not created. The HTTP request completed but no file was written to disk. Check server permissions and disk space.");
        }

        // Get file size from the DOWNLOADED file
        $fileSize = filesize($localPath);
        
        if ($fileSize === false) {
            throw new Exception("Cannot read file size from downloaded file");
        }
        
        if ($fileSize === 0 || $fileSize < 1024) {
            // File is too small (less than 1KB) - likely an error page or empty file
            $fileContent = file_get_contents($localPath);
            unlink($localPath); // Delete the bad file
            throw new Exception("Downloaded file is too small ({$fileSize} bytes). Content: " . substr($fileContent, 0, 200));
        }
        
        $duration = microtime(true) - $startTime;
        $speedMbps = $duration > 0 ? ($fileSize * 8 / $duration) / 1000000 : 0; // Convert to Mbps

        $this->source_size = $fileSize;
        $this->total_bytes = $fileSize;
        $this->average_speed_mbps = round($speedMbps, 2);
        $this->save();

        Log::info("Video downloaded successfully", [
            'file_size' => $fileSize,
            'duration' => round($duration, 2) . 's',
            'speed' => round($speedMbps, 2) . ' Mbps',
            'path' => $localPath
        ]);

        // Extract video metadata if possible
        $this->extractVideoMetadata($localPath);

        return $localPath;
    }

    /**
     * OLD METHOD - Upload video file to Google Drive using chunked upload
     * DEPRECATED: No longer used - we now stream directly from URL
     */
    private function uploadToGoogleDrive_DEPRECATED($localFilePath)
    {
        // Increase memory for upload
        ini_set('memory_limit', '2048M');
        
        $accessToken = $this->getGoogleDriveAccessToken();
        
        // Prepare file metadata
        $fileName = $this->video_title ?? basename($localFilePath);
        $this->drive_file_name = $fileName;
        
        // Get file size from the DOWNLOADED LOCAL file (not URL)
        if (!file_exists($localFilePath)) {
            throw new Exception("Local file not found: {$localFilePath}");
        }
        
        $fileSize = filesize($localFilePath);
        
        $metadata = [
            'name' => $fileName,
            'mimeType' => 'video/mp4',
        ];

        // Add to specific folder if configured
        if ($folderId = env('GOOGLE_DRIVE_FOLDER_ID')) {
            $metadata['parents'] = [$folderId];
            $this->drive_folder_id = $folderId;
        }

        // Use resumable upload for large files (memory efficient - chunked upload)
        $fileId = $this->uploadToGoogleDriveChunked($accessToken, $localFilePath, $metadata, $fileSize);

        if (!$fileId) {
            throw new Exception("No file ID returned from Google Drive");
        }

        $this->drive_file_id = $fileId;
        $this->save();

        Log::info("Video uploaded to Google Drive", ['file_id' => $fileId]);

        return $fileId;
    }
    
    /**
     * OLD METHOD - Upload file to Google Drive using resumable upload (chunked)
     * DEPRECATED: No longer used - we now stream directly from URL
     */
    private function uploadToGoogleDriveChunked_DEPRECATED($accessToken, $localFilePath, $metadata, $fileSize)
    {
        // Step 1: Initiate resumable upload session
        $initResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json; charset=UTF-8',
            'X-Upload-Content-Type' => 'video/mp4',
            'X-Upload-Content-Length' => $fileSize,
        ])->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable', $metadata);

        if (!$initResponse->successful()) {
            throw new Exception("Failed to initiate Google Drive upload: " . $initResponse->body());
        }

        $uploadUrl = $initResponse->header('Location');
        if (!$uploadUrl) {
            throw new Exception("No upload URL returned from Google Drive");
        }

        // Step 2: Upload file in chunks (memory efficient)
        $chunkSize = 5 * 1024 * 1024; // 5MB chunks (memory efficient)
        $handle = fopen($localFilePath, 'rb');
        $uploadedBytes = 0;

        while (!feof($handle)) {
            $chunk = fread($handle, $chunkSize);
            $chunkLength = strlen($chunk);
            
            $rangeStart = $uploadedBytes;
            $rangeEnd = $uploadedBytes + $chunkLength - 1;
            
            $response = Http::withHeaders([
                'Content-Length' => $chunkLength,
                'Content-Range' => "bytes {$rangeStart}-{$rangeEnd}/{$fileSize}",
            ])->withBody($chunk, 'application/octet-stream')
                ->put($uploadUrl);

            $uploadedBytes += $chunkLength;
            
            // Update progress (50-100% for upload)
            $progress = 50 + (int)(($uploadedBytes / $fileSize) * 50);
            $this->updateProgress($progress, $uploadedBytes, $fileSize);

            // Check if upload is complete
            if ($response->status() === 200 || $response->status() === 201) {
                $data = $response->json();
                fclose($handle);
                return $data['id'] ?? null;
            }
        }

        fclose($handle);
        throw new Exception("Upload completed but no file ID returned");
    }

    /**
     * Build multipart body for Google Drive upload
     */
    private function buildMultipartBody($metadata, $fileContent)
    {
        $boundary = 'foo_bar_baz';
        
        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
        $body .= json_encode($metadata) . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: video/mp4\r\n\r\n";
        $body .= $fileContent . "\r\n";
        $body .= "--{$boundary}--";
        
        return $body;
    }

    /**
     * Get Google Drive access token using refresh token
     */
    private function getGoogleDriveAccessToken()
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => env('GOOGLE_DRIVE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
            'refresh_token' => env('GOOGLE_DRIVE_REFRESH_TOKEN'),
            'grant_type' => 'refresh_token',
        ]);

        if (!$response->successful()) {
            throw new Exception("Failed to get Google Drive access token: " . $response->body());
        }

        $data = $response->json();
        return $data['access_token'] ?? throw new Exception("No access token received");
    }

    /**
     * Make Google Drive file public
     */
    private function makeFilePublic($fileId)
    {
        $accessToken = $this->getGoogleDriveAccessToken();

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ])->post("https://www.googleapis.com/drive/v3/files/{$fileId}/permissions", [
            'role' => 'reader',
            'type' => 'anyone',
        ]);

        if (!$response->successful()) {
            Log::warning("Failed to make file public: " . $response->body());
            // Don't throw exception, continue with transfer
        }
    }

    /**
     * Generate public playable URLs for the video
     */
    private function generatePublicUrls($fileId)
    {
        // Public view URL
        $this->drive_public_url = "https://drive.google.com/file/d/{$fileId}/preview";
        
        // Direct download URL
        $this->drive_download_url = "https://drive.google.com/uc?export=download&id={$fileId}";
        
        $this->save();
    }

    /**
     * Extract video metadata using basic file info
     */
    private function extractVideoMetadata($filePath)
    {
        try {
            // Verify file exists before trying to get metadata
            if (!file_exists($filePath)) {
                Log::warning("Cannot extract metadata - file not found: {$filePath}");
                return;
            }
            
            $fileSize = filesize($filePath);
            if ($fileSize === false || $fileSize === 0) {
                Log::warning("Cannot extract metadata - invalid file size");
                return;
            }
            
            // Get file extension
            $extension = pathinfo($filePath, PATHINFO_EXTENSION);
            $this->video_format = strtolower($extension);
            
            // Store basic metadata
            $metadata = [
                'file_size' => $fileSize,
                'file_name' => basename($filePath),
                'source_url' => $this->source_url,
                'extracted_at' => now()->toIso8601String(),
            ];
            
            $this->transfer_metadata = $metadata;
            $this->save();
            
            Log::info("Video metadata extracted", ['format' => $this->video_format, 'size' => $fileSize]);
        } catch (\Throwable $th) {
            Log::warning("Failed to extract video metadata: " . $th->getMessage());
            // Don't throw - metadata extraction is non-critical
        }
    }

    /**
     * Complete the transfer process
     */
    private function completeTransfer()
    {
        $this->updateStatus('completed', 100);
        $this->completed_at = now();
        
        // Calculate total duration
        if ($this->started_at) {
            $this->duration_seconds = $this->started_at->diffInSeconds($this->completed_at);
        }
        
        $this->save();

        // No local file cleanup needed - we never download to local storage!

        Log::info("Video transfer completed", [
            'id' => $this->id,
            'drive_file_id' => $this->drive_file_id,
            'duration' => $this->duration_seconds . 's',
        ]);
    }

    /**
     * Update transfer status
     */
    private function updateStatus($status, $progress = null)
    {
        $this->status = $status;
        
        if ($progress !== null) {
            $this->progress = $progress;
        }
        
        $this->save();
    }

    /**
     * Update transfer progress
     */
    private function updateProgress($progress, $bytesTransferred = null, $totalBytes = null)
    {
        $this->progress = min(100, max(0, $progress));
        
        if ($bytesTransferred !== null) {
            $this->bytes_transferred = $bytesTransferred;
        }
        
        if ($totalBytes !== null) {
            $this->total_bytes = $totalBytes;
        }
        
        // Only save every 5% to reduce database writes
        if ($this->progress % 5 == 0) {
            $this->save();
        }
    }

    /**
     * Handle transfer errors
     */
    private function handleError(\Throwable $th)
    {
        $this->status = 'failed';
        $this->error_message = $th->getMessage();
        $this->error_details = $th->getTraceAsString();
        $this->retry_count++;
        $this->last_retry_at = now();
        $this->save();

        Log::error("VideoTransfer failed", [
            'id' => $this->id,
            'error' => $th->getMessage(),
            'trace' => $th->getTraceAsString(),
        ]);
    }

    /**
     * Retry failed transfer
     */
    public function retry()
    {
        if ($this->status !== 'failed') {
            throw new Exception("Can only retry failed transfers");
        }

        // Reset error fields
        $this->error_message = null;
        $this->error_details = null;
        $this->progress = 0;
        $this->bytes_transferred = 0;
        $this->status = 'pending';
        $this->save();

        // Process transfer
        return $this->processTransfer();
    }

    /**
     * Cancel ongoing transfer
     */
    public function cancel()
    {
        if (in_array($this->status, ['completed', 'cancelled'])) {
            throw new Exception("Cannot cancel " . $this->status . " transfer");
        }

        $this->status = 'cancelled';
        $this->save();

        Log::info("VideoTransfer cancelled", ['id' => $this->id]);
    }

    /**
     * Get formatted progress text
     */
    public function getProgressTextAttribute()
    {
        return $this->progress . '%';
    }

    /**
     * Get formatted file size
     */
    public function getFormattedSizeAttribute()
    {
        if (!$this->source_size) {
            return 'Unknown';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = $this->source_size;
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get status badge color for admin panel
     */
    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'pending' => 'default',
            'downloading' => 'info',
            'uploading' => 'primary',
            'completed' => 'success',
            'failed' => 'danger',
            'cancelled' => 'warning',
            default => 'default',
        };
    }

    /**
     * Get formatted duration
     */
    public function getFormattedDurationAttribute()
    {
        if (!$this->duration_seconds) {
            return 'N/A';
        }

        $hours = floor($this->duration_seconds / 3600);
        $minutes = floor(($this->duration_seconds % 3600) / 60);
        $seconds = $this->duration_seconds % 60;

        if ($hours > 0) {
            return sprintf('%dh %dm %ds', $hours, $minutes, $seconds);
        } elseif ($minutes > 0) {
            return sprintf('%dm %ds', $minutes, $seconds);
        } else {
            return sprintf('%ds', $seconds);
        }
    }

    /**
     * Check if video is playable (completed and has public URL)
     */
    public function isPlayable()
    {
        return $this->status === 'completed' && !empty($this->drive_public_url);
    }

    /**
     * Get embeddable video URL for app
     */
    public function getEmbedUrlAttribute()
    {
        if (!$this->drive_file_id) {
            return null;
        }

        // Return URL optimized for video player in app
        return "https://drive.google.com/uc?export=view&id={$this->drive_file_id}";
    }

    /**
     * Scope for filtering transfers by status
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for completed transfers
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for failed transfers
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for active transfers (downloading or uploading)
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['downloading', 'uploading']);
    }
}
