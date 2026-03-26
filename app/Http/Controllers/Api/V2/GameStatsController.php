<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\GameStat;
use App\Traits\ApiResponser;
use App\Utils\Utils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class GameStatsController extends Controller
{
    use ApiResponser;

    private const VALID_GAME_TYPES = ['matatu', 'chess', 'ludo', 'draft', 'trivia'];

    /**
     * GET /api/v2/game-stats
     * Retrieve authenticated user's stats for all games (or a specific game_type).
     */
    public function index(Request $request)
    {
        $user = Utils::get_user($request);
        if (!$user) {
            return $this->error('Authentication required', 401);
        }

        $gameType = $request->query('game_type');

        $query = GameStat::where('user_id', $user->id);

        if ($gameType && in_array($gameType, self::VALID_GAME_TYPES, true)) {
            $query->where('game_type', $gameType);
        }

        $stats = $query->get()->keyBy('game_type');

        return $this->success($stats, 'Game stats retrieved');
    }

    /**
     * POST /api/v2/game-stats/sync
     * Batch upsert stats from offline device.
     *
     * Expected body: { "stats": [ { "game_type", "games_played", "wins", "losses", "draws", "high_score", "total_play_seconds", "last_played_at" }, ... ] }
     */
    public function sync(Request $request)
    {
        $user = Utils::get_user($request);
        if (!$user) {
            return $this->error('Authentication required', 401);
        }

        // Accept either stats array or stats_json string (FormData compat)
        $stats = $request->input('stats');
        if (!$stats && $request->has('stats_json')) {
            $stats = json_decode($request->input('stats_json'), true);
            if (!is_array($stats)) {
                return $this->error('Invalid stats_json format', 422);
            }
            $request->merge(['stats' => $stats]);
        }

        $validator = Validator::make($request->all(), [
            'stats'                      => 'required|array|max:10',
            'stats.*.game_type'          => 'required|string|in:' . implode(',', self::VALID_GAME_TYPES),
            'stats.*.games_played'       => 'required|integer|min:0|max:999999',
            'stats.*.wins'               => 'required|integer|min:0|max:999999',
            'stats.*.losses'             => 'required|integer|min:0|max:999999',
            'stats.*.draws'              => 'required|integer|min:0|max:999999',
            'stats.*.high_score'         => 'sometimes|integer|min:0|max:999999',
            'stats.*.total_play_seconds' => 'sometimes|integer|min:0|max:99999999',
            'stats.*.last_played_at'     => 'sometimes|nullable|date',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $synced = [];

        foreach ($request->input('stats') as $item) {
            try {
                $stat = GameStat::updateOrCreate(
                    [
                        'user_id'   => $user->id,
                        'game_type' => $item['game_type'],
                    ],
                    [
                        'games_played'       => max($item['games_played'] ?? 0, 0),
                        'wins'               => max($item['wins'] ?? 0, 0),
                        'losses'             => max($item['losses'] ?? 0, 0),
                        'draws'              => max($item['draws'] ?? 0, 0),
                        'high_score'         => max($item['high_score'] ?? 0, 0),
                        'total_play_seconds' => max($item['total_play_seconds'] ?? 0, 0),
                        'last_played_at'     => $item['last_played_at'] ?? now(),
                    ]
                );

                $synced[] = $stat->game_type;
            } catch (\Exception $e) {
                Log::warning('GameStats sync error', [
                    'user_id'   => $user->id,
                    'game_type' => $item['game_type'] ?? 'unknown',
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        // Also update the user-level totals for backward compatibility
        $totals = GameStat::where('user_id', $user->id)->selectRaw(
            'SUM(games_played) as total_played, SUM(wins) as total_won'
        )->first();

        if ($totals) {
            $user->total_games_played = $totals->total_played ?? 0;
            $user->total_games_won    = $totals->total_won ?? 0;
            $user->save();
        }

        return $this->success([
            'synced'     => $synced,
            'server_at'  => now()->toISOString(),
        ], 'Stats synced successfully');
    }
}
