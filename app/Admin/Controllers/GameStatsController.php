<?php

namespace App\Admin\Controllers;

use App\Models\GameStat;
use App\Models\User;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Layout\Content;
use Encore\Admin\Layout\Row;
use Encore\Admin\Layout\Column;
use Encore\Admin\Widgets\Box;
use Encore\Admin\Widgets\InfoBox;
use Encore\Admin\Widgets\Table;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GameStatsController extends AdminController
{
    protected $title = 'Offline Game Statistics';

    private const GAME_ICONS = [
        'matatu' => '🃏',
        'chess'  => '♟️',
        'ludo'   => '🎲',
        'draft'  => '⛀',
        'trivia' => '🧠',
    ];

    private const GAME_COLORS = [
        'matatu' => 'orange',
        'chess'  => 'maroon',
        'ludo'   => 'blue',
        'draft'  => 'green',
        'trivia' => 'purple',
    ];

    private const GAME_LABELS = [
        'matatu' => 'Matatu',
        'chess'  => 'Chess',
        'ludo'   => 'Ludo',
        'draft'  => 'Draft',
        'trivia' => 'Trivia',
    ];

    // ─── Dashboard ─────────────────────────────────────

    public function index(Content $content)
    {
        return $content
            ->title('🎮 Offline Game Statistics')
            ->description('Detailed analytics for all offline game modes')
            ->row(function (Row $row) {
                $row->column(4, $this->totalPlayersInfoBox());
                $row->column(4, $this->totalGamesInfoBox());
                $row->column(4, $this->totalPlayTimeInfoBox());
            })
            ->row(function (Row $row) {
                foreach (self::GAME_LABELS as $type => $label) {
                    $icon = self::GAME_ICONS[$type];
                    $color = self::GAME_COLORS[$type];
                    $stat = GameStat::where('game_type', $type);
                    $players = (clone $stat)->count();
                    $games   = (clone $stat)->sum('games_played');
                    $wins    = (clone $stat)->sum('wins');

                    $row->column(2, new InfoBox(
                        "{$icon} {$label}",
                        'gamepad',
                        $color,
                        "/admin/game-stats/records?game_type={$type}",
                        "{$games} games · {$players} players"
                    ));
                }
                // 6th column: win rate overview
                $totalGames = GameStat::sum('games_played');
                $totalWins  = GameStat::sum('wins');
                $winRate    = $totalGames > 0 ? round(($totalWins / $totalGames) * 100, 1) : 0;
                $row->column(2, new InfoBox(
                    "🏆 Avg Win Rate",
                    'trophy',
                    'red',
                    '/admin/game-stats/records',
                    "{$winRate}%"
                ));
            })
            ->row(function (Row $row) {
                $row->column(12, $this->perGameBreakdownBox());
            })
            ->row(function (Row $row) {
                $row->column(6, $this->topPlayersBox());
                $row->column(6, $this->timelineBox());
            })
            ->row(function (Row $row) {
                $row->column(6, $this->highScoresBox());
                $row->column(6, $this->mostActivePlayersBox());
            })
            ->row(function (Row $row) {
                $row->column(12, $this->recentActivityBox());
            });
    }

    // ─── Info Boxes ────────────────────────────────────

    protected function totalPlayersInfoBox()
    {
        $count = GameStat::distinct('user_id')->count('user_id');
        return new InfoBox('Total Players', 'users', 'aqua', '/admin/game-stats/records', $count);
    }

    protected function totalGamesInfoBox()
    {
        $total = GameStat::sum('games_played');
        return new InfoBox('Total Games Played', 'gamepad', 'green', '/admin/game-stats/records', number_format($total));
    }

    protected function totalPlayTimeInfoBox()
    {
        $seconds = GameStat::sum('total_play_seconds');
        $hours   = round($seconds / 3600, 1);
        return new InfoBox('Total Play Time', 'clock-o', 'yellow', '#', "{$hours} hrs");
    }

    // ─── Per-Game Breakdown Table ──────────────────────

    protected function perGameBreakdownBox()
    {
        $rows = [];
        foreach (self::GAME_LABELS as $type => $label) {
            $icon  = self::GAME_ICONS[$type];
            $stats = GameStat::where('game_type', $type);
            $players     = (clone $stats)->count();
            $gamesPlayed = (clone $stats)->sum('games_played');
            $wins        = (clone $stats)->sum('wins');
            $losses      = (clone $stats)->sum('losses');
            $draws       = (clone $stats)->sum('draws');
            $highScore   = (clone $stats)->max('high_score');
            $totalSecs   = (clone $stats)->sum('total_play_seconds');
            $avgSecs     = $players > 0 ? round($totalSecs / $players) : 0;
            $winRate     = $gamesPlayed > 0 ? round(($wins / $gamesPlayed) * 100, 1) : 0;

            $winRateColor = $winRate >= 50 ? 'green' : ($winRate >= 30 ? 'orange' : 'red');

            $rows[] = [
                "{$icon} <strong>{$label}</strong>",
                number_format($players),
                number_format($gamesPlayed),
                "<span style='color:green;font-weight:bold'>{$wins}</span>",
                "<span style='color:red;font-weight:bold'>{$losses}</span>",
                "<span style='color:gray;font-weight:bold'>{$draws}</span>",
                "<span style='color:{$winRateColor};font-weight:bold'>{$winRate}%</span>",
                number_format($highScore ?? 0),
                $this->formatDuration($totalSecs),
                $this->formatDuration($avgSecs) . '/player',
            ];
        }

        // Totals row
        $tPlayers = GameStat::distinct('user_id')->count('user_id');
        $tGames   = GameStat::sum('games_played');
        $tWins    = GameStat::sum('wins');
        $tLosses  = GameStat::sum('losses');
        $tDraws   = GameStat::sum('draws');
        $tHigh    = GameStat::max('high_score');
        $tSecs    = GameStat::sum('total_play_seconds');
        $tRate    = $tGames > 0 ? round(($tWins / $tGames) * 100, 1) : 0;

        $rows[] = [
            '<strong>TOTAL</strong>',
            "<strong>{$tPlayers}</strong>",
            "<strong>" . number_format($tGames) . "</strong>",
            "<strong style='color:green'>{$tWins}</strong>",
            "<strong style='color:red'>{$tLosses}</strong>",
            "<strong style='color:gray'>{$tDraws}</strong>",
            "<strong>{$tRate}%</strong>",
            "<strong>" . number_format($tHigh ?? 0) . "</strong>",
            "<strong>{$this->formatDuration($tSecs)}</strong>",
            '-',
        ];

        $headers = ['Game', 'Players', 'Played', 'Wins', 'Losses', 'Draws', 'Win Rate', 'Best Score', 'Total Time', 'Avg Time'];
        $table = new Table($headers, $rows);

        $box = new Box('📊 Per-Game Breakdown', $table);
        $box->style('primary');
        $box->solid();

        return $box;
    }

    // ─── Top Players (by total wins) ───────────────────

    protected function topPlayersBox()
    {
        $topPlayers = GameStat::select('user_id')
            ->selectRaw('SUM(wins) as total_wins')
            ->selectRaw('SUM(games_played) as total_games')
            ->selectRaw('SUM(losses) as total_losses')
            ->selectRaw('SUM(draws) as total_draws')
            ->selectRaw('MAX(high_score) as best_score')
            ->groupBy('user_id')
            ->orderByDesc('total_wins')
            ->limit(15)
            ->get();

        $userIds = $topPlayers->pluck('user_id')->toArray();
        $users   = User::whereIn('id', $userIds)->get(['id', 'name'])->keyBy('id');

        $rows = [];
        $rank = 1;
        foreach ($topPlayers as $p) {
            $user = $users->get($p->user_id);
            if (!$user) continue;

            $medal = match ($rank) {
                1 => '🥇',
                2 => '🥈',
                3 => '🥉',
                default => "#{$rank}",
            };

            $winRate = $p->total_games > 0 ? round(($p->total_wins / $p->total_games) * 100, 1) : 0;

            // per-game breakdown
            $breakdown = GameStat::where('user_id', $p->user_id)->get();
            $parts = [];
            foreach ($breakdown as $b) {
                $icon = self::GAME_ICONS[$b->game_type] ?? '🎮';
                $parts[] = "{$icon}{$b->wins}W";
            }

            $rows[] = [
                $medal,
                $user->name . " <small style='color:#999'>#{$user->id}</small>",
                "<strong>{$p->total_wins}</strong>",
                "{$p->total_games}",
                "{$winRate}%",
                number_format($p->best_score ?? 0),
                implode(' ', $parts),
            ];
            $rank++;
        }

        if (empty($rows)) {
            $rows[] = ['-', 'No players yet', '-', '-', '-', '-', '-'];
        }

        $headers = ['Rank', 'Player', 'Wins', 'Games', 'Win%', 'Best Score', 'Breakdown'];
        $table = new Table($headers, $rows);

        $box = new Box('🏆 Top Players by Wins', $table);
        $box->style('danger');
        $box->solid();

        return $box;
    }

    // ─── Activity Timeline (Last 14 Days) ─────────────

    protected function timelineBox()
    {
        $rows = [];
        for ($i = 0; $i < 14; $i++) {
            $date  = Carbon::today()->subDays($i);
            $label = $i === 0 ? 'Today' : ($i === 1 ? 'Yesterday' : $date->format('M d'));

            $stats = GameStat::whereDate('last_played_at', $date);
            $activePlayers = (clone $stats)->count();

            if ($activePlayers === 0) {
                $rows[] = [$label, '0', '-', '-', '-'];
                continue;
            }

            // For a proper "games played today" we'd need per-day logs.
            // Using active player count as the primary metric since game_stats stores cumulative totals.
            $games = [];
            foreach (self::GAME_LABELS as $type => $gameLabel) {
                $c = GameStat::where('game_type', $type)->whereDate('last_played_at', $date)->count();
                if ($c > 0) {
                    $games[] = self::GAME_ICONS[$type] . " {$c}";
                }
            }

            $rows[] = [
                "<strong>{$label}</strong>",
                $activePlayers,
                implode('  ', $games) ?: '-',
                $date->format('D'),
                $date->format('Y-m-d'),
            ];
        }

        $headers = ['Day', 'Active Players', 'By Game', 'Weekday', 'Date'];
        $table = new Table($headers, $rows);

        $box = new Box('📅 Activity Timeline (Last 14 Days)', $table);
        $box->style('info');
        $box->solid();

        return $box;
    }

    // ─── High Scores Leaderboard ──────────────────────

    protected function highScoresBox()
    {
        $rows = [];
        foreach (self::GAME_LABELS as $type => $label) {
            $icon = self::GAME_ICONS[$type];
            $topScorers = GameStat::where('game_type', $type)
                ->where('high_score', '>', 0)
                ->orderByDesc('high_score')
                ->limit(3)
                ->get();

            if ($topScorers->isEmpty()) {
                $rows[] = ["{$icon} {$label}", '-', '-', '-'];
                continue;
            }

            $userIds = $topScorers->pluck('user_id')->toArray();
            $users   = User::whereIn('id', $userIds)->get(['id', 'name'])->keyBy('id');

            $medals = ['🥇', '🥈', '🥉'];
            $scoreParts = [];
            foreach ($topScorers as $i => $s) {
                $user = $users->get($s->user_id);
                $name = $user ? $user->name : "#{$s->user_id}";
                $scoreParts[] = ($medals[$i] ?? '') . " {$name}: <strong>{$s->high_score}</strong>";
            }

            $rows[] = [
                "{$icon} <strong>{$label}</strong>",
                $scoreParts[0] ?? '-',
                $scoreParts[1] ?? '-',
                $scoreParts[2] ?? '-',
            ];
        }

        $headers = ['Game', '1st Place', '2nd Place', '3rd Place'];
        $table = new Table($headers, $rows);

        $box = new Box('🏅 High Score Leaderboards', $table);
        $box->style('warning');
        $box->solid();

        return $box;
    }

    // ─── Most Active Players (by play time) ───────────

    protected function mostActivePlayersBox()
    {
        $players = GameStat::select('user_id')
            ->selectRaw('SUM(total_play_seconds) as total_secs')
            ->selectRaw('SUM(games_played) as total_games')
            ->selectRaw('COUNT(DISTINCT game_type) as game_types_played')
            ->groupBy('user_id')
            ->orderByDesc('total_secs')
            ->limit(10)
            ->get();

        $userIds = $players->pluck('user_id')->toArray();
        $users   = User::whereIn('id', $userIds)->get(['id', 'name'])->keyBy('id');

        $rows = [];
        $rank = 1;
        foreach ($players as $p) {
            $user = $users->get($p->user_id);
            if (!$user) continue;

            $rows[] = [
                "#{$rank}",
                $user->name . " <small style='color:#999'>#{$user->id}</small>",
                $this->formatDuration($p->total_secs),
                number_format($p->total_games),
                "{$p->game_types_played}/5 games",
            ];
            $rank++;
        }

        if (empty($rows)) {
            $rows[] = ['-', 'No data yet', '-', '-', '-'];
        }

        $headers = ['Rank', 'Player', 'Play Time', 'Games', 'Variety'];
        $table = new Table($headers, $rows);

        $box = new Box('⏱️ Most Active Players (by Play Time)', $table);
        $box->style('success');
        $box->solid();

        return $box;
    }

    // ─── Recent Activity ──────────────────────────────

    protected function recentActivityBox()
    {
        $recent = GameStat::orderByDesc('updated_at')
            ->limit(20)
            ->get();

        $userIds = $recent->pluck('user_id')->unique()->toArray();
        $users   = User::whereIn('id', $userIds)->get(['id', 'name'])->keyBy('id');

        $rows = [];
        foreach ($recent as $s) {
            $user = $users->get($s->user_id);
            $icon = self::GAME_ICONS[$s->game_type] ?? '🎮';
            $label = self::GAME_LABELS[$s->game_type] ?? ucfirst($s->game_type);

            $winRate = $s->games_played > 0 ? round(($s->wins / $s->games_played) * 100) : 0;

            $rows[] = [
                $user ? $user->name : "#{$s->user_id}",
                "{$icon} {$label}",
                $s->games_played,
                "<span style='color:green'>{$s->wins}W</span> / <span style='color:red'>{$s->losses}L</span> / <span style='color:gray'>{$s->draws}D</span>",
                "{$winRate}%",
                number_format($s->high_score),
                $this->formatDuration($s->total_play_seconds),
                $s->last_played_at ? Carbon::parse($s->last_played_at)->diffForHumans() : '-',
            ];
        }

        if (empty($rows)) {
            $rows[] = ['-', '-', '-', '-', '-', '-', '-', '-'];
        }

        $headers = ['Player', 'Game', 'Played', 'W/L/D', 'Win%', 'High Score', 'Time', 'Last Played'];
        $table = new Table($headers, $rows);

        $box = new Box('🕐 Recent Activity (Last Updated Records)', $table);
        $box->style('default');
        $box->solid();

        return $box;
    }

    // ─── Grid View (Browsable Records) ────────────────

    public function records(Content $content)
    {
        return $content
            ->title('🎮 Game Stats Records')
            ->description('Browse and search individual player game stats')
            ->body($this->grid());
    }

    protected function grid()
    {
        $grid = new Grid(new GameStat());
        $grid->model()->orderBy('updated_at', 'desc');

        $grid->column('id', 'ID')->sortable();

        $grid->column('user_id', 'Player')
            ->display(function ($userId) {
                $user = User::find($userId);
                return $user ? "{$user->name} <small style='color:#999'>#{$user->id}</small>" : "#{$userId}";
            });

        $grid->column('game_type', 'Game')
            ->display(function ($type) {
                $icons = [
                    'matatu' => '🃏 Matatu',
                    'chess'  => '♟️ Chess',
                    'ludo'   => '🎲 Ludo',
                    'draft'  => '⛀ Draft',
                    'trivia' => '🧠 Trivia',
                ];
                return $icons[$type] ?? ucfirst($type);
            })
            ->label([
                'matatu' => 'warning',
                'chess'  => 'default',
                'ludo'   => 'primary',
                'draft'  => 'success',
                'trivia' => 'info',
            ])
            ->sortable();

        $grid->column('games_played', 'Played')->sortable();

        $grid->column('wins', 'Wins')
            ->display(function ($v) {
                return "<span style='color:green;font-weight:bold'>{$v}</span>";
            })->sortable();

        $grid->column('losses', 'Losses')
            ->display(function ($v) {
                return "<span style='color:red;font-weight:bold'>{$v}</span>";
            })->sortable();

        $grid->column('draws', 'Draws')
            ->display(function ($v) {
                return "<span style='color:gray;font-weight:bold'>{$v}</span>";
            })->sortable();

        $grid->column('win_rate', 'Win%')
            ->display(function () {
                if ($this->games_played == 0) return '-';
                $rate = round(($this->wins / $this->games_played) * 100, 1);
                $color = $rate >= 60 ? 'green' : ($rate >= 40 ? 'orange' : 'red');
                return "<span style='color:{$color};font-weight:bold'>{$rate}%</span>";
            })->sortable('wins');

        $grid->column('high_score', 'Best Score')
            ->display(function ($v) {
                return $v > 0 ? "<strong>{$v}</strong>" : '-';
            })->sortable();

        $grid->column('total_play_seconds', 'Play Time')
            ->display(function ($secs) {
                if ($secs < 60) return "{$secs}s";
                if ($secs < 3600) return round($secs / 60) . 'm';
                return round($secs / 3600, 1) . 'h';
            })->sortable();

        $grid->column('last_played_at', 'Last Played')
            ->display(function ($v) {
                return $v ? Carbon::parse($v)->diffForHumans() : '-';
            })->sortable();

        $grid->column('updated_at', 'Synced')
            ->display(function ($v) {
                return $v ? Carbon::parse($v)->diffForHumans() : '-';
            })->sortable();

        // Filters
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();

            $filter->equal('user_id', 'User ID');

            $filter->equal('game_type', 'Game')->select([
                'matatu' => '🃏 Matatu',
                'chess'  => '♟️ Chess',
                'ludo'   => '🎲 Ludo',
                'draft'  => '⛀ Draft',
                'trivia' => '🧠 Trivia',
            ]);

            $filter->between('games_played', 'Games Played');
            $filter->between('wins', 'Wins');

            $filter->where(function ($query) {
                if ($this->input === 'high') {
                    $query->whereRaw('wins > losses');
                } elseif ($this->input === 'low') {
                    $query->whereRaw('losses > wins');
                } elseif ($this->input === 'even') {
                    $query->whereRaw('wins = losses');
                }
            }, 'Performance')->select([
                'high' => 'More Wins than Losses',
                'low'  => 'More Losses than Wins',
                'even' => 'Equal Wins & Losses',
            ]);

            $filter->between('last_played_at', 'Last Played')->datetime();
        });

        // Disable create/delete — stats are synced from devices
        $grid->disableCreateButton();
        $grid->actions(function ($actions) {
            $actions->disableDelete();
            $actions->disableEdit();
        });

        $grid->export(function ($export) {
            $export->filename('game-stats-export');
        });

        return $grid;
    }

    // ─── Detail View ──────────────────────────────────

    public function show($id, Content $content)
    {
        return $content
            ->title('Player Game Stat')
            ->description('Detailed view')
            ->body($this->detail($id));
    }

    protected function detail($id)
    {
        $show = new Show(GameStat::findOrFail($id));

        $show->field('id', 'ID');

        $show->field('user_id', 'Player')->as(function ($userId) {
            $user = User::find($userId);
            return $user ? "{$user->name} (ID: {$user->id}, Email: {$user->email})" : "User #{$userId}";
        });

        $show->divider();

        $show->field('game_type', 'Game')->as(function ($type) {
            $icons = [
                'matatu' => '🃏 Matatu',
                'chess'  => '♟️ Chess',
                'ludo'   => '🎲 Ludo',
                'draft'  => '⛀ Draft',
                'trivia' => '🧠 Trivia',
            ];
            return $icons[$type] ?? ucfirst($type);
        });

        $show->field('games_played', 'Games Played');

        $show->divider();

        $show->field('wins', 'Wins')->as(function ($v) {
            return "🏆 {$v}";
        });

        $show->field('losses', 'Losses')->as(function ($v) {
            return "❌ {$v}";
        });

        $show->field('draws', 'Draws')->as(function ($v) {
            return "🤝 {$v}";
        });

        $show->field('win_rate', 'Win Rate')->as(function () {
            if ($this->games_played == 0) return 'N/A';
            return round(($this->wins / $this->games_played) * 100, 1) . '%';
        });

        $show->divider();

        $show->field('high_score', 'High Score')->as(function ($v) {
            return $v > 0 ? "⭐ {$v}" : 'N/A';
        });

        $show->field('total_play_seconds', 'Total Play Time')->as(function ($secs) {
            $h = floor($secs / 3600);
            $m = floor(($secs % 3600) / 60);
            $s = $secs % 60;
            $parts = [];
            if ($h > 0) $parts[] = "{$h}h";
            if ($m > 0) $parts[] = "{$m}m";
            if ($s > 0 || empty($parts)) $parts[] = "{$s}s";
            return '⏱️ ' . implode(' ', $parts);
        });

        $show->field('last_played_at', 'Last Played')->as(function ($v) {
            return $v ? Carbon::parse($v)->format('M d, Y H:i') . ' (' . Carbon::parse($v)->diffForHumans() . ')' : 'Never';
        });

        $show->divider();

        $show->field('created_at', 'First Synced');
        $show->field('updated_at', 'Last Synced');

        // Player's other game stats
        $stat = GameStat::find($id);
        if ($stat) {
            $otherStats = GameStat::where('user_id', $stat->user_id)
                ->where('id', '!=', $id)
                ->get();

            if ($otherStats->isNotEmpty()) {
                $show->divider();
                $icons  = self::GAME_ICONS;
                $labels = self::GAME_LABELS;
                $show->field('other_games', 'Other Games by This Player')->as(function () use ($otherStats, $icons, $labels) {
                    $parts = [];
                    foreach ($otherStats as $s) {
                        $icon  = $icons[$s->game_type] ?? '🎮';
                        $label = $labels[$s->game_type] ?? $s->game_type;
                        $parts[] = "{$icon} {$label}: {$s->wins}W/{$s->losses}L/{$s->draws}D ({$s->games_played} games)";
                    }
                    return implode("\n", $parts);
                });
            }
        }

        return $show;
    }

    // ─── Helpers ──────────────────────────────────────

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) return "{$seconds}s";
        if ($seconds < 3600) return round($seconds / 60) . 'm';
        $h = floor($seconds / 3600);
        $m = round(($seconds % 3600) / 60);
        return "{$h}h {$m}m";
    }
}
