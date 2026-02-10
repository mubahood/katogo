<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\SafemodeView;
use App\Traits\ApiResponser;
use App\Models\Utils;
use Illuminate\Http\Request;

/**
 * SafeMode Analytics Controller
 *
 * Endpoints:
 *   POST  /api/v2/safemode/track       — Record a view / play / like / mylist action
 *   POST  /api/v2/safemode/progress    — Save watch progress
 *   GET   /api/v2/safemode/progress/{external_video_id} — Get progress for a video
 *   GET   /api/v2/safemode/history     — Get user's SafeMode watch history
 */
class SafeModeAnalyticsController extends Controller
{
    use ApiResponser;

    // ───────────────────────────────────────────────
    //  POST /api/v2/safemode/track
    // ───────────────────────────────────────────────
    public function track(Request $request)
    {
        $user = Utils::get_user($request);
        if (!$user) return $this->error('Authentication required', 401);

        $request->validate([
            'external_video_id' => 'required|integer',
            'action'            => 'required|in:view,play,like,mylist',
            'video_title'       => 'nullable|string|max:500',
            'category'          => 'nullable|string|max:100',
            'genre'             => 'nullable|string|max:100',
        ]);

        $record = SafemodeView::updateOrCreate(
            [
                'user_id'           => $user->id,
                'external_video_id' => $request->input('external_video_id'),
                'action'            => $request->input('action'),
            ],
            [
                'video_title' => $request->input('video_title'),
                'category'    => $request->input('category'),
                'genre'       => $request->input('genre'),
                'device'      => $request->input('device', 'mobile'),
                'platform'    => 'safemode',
                'ip_address'  => $request->ip(),
            ]
        );

        return $this->success(['id' => $record->id], 'Tracked');
    }

    // ───────────────────────────────────────────────
    //  POST /api/v2/safemode/progress
    // ───────────────────────────────────────────────
    public function saveProgress(Request $request)
    {
        $user = Utils::get_user($request);
        if (!$user) return $this->error('Authentication required', 401);

        $request->validate([
            'external_video_id' => 'required|integer',
            'progress_seconds'  => 'required|numeric|min:0',
            'duration_seconds'  => 'required|numeric|min:0',
            'video_title'       => 'nullable|string|max:500',
        ]);

        $progress = floatval($request->input('progress_seconds'));
        $duration = floatval($request->input('duration_seconds'));
        $pct      = $duration > 0 ? round(($progress / $duration) * 100, 2) : 0;
        if ($pct > 100) $pct = 100;

        $existing = SafemodeView::where('user_id', $user->id)
            ->where('external_video_id', $request->input('external_video_id'))
            ->where('action', 'play')
            ->first();

        $maxProg = $existing ? max($progress, floatval($existing->max_progress_seconds)) : $progress;

        $record = SafemodeView::updateOrCreate(
            [
                'user_id'           => $user->id,
                'external_video_id' => $request->input('external_video_id'),
                'action'            => 'play',
            ],
            [
                'video_title'         => $request->input('video_title'),
                'progress_seconds'    => $progress,
                'duration_seconds'    => $duration,
                'max_progress_seconds'=> $maxProg,
                'percentage'          => $pct,
                'status'              => $pct >= 90 ? 'Completed' : 'Active',
                'device'              => $request->input('device', 'mobile'),
                'platform'            => 'safemode',
                'ip_address'          => $request->ip(),
            ]
        );

        return $this->success([
            'id'                   => $record->id,
            'progress_seconds'     => $progress,
            'max_progress_seconds' => $maxProg,
            'percentage'           => $pct,
            'status'               => $record->status,
        ], 'Progress saved');
    }

    // ───────────────────────────────────────────────
    //  GET /api/v2/safemode/progress/{external_video_id}
    // ───────────────────────────────────────────────
    public function getProgress(Request $request, int $externalVideoId)
    {
        $user = Utils::get_user($request);
        if (!$user) return $this->error('Authentication required', 401);

        $record = SafemodeView::where('user_id', $user->id)
            ->where('external_video_id', $externalVideoId)
            ->where('action', 'play')
            ->first();

        if (!$record) {
            return $this->success([
                'progress_seconds'     => 0,
                'max_progress_seconds' => 0,
                'duration_seconds'     => 0,
                'percentage'           => 0,
                'status'               => 'None',
            ], 'No progress');
        }

        return $this->success([
            'progress_seconds'     => $record->progress_seconds,
            'max_progress_seconds' => $record->max_progress_seconds,
            'duration_seconds'     => $record->duration_seconds,
            'percentage'           => $record->percentage,
            'status'               => $record->status,
        ], 'Progress found');
    }

    // ───────────────────────────────────────────────
    //  GET /api/v2/safemode/history
    // ───────────────────────────────────────────────
    public function history(Request $request)
    {
        $user = Utils::get_user($request);
        if (!$user) return $this->error('Authentication required', 401);

        $records = SafemodeView::where('user_id', $user->id)
            ->where('action', 'play')
            ->where('progress_seconds', '>', 0)
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get(['external_video_id', 'video_title', 'progress_seconds',
                    'duration_seconds', 'max_progress_seconds', 'percentage',
                    'status', 'updated_at']);

        return $this->success($records, 'SafeMode history');
    }
}
