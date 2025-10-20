<?php

namespace App\Http\Controllers;

use App\Models\VideoTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiVideoTransferController extends Controller
{
    /**
     * Get paginated list of video transfers
     * GET /api/video-transfers
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 20);
            $status = $request->get('status'); // Filter by status if provided
            
            $query = VideoTransfer::with(['movie' => function($q) {
                $q->select('id', 'title', 'thumbnail', 'duration', 'year');
            }])
            ->orderBy('created_at', 'desc');
            
            // Filter by status if provided
            if ($status && in_array($status, ['pending', 'downloading', 'uploading', 'completed', 'failed'])) {
                $query->where('status', $status);
            }
            
            $transfers = $query->paginate($perPage);
            
            // Format the response
            $data = $transfers->map(function($transfer) {
                return [
                    'id' => $transfer->id,
                    'video_title' => $transfer->video_title,
                    'status' => $transfer->status,
                    'progress' => $transfer->progress,
                    'source_url' => $transfer->source_url,
                    'drive_file_id' => $transfer->drive_file_id,
                    'drive_file_url' => $transfer->drive_file_url,
                    'source_size' => $transfer->source_size,
                    'bytes_transferred' => $transfer->bytes_transferred,
                    'average_speed_mbps' => $transfer->average_speed_mbps,
                    'error_message' => $transfer->error_message,
                    'started_at' => $transfer->started_at,
                    'completed_at' => $transfer->completed_at,
                    'created_at' => $transfer->created_at->toISOString(),
                    'updated_at' => $transfer->updated_at->toISOString(),
                    
                    // Movie details if available
                    'movie' => $transfer->movie ? [
                        'id' => $transfer->movie->id,
                        'title' => $transfer->movie->title,
                        'thumbnail' => $transfer->movie->thumbnail,
                        'duration' => $transfer->movie->duration,
                        'year' => $transfer->movie->year,
                    ] : null,
                    
                    // Formatted file size
                    'formatted_size' => $this->formatBytes($transfer->source_size),
                    'formatted_transferred' => $this->formatBytes($transfer->bytes_transferred),
                    
                    // Status badge info
                    'status_color' => $this->getStatusColor($transfer->status),
                    'status_text' => $this->getStatusText($transfer->status),
                    
                    // Can play (only if completed and has drive_file_id)
                    'can_play' => $transfer->status === 'completed' && !empty($transfer->drive_file_id),
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'current_page' => $transfers->currentPage(),
                    'last_page' => $transfers->lastPage(),
                    'per_page' => $transfers->perPage(),
                    'total' => $transfers->total(),
                    'from' => $transfers->firstItem(),
                    'to' => $transfers->lastItem(),
                ],
                'stats' => [
                    'total' => VideoTransfer::count(),
                    'completed' => VideoTransfer::where('status', 'completed')->count(),
                    'pending' => VideoTransfer::where('status', 'pending')->count(),
                    'failed' => VideoTransfer::where('status', 'failed')->count(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Video Transfer API Index Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch video transfers',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get single video transfer details
     * GET /api/video-transfers/{id}
     */
    public function show($id)
    {
        try {
            $transfer = VideoTransfer::with(['movie' => function($q) {
                $q->select('id', 'title', 'thumbnail', 'description', 'duration', 'year', 'rating');
            }])->findOrFail($id);
            
            $data = [
                'id' => $transfer->id,
                'video_title' => $transfer->video_title,
                'status' => $transfer->status,
                'progress' => $transfer->progress,
                'source_url' => $transfer->source_url,
                'drive_file_id' => $transfer->drive_file_id,
                'drive_file_url' => $transfer->drive_file_url,
                'source_size' => $transfer->source_size,
                'bytes_transferred' => $transfer->bytes_transferred,
                'average_speed_mbps' => $transfer->average_speed_mbps,
                'error_message' => $transfer->error_message,
                'started_at' => $transfer->started_at,
                'completed_at' => $transfer->completed_at,
                'created_at' => $transfer->created_at->toISOString(),
                'updated_at' => $transfer->updated_at->toISOString(),
                
                // Movie details
                'movie' => $transfer->movie ? [
                    'id' => $transfer->movie->id,
                    'title' => $transfer->movie->title,
                    'thumbnail' => $transfer->movie->thumbnail,
                    'description' => $transfer->movie->description,
                    'duration' => $transfer->movie->duration,
                    'year' => $transfer->movie->year,
                    'rating' => $transfer->movie->rating,
                ] : null,
                
                // Formatted values
                'formatted_size' => $this->formatBytes($transfer->source_size),
                'formatted_transferred' => $this->formatBytes($transfer->bytes_transferred),
                'formatted_speed' => $transfer->average_speed_mbps . ' Mbps',
                
                // Status info
                'status_color' => $this->getStatusColor($transfer->status),
                'status_text' => $this->getStatusText($transfer->status),
                
                // Playback info
                'can_play' => $transfer->status === 'completed' && !empty($transfer->drive_file_id),
                'stream_url' => $this->getStreamUrl($transfer),
                
                // Duration info
                'duration_formatted' => $this->formatDuration($transfer->started_at, $transfer->completed_at),
            ];
            
            return response()->json([
                'success' => true,
                'data' => $data
            ]);
            
        } catch (\Exception $e) {
            Log::error('Video Transfer API Show Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Video transfer not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }
    
    /**
     * Get Google Drive streaming URL for video playback
     * GET /api/video-transfers/{id}/stream-url
     */
    public function getStreamUrl($id)
    {
        try {
            if (is_object($id) && $id instanceof VideoTransfer) {
                $transfer = $id;
            } else {
                $transfer = VideoTransfer::findOrFail($id);
            }
            
            if ($transfer->status !== 'completed' || empty($transfer->drive_file_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Video transfer not completed or file ID missing'
                ], 400);
            }
            
            // Get access token
            $accessToken = $this->getGoogleDriveAccessToken();
            
            // Google Drive direct download URL with access token
            $streamUrl = "https://www.googleapis.com/drive/v3/files/{$transfer->drive_file_id}?alt=media";
            
            // Also get file metadata for additional info
            $metadataUrl = "https://www.googleapis.com/drive/v3/files/{$transfer->drive_file_id}?fields=name,size,mimeType,webContentLink,thumbnailLink";
            
            $metadataResponse = Http::withToken($accessToken)
                ->get($metadataUrl);
            
            $metadata = $metadataResponse->successful() ? $metadataResponse->json() : null;
            
            return is_object($id) ? $streamUrl : response()->json([
                'success' => true,
                'data' => [
                    'stream_url' => $streamUrl,
                    'access_token' => $accessToken,
                    'file_id' => $transfer->drive_file_id,
                    'metadata' => $metadata,
                    // Alternative URLs
                    'alternative_urls' => [
                        'embed' => "https://drive.google.com/file/d/{$transfer->drive_file_id}/preview",
                        'direct' => "https://drive.google.com/uc?export=download&id={$transfer->drive_file_id}",
                        'view' => "https://drive.google.com/file/d/{$transfer->drive_file_id}/view",
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Video Transfer Stream URL Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get stream URL',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Retry a failed transfer
     * POST /api/video-transfers/{id}/retry
     */
    public function retry($id)
    {
        try {
            $transfer = VideoTransfer::findOrFail($id);
            
            if ($transfer->status !== 'failed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Can only retry failed transfers'
                ], 400);
            }
            
            // Reset transfer
            $transfer->status = 'pending';
            $transfer->progress = 0;
            $transfer->error_message = null;
            $transfer->started_at = null;
            $transfer->completed_at = null;
            $transfer->save();
            
            // Start processing in background
            dispatch(function() use ($transfer) {
                $transfer->processTransfer();
            });
            
            return response()->json([
                'success' => true,
                'message' => 'Transfer retry started',
                'data' => [
                    'id' => $transfer->id,
                    'status' => $transfer->status,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Video Transfer Retry Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retry transfer',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get access token for Google Drive
     */
    private function getGoogleDriveAccessToken()
    {
        $clientId = env('GOOGLE_DRIVE_CLIENT_ID');
        $clientSecret = env('GOOGLE_DRIVE_CLIENT_SECRET');
        $refreshToken = env('GOOGLE_DRIVE_REFRESH_TOKEN');
        
        $response = Http::post('https://oauth2.googleapis.com/token', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);
        
        if ($response->failed()) {
            throw new \Exception('Failed to get Google Drive access token: ' . $response->body());
        }
        
        return $response->json()['access_token'];
    }
    
    // Helper methods
    private function formatBytes($bytes)
    {
        if ($bytes == 0) return '0 B';
        $k = 1024;
        $sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes) / log($k));
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }
    
    private function getStatusColor($status)
    {
        $colors = [
            'pending' => '#FFA500',
            'downloading' => '#2196F3',
            'uploading' => '#9C27B0',
            'completed' => '#4CAF50',
            'failed' => '#F44336',
        ];
        return $colors[$status] ?? '#757575';
    }
    
    private function getStatusText($status)
    {
        $texts = [
            'pending' => 'Pending',
            'downloading' => 'Downloading',
            'uploading' => 'Uploading',
            'completed' => 'Completed',
            'failed' => 'Failed',
        ];
        return $texts[$status] ?? ucfirst($status);
    }
    
    private function formatDuration($startedAt, $completedAt)
    {
        if (!$startedAt || !$completedAt) {
            return null;
        }
        
        $start = \Carbon\Carbon::parse($startedAt);
        $end = \Carbon\Carbon::parse($completedAt);
        
        return $start->diffForHumans($end, true);
    }
}
