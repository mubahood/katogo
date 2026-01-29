<?php

namespace App\Admin\Controllers;

use App\Models\GameInvitation;
use App\Models\User;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class GameInvitationController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Game Invitations';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new GameInvitation());
        $grid->model()->orderBy('id', 'desc');

        $grid->column('id', __('ID'))->sortable();
        
        $grid->column('game_type', __('Game'))
            ->display(function ($type) {
                $icons = [
                    'matatu' => '🃏 Matatu',
                    'ludo' => '🎲 Ludo',
                ];
                return $icons[$type] ?? ucfirst($type);
            })
            ->label([
                'matatu' => 'warning',
                'ludo' => 'success',
            ])
            ->sortable()
            ->filter([
                'matatu' => 'Matatu',
                'ludo' => 'Ludo',
            ]);

        $grid->column('status', __('Status'))
            ->label([
                'pending' => 'warning',
                'accepted' => 'success',
                'declined' => 'danger',
                'expired' => 'default',
                'cancelled' => 'default',
            ])
            ->sortable()
            ->filter([
                'pending' => 'Pending',
                'accepted' => 'Accepted',
                'declined' => 'Declined',
                'expired' => 'Expired',
                'cancelled' => 'Cancelled',
            ]);

        $grid->column('sender_id', __('Sender'))
            ->display(function ($sender_id) {
                $user = User::find($sender_id);
                return $user ? $user->name . " (#{$user->id})" : "N/A";
            });

        $grid->column('receiver_id', __('Receiver'))
            ->display(function ($receiver_id) {
                $user = User::find($receiver_id);
                return $user ? $user->name . " (#{$user->id})" : "N/A";
            });

        $grid->column('message', __('Message'))->limit(30);

        $grid->column('game_session_id', __('Game Session'))
            ->display(function ($game_session_id) {
                return $game_session_id ? "#{$game_session_id}" : '-';
            });

        $grid->column('expires_at', __('Expires'))
            ->display(function ($expires_at) {
                if (!$expires_at) return '-';
                $expired = strtotime($expires_at) < time();
                $formatted = date('M d, H:i:s', strtotime($expires_at));
                return $expired ? "⏰ {$formatted}" : $formatted;
            })->sortable();

        $grid->column('created_at', __('Created'))
            ->display(function ($created_at) {
                return date('M d, Y H:i:s', strtotime($created_at));
            })->sortable();

        // Filters
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();
            
            $filter->equal('game_type', 'Game Type')->select([
                'matatu' => 'Matatu',
                'ludo' => 'Ludo',
            ]);
            
            $filter->equal('status', 'Status')->select([
                'pending' => 'Pending',
                'accepted' => 'Accepted',
                'declined' => 'Declined',
                'expired' => 'Expired',
                'cancelled' => 'Cancelled',
            ]);
            
            $filter->equal('sender_id', 'Sender ID');
            $filter->equal('receiver_id', 'Receiver ID');
            
            $filter->between('created_at', 'Created')->datetime();
        });

        // Disable create button (invitations are created through the app)
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
        $show = new Show(GameInvitation::findOrFail($id));

        $show->field('id', __('ID'));
        
        $show->field('game_type', __('Game Type'))->as(function ($type) {
            $icons = [
                'matatu' => '🃏 Matatu (Card Game)',
                'ludo' => '🎲 Ludo (Board Game)',
            ];
            return $icons[$type] ?? ucfirst($type);
        });
        
        $show->field('status', __('Status'));
        
        $show->divider();
        
        $show->field('sender_id', __('Sender'))->as(function ($sender_id) {
            $user = User::find($sender_id);
            return $user ? $user->name . " (ID: {$user->id}, Email: {$user->email})" : "N/A";
        });
        
        $show->field('receiver_id', __('Receiver'))->as(function ($receiver_id) {
            $user = User::find($receiver_id);
            return $user ? $user->name . " (ID: {$user->id}, Email: {$user->email})" : "N/A";
        });
        
        $show->divider();
        
        $show->field('message', __('Message'));
        
        $show->divider();
        
        $show->field('game_session_id', __('Game Session ID'))->as(function ($id) {
            return $id ? "#{$id}" : 'No game session created';
        });
        
        $show->divider();
        
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
        $form = new Form(new GameInvitation());

        $form->display('id', __('ID'));
        
        $form->select('game_type', __('Game Type'))->options([
            'matatu' => 'Matatu',
            'ludo' => 'Ludo',
        ]);
        
        $form->select('status', __('Status'))->options([
            'pending' => 'Pending',
            'accepted' => 'Accepted',
            'declined' => 'Declined',
            'expired' => 'Expired',
            'cancelled' => 'Cancelled',
        ]);
        
        $form->number('sender_id', __('Sender ID'));
        $form->number('receiver_id', __('Receiver ID'));
        
        $form->text('message', __('Message'));
        
        $form->number('game_session_id', __('Game Session ID'));
        
        $form->datetime('expires_at', __('Expires At'));

        return $form;
    }
}
