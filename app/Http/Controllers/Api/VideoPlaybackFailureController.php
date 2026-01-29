<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VideoPlaybackFailure;
use App\Models\MovieModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class VideoPlaybackFailureController extends Controller
{
    /**
     * Store a new video playback failure report
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|integer',
            'user_name' => 'nullable|string|max:255',
            'user_email' => 'nullable|email|max:255',
            'user_phone' => 'nullable|string|max:50',
            
            'movie_id' => 'nullable|integer',
            'movie_title' => 'nullable|string|max:500',
            'original_url' => 'nullable|string',
            'transformed_url' => 'nullable|string',
            
            'error_message' => 'nullable|string',
            'error_code' => 'nullable|string|max:50',
            'error_type' => 'nullable|string|max:50',
            'retry_count' => 'nullable|integer|min:0',
            
            'device_model' => 'nullable|string|max:100',
            'device_os' => 'nullable|string|max:50',
            'device_os_version' => 'nullable|string|max:50',
            'app_version' => 'nullable|string|max:20',
            'player_type' => 'nullable|string|max:50',
            
            'network_type' => 'nullable|string|max:50',
            'user_agent' => 'nullable|string',
            
            'has_subscription' => 'nullable|boolean',
            'subscription_type' => 'nullable|string|max:100',
            'subscription_expires_at' => 'nullable|date',
            
            'screen_name' => 'nullable|string|max:100',
            'additional_data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Create the failure record
            $failure = VideoPlaybackFailure::create([
                // User information
                'user_id' => $request->input('user_id'),
                'user_name' => $request->input('user_name'),
                'user_email' => $request->input('user_email'),
                'user_phone' => $request->input('user_phone'),
                
                // Movie information
                'movie_id' => $request->input('movie_id'),
                'movie_title' => $request->input('movie_title'),
                'original_url' => $request->input('original_url'),
                'transformed_url' => $request->input('transformed_url'),
                
                // Failure details
                'error_message' => $request->input('error_message'),
                'error_code' => $request->input('error_code'),
                'error_type' => $request->input('error_type', 'unknown'),
                'retry_count' => $request->input('retry_count', 0),
                
                // Device & App information
                'device_model' => $request->input('device_model'),
                'device_os' => $request->input('device_os'),
                'device_os_version' => $request->input('device_os_version'),
                'app_version' => $request->input('app_version'),
                'player_type' => $request->input('player_type'),
                
                // Network information
                'network_type' => $request->input('network_type'),
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
                
                // Subscription status
                'has_subscription' => $request->input('has_subscription', false),
                'subscription_type' => $request->input('subscription_type'),
                'subscription_expires_at' => $request->input('subscription_expires_at'),
                
                // Context
                'screen_name' => $request->input('screen_name'),
                'additional_data' => $request->input('additional_data'),
                
                // Default status
                'status' => 'pending',
            ]);

            // Note: Movie is automatically deactivated via the model's boot() hook
            // But we also log it here for visibility
            $movieDeactivated = false;
            $movieId = $request->input('movie_id');
            if ($movieId) {
                $movie = MovieModel::find($movieId);
                if ($movie) {
                    $movieDeactivated = ($movie->status === 'Inactive');
                    Log::info("Video playback failure reported for movie #{$movieId}. Movie status: {$movie->status}");
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Failure report recorded successfully',
                'data' => [
                    'id' => $failure->id,
                    'created_at' => $failure->created_at,
                    'movie_deactivated' => $movieDeactivated,
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to record failure report',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get statistics about video playback failures
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function statistics(Request $request)
    {
        try {
            $days = $request->input('days', 7);

            $stats = [
                'total_failures' => VideoPlaybackFailure::recent($days)->count(),
                'pending_failures' => VideoPlaybackFailure::recent($days)->pending()->count(),
                'subscribed_users_failures' => VideoPlaybackFailure::recent($days)->subscribed()->count(),
                'most_common_errors' => VideoPlaybackFailure::getMostCommonErrors(5),
                'movies_with_most_failures' => VideoPlaybackFailure::getMoviesWithMostFailures(10),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'period_days' => $days,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
