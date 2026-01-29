<?php

namespace App\Admin\Controllers;

use App\Models\CoinTransaction;
use App\Models\User;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class CoinTransactionController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Coin Transactions';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new CoinTransaction());
        $grid->model()->orderBy('id', 'desc');

        $grid->column('id', __('ID'))->sortable();
        
        $grid->column('user_id', __('User'))
            ->display(function ($user_id) {
                $user = User::find($user_id);
                return $user ? $user->name . " (#{$user->id})" : "N/A";
            });

        $grid->column('type', __('Type'))
            ->display(function ($type) {
                $labels = [
                    'game_win_online' => '🏆 Win (Online)',
                    'game_win_offline' => '🎮 Win (Offline)',
                    'game_forfeit' => '❌ Forfeit',
                    'purchase' => '💳 Purchase',
                    'reward' => '🎁 Reward',
                    'admin_adjustment' => '⚙️ Admin Adjustment',
                    'signup_bonus' => '🎉 Signup Bonus',
                ];
                return $labels[$type] ?? ucfirst(str_replace('_', ' ', $type));
            })
            ->label([
                'game_win_online' => 'success',
                'game_win_offline' => 'info',
                'game_forfeit' => 'danger',
                'purchase' => 'primary',
                'reward' => 'warning',
                'admin_adjustment' => 'default',
                'signup_bonus' => 'success',
            ])
            ->sortable()
            ->filter([
                'game_win_online' => 'Win (Online)',
                'game_win_offline' => 'Win (Offline)',
                'game_forfeit' => 'Forfeit',
                'purchase' => 'Purchase',
                'reward' => 'Reward',
                'admin_adjustment' => 'Admin Adjustment',
                'signup_bonus' => 'Signup Bonus',
            ]);

        $grid->column('amount', __('Amount'))
            ->display(function ($amount) {
                $color = $amount >= 0 ? 'green' : 'red';
                $sign = $amount >= 0 ? '+' : '';
                return "<span style='color:{$color};font-weight:bold'>{$sign}{$amount}</span>";
            })->sortable();

        $grid->column('balance_after', __('Balance After'))
            ->display(function ($balance) {
                return "🪙 {$balance}";
            })->sortable();

        $grid->column('description', __('Description'))->limit(40);

        $grid->column('game_session_id', __('Game'))
            ->display(function ($game_session_id) {
                return $game_session_id ? "🎮 #{$game_session_id}" : '-';
            });

        $grid->column('related_user_id', __('Opponent'))
            ->display(function ($related_user_id) {
                if (!$related_user_id) return '-';
                $user = User::find($related_user_id);
                return $user ? $user->name : "#{$related_user_id}";
            });

        $grid->column('created_at', __('Date'))
            ->display(function ($created_at) {
                return date('M d, Y H:i', strtotime($created_at));
            })->sortable();

        // Filters
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();
            
            $filter->equal('user_id', 'User ID');
            
            $filter->equal('type', 'Type')->select([
                'game_win_online' => 'Win (Online)',
                'game_win_offline' => 'Win (Offline)',
                'game_forfeit' => 'Forfeit',
                'purchase' => 'Purchase',
                'reward' => 'Reward',
                'admin_adjustment' => 'Admin Adjustment',
                'signup_bonus' => 'Signup Bonus',
            ]);
            
            $filter->where(function ($query) {
                if ($this->input === 'positive') {
                    $query->where('amount', '>', 0);
                } elseif ($this->input === 'negative') {
                    $query->where('amount', '<', 0);
                }
            }, 'Amount Type')->select([
                'positive' => 'Positive (Credit)',
                'negative' => 'Negative (Debit)',
            ]);
            
            $filter->equal('game_session_id', 'Game Session ID');
            $filter->equal('related_user_id', 'Opponent ID');
            
            $filter->between('created_at', 'Date')->datetime();
        });

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
        $show = new Show(CoinTransaction::findOrFail($id));

        $show->field('id', __('ID'));
        
        $show->field('user_id', __('User'))->as(function ($user_id) {
            $user = User::find($user_id);
            return $user ? $user->name . " (ID: {$user->id}, Email: {$user->email})" : "N/A";
        });
        
        $show->divider();
        
        $show->field('type', __('Transaction Type'))->as(function ($type) {
            $labels = [
                'game_win_online' => '🏆 Game Win (Online Multiplayer)',
                'game_win_offline' => '🎮 Game Win (Offline/Bot)',
                'game_forfeit' => '❌ Game Forfeit Penalty',
                'purchase' => '💳 In-App Purchase',
                'reward' => '🎁 Reward',
                'admin_adjustment' => '⚙️ Admin Adjustment',
                'signup_bonus' => '🎉 Signup Bonus',
            ];
            return $labels[$type] ?? ucfirst(str_replace('_', ' ', $type));
        });
        
        $show->field('amount', __('Amount'))->as(function ($amount) {
            $sign = $amount >= 0 ? '+' : '';
            return "{$sign}{$amount} coins";
        });
        
        $show->field('balance_after', __('Balance After Transaction'))->as(function ($balance) {
            return "🪙 {$balance} coins";
        });
        
        $show->field('description', __('Description'));
        
        $show->divider();
        
        $show->field('game_session_id', __('Game Session'))->as(function ($id) {
            return $id ? "Game Session #{$id}" : 'N/A';
        });
        
        $show->field('related_user_id', __('Related User (Opponent)'))->as(function ($related_user_id) {
            if (!$related_user_id) return 'N/A';
            $user = User::find($related_user_id);
            return $user ? $user->name . " (ID: {$user->id})" : "ID: {$related_user_id}";
        });
        
        $show->field('metadata', __('Metadata'))->as(function ($metadata) {
            return $metadata ? json_encode($metadata, JSON_PRETTY_PRINT) : 'N/A';
        })->unescape();
        
        $show->divider();
        
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
        $form = new Form(new CoinTransaction());

        $form->display('id', __('ID'));
        
        $form->number('user_id', __('User ID'))->rules('required');
        
        $form->select('type', __('Type'))->options([
            'game_win_online' => 'Game Win (Online)',
            'game_win_offline' => 'Game Win (Offline)',
            'game_forfeit' => 'Game Forfeit',
            'purchase' => 'Purchase',
            'reward' => 'Reward',
            'admin_adjustment' => 'Admin Adjustment',
            'signup_bonus' => 'Signup Bonus',
        ])->rules('required');
        
        $form->number('amount', __('Amount'))->rules('required')
            ->help('Positive for credit, negative for debit');
        
        $form->number('balance_after', __('Balance After'))->rules('required');
        
        $form->text('description', __('Description'));
        
        $form->number('game_session_id', __('Game Session ID'));
        $form->number('related_user_id', __('Related User ID'));
        
        $form->textarea('metadata', __('Metadata (JSON)'))
            ->help('Optional JSON metadata');

        return $form;
    }
}
