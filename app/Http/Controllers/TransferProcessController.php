<?php

namespace App\Http\Controllers;

use App\Models\VideoTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TransferProcessController extends Controller
{
    /**
     * Show the transfer processing page
     */
    public function show($id)
    {
        $transfer = VideoTransfer::findOrFail($id);
        
        return view('transfer.process', compact('transfer'));
    }
    
    /**
     * Start the transfer process
     */
    public function start($id)
    {
        // Increase memory and execution time limits for large video transfers
        ini_set('memory_limit', '2048M'); // 2GB memory limit
        ini_set('max_execution_time', '3600'); // 1 hour
        set_time_limit(3600); // 1 hour
        
        try {
            $transfer = VideoTransfer::findOrFail($id);
            
            // Reset if it was previously failed
            if ($transfer->status === 'failed') {
                $transfer->status = 'pending';
                $transfer->progress = 0;
                $transfer->error_message = null;
                $transfer->retry_count = ($transfer->retry_count ?? 0) + 1;
                $transfer->save();
            }
            
            // Process the transfer
            $transfer->processTransfer();
            
            return response()->json([
                'success' => true,
                'message' => 'Transfer completed successfully',
                'transfer' => $transfer->fresh()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Transfer process error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get transfer status (AJAX endpoint)
     */
    public function status($id)
    {
        try {
            $transfer = VideoTransfer::findOrFail($id);
            
            return response()->json([
                'success' => true,
                'transfer' => [
                    'id' => $transfer->id,
                    'video_title' => $transfer->video_title,
                    'status' => $transfer->status,
                    'progress' => $transfer->progress ?? 0,
                    'source_url' => $transfer->source_url,
                    'drive_public_url' => $transfer->drive_public_url,
                    'drive_file_id' => $transfer->drive_file_id,
                    'source_size' => $transfer->source_size,
                    'mime_type' => $transfer->mime_type,
                    'duration_seconds' => $transfer->duration_seconds,
                    'transfer_speed' => $transfer->transfer_speed,
                    'error_message' => $transfer->error_message,
                    'retry_count' => $transfer->retry_count ?? 0,
                    'started_at' => $transfer->started_at,
                    'completed_at' => $transfer->completed_at,
                    'created_at' => $transfer->created_at,
                    'updated_at' => $transfer->updated_at,
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 404);
        }
    }
}
