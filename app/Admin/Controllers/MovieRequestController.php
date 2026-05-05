<?php

namespace App\Admin\Controllers;

use App\Models\MovieRequest;
use App\Models\CustomerTicket;
use App\Models\CustomerTicketRecord;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class MovieRequestController extends AdminController
{
    protected $title = 'Movie Requests';

    protected function grid(): Grid
    {
        $grid = new Grid(new MovieRequest());

        $grid->model()
            ->with(['user', 'ticket', 'handledBy'])
            ->orderByDesc('id');

        $grid->column('id', 'ID')->sortable();

        $grid->column('user.name', 'User')->display(function () {
            return e((string) ($this->user->name ?? 'Unknown user'));
        });

        $grid->column('user.phone_number', 'Phone')->display(function () {
            $phone = trim((string) ($this->user->phone_number ?? ''));
            return $phone !== '' ? e($phone) : '—';
        });

        $grid->column('app_type', 'App')->display(fn($v) => e((string) ($v ?: 'lugaflix')))->sortable();
        $grid->column('platform_type', 'Platform')->display(fn($v) => e((string) ($v ?: 'lugaflix')))->sortable();
        $grid->column('request_source', 'Source')->display(fn($v) => ucwords(str_replace('_', ' ', (string) $v)))->sortable();
        $statusColors = [
            'submitted'   => 'info',
            'reviewing'   => 'warning',
            'in_progress' => 'primary',
            'fulfilled'   => 'success',
            'rejected'    => 'danger',
            'cancelled'   => 'default',
        ];
        $statusOptions = array_combine(MovieRequest::$validStatuses, array_map(
            fn($s) => ucwords(str_replace('_', ' ', $s)),
            MovieRequest::$validStatuses
        ));

        $grid->column('status', 'Status')
            ->display(function ($value) use ($statusColors, $statusOptions) {
                $label = $statusOptions[$value] ?? ucwords(str_replace('_', ' ', (string) $value));
                $color = $statusColors[$value] ?? 'default';
                return "<span class='label label-{$color}'>{$label}</span>";
            })
            ->editable('select', $statusOptions)
            ->sortable();

        $grid->column('searched_query', 'Query')->display(fn($v) => e((string) ($v ?: '—')));

        $grid->column('requested_movies', 'Requested Movies')->display(function ($value) {
            $items = is_array($value) ? $value : [];
            if (empty($items)) {
                return '—';
            }
            $safe = array_map(fn($x) => e((string) $x), $items);
            return '<div style="line-height:1.4">' . implode('<br>', $safe) . '</div>';
        });

        $grid->column('customer_ticket_id', 'Ticket')->display(function ($ticketId) {
            if (!$ticketId) {
                return '—';
            }
            return '<a class="btn btn-xs btn-primary" href="' . e(admin_url('support-tickets/' . $ticketId)) . '">#' . (int) $ticketId . '</a>';
        });

        $grid->column('handledBy.name', 'Handled By')->display(fn($v) => e((string) ($v ?: '—')))->hide();

        $grid->column('created_at', 'Created')->display(fn($v) => $v ? date('d M Y H:i', strtotime((string) $v)) : '—')->sortable();

        $grid->quickSearch(function ($model, $query) {
            $q = '%' . trim((string) $query) . '%';
            $model->where('searched_query', 'like', $q)
                ->orWhere('user_message', 'like', $q)
                ->orWhereHas('user', function ($userQ) use ($q) {
                    $userQ->where('name', 'like', $q)
                        ->orWhere('email', 'like', $q)
                        ->orWhere('phone_number', 'like', $q);
                });
        });

        $grid->filter(function (Grid\Filter $filter) {
            $filter->disableIdFilter();
            $filter->equal('status', 'Status')->select(array_combine(MovieRequest::$validStatuses, MovieRequest::$validStatuses));
            $filter->equal('request_source', 'Source')->select(array_combine(MovieRequest::$validSources, MovieRequest::$validSources));
            $filter->equal('platform_type', 'Platform')->select([
                'lugaflix' => 'Lugaflix',
                'ugflix' => 'Ugflix',
                'muno_app' => 'Muno App',
                'luga' => 'Luga',
                'muno' => 'Muno',
            ]);
            $filter->between('created_at', 'Created')->datetime();
        });

        $grid->actions(function (Grid\Displayers\Actions $actions) {
            $actions->disableDelete();
        });

        return $grid;
    }

    protected function detail($id): Show
    {
        $show = new Show(MovieRequest::with(['user', 'ticket', 'handledBy'])->findOrFail($id));

        $show->field('id', 'ID');
        $show->field('status', 'Status');
        $show->field('request_source', 'Request Source');
        $show->field('app_type', 'App');
        $show->field('platform_type', 'Platform');
        $show->field('searched_query', 'Search Query');
        $show->field('requested_movies', 'Requested Movies')->as(function ($v) {
            if (!is_array($v) || count($v) < 1) {
                return '—';
            }
            return implode(', ', $v);
        });
        $show->field('user_message', 'User Message');
        $show->field('support_reply', 'Support Reply');
        $show->field('support_reply_at', 'Support Reply At');
        $show->field('user.name', 'User');
        $show->field('user.phone_number', 'Phone');
        $show->field('customer_ticket_id', 'Ticket ID');
        $show->field('handledBy.name', 'Handled By');
        $show->field('created_at', 'Created');
        $show->field('updated_at', 'Updated');

        return $show;
    }

    protected function form(): Form
    {
        $form = new Form(new MovieRequest());

        $form->display('id', 'ID');

        $form->select('status', 'Status')
            ->options(array_combine(MovieRequest::$validStatuses, MovieRequest::$validStatuses))
            ->required();

        $form->text('searched_query', 'Search Query');

        $form->textarea('requested_movies_input', 'Requested Movies (one per line)')
            ->customFormat(function ($v) {
                // $v is unused; we load from the actual model column.
                /** @var MovieRequest $model */
                $model = $this->model();
                $arr   = is_array($model->requested_movies) ? $model->requested_movies : [];
                return implode("\n", $arr);
            })
            ->help('One title per line (or comma-separated).');

        $form->textarea('user_message', 'User Message');
        $form->textarea('support_reply', 'Support Reply');

        $form->saving(function (Form $form) {
            $raw = (string) ($form->requested_movies_input ?? '');
            if ($raw !== '') {
                $list = preg_split('/[\r\n,]+/', $raw) ?: [];
                $list = array_values(array_filter(array_map(fn($v) => trim((string) $v), $list), fn($v) => $v !== ''));
                $form->model()->requested_movies = array_slice(array_values(array_unique($list)), 0, 20);
            }

            if ($form->support_reply) {
                $form->model()->support_reply_at = now();
            }
        });

        $form->saved(function (Form $form) {
            $movieRequest = $form->model();
            if (!$movieRequest instanceof MovieRequest) {
                return;
            }

            $ticket = CustomerTicket::find($movieRequest->customer_ticket_id);
            if (!$ticket) {
                return;
            }

            $ticket->is_movie_request = true;
            $ticket->movie_request_payload = [
                'request_id' => $movieRequest->id,
                'searched_query' => $movieRequest->searched_query,
                'requested_movies' => $movieRequest->requested_movies,
                'source' => $movieRequest->request_source,
            ];

            if ($movieRequest->status === 'fulfilled') {
                $ticket->status = 'resolved';
                $ticket->resolution_state = 'resolved';
            } elseif (in_array($movieRequest->status, ['rejected', 'cancelled'], true)) {
                $ticket->status = 'closed';
                $ticket->resolution_state = 'cancelled';
            }

            if ($movieRequest->support_reply) {
                CustomerTicketRecord::create([
                    'customer_ticket_id' => $ticket->id,
                    'sender_type' => 'support_team',
                    'sender_id' => admin_user()?->id,
                    'message' => (string) $movieRequest->support_reply,
                    'action_type' => 'needs_user_action',
                    'action_description' => 'Movie request follow-up from admin movie request module.',
                    'is_internal_note' => false,
                    'show_to_customer' => true,
                    'is_read_by_user' => false,
                    'customer_seen' => false,
                    'customer_seen_at' => null,
                    'is_read_by_support' => true,
                ]);

                $ticket->last_reply_at = now();
                $ticket->reply_count = ((int) $ticket->reply_count) + 1;
                $ticket->agent_has_contacted_customer = true;
                $ticket->has_unread_user = true;
                $ticket->has_unread_support = false;
            }

            $ticket->save();
        });

        return $form;
    }
}
