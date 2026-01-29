<?php

namespace App\Http\Controllers;

use App\Models\CoinTransaction;
use App\Models\User;
use App\Models\Utils;
use Illuminate\Http\Request;

class CoinController extends Controller
{
    /**
     * Get current user's coin balance
     * GET /api/coins/balance
     */
    public function getBalance(Request $request)
    {
        $currentUser = Utils::get_user($request);
        if (!$currentUser) {
            return $this->error('Authentication required', 401);
        }

        $user = User::find($currentUser->id);
        
        return $this->success([
            'balance' => $user->game_coins_balance ?? 0,
            'total_games_played' => $user->total_games_played ?? 0,
            'total_games_won' => $user->total_games_won ?? 0,
        ], 'Balance retrieved');
    }

    /**
     * Get user's transaction history
     * GET /api/coins/history
     */
    public function getHistory(Request $request)
    {
        $currentUser = Utils::get_user($request);
        if (!$currentUser) {
            return $this->error('Authentication required', 401);
        }

        $limit = $request->input('limit', 50);
        $transactions = CoinTransaction::getUserHistory($currentUser->id, $limit);

        return $this->success([
            'transactions' => $transactions->map(function ($t) {
                return [
                    'id' => $t->id,
                    'amount' => $t->amount,
                    'balance_after' => $t->balance_after,
                    'type' => $t->type,
                    'description' => $t->description,
                    'game_session_id' => $t->game_session_id,
                    'created_at' => $t->created_at->toIso8601String(),
                ];
            }),
            'current_balance' => User::find($currentUser->id)->game_coins_balance ?? 0,
        ], 'History retrieved');
    }

    /**
     * Award offline win (called from Flutter for bot games)
     * POST /api/coins/award-offline-win
     */
    public function awardOfflineWin(Request $request)
    {
        $currentUser = Utils::get_user($request);
        if (!$currentUser) {
            return $this->error('Authentication required', 401);
        }

        $transaction = CoinTransaction::awardOfflineWin($currentUser->id);
        
        if (!$transaction) {
            return $this->error('Failed to award coins');
        }

        // Update games stats
        $user = User::find($currentUser->id);
        $user->total_games_played = ($user->total_games_played ?? 0) + 1;
        $user->total_games_won = ($user->total_games_won ?? 0) + 1;
        $user->save();

        return $this->success([
            'coins_awarded' => CoinTransaction::COINS_WIN_OFFLINE,
            'new_balance' => $transaction->balance_after,
            'transaction_id' => $transaction->id,
        ], 'Coins awarded for offline win!');
    }

    /**
     * Get leaderboard
     * GET /api/coins/leaderboard
     */
    public function getLeaderboard(Request $request)
    {
        $limit = $request->input('limit', 100);
        
        $leaders = User::where('game_coins_balance', '>', 0)
            ->orderBy('game_coins_balance', 'desc')
            ->limit($limit)
            ->get(['id', 'name', 'avatar', 'game_coins_balance', 'total_games_played', 'total_games_won']);

        return $this->success([
            'leaderboard' => $leaders->map(function ($user, $index) {
                // Convert relative avatar path to full URL
                $avatarUrl = null;
                if ($user->avatar && !empty($user->avatar)) {
                    if (str_starts_with($user->avatar, 'http')) {
                        $avatarUrl = $user->avatar;
                    } else {
                        $avatarUrl = url('storage/' . $user->avatar);
                    }
                }
                
                return [
                    'rank' => $index + 1,
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'avatar' => $avatarUrl,
                    'coins' => $user->game_coins_balance,
                    'games_played' => $user->total_games_played,
                    'games_won' => $user->total_games_won,
                ];
            }),
        ], 'Leaderboard retrieved');
    }

    // ========================================
    // HELPER METHODS
    // ========================================

    protected function success($data, $message = 'Success')
    {
        return response()->json([
            'code' => 1,
            'message' => $message,
            'data' => $data
        ]);
    }

    protected function error($message, $code = 400)
    {
        return response()->json([
            'code' => 0,
            'message' => $message,
            'data' => null
        ], $code);
    }
}
