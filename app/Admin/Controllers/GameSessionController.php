<?php

namespace App\Admin\Controllers;

use App\Models\GameSession;
use App\Models\User;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Encore\Admin\Layout\Content;

class GameSessionController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Matatu Game Sessions';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new GameSession());
        $grid->model()->orderBy('id', 'desc');

        $grid->column('id', __('ID'))->sortable();
        
        $grid->column('status', __('Status'))
            ->label([
                'waiting' => 'warning',
                'active' => 'primary',
                'completed' => 'success',
                'abandoned' => 'danger',
                'forfeited' => 'default',
            ])
            ->sortable()
            ->filter([
                'waiting' => 'Waiting',
                'active' => 'Active',
                'completed' => 'Completed',
                'abandoned' => 'Abandoned',
                'forfeited' => 'Forfeited',
            ]);

        $grid->column('player1_id', __('Player 1'))
            ->display(function ($player1_id) {
                $user = User::find($player1_id);
                return $user ? $user->name . " (#{$user->id})" : "N/A";
            });

        $grid->column('player2_id', __('Player 2'))
            ->display(function ($player2_id) {
                $user = User::find($player2_id);
                return $user ? $user->name . " (#{$user->id})" : "N/A";
            });

        $grid->column('current_round', __('Round'))->sortable();
        
        $grid->column('player1_score', __('P1 Score'))->sortable();
        $grid->column('player2_score', __('P2 Score'))->sortable();
        
        $grid->column('player1_rounds_won', __('P1 Rounds'))->sortable();
        $grid->column('player2_rounds_won', __('P2 Rounds'))->sortable();

        $grid->column('winner_id', __('Winner'))
            ->display(function ($winner_id) {
                if (!$winner_id) return '-';
                $user = User::find($winner_id);
                return $user ? "🏆 " . $user->name : "N/A";
            });

        $grid->column('forfeit_user_id', __('Forfeited By'))
            ->display(function ($forfeit_user_id) {
                if (!$forfeit_user_id) return '-';
                $user = User::find($forfeit_user_id);
                return $user ? "❌ " . $user->name : "N/A";
            });

        $grid->column('current_turn_user_id', __('Current Turn'))
            ->display(function ($current_turn_user_id) {
                if (!$current_turn_user_id) return '-';
                $user = User::find($current_turn_user_id);
                return $user ? $user->name : "N/A";
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
            
            $filter->equal('status', 'Status')->select([
                'waiting' => 'Waiting',
                'active' => 'Active',
                'completed' => 'Completed',
                'abandoned' => 'Abandoned',
                'forfeited' => 'Forfeited',
            ]);
            
            $filter->equal('player1_id', 'Player 1 ID');
            $filter->equal('player2_id', 'Player 2 ID');
            $filter->equal('winner_id', 'Winner ID');
            
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
        $show = new Show(GameSession::findOrFail($id));

        $show->field('id', __('ID'));
        $show->field('status', __('Status'));
        
        $show->field('player1_id', __('Player 1'))->as(function ($player1_id) {
            $user = User::find($player1_id);
            return $user ? $user->name . " (ID: {$user->id}, Email: {$user->email})" : "N/A";
        });
        
        $show->field('player2_id', __('Player 2'))->as(function ($player2_id) {
            $user = User::find($player2_id);
            return $user ? $user->name . " (ID: {$user->id}, Email: {$user->email})" : "N/A";
        });
        
        $show->divider();
        
        $show->field('current_round', __('Current Round'));
        $show->field('target_score', __('Target Score'));
        
        $show->divider();
        
        $show->field('player1_score', __('Player 1 Score'));
        $show->field('player2_score', __('Player 2 Score'));
        $show->field('player1_rounds_won', __('Player 1 Rounds Won'));
        $show->field('player2_rounds_won', __('Player 2 Rounds Won'));
        
        $show->divider();
        
        $show->field('winner_id', __('Winner'))->as(function ($winner_id) {
            if (!$winner_id) return 'No winner yet';
            $user = User::find($winner_id);
            return $user ? "🏆 " . $user->name . " (ID: {$user->id})" : "N/A";
        });
        
        $show->field('forfeit_user_id', __('Forfeited By'))->as(function ($forfeit_user_id) {
            if (!$forfeit_user_id) return 'N/A';
            $user = User::find($forfeit_user_id);
            return $user ? "❌ " . $user->name . " (ID: {$user->id})" : "N/A";
        });
        
        $show->divider();
        
        $show->field('current_turn_user_id', __('Current Turn Player'))->as(function ($current_turn_user_id) {
            if (!$current_turn_user_id) return 'N/A';
            $user = User::find($current_turn_user_id);
            return $user ? $user->name : "N/A";
        });
        
        $show->field('current_suit', __('Current Suit'));
        $show->field('draw_stack', __('Draw Stack'));
        
        $show->divider();
        
        $show->field('player1_hand', __('Player 1 Hand'))->as(function ($hand) {
            return $hand ? json_encode(json_decode($hand, true), JSON_PRETTY_PRINT) : 'N/A';
        })->unescape();
        
        $show->field('player2_hand', __('Player 2 Hand'))->as(function ($hand) {
            return $hand ? json_encode(json_decode($hand, true), JSON_PRETTY_PRINT) : 'N/A';
        })->unescape();
        
        $show->field('discard_pile', __('Discard Pile'))->as(function ($pile) {
            return $pile ? json_encode(json_decode($pile, true), JSON_PRETTY_PRINT) : 'N/A';
        })->unescape();
        
        $show->field('cut_card', __('Cut Card'))->as(function ($card) {
            return $card ? json_encode(json_decode($card, true), JSON_PRETTY_PRINT) : 'N/A';
        })->unescape();
        
        $show->divider();
        
        $show->field('chat_head_id', __('Chat Head ID'));
        
        $show->divider();
        
        $show->field('player1_last_poll', __('Player 1 Last Poll'));
        $show->field('player2_last_poll', __('Player 2 Last Poll'));
        
        $show->divider();
        
        $show->field('started_at', __('Started At'));
        $show->field('ended_at', __('Ended At'));
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
        $form = new Form(new GameSession());

        $form->display('id', __('ID'));
        
        $form->select('status', __('Status'))->options([
            'waiting' => 'Waiting',
            'active' => 'Active',
            'completed' => 'Completed',
            'abandoned' => 'Abandoned',
            'forfeited' => 'Forfeited',
        ]);
        
        $form->number('player1_id', __('Player 1 ID'));
        $form->number('player2_id', __('Player 2 ID'));
        $form->number('winner_id', __('Winner ID'));
        $form->number('forfeit_user_id', __('Forfeit User ID'));
        
        $form->number('player1_score', __('Player 1 Score'));
        $form->number('player2_score', __('Player 2 Score'));
        $form->number('player1_rounds_won', __('Player 1 Rounds Won'));
        $form->number('player2_rounds_won', __('Player 2 Rounds Won'));
        
        $form->number('current_round', __('Current Round'));
        $form->number('target_score', __('Target Score'));

        $form->datetime('started_at', __('Started At'));
        $form->datetime('ended_at', __('Ended At'));

        return $form;
    }
}
