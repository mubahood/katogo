<?php

namespace App\Admin\Controllers;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\Carbon;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Encore\Admin\Layout\Content;
use Encore\Admin\Layout\Row;
use Encore\Admin\Widgets\InfoBox;
use Encore\Admin\Widgets\Box;
use Encore\Admin\Widgets\Table;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Subscription Management';

    /**
     * Custom index method with dashboard
     */
    public function index(Content $content)
    {
        return $content
            ->title('💎 ' . $this->title())
            ->description('Manage and monitor all subscriptions')
            ->row(function (Row $row) {
                $row->column(3, $this->totalRevenueBox());
                $row->column(3, $this->activeSubscriptionsBox());
                $row->column(3, $this->monthlyRevenueBox());
                $row->column(3, $this->pendingPaymentsBox());
            })
            ->row(function (Row $row) {
                $row->column(3, $this->todayRevenueBox());
                $row->column(3, $this->expiringTodayBox());
                $row->column(3, $this->newThisWeekBox());
                $row->column(3, $this->churnRateBox());
            })
            ->row(function (Row $row) {
                // LugaFlix Stats
                $row->column(6, $this->lugaflixStatsBox());
                // UGFlix Stats
                $row->column(6, $this->ugflixStatsBox());
            })
            ->row(function (Row $row) {
                $row->column(6, $this->revenueChartBox());
                $row->column(6, $this->planBreakdownBox());
            })
            ->row(function (Row $row) {
                $row->column(6, $this->appTypeBreakdownBox());
                $row->column(6, $this->expiringSubscriptionsBox());
            })
            ->body($this->grid());
    }

    /**
     * LugaFlix stats box
     */
    protected function lugaflixStatsBox()
    {
        $completed = Subscription::where('app_type', 'lugaflix')
            ->where('payment_status', 'Completed');
        
        $totalRevenue = (clone $completed)->sum('amount_paid');
        $totalSubs = (clone $completed)->count();
        $activeSubs = Subscription::where('app_type', 'lugaflix')
            ->where('status', 'Active')
            ->where('payment_status', 'Completed')
            ->count();
        
        $thisMonth = Subscription::where('app_type', 'lugaflix')
            ->where('payment_status', 'Completed')
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->sum('amount_paid');
        
        $today = Subscription::where('app_type', 'lugaflix')
            ->where('payment_status', 'Completed')
            ->whereDate('created_at', Carbon::today())
            ->sum('amount_paid');
        
        $todayCount = Subscription::where('app_type', 'lugaflix')
            ->where('payment_status', 'Completed')
            ->whereDate('created_at', Carbon::today())
            ->count();
        
        $pending = Subscription::where('app_type', 'lugaflix')
            ->where('payment_status', 'Pending')
            ->count();
        
        $processing = Subscription::where('app_type', 'lugaflix')
            ->where('payment_status', 'Processing')
            ->count();
        
        $failed = Subscription::where('app_type', 'lugaflix')
            ->where('payment_status', 'Failed')
            ->count();

        $rows = [
            ['💰 Total Revenue', 'UGX ' . number_format($totalRevenue)],
            ['✅ Total Completed', number_format($totalSubs)],
            ['🟢 Active Now', number_format($activeSubs)],
            ['📅 This Month', 'UGX ' . number_format($thisMonth)],
            ['⭐ Today (' . $todayCount . ' sales)', 'UGX ' . number_format($today)],
            ['⏳ Pending', "<span class='label label-warning'>{$pending}</span>"],
            ['🔄 Processing', "<span class='label label-info'>{$processing}</span>"],
            ['❌ Failed', "<span class='label label-danger'>{$failed}</span>"],
        ];

        $table = new Table(['Metric', 'Value'], $rows);
        $box = new Box('🎭 LugaFlix Stats', $table);
        $box->style('primary');
        $box->solid();

        return $box;
    }

    /**
     * UGFlix stats box
     */
    protected function ugflixStatsBox()
    {
        $completed = Subscription::where('app_type', 'ugflix')
            ->where('payment_status', 'Completed');
        
        $totalRevenue = (clone $completed)->sum('amount_paid');
        $totalSubs = (clone $completed)->count();
        $activeSubs = Subscription::where('app_type', 'ugflix')
            ->where('status', 'Active')
            ->where('payment_status', 'Completed')
            ->count();
        
        $thisMonth = Subscription::where('app_type', 'ugflix')
            ->where('payment_status', 'Completed')
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->sum('amount_paid');
        
        $today = Subscription::where('app_type', 'ugflix')
            ->where('payment_status', 'Completed')
            ->whereDate('created_at', Carbon::today())
            ->sum('amount_paid');
        
        $todayCount = Subscription::where('app_type', 'ugflix')
            ->where('payment_status', 'Completed')
            ->whereDate('created_at', Carbon::today())
            ->count();
        
        $pending = Subscription::where('app_type', 'ugflix')
            ->where('payment_status', 'Pending')
            ->count();
        
        $processing = Subscription::where('app_type', 'ugflix')
            ->where('payment_status', 'Processing')
            ->count();
        
        $failed = Subscription::where('app_type', 'ugflix')
            ->where('payment_status', 'Failed')
            ->count();

        $rows = [
            ['💰 Total Revenue', 'UGX ' . number_format($totalRevenue)],
            ['✅ Total Completed', number_format($totalSubs)],
            ['🟢 Active Now', number_format($activeSubs)],
            ['📅 This Month', 'UGX ' . number_format($thisMonth)],
            ['⭐ Today (' . $todayCount . ' sales)', 'UGX ' . number_format($today)],
            ['⏳ Pending', "<span class='label label-warning'>{$pending}</span>"],
            ['🔄 Processing', "<span class='label label-info'>{$processing}</span>"],
            ['❌ Failed', "<span class='label label-danger'>{$failed}</span>"],
        ];

        $table = new Table(['Metric', 'Value'], $rows);
        $box = new Box('🎬 UGFlix Stats', $table);
        $box->style('success');
        $box->solid();

        return $box;
    }

    /**
     * Total revenue info box
     */
    protected function totalRevenueBox()
    {
        $total = Subscription::where('payment_status', 'Completed')->sum('amount_paid');
        return new InfoBox('Total Revenue', 'money', 'green', '/admin/subscriptions?payment_status=Completed', 'UGX ' . number_format($total));
    }

    /**
     * Active subscriptions info box
     */
    protected function activeSubscriptionsBox()
    {
        $count = Subscription::where('status', 'Active')->count();
        return new InfoBox('Active Subscriptions', 'users', 'aqua', '/admin/subscriptions?status=Active', number_format($count));
    }

    /**
     * Monthly revenue info box
     */
    protected function monthlyRevenueBox()
    {
        $thisMonth = Subscription::where('payment_status', 'Completed')
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->sum('amount_paid');
        
        $lastMonth = Subscription::where('payment_status', 'Completed')
            ->whereMonth('created_at', Carbon::now()->subMonth()->month)
            ->whereYear('created_at', Carbon::now()->subMonth()->year)
            ->sum('amount_paid');
        
        $trend = $thisMonth > $lastMonth ? '↑' : ($thisMonth < $lastMonth ? '↓' : '→');
        
        return new InfoBox("This Month {$trend}", 'calendar', 'yellow', '#', 'UGX ' . number_format($thisMonth));
    }

    /**
     * Pending payments info box
     */
    protected function pendingPaymentsBox()
    {
        $count = Subscription::where('payment_status', 'Pending')->count();
        $amount = Subscription::where('payment_status', 'Pending')->sum('amount_paid');
        return new InfoBox("Pending ({$count})", 'clock-o', 'red', '/admin/subscriptions?payment_status=Pending', 'UGX ' . number_format($amount));
    }

    /**
     * Today's revenue info box
     */
    protected function todayRevenueBox()
    {
        $today = Subscription::where('payment_status', 'Completed')
            ->whereDate('created_at', Carbon::today())
            ->sum('amount_paid');
        $count = Subscription::where('payment_status', 'Completed')
            ->whereDate('created_at', Carbon::today())
            ->count();
        return new InfoBox("Today ({$count} sales)", 'star', 'olive', '#', 'UGX ' . number_format($today));
    }

    /**
     * Expiring today info box
     */
    protected function expiringTodayBox()
    {
        $count = Subscription::where('status', 'Active')
            ->whereDate('end_date_time', Carbon::today())
            ->count();
        return new InfoBox('Expiring Today', 'exclamation-triangle', 'orange', '#', $count);
    }

    /**
     * New subscriptions this week info box
     */
    protected function newThisWeekBox()
    {
        $count = Subscription::where('payment_status', 'Completed')
            ->where('created_at', '>=', Carbon::now()->startOfWeek())
            ->count();
        return new InfoBox('New This Week', 'plus-circle', 'purple', '#', $count);
    }

    /**
     * Churn rate info box (expired/cancelled vs total)
     */
    protected function churnRateBox()
    {
        $total = Subscription::count() ?: 1;
        $churned = Subscription::whereIn('status', ['Expired', 'Cancelled'])->count();
        $rate = round(($churned / $total) * 100, 1);
        $color = $rate > 30 ? 'red' : ($rate > 15 ? 'yellow' : 'green');
        return new InfoBox('Churn Rate', 'line-chart', $color, '#', $rate . '%');
    }

    /**
     * Revenue chart (last 7 days)
     */
    protected function revenueChartBox()
    {
        $days = [];
        $revenues = [];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $days[] = $date->format('M d');
            $revenues[] = Subscription::where('payment_status', 'Completed')
                ->whereDate('created_at', $date)
                ->sum('amount_paid');
        }

        $maxRevenue = max($revenues) ?: 1;
        $rows = [];
        foreach ($days as $idx => $day) {
            $amount = $revenues[$idx];
            $barLength = intval(($amount / $maxRevenue) * 20);
            $bar = str_repeat('█', max(1, $barLength));
            $rows[] = [$day, 'UGX ' . number_format($amount), "<span style='color:#28a745'>{$bar}</span>"];
        }

        $table = new Table(['Date', 'Revenue', 'Visual'], $rows);
        $box = new Box('📈 Revenue Last 7 Days', $table);
        $box->style('success');
        $box->solid();

        return $box;
    }

    /**
     * Plan breakdown box
     */
    protected function planBreakdownBox()
    {
        $plans = Subscription::where('payment_status', 'Completed')
            ->join('subscription_plans', 'subscriptions.plan_id', '=', 'subscription_plans.id')
            ->selectRaw('subscription_plans.name, COUNT(*) as count, SUM(subscriptions.amount_paid) as total')
            ->groupBy('subscription_plans.name')
            ->orderByDesc('total')
            ->get();

        $rows = [];
        foreach ($plans as $plan) {
            $rows[] = [
                $plan->name,
                number_format($plan->count),
                'UGX ' . number_format($plan->total),
            ];
        }

        if (empty($rows)) {
            $rows[] = ['No data yet', '-', '-'];
        }

        $table = new Table(['Plan', 'Subscribers', 'Revenue'], $rows);
        $box = new Box('📊 Plan Breakdown', $table);
        $box->style('info');
        $box->solid();

        return $box;
    }

    /**
     * App type breakdown box
     */
    protected function appTypeBreakdownBox()
    {
        $apps = Subscription::where('payment_status', 'Completed')
            ->selectRaw('app_type, platform, COUNT(*) as count, SUM(amount_paid) as total')
            ->groupBy('app_type', 'platform')
            ->orderByDesc('total')
            ->get();

        $rows = [];
        foreach ($apps as $app) {
            $icon = strtolower($app->platform ?? '') === 'ios' ? '🍎' : '🤖';
            $rows[] = [
                ucfirst($app->app_type ?? 'Unknown') . " {$icon}",
                ucfirst($app->platform ?? 'Unknown'),
                number_format($app->count),
                'UGX ' . number_format($app->total),
            ];
        }

        if (empty($rows)) {
            $rows[] = ['No data', '-', '-', '-'];
        }

        $table = new Table(['App', 'Platform', 'Count', 'Revenue'], $rows);
        $box = new Box('📱 App & Platform Breakdown', $table);
        $box->style('warning');
        $box->solid();

        return $box;
    }

    /**
     * Expiring subscriptions box
     */
    protected function expiringSubscriptionsBox()
    {
        $expiring = Subscription::where('status', 'Active')
            ->whereBetween('end_date_time', [Carbon::now(), Carbon::now()->addDays(7)])
            ->with('user')
            ->orderBy('end_date_time')
            ->limit(10)
            ->get();

        $rows = [];
        foreach ($expiring as $sub) {
            $user = $sub->user;
            $daysLeft = Carbon::now()->diffInDays(Carbon::parse($sub->end_date_time), false);
            $urgency = $daysLeft <= 1 ? 'danger' : ($daysLeft <= 3 ? 'warning' : 'info');
            $rows[] = [
                $user ? $user->name : 'Unknown',
                "<span class='label label-{$urgency}'>{$daysLeft} days</span>",
                Carbon::parse($sub->end_date_time)->format('M d'),
            ];
        }

        if (empty($rows)) {
            $rows[] = ['No expiring subscriptions', '-', '-'];
        }

        $table = new Table(['User', 'Days Left', 'Expires'], $rows);
        $box = new Box('⏰ Expiring Soon (Next 7 Days)', $table);
        $box->style('danger');
        $box->solid();

        return $box;
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new Subscription());
        
        // Default filter: only show Completed payments unless another payment_status is specified
        $grid->model()->with(['user', 'plan']);
        
        // Apply default filter for completed payments only (hide non-completed by default)
        if (!request()->has('payment_status') && !request()->has('_pjax')) {
            $grid->model()->where('payment_status', 'Completed');
        }
        
        $grid->model()->orderBy('id', 'desc');

        // Quick search
        $grid->quickSearch(['user.name', 'user.email', 'pesapal_merchant_reference']);

        // Filters
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();

            $filter->column(1/3, function ($filter) {
                $filter->equal('status', 'Status')->select([
                    'Pending' => 'Pending',
                    'Active' => 'Active',
                    'Expired' => 'Expired',
                    'Cancelled' => 'Cancelled',
                    'Failed' => 'Failed',
                ]);
                
                $filter->equal('payment_status', 'Payment Status')->select([
                    'Pending' => 'Pending',
                    'Processing' => 'Processing',
                    'Completed' => 'Completed',
                    'Failed' => 'Failed',
                    'Refunded' => 'Refunded',
                    '' => '-- Show All --',
                ])->default('Completed');
            });

            $filter->column(1/3, function ($filter) {
                $filter->equal('plan_id', 'Plan')->select(
                    SubscriptionPlan::pluck('name', 'id')->toArray()
                );
                
                $filter->equal('app_type', 'App Type')->select([
                    'ugflix' => 'UGFlix',
                    'lugaflix' => 'LugaFlix',
                ]);
                
                $filter->equal('platform', 'Platform')->select([
                    'android' => 'Android',
                    'ios' => 'iOS',
                ]);
            });

            $filter->column(1/3, function ($filter) {
                $filter->equal('user_id', 'User ID');
                $filter->between('created_at', 'Created Date')->datetime();
                $filter->between('amount_paid', 'Amount Range');
            });
        });

        // Columns
        $grid->column('id', __('ID'))->sortable();

        $grid->column('app_type', __('App'))
            ->display(function ($type) {
                $icons = [
                    'ugflix' => '🎬 UGFlix',
                    'lugaflix' => '🎭 LugaFlix',
                ];
                return $icons[strtolower($type ?? '')] ?? ucfirst($type ?? 'Unknown');
            })->sortable();

        $grid->column('platform', __('Platform'))
            ->display(function ($platform) {
                $icons = [
                    'android' => '🤖 Android',
                    'ios' => '🍎 iOS',
                ];
                return $icons[strtolower($platform ?? '')] ?? ucfirst($platform ?? 'Unknown');
            })->sortable();

        $grid->column('user.name', __('Subscriber'))
            ->display(function ($name) {
                $model = $this;
                if ($model->user) {
                    return "👤 <a href='/admin/users/{$model->user->id}'><strong>{$model->user->name}</strong></a><br><small class='text-muted'>{$model->user->email}</small>";
                }
                return '<span class="text-danger">❌ User not found</span>';
            });

        $grid->column('plan.name', __('Plan'))
            ->display(function ($planName) {
                $model = $this;
                if ($model->plan) {
                    $badge = '';
                    $days = $model->plan->duration_days ?? 0;
                    if ($days >= 365) {
                        $badge = "<span class='badge badge-danger'>🔥 Yearly</span>";
                    } elseif ($days >= 30) {
                        $badge = "<span class='badge badge-primary'>📅 Monthly</span>";
                    } elseif ($days >= 7) {
                        $badge = "<span class='badge badge-info'>📆 Weekly</span>";
                    } else {
                        $badge = "<span class='badge badge-secondary'>🕐 {$days} days</span>";
                    }
                    return "<strong>{$model->plan->name}</strong><br>{$badge}";
                }
                return '<span class="text-danger">Plan not found</span>';
            });

        $grid->column('amount_paid', __('💰 Amount'))
            ->display(function ($amount) {
                $model = $this;
                return "<strong style='color:#28a745'>{$model->currency} " . number_format($amount, 0) . "</strong>";
            })->sortable()
            ->totalRow(function ($amount) {
                return "<strong style='color:#28a745'>Total: UGX " . number_format($amount) . "</strong>";
            });

        $grid->column('status', __('Status'))
            ->display(function ($status) {
                $styles = [
                    'Active' => ['success', '✅'],
                    'Pending' => ['warning', '⏳'],
                    'Expired' => ['danger', '⏰'],
                    'Cancelled' => ['secondary', '❌'],
                    'Failed' => ['danger', '💔'],
                ];
                $style = $styles[$status] ?? ['info', '❓'];
                return "<span class='btn btn-sm btn-{$style[0]}'>{$style[1]} {$status}</span>";
            })->sortable();

        $grid->column('payment_status', __('Payment'))
            ->display(function ($payment_status) {
                $styles = [
                    'Completed' => ['success', '✅'],
                    'Pending' => ['warning', '⏳'],
                    'Processing' => ['info', '🔄'],
                    'Failed' => ['danger', '❌'],
                    'Refunded' => ['secondary', '↩️'],
                ];
                $style = $styles[$payment_status] ?? ['light', '❓'];
                return "<span class='badge badge-{$style[0]}'>{$style[1]} {$payment_status}</span>";
            })->sortable();

        $grid->column('days_remaining', __('⏱️ Days Left'))
            ->display(function ($value) {
                $model = $this;
                if ($model->status === 'Active' && $model->end_date_time) {
                    $days = Carbon::now()->diffInDays(Carbon::parse($model->end_date_time), false);
                    if ($days > 7) {
                        return "<span class='badge badge-success'>🟢 {$days} days</span>";
                    } elseif ($days > 0) {
                        return "<span class='badge badge-warning'>🟡 {$days} days</span>";
                    } elseif ($days >= -3) {
                        return "<span class='badge badge-danger'>🔴 Grace period</span>";
                    } else {
                        return "<span class='badge badge-dark'>⚫ Expired</span>";
                    }
                }
                return '-';
            });

        $grid->column('start_date_time', __('📅 Start'))
            ->display(function ($date) {
                return $date ? Carbon::parse($date)->format('M j, Y') : '-';
            })->sortable()->hide();

        $grid->column('end_date_time', __('📅 Expires'))
            ->display(function ($date) {
                if (!$date) return '-';
                $endDate = Carbon::parse($date);
                $isExpired = $endDate->isPast();
                $color = $isExpired ? 'danger' : 'success';
                return "<span class='text-{$color}'>" . $endDate->format('M j, Y') . "</span>";
            })->sortable();

        $grid->column('created_at', __('📆 Created'))
            ->display(function ($date) {
                return Carbon::parse($date)->format('M j, Y');
            })->sortable();

        // Export
        $grid->export(function ($export) {
            $export->filename('Subscriptions_' . date('Y-m-d'));
            $export->except(['actions']);
        });

        // Batch actions
        $grid->batchActions(function ($batch) {
            $batch->disableDelete();
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
        $subscription = Subscription::with(['user', 'plan'])->findOrFail($id);
        $show = new Show($subscription);
        $show->resource('/admin/subscriptions');

        // Subscription Info Panel
        $show->panel()->title('💎 Subscription Details');

        $show->divider('👤 Subscriber Information');
        
        $show->field('user.name', __('Name'))->as(function ($value) {
            return "👤 " . ($value ?? 'Unknown');
        });
        
        $show->field('user.email', __('Email'))->as(function ($value) {
            return "📧 " . ($value ?? 'N/A');
        });

        $show->divider('📋 Subscription Details');

        $show->field('plan.name', __('Plan Name'))->as(function ($value) {
            return "📦 " . ($value ?? 'Unknown');
        });
        
        $show->field('plan.duration_days', __('Plan Duration'))->as(function ($value) {
            return "📅 " . ($value ?? '0') . " days";
        });

        $show->field('amount_paid', __('Amount Paid'))->as(function ($value) use ($subscription) {
            return "💰 " . ($subscription->currency ?? 'UGX') . " " . number_format($value ?? 0);
        });

        $show->field('status', __('Status'))->as(function ($value) {
            $icons = [
                'Active' => '✅',
                'Pending' => '⏳',
                'Expired' => '⏰',
                'Cancelled' => '❌',
                'Failed' => '💔',
            ];
            return ($icons[$value] ?? '❓') . " " . $value;
        })->label([
            'Active' => 'success',
            'Pending' => 'warning',
            'Expired' => 'danger',
            'Cancelled' => 'default',
            'Failed' => 'danger',
        ]);

        $show->field('payment_status', __('Payment Status'))->as(function ($value) {
            $icons = [
                'Completed' => '✅',
                'Pending' => '⏳',
                'Processing' => '🔄',
                'Failed' => '❌',
                'Refunded' => '↩️',
            ];
            return ($icons[$value] ?? '❓') . " " . $value;
        })->label([
            'Completed' => 'success',
            'Pending' => 'warning',
            'Processing' => 'info',
            'Failed' => 'danger',
            'Refunded' => 'default',
        ]);

        $show->divider('📆 Timeline');

        $show->field('start_date_time', __('Start Date'))->as(function ($value) {
            return $value ? "🟢 " . Carbon::parse($value)->format('F j, Y g:i A') : 'Not set';
        });
        
        $show->field('end_date_time', __('End Date'))->as(function ($value) {
            if (!$value) return 'Not set';
            $endDate = Carbon::parse($value);
            $icon = $endDate->isPast() ? '🔴' : '🟢';
            return $icon . " " . $endDate->format('F j, Y g:i A');
        });

        $show->divider('💳 Payment Information');

        $show->field('app_type', __('App Type'))->as(function ($value) {
            $icons = ['ugflix' => '🎬', 'lugaflix' => '🎭'];
            return ($icons[strtolower($value ?? '')] ?? '📱') . " " . ucfirst($value ?? 'Unknown');
        });
        
        $show->field('platform', __('Platform'))->as(function ($value) {
            $icons = ['android' => '🤖', 'ios' => '🍎'];
            return ($icons[strtolower($value ?? '')] ?? '📱') . " " . ucfirst($value ?? 'Unknown');
        });

        $show->field('pesapal_tracking_id', __('Pesapal Tracking ID'))->as(function ($value) {
            return "🔗 " . ($value ?? 'N/A');
        });
        
        $show->field('pesapal_merchant_reference', __('Merchant Reference'))->as(function ($value) {
            return "📝 " . ($value ?? 'N/A');
        });

        $show->divider('🕐 Timestamps');

        $show->field('created_at', __('Created At'))->as(function ($value) {
            return $value ? Carbon::parse($value)->format('F j, Y g:i A') : 'N/A';
        });
        
        $show->field('updated_at', __('Updated At'))->as(function ($value) {
            return $value ? Carbon::parse($value)->format('F j, Y g:i A') : 'N/A';
        });

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new Subscription());

        $form->tab('💎 Subscription Details', function ($form) {
            $form->select('user_id', __('👤 User'))
                ->options(function ($id) {
                    $user = User::find($id);
                    if ($user) {
                        return [$user->id => $user->name . ' (' . $user->email . ')'];
                    }
                    return [];
                })
                ->ajax('/admin/api/users')
                ->rules('required')
                ->help('Search for a user by name or email');

            $form->select('plan_id', __('📦 Plan'))
                ->options(SubscriptionPlan::pluck('name', 'id'))
                ->rules('required');

            $form->decimal('amount_paid', __('💰 Amount Paid'))
                ->rules('required|numeric|min:0');

            $form->text('currency', __('💱 Currency'))
                ->default('UGX')
                ->rules('required');
        });

        $form->tab('📊 Status & Payment', function ($form) {
            $form->radio('status', __('📋 Status'))
                ->options([
                    'Pending' => '⏳ Pending',
                    'Active' => '✅ Active',
                    'Expired' => '⏰ Expired',
                    'Cancelled' => '❌ Cancelled',
                    'Failed' => '💔 Failed',
                ])
                ->default('Pending')
                ->rules('required');

            $form->radio('payment_status', __('💳 Payment Status'))
                ->options([
                    'Pending' => '⏳ Pending',
                    'Processing' => '🔄 Processing',
                    'Completed' => '✅ Completed',
                    'Failed' => '❌ Failed',
                    'Refunded' => '↩️ Refunded',
                ])
                ->default('Pending')
                ->rules('required');

            $form->select('app_type', __('📱 App Type'))
                ->options([
                    'ugflix' => '🎬 UGFlix',
                    'lugaflix' => '🎭 LugaFlix',
                ]);

            $form->select('platform', __('📲 Platform'))
                ->options([
                    'android' => '🤖 Android',
                    'ios' => '🍎 iOS',
                ]);
        });

        $form->tab('📆 Dates', function ($form) {
            $form->datetime('start_date_time', __('🟢 Start Date'))
                ->help('When the subscription becomes active');
            
            $form->datetime('end_date_time', __('🔴 End Date'))
                ->help('When the subscription expires');
        });

        $form->tab('💳 Payment Reference', function ($form) {
            $form->text('pesapal_tracking_id', __('🔗 Pesapal Tracking ID'))
                ->help('Payment gateway order tracking ID');
            
            $form->text('pesapal_merchant_reference', __('📝 Merchant Reference'))
                ->help('Merchant reference for the transaction');
        });

        // Auto-set dates when status changes to Active
        $form->saving(function (Form $form) {
            if ($form->status === 'Active' && !$form->model()->start_date_time) {
                $form->start_date_time = Carbon::now();
                
                // Get plan duration
                $plan = SubscriptionPlan::find($form->plan_id);
                if ($plan) {
                    $form->end_date_time = Carbon::now()->addDays($plan->duration_days);
                }
            }
        });

        return $form;
    }
}
