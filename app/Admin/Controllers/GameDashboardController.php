<?php

namespace App\Admin\Controllers;

use App\Models\GameSession;
use App\Models\LudoSession;
use App\Models\GameInvitation;
use App\Models\CoinTransaction;
use App\Models\User;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Layout\Content;
use Encore\Admin\Layout\Row;
use Encore\Admin\Layout\Column;
use Encore\Admin\Widgets\Box;
use Encore\Admin\Widgets\InfoBox;
use Encore\Admin\Widgets\Table;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GameDashboardController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Game Dashboard';

    /**
     * Index interface.
     *
     * @param Content $content
     *
     * @return Content
     */
    public function index(Content $content)
    {
        return $content
            ->title('🎮 Game Dashboard')
            ->description('Complete overview of game module activity')
            ->row(function (Row $row) {
                // Total Games Stats
                $row->column(3, $this->totalGamesInfoBox());
                $row->column(3, $this->activeGamesInfoBox());
                $row->column(3, $this->totalInvitationsInfoBox());
                $row->column(3, $this->totalCoinsInfoBox());
            })
            ->row(function (Row $row) {
                // Game Type Breakdown
                $row->column(3, $this->matatuGamesInfoBox());
                $row->column(3, $this->ludoGamesInfoBox());
                $row->column(3, $this->completedGamesInfoBox());
                $row->column(3, $this->forfeitedGamesInfoBox());
            })
            ->row(function (Row $row) {
                // Tables side by side
                $row->column(6, $this->recentMatatuGamesBox());
                $row->column(6, $this->recentLudoGamesBox());
            })
            ->row(function (Row $row) {
                // More tables
                $row->column(6, $this->recentInvitationsBox());
                $row->column(6, $this->recentCoinTransactionsBox());
            })
            ->row(function (Row $row) {
                // Leaderboards
                $row->column(6, $this->topPlayersBox());
                $row->column(6, $this->gameStatisticsBox());
            });
    }

    /**
     * Total games info box
     */
    protected function totalGamesInfoBox()
    {
        $matatuCount = GameSession::count();
        $ludoCount = LudoSession::count();
        $total = $matatuCount + $ludoCount;

        return new InfoBox('Total Games', 'gamepad', 'aqua', '/admin/game-sessions', $total);
    }

    /**
     * Active games info box
     */
    protected function activeGamesInfoBox()
    {
        $matatuActive = GameSession::whereIn('status', ['waiting', 'active'])->count();
        $ludoActive = LudoSession::whereIn('status', ['pending', 'waiting', 'playing'])->count();
        $total = $matatuActive + $ludoActive;

        return new InfoBox('Active Games', 'play-circle', 'green', '/admin/game-sessions?status=active', $total);
    }

    /**
     * Total invitations info box
     */
    protected function totalInvitationsInfoBox()
    {
        $count = GameInvitation::count();
        $pending = GameInvitation::where('status', 'pending')
            ->where('expires_at', '>', now())
            ->count();

        return new InfoBox("Invitations ({$pending} pending)", 'envelope', 'yellow', '/admin/game-invitations', $count);
    }

    /**
     * Total coins info box
     */
    protected function totalCoinsInfoBox()
    {
        // Check if coin_transactions table exists and has data
        try {
            $totalCoins = CoinTransaction::where('amount', '>', 0)->sum('amount');
            $formatted = number_format($totalCoins);
        } catch (\Exception $e) {
            $formatted = '0';
        }

        return new InfoBox('Total Coins Awarded', 'bitcoin', 'orange', '/admin/coin-transactions', $formatted);
    }

    /**
     * Matatu games info box
     */
    protected function matatuGamesInfoBox()
    {
        $count = GameSession::count();
        $completed = GameSession::where('status', 'completed')->count();

        return new InfoBox("🃏 Matatu ({$completed} done)", 'th', 'purple', '/admin/game-sessions', $count);
    }

    /**
     * Ludo games info box
     */
    protected function ludoGamesInfoBox()
    {
        $count = LudoSession::count();
        $completed = LudoSession::where('status', 'completed')->count();

        return new InfoBox("🎲 Ludo ({$completed} done)", 'circle-o', 'maroon', '/admin/ludo-sessions', $count);
    }

    /**
     * Completed games info box
     */
    protected function completedGamesInfoBox()
    {
        $matatuCompleted = GameSession::where('status', 'completed')->count();
        $ludoCompleted = LudoSession::where('status', 'completed')->count();
        $total = $matatuCompleted + $ludoCompleted;

        return new InfoBox('Completed Games', 'check-circle', 'green', '#', $total);
    }

    /**
     * Forfeited games info box
     */
    protected function forfeitedGamesInfoBox()
    {
        $matatuForfeited = GameSession::whereIn('status', ['forfeited', 'abandoned'])->count();
        $ludoForfeited = LudoSession::whereIn('status', ['cancelled', 'expired'])->count();
        $total = $matatuForfeited + $ludoForfeited;

        return new InfoBox('Forfeited/Cancelled', 'times-circle', 'red', '#', $total);
    }

    /**
     * Recent Matatu games box
     */
    protected function recentMatatuGamesBox()
    {
        $games = GameSession::orderBy('id', 'desc')
            ->limit(10)
            ->get();

        $rows = [];
        foreach ($games as $game) {
            $player1 = User::find($game->player1_id);
            $player2 = User::find($game->player2_id);
            $winner = $game->winner_id ? User::find($game->winner_id) : null;
            
            $statusBadge = $this->getStatusBadge($game->status);
            
            $rows[] = [
                "#{$game->id}",
                $player1 ? $player1->name : 'N/A',
                $player2 ? $player2->name : 'N/A',
                $statusBadge,
                $winner ? "🏆 " . $winner->name : '-',
                $game->created_at->diffForHumans(),
            ];
        }

        $headers = ['ID', 'Player 1', 'Player 2', 'Status', 'Winner', 'Created'];
        $table = new Table($headers, $rows);

        $box = new Box('🃏 Recent Matatu Games', $table);
        $box->style('primary');
        $box->solid();

        return $box;
    }

    /**
     * Recent Ludo games box
     */
    protected function recentLudoGamesBox()
    {
        $games = LudoSession::orderBy('id', 'desc')
            ->limit(10)
            ->get();

        $rows = [];
        foreach ($games as $game) {
            $player1 = $game->player1_id ? User::find($game->player1_id) : null;
            $player2 = $game->player2_id ? User::find($game->player2_id) : null;
            $winner = $game->winner_user_id ? User::find($game->winner_user_id) : null;
            
            $statusBadge = $this->getLudoStatusBadge($game->status);
            $typeBadge = $game->game_type === '2_player' ? '2P' : '4P';
            
            $rows[] = [
                $game->session_code,
                $typeBadge,
                $player1 ? $player1->name : '-',
                $player2 ? $player2->name : '-',
                $statusBadge,
                $winner ? "🏆 " . $winner->name : '-',
            ];
        }

        $headers = ['Code', 'Type', 'Player 1', 'Player 2', 'Status', 'Winner'];
        $table = new Table($headers, $rows);

        $box = new Box('🎲 Recent Ludo Games', $table);
        $box->style('success');
        $box->solid();

        return $box;
    }

    /**
     * Recent invitations box
     */
    protected function recentInvitationsBox()
    {
        $invitations = GameInvitation::orderBy('id', 'desc')
            ->limit(10)
            ->get();

        $rows = [];
        foreach ($invitations as $inv) {
            $sender = User::find($inv->sender_id);
            $receiver = User::find($inv->receiver_id);
            
            $gameIcon = $inv->game_type === 'matatu' ? '🃏' : '🎲';
            $statusBadge = $this->getInvitationStatusBadge($inv->status);
            
            $rows[] = [
                "#{$inv->id}",
                $gameIcon . ' ' . ucfirst($inv->game_type),
                $sender ? $sender->name : 'N/A',
                $receiver ? $receiver->name : 'N/A',
                $statusBadge,
                $inv->created_at->diffForHumans(),
            ];
        }

        $headers = ['ID', 'Game', 'From', 'To', 'Status', 'Created'];
        $table = new Table($headers, $rows);

        $box = new Box('📨 Recent Invitations', $table);
        $box->style('warning');
        $box->solid();

        return $box;
    }

    /**
     * Recent coin transactions box
     */
    protected function recentCoinTransactionsBox()
    {
        $transactions = CoinTransaction::orderBy('id', 'desc')
            ->limit(10)
            ->get();

        $rows = [];
        foreach ($transactions as $tx) {
            $user = User::find($tx->user_id);
            
            $amountColor = $tx->amount >= 0 ? 'green' : 'red';
            $amountSign = $tx->amount >= 0 ? '+' : '';
            $amountFormatted = "<span style='color:{$amountColor};font-weight:bold'>{$amountSign}{$tx->amount}</span>";
            
            $typeLabels = [
                'game_win_online' => '🏆 Online Win',
                'game_win_offline' => '🎮 Offline Win',
                'game_forfeit' => '❌ Forfeit',
                'signup_bonus' => '🎉 Signup',
                'admin_adjustment' => '⚙️ Admin',
            ];
            $typeLabel = $typeLabels[$tx->type] ?? ucfirst(str_replace('_', ' ', $tx->type));
            
            $rows[] = [
                "#{$tx->id}",
                $user ? $user->name : 'N/A',
                $typeLabel,
                $amountFormatted,
                "🪙 {$tx->balance_after}",
                $tx->created_at->diffForHumans(),
            ];
        }

        $headers = ['ID', 'User', 'Type', 'Amount', 'Balance', 'Date'];
        $table = new Table($headers, $rows);

        $box = new Box('🪙 Recent Coin Transactions', $table);
        $box->style('info');
        $box->solid();

        return $box;
    }

    /**
     * Top players box
     */
    protected function topPlayersBox()
    {
        // Get top players by game wins (combining Matatu and Ludo wins)
        // First, get all winners from both game types
        $matatuWinners = GameSession::whereNotNull('winner_id')
            ->where('status', 'completed')
            ->selectRaw('winner_id as user_id, COUNT(*) as wins')
            ->groupBy('winner_id')
            ->get()
            ->keyBy('user_id');

        $ludoWinners = LudoSession::whereNotNull('winner_user_id')
            ->where('status', 'completed')
            ->selectRaw('winner_user_id as user_id, COUNT(*) as wins')
            ->groupBy('winner_user_id')
            ->get()
            ->keyBy('user_id');

        // Combine all unique user IDs
        $allUserIds = $matatuWinners->keys()->merge($ludoWinners->keys())->unique();

        // Calculate total wins for each user
        $userWins = [];
        foreach ($allUserIds as $userId) {
            $matatuCount = $matatuWinners->get($userId)->wins ?? 0;
            $ludoCount = $ludoWinners->get($userId)->wins ?? 0;
            $userWins[$userId] = [
                'total' => $matatuCount + $ludoCount,
                'matatu' => $matatuCount,
                'ludo' => $ludoCount,
            ];
        }

        // Sort by total wins descending and take top 10
        uasort($userWins, fn($a, $b) => $b['total'] - $a['total']);
        $topUserIds = array_slice(array_keys($userWins), 0, 10, true);

        // Get user details
        $users = DB::table('users')
            ->whereIn('id', $topUserIds)
            ->get(['id', 'name'])
            ->keyBy('id');

        // Check if coins column exists
        $hasCoinsColumn = DB::getSchemaBuilder()->hasColumn('users', 'coins');

        $rows = [];
        $rank = 1;
        foreach ($topUserIds as $userId) {
            $user = $users->get($userId);
            if (!$user) continue;

            $wins = $userWins[$userId];
            
            $medal = '';
            if ($rank === 1) $medal = '🥇 ';
            if ($rank === 2) $medal = '🥈 ';
            if ($rank === 3) $medal = '🥉 ';

            // Get coins if column exists
            $coinsDisplay = '-';
            if ($hasCoinsColumn) {
                $userCoins = DB::table('users')->where('id', $userId)->value('coins') ?? 0;
                $coinsDisplay = "🪙 " . number_format($userCoins);
            }
            
            $rows[] = [
                $medal . $rank,
                $user->name,
                "🏆 {$wins['total']}",
                "🃏 {$wins['matatu']} | 🎲 {$wins['ludo']}",
                $coinsDisplay,
            ];
            $rank++;
        }

        // If no winners yet, show a message
        if (empty($rows)) {
            $rows[] = ['-', 'No game winners yet', '-', '-', '-'];
        }

        $headers = ['Rank', 'Player', 'Total Wins', 'Breakdown', 'Coins'];
        $table = new Table($headers, $rows);

        $box = new Box('🏆 Top Players Leaderboard', $table);
        $box->style('danger');
        $box->solid();

        return $box;
    }

    /**
     * Game statistics box
     */
    protected function gameStatisticsBox()
    {
        // Calculate various statistics
        $today = Carbon::today();
        $thisWeek = Carbon::now()->startOfWeek();
        $thisMonth = Carbon::now()->startOfMonth();

        // Today's games
        $matatuToday = GameSession::whereDate('created_at', $today)->count();
        $ludoToday = LudoSession::whereDate('created_at', $today)->count();

        // This week's games
        $matatuWeek = GameSession::where('created_at', '>=', $thisWeek)->count();
        $ludoWeek = LudoSession::where('created_at', '>=', $thisWeek)->count();

        // This month's games
        $matatuMonth = GameSession::where('created_at', '>=', $thisMonth)->count();
        $ludoMonth = LudoSession::where('created_at', '>=', $thisMonth)->count();

        // Average game duration (completed games only)
        $avgMatatuDuration = GameSession::where('status', 'completed')
            ->whereNotNull('started_at')
            ->whereNotNull('ended_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, started_at, ended_at)) as avg_minutes')
            ->value('avg_minutes');

        $avgLudoDuration = LudoSession::where('status', 'completed')
            ->whereNotNull('started_at')
            ->whereNotNull('ended_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, started_at, ended_at)) as avg_minutes')
            ->value('avg_minutes');

        // Coins today
        $coinsToday = CoinTransaction::whereDate('created_at', $today)
            ->where('amount', '>', 0)
            ->sum('amount');

        $rows = [
            ['📅 Games Today', "🃏 {$matatuToday}", "🎲 {$ludoToday}", ($matatuToday + $ludoToday) . " total"],
            ['📆 Games This Week', "🃏 {$matatuWeek}", "🎲 {$ludoWeek}", ($matatuWeek + $ludoWeek) . " total"],
            ['📆 Games This Month', "🃏 {$matatuMonth}", "🎲 {$ludoMonth}", ($matatuMonth + $ludoMonth) . " total"],
            ['⏱️ Avg Game Duration', 
                "🃏 " . ($avgMatatuDuration ? round($avgMatatuDuration) . " min" : "N/A"), 
                "🎲 " . ($avgLudoDuration ? round($avgLudoDuration) . " min" : "N/A"), 
                "-"
            ],
            ['🪙 Coins Awarded Today', number_format($coinsToday), '-', '-'],
            ['📊 Completion Rate', 
                "🃏 " . $this->getCompletionRate('matatu') . "%",
                "🎲 " . $this->getCompletionRate('ludo') . "%",
                "-"
            ],
        ];

        $headers = ['Metric', 'Matatu', 'Ludo', 'Total'];
        $table = new Table($headers, $rows);

        $box = new Box('📊 Game Statistics', $table);
        $box->style('default');
        $box->solid();

        return $box;
    }

    /**
     * Get completion rate for a game type
     */
    protected function getCompletionRate($type)
    {
        if ($type === 'matatu') {
            $total = GameSession::count();
            if ($total === 0) return 0;
            $completed = GameSession::where('status', 'completed')->count();
            return round(($completed / $total) * 100, 1);
        } else {
            $total = LudoSession::count();
            if ($total === 0) return 0;
            $completed = LudoSession::where('status', 'completed')->count();
            return round(($completed / $total) * 100, 1);
        }
    }

    /**
     * Get status badge HTML
     */
    protected function getStatusBadge($status)
    {
        $badges = [
            'waiting' => '<span class="label label-warning">Waiting</span>',
            'active' => '<span class="label label-primary">Active</span>',
            'completed' => '<span class="label label-success">Completed</span>',
            'abandoned' => '<span class="label label-danger">Abandoned</span>',
            'forfeited' => '<span class="label label-default">Forfeited</span>',
        ];
        return $badges[$status] ?? "<span class='label label-default'>{$status}</span>";
    }

    /**
     * Get Ludo status badge HTML
     */
    protected function getLudoStatusBadge($status)
    {
        $badges = [
            'pending' => '<span class="label label-default">Pending</span>',
            'waiting' => '<span class="label label-warning">Waiting</span>',
            'playing' => '<span class="label label-primary">Playing</span>',
            'completed' => '<span class="label label-success">Completed</span>',
            'cancelled' => '<span class="label label-danger">Cancelled</span>',
            'expired' => '<span class="label label-default">Expired</span>',
        ];
        return $badges[$status] ?? "<span class='label label-default'>{$status}</span>";
    }

    /**
     * Get invitation status badge HTML
     */
    protected function getInvitationStatusBadge($status)
    {
        $badges = [
            'pending' => '<span class="label label-warning">Pending</span>',
            'accepted' => '<span class="label label-success">Accepted</span>',
            'declined' => '<span class="label label-danger">Declined</span>',
            'expired' => '<span class="label label-default">Expired</span>',
            'cancelled' => '<span class="label label-default">Cancelled</span>',
        ];
        return $badges[$status] ?? "<span class='label label-default'>{$status}</span>";
    }
}
