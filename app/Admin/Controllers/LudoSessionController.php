<?php

namespace App\Admin\Controllers;

use App\Models\LudoSession;
use App\Models\User;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class LudoSessionController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Ludo Game Sessions';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new LudoSession());
        $grid->model()->orderBy('id', 'desc');

        $grid->column('id', __('ID'))->sortable();
        $grid->column('session_code', __('Code'))->copyable()->sortable();
        
        $grid->column('game_type', __('Type'))
            ->display(function ($type) {
                return $type === '2_player' ? '2 Players' : '4 Players';
            })
            ->label([
                '2_player' => 'info',
                '4_player' => 'primary',
            ])
            ->sortable()
            ->filter([
                '2_player' => '2 Players',
                '4_player' => '4 Players',
            ]);
        
        $grid->column('status', __('Status'))
            ->label([
                'pending' => 'default',
                'waiting' => 'warning',
                'playing' => 'primary',
                'completed' => 'success',
                'cancelled' => 'danger',
                'expired' => 'default',
            ])
            ->sortable()
            ->filter([
                'pending' => 'Pending',
                'waiting' => 'Waiting',
                'playing' => 'Playing',
                'completed' => 'Completed',
                'cancelled' => 'Cancelled',
                'expired' => 'Expired',
            ]);

        // Player 1 (Red)
        $grid->column('player1_id', __('🔴 Red'))
            ->display(function ($player1_id) {
                if (!$player1_id) return '-';
                $user = User::find($player1_id);
                return $user ? $user->name : "ID: {$player1_id}";
            });

        // Player 2 (Green)
        $grid->column('player2_id', __('🟢 Green'))
            ->display(function ($player2_id) {
                if (!$player2_id) return '-';
                $user = User::find($player2_id);
                return $user ? $user->name : "ID: {$player2_id}";
            });

        // Player 3 (Yellow)
        $grid->column('player3_id', __('🟡 Yellow'))
            ->display(function ($player3_id) {
                if (!$player3_id) return '-';
                $user = User::find($player3_id);
                return $user ? $user->name : "ID: {$player3_id}";
            });

        // Player 4 (Blue)
        $grid->column('player4_id', __('🔵 Blue'))
            ->display(function ($player4_id) {
                if (!$player4_id) return '-';
                $user = User::find($player4_id);
                return $user ? $user->name : "ID: {$player4_id}";
            });

        $grid->column('current_turn_player', __('Turn'))->display(function ($turn) {
            $colors = [1 => '🔴', 2 => '🟢', 3 => '🟡', 4 => '🔵'];
            return isset($colors[$turn]) ? $colors[$turn] . " Player {$turn}" : '-';
        });

        $grid->column('last_dice_roll', __('Last Dice'))->display(function ($roll) {
            $dice = ['⚀', '⚁', '⚂', '⚃', '⚄', '⚅'];
            return $roll > 0 && $roll <= 6 ? $dice[$roll - 1] . " ({$roll})" : '-';
        });

        $grid->column('winner_player', __('Winner'))
            ->display(function ($winner) {
                if (!$winner) return '-';
                $colors = [1 => '🔴 Red', 2 => '🟢 Green', 3 => '🟡 Yellow', 4 => '🔵 Blue'];
                return "🏆 " . ($colors[$winner] ?? "Player {$winner}");
            });

        $grid->column('started_at', __('Started'))
            ->display(function ($started_at) {
                return $started_at ? date('M d, Y H:i', strtotime($started_at)) : '-';
            })->sortable();

        $grid->column('ended_at', __('Ended'))
            ->display(function ($ended_at) {
                return $ended_at ? date('M d, Y H:i', strtotime($ended_at)) : '-';
            })->sortable();

        $grid->column('created_at', __('Created'))
            ->display(function ($created_at) {
                return date('M d, Y H:i', strtotime($created_at));
            })->sortable();

        // Filters
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();
            
            $filter->equal('session_code', 'Session Code');
            
            $filter->equal('game_type', 'Game Type')->select([
                '2_player' => '2 Players',
                '4_player' => '4 Players',
            ]);
            
            $filter->equal('status', 'Status')->select([
                'pending' => 'Pending',
                'waiting' => 'Waiting',
                'playing' => 'Playing',
                'completed' => 'Completed',
                'cancelled' => 'Cancelled',
                'expired' => 'Expired',
            ]);
            
            $filter->equal('player1_id', 'Player 1 ID');
            $filter->equal('player2_id', 'Player 2 ID');
            $filter->equal('winner_user_id', 'Winner User ID');
            
            $filter->between('created_at', 'Created')->datetime();
        });

        // Disable create button (games are created through the app)
        $grid->disableCreateButton();

        return $grid;
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(LudoSession::findOrFail($id));

        $show->field('id', __('ID'));
        $show->field('session_code', __('Session Code'));
        $show->field('game_type', __('Game Type'))->as(function ($type) {
            return $type === '2_player' ? '2 Players' : '4 Players';
        });
        $show->field('status', __('Status'));
        
        $show->divider();
        
        // Player 1 (Red)
        $show->field('player1_id', __('🔴 Player 1 (Red)'))->as(function ($player1_id) {
            if (!$player1_id) return 'Empty';
            $user = User::find($player1_id);
            return $user ? $user->name . " (ID: {$user->id})" : "ID: {$player1_id}";
        });
        $show->field('player1_name', __('Player 1 Name'));
        $show->field('player1_finished_count', __('Player 1 Pieces Home'));
        $show->field('player1_pieces', __('Player 1 Pieces'))->as(function ($pieces) {
            return $pieces ? json_encode($pieces, JSON_PRETTY_PRINT) : 'N/A';
        })->unescape();
        
        $show->divider();
        
        // Player 2 (Green)
        $show->field('player2_id', __('🟢 Player 2 (Green)'))->as(function ($player2_id) {
            if (!$player2_id) return 'Empty';
            $user = User::find($player2_id);
            return $user ? $user->name . " (ID: {$user->id})" : "ID: {$player2_id}";
        });
        $show->field('player2_name', __('Player 2 Name'));
        $show->field('player2_finished_count', __('Player 2 Pieces Home'));
        $show->field('player2_pieces', __('Player 2 Pieces'))->as(function ($pieces) {
            return $pieces ? json_encode($pieces, JSON_PRETTY_PRINT) : 'N/A';
        })->unescape();
        
        $show->divider();
        
        // Player 3 (Yellow)
        $show->field('player3_id', __('🟡 Player 3 (Yellow)'))->as(function ($player3_id) {
            if (!$player3_id) return 'Empty';
            $user = User::find($player3_id);
            return $user ? $user->name . " (ID: {$user->id})" : "ID: {$player3_id}";
        });
        $show->field('player3_name', __('Player 3 Name'));
        $show->field('player3_finished_count', __('Player 3 Pieces Home'));
        $show->field('player3_pieces', __('Player 3 Pieces'))->as(function ($pieces) {
            return $pieces ? json_encode($pieces, JSON_PRETTY_PRINT) : 'N/A';
        })->unescape();
        
        $show->divider();
        
        // Player 4 (Blue)
        $show->field('player4_id', __('🔵 Player 4 (Blue)'))->as(function ($player4_id) {
            if (!$player4_id) return 'Empty';
            $user = User::find($player4_id);
            return $user ? $user->name . " (ID: {$user->id})" : "ID: {$player4_id}";
        });
        $show->field('player4_name', __('Player 4 Name'));
        $show->field('player4_finished_count', __('Player 4 Pieces Home'));
        $show->field('player4_pieces', __('Player 4 Pieces'))->as(function ($pieces) {
            return $pieces ? json_encode($pieces, JSON_PRETTY_PRINT) : 'N/A';
        })->unescape();
        
        $show->divider();
        
        // Game State
        $show->field('current_turn_player', __('Current Turn Player'));
        $show->field('current_turn_user_id', __('Current Turn User ID'));
        $show->field('last_dice_roll', __('Last Dice Roll'));
        $show->field('consecutive_sixes', __('Consecutive Sixes'));
        $show->field('can_roll_again', __('Can Roll Again'));
        $show->field('must_move_piece', __('Must Move Piece'));
        
        $show->divider();
        
        // Actions
        $show->field('last_action', __('Last Action'));
        $show->field('last_action_player', __('Last Action Player'));
        $show->field('last_captured_piece', __('Last Captured Piece'))->as(function ($piece) {
            return $piece ? json_encode($piece, JSON_PRETTY_PRINT) : 'N/A';
        })->unescape();
        
        $show->divider();
        
        // Winner
        $show->field('winner_player', __('Winner Player'));
        $show->field('winner_user_id', __('Winner User ID'))->as(function ($winner_user_id) {
            if (!$winner_user_id) return 'No winner yet';
            $user = User::find($winner_user_id);
            return $user ? "🏆 " . $user->name . " (ID: {$user->id})" : "ID: {$winner_user_id}";
        });
        $show->field('rankings', __('Final Rankings'))->as(function ($rankings) {
            return $rankings ? json_encode($rankings, JSON_PRETTY_PRINT) : 'N/A';
        })->unescape();
        
        $show->divider();
        
        // Polling Timestamps
        $show->field('player1_last_poll', __('Player 1 Last Poll'));
        $show->field('player2_last_poll', __('Player 2 Last Poll'));
        $show->field('player3_last_poll', __('Player 3 Last Poll'));
        $show->field('player4_last_poll', __('Player 4 Last Poll'));
        
        $show->divider();
        
        // Timing
        $show->field('turn_started_at', __('Turn Started At'));
        $show->field('started_at', __('Game Started At'));
        $show->field('ended_at', __('Game Ended At'));
        $show->field('expires_at', __('Expires At'));
        $show->field('created_at', __('Created At'));
        $show->field('updated_at', __('Updated At'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new LudoSession());

        $form->display('id', __('ID'));
        $form->display('session_code', __('Session Code'));
        
        $form->select('status', __('Status'))->options([
            'pending' => 'Pending',
            'waiting' => 'Waiting',
            'playing' => 'Playing',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'expired' => 'Expired',
        ]);
        
        $form->select('game_type', __('Game Type'))->options([
            '2_player' => '2 Players',
            '4_player' => '4 Players',
        ]);
        
        $form->divider('Players');
        
        $form->number('player1_id', __('Player 1 ID'));
        $form->number('player2_id', __('Player 2 ID'));
        $form->number('player3_id', __('Player 3 ID'));
        $form->number('player4_id', __('Player 4 ID'));
        
        $form->divider('Winner');
        
        $form->number('winner_player', __('Winner Player (1-4)'));
        $form->number('winner_user_id', __('Winner User ID'));

        $form->divider('Timing');
        
        $form->datetime('started_at', __('Started At'));
        $form->datetime('ended_at', __('Ended At'));

        return $form;
    }
}
