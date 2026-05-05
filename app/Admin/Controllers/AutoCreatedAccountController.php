<?php

namespace App\Admin\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Illuminate\Support\Facades\DB;

class AutoCreatedAccountController extends AdminController
{
    protected $title = 'Auto-Created Accounts';

    public function index(Content $content)
    {
        return $content
            ->title('Auto-Created Accounts')
            ->description('Users who entered through device auto-account creation and have not completed full registration yet.')
            ->body($this->statsPanel())
            ->body($this->grid());
    }

    protected function statsPanel(): string
    {
        $baseQuery = User::query()->where(function ($query) {
            $query->where('account_state', 'auto_created')
                ->orWhere(function ($inner) {
                    $inner->where('account_origin', 'auto_device')
                        ->where(function ($stateQuery) {
                            $stateQuery->whereNull('account_state')
                                ->orWhere('account_state', '!=', 'registered');
                        });
                });
        });

        $totalAutoPending = (clone $baseQuery)->count();
        $todayAutoPending = (clone $baseQuery)->whereDate('created_at', Carbon::today())->count();
        $lugaflixCount = (clone $baseQuery)->where('app_type', 'lugaflix')->count();
        $androidCount = (clone $baseQuery)->where(function ($query) {
            $query->where('device_platform', 'android')
                ->orWhere('platform', 'android');
        })->count();
        $withTicketsCount = (clone $baseQuery)
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('customer_tickets')
                    ->whereColumn('customer_tickets.user_id', 'admin_users.id');
            })
            ->count();

        return <<<HTML
<div class="row" style="margin-bottom: 15px;">
    <div class="col-md-3 col-sm-6 col-xs-12">
        <div class="info-box">
            <span class="info-box-icon bg-orange"><i class="fa fa-magic"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Auto Pending</span>
                <span class="info-box-number">{$totalAutoPending}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 col-xs-12">
        <div class="info-box">
            <span class="info-box-icon bg-blue"><i class="fa fa-calendar"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Created Today</span>
                <span class="info-box-number">{$todayAutoPending}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 col-xs-12">
        <div class="info-box">
            <span class="info-box-icon bg-green"><i class="fa fa-mobile"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Android Devices</span>
                <span class="info-box-number">{$androidCount}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 col-xs-12">
        <div class="info-box">
            <span class="info-box-icon bg-red"><i class="fa fa-life-ring"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">With Tickets</span>
                <span class="info-box-number">{$withTicketsCount}</span>
            </div>
        </div>
    </div>
</div>
<div class="box box-warning" style="margin-bottom: 15px;">
    <div class="box-body">
        <strong>Lugaflix accounts awaiting profile completion:</strong> {$lugaflixCount}
        <br>
        <span class="text-muted">This page focuses on device-created accounts that have not yet become fully registered accounts.</span>
    </div>
</div>
HTML;
    }

    protected function grid(): Grid
    {
        $grid = new Grid(new User());

        $grid->model()
            ->where(function ($query) {
                $query->where('account_state', 'auto_created')
                    ->orWhere(function ($inner) {
                        $inner->where('account_origin', 'auto_device')
                            ->where(function ($stateQuery) {
                                $stateQuery->whereNull('account_state')
                                    ->orWhere('account_state', '!=', 'registered');
                            });
                    });
            })
            ->orderByDesc('id');

        $grid->disableCreateButton();
        $grid->disableBatchActions();
        $grid->disableRowSelector();

        $grid->quickSearch('id', 'name', 'username', 'email', 'phone_number');

        $grid->column('id', 'ID')->sortable();
        $grid->column('name', 'Name')->display(function ($value) {
            return $value ?: '<span class="text-muted">Unnamed</span>';
        });
        $grid->column('username', 'Username');
        $grid->column('email', 'Email')->display(function ($value) {
            return $value ?: '<span class="text-muted">No email</span>';
        });
        $grid->column('phone_number', 'Phone')->display(function ($value) {
            return $value ?: '<span class="text-muted">No phone</span>';
        });
        $grid->column('app_type', 'App')->label([
            'lugaflix' => 'primary',
            'ugflix' => 'success',
            'muno_app' => 'warning',
        ], 'default');
        $grid->column('account_origin', 'Origin')->display(function ($value) {
            if ($value === 'auto_device') {
                return '<span class="label label-warning">Auto Device</span>';
            }

            return $value ?: '<span class="text-muted">Unknown</span>';
        });
        $grid->column('account_state', 'State')->display(function ($value) {
            if ($value === 'auto_created') {
                return '<span class="label label-danger">Auto Created</span>';
            }

            return $value ?: '<span class="label label-default">Unknown</span>';
        });
        $grid->column('device_platform', 'Device Platform')->display(function ($value) {
            return $value ?: ($this->platform ?: '<span class="text-muted">Unknown</span>');
        });
        $grid->column('device_model', 'Device Model')->display(function ($value) {
            return $value ?: '<span class="text-muted">Unknown</span>';
        });
        $grid->column('ticket_count', 'Tickets')->display(function () {
            $count = DB::table('customer_tickets')->where('user_id', $this->id)->count();
            if ($count < 1) {
                return '0';
            }

            $url = admin_url('support-tickets') . '?user_id=' . $this->id;

            return "<a href=\"{$url}\">{$count}</a>";
        });
        $grid->column('created_at', 'Created')->display(function ($value) {
            return $value ? Carbon::parse($value)->format('d M Y H:i') : '—';
        })->sortable();

        $grid->filter(function (Grid\Filter $filter) {
            $filter->disableIdFilter();
            $filter->like('name', 'Name');
            $filter->like('email', 'Email');
            $filter->equal('app_type', 'App Type')->select([
                'lugaflix' => 'Lugaflix',
                'ugflix' => 'UGFlix',
                'muno_app' => 'Muno App',
                'web' => 'Web',
            ]);
            $filter->equal('device_platform', 'Device Platform')->select([
                'android' => 'Android',
                'ios' => 'iOS',
            ]);
            $filter->between('created_at', 'Created')->datetime();
        });

        $grid->actions(function (Grid\Displayers\Actions $actions) {
            $id = $actions->getKey();
            $actions->disableEdit();
            $actions->disableDelete();

            $profileUrl = admin_url("users/{$id}/profile");
            $actions->append("<a href=\"{$profileUrl}\" class=\"btn btn-xs btn-default\" style=\"margin-left:5px\"><i class=\"fa fa-user\"></i> Profile</a>");
        });

        return $grid;
    }
}