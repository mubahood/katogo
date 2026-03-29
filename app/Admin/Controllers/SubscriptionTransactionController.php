<?php

namespace App\Admin\Controllers;

use App\Models\SubscriptionTransaction;
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
use Encore\Admin\Layout\Column;
use Encore\Admin\Widgets\Box;
use Encore\Admin\Widgets\InfoBox;
use Encore\Admin\Widgets\Table;
use Illuminate\Support\Facades\DB;

class SubscriptionTransactionController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Payment Transactions';

    /**
     * Index interface with dashboard.
     *
     * @param Content $content
     * @return Content
     */
    public function index(Content $content)
    {
        return $content
            ->title('💳 Payment Transactions')
            ->description('Monitor all subscription payments and transactions')
            ->row(function (Row $row) {
                $row->column(12, $this->platformBalanceBox());
            })
            ->row(function (Row $row) {
                $row->column(3, $this->totalRevenueBox());
                $row->column(3, $this->todayRevenueBox());
                $row->column(3, $this->pendingPaymentsBox());
                $row->column(3, $this->failedPaymentsBox());
            })
            ->row(function (Row $row) {
                $row->column(3, $this->completedCountBox());
                $row->column(3, $this->withdrawalsBox());
                $row->column(3, $this->netRevenueBox());
                $row->column(3, $this->refundedBox());
            })
            ->row(function (Row $row) {
                $row->column(6, $this->revenueChartBox());
                $row->column(6, $this->paymentMethodsBox());
            })
            ->body($this->grid());
    }

    /**
     * Total revenue info box
     */
    protected function totalRevenueBox()
    {
        $total = SubscriptionTransaction::where('status', 'Completed')
            ->where('transaction_type', '!=', 'Withdrawal')
            ->sum('amount');
        return new InfoBox('Total Revenue', 'money', 'green', '/subscription-transactions?status=Completed', 'UGX ' . number_format($total));
    }

    /**
     * Today's revenue info box
     */
    protected function todayRevenueBox()
    {
        $today = SubscriptionTransaction::where('status', 'Completed')
            ->where('transaction_type', '!=', 'Withdrawal')
            ->whereDate('created_at', Carbon::today())
            ->sum('amount');
        return new InfoBox("Today's Revenue", 'calendar', 'aqua', '#', 'UGX ' . number_format($today));
    }

    /**
     * Pending payments info box
     */
    protected function pendingPaymentsBox()
    {
        $count = SubscriptionTransaction::where('status', 'Pending')->count();
        $amount = SubscriptionTransaction::where('status', 'Pending')->sum('amount');
        return new InfoBox("Pending ({$count})", 'clock-o', 'yellow', '/subscription-transactions?status=Pending', 'UGX ' . number_format($amount));
    }

    /**
     * Failed payments info box
     */
    protected function failedPaymentsBox()
    {
        $count = SubscriptionTransaction::where('status', 'Failed')->count();
        return new InfoBox('Failed Payments', 'times-circle', 'red', '/subscription-transactions?status=Failed', $count);
    }

    /**
     * Completed count info box
     */
    protected function completedCountBox()
    {
        $count = SubscriptionTransaction::where('status', 'Completed')->count();
        return new InfoBox('Completed Txns', 'check-circle', 'green', '/subscription-transactions?status=Completed', number_format($count));
    }

    /**
     * Mobile money info box
     */
    protected function mobileMoneyBox()
    {
        $amount = SubscriptionTransaction::where('status', 'Completed')
            ->where(function ($q) {
                $q->where('payment_method', 'like', '%mobile%')
                    ->orWhere('payment_method', 'like', '%mtn%')
                    ->orWhere('payment_method', 'like', '%airtel%');
            })
            ->sum('amount');
        return new InfoBox('Mobile Money', 'mobile', 'purple', '#', 'UGX ' . number_format($amount));
    }

    /**
     * Card payments info box
     */
    protected function cardPaymentsBox()
    {
        $amount = SubscriptionTransaction::where('status', 'Completed')
            ->where(function ($q) {
                $q->where('payment_method', 'like', '%card%')
                    ->orWhere('payment_method', 'like', '%visa%')
                    ->orWhere('payment_method', 'like', '%mastercard%');
            })
            ->sum('amount');
        return new InfoBox('Card Payments', 'credit-card', 'blue', '#', 'UGX ' . number_format($amount));
    }

    /**
     * Withdrawals info box
     */
    protected function withdrawalsBox()
    {
        $amount = SubscriptionTransaction::where('status', 'Completed')
            ->where('transaction_type', 'Withdrawal')
            ->sum('amount');
        $count = SubscriptionTransaction::where('status', 'Completed')
            ->where('transaction_type', 'Withdrawal')
            ->count();
        return new InfoBox("Withdrawn ({$count})", 'arrow-circle-up', 'maroon', '/subscription-transactions?transaction_type=Withdrawal&status=all', 'UGX ' . number_format(abs($amount)));
    }

    /**
     * Net revenue info box (revenue minus withdrawals)
     */
    protected function netRevenueBox()
    {
        $net = SubscriptionTransaction::where('status', 'Completed')->sum('amount');
        $color = $net >= 0 ? 'green' : 'red';
        return new InfoBox('Net Balance', 'balance-scale', $color, '#', 'UGX ' . number_format($net));
    }

    /**
     * Refunded info box
     */
    protected function refundedBox()
    {
        $amount = SubscriptionTransaction::where('status', 'Refunded')->sum('amount');
        $count = SubscriptionTransaction::where('status', 'Refunded')->count();
        return new InfoBox("Refunded ({$count})", 'undo', 'gray', '/subscription-transactions?status=Refunded', 'UGX ' . number_format($amount));
    }

    /**
     * Platform balance breakdown box
     */
    protected function platformBalanceBox()
    {
        $platforms = [
            'lugaflix'  => ['LugaFlix',      '#3498db', 'fa-play-circle-o'],
            'muno_app'  => ['Muno App',      '#e74c3c', 'fa-fire'],
            'ugflix'    => ['UgFlix',         '#2ecc71', 'fa-bolt'],
            'web'       => ['Web (Katogo)',   '#9b59b6', 'fa-globe'],
        ];
        $pesapalRate = 0.035;

        $revenueByPlatform = DB::table('subscriptions')
            ->where('payment_status', 'Completed')
            ->selectRaw("COALESCE(app_type, 'unassigned') as plat, SUM(amount_paid) as total")
            ->groupBy('plat')
            ->pluck('total', 'plat');

        $withdrawalsByPlatform = SubscriptionTransaction::where('status', 'Completed')
            ->where('transaction_type', 'Withdrawal')
            ->selectRaw("COALESCE(platform, 'unassigned') as plat, SUM(ABS(amount)) as total")
            ->groupBy('plat')
            ->pluck('total', 'plat');

        $grandRevenue = 0;
        $grandPesapal = 0;
        $grandWithdrawn = 0;
        $grandBalance = 0;
        $platformData = [];

        foreach ($platforms as $key => [$label, $color, $icon]) {
            $revenue = (float) ($revenueByPlatform[$key] ?? 0);
            $fee = round($revenue * $pesapalRate, 2);
            $after = $revenue - $fee;
            $withdrawn = (float) ($withdrawalsByPlatform[$key] ?? 0);
            $balance = $after - $withdrawn;
            $grandRevenue += $revenue;
            $grandPesapal += $fee;
            $grandWithdrawn += $withdrawn;
            $grandBalance += $balance;
            $platformData[] = compact('key', 'label', 'color', 'icon', 'revenue', 'fee', 'after', 'withdrawn', 'balance');
        }

        // Unassigned
        $uRev = (float) ($revenueByPlatform['unassigned'] ?? 0);
        $uWith = (float) ($withdrawalsByPlatform['unassigned'] ?? 0);
        if ($uRev > 0 || $uWith > 0) {
            $uFee = round($uRev * $pesapalRate, 2);
            $uAfter = $uRev - $uFee;
            $uBal = $uAfter - $uWith;
            $grandRevenue += $uRev;
            $grandPesapal += $uFee;
            $grandWithdrawn += $uWith;
            $grandBalance += $uBal;
            $platformData[] = ['key' => 'unassigned', 'label' => 'Unassigned', 'color' => '#95a5a6', 'icon' => 'fa-question-circle', 'revenue' => $uRev, 'fee' => $uFee, 'after' => $uAfter, 'withdrawn' => $uWith, 'balance' => $uBal];
        }

        $revValues = array_column($platformData, 'revenue');
        $maxRevenue = !empty($revValues) ? max(max($revValues), 1) : 1;

        // Build styled HTML
        $html = '
<style>
.pb-wrap{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
.pb-table{width:100%;border-collapse:separate;border-spacing:0;font-size:13px}
.pb-table th{background:linear-gradient(135deg,#1a1a2e,#16213e);color:#fff;padding:10px 14px;font-size:10px;text-transform:uppercase;letter-spacing:.8px;font-weight:600;white-space:nowrap}
.pb-table th:first-child{border-radius:8px 0 0 0}
.pb-table th:last-child{border-radius:0 8px 0 0}
.pb-table td{padding:10px 14px;border-bottom:1px solid #f0f0f0;vertical-align:middle;transition:all .2s ease}
.pb-table tr.pb-row:hover td{background:#f8fafd;transform:scale(1.001)}
.pb-table tr.pb-row{cursor:default}
.pb-plat{display:flex;align-items:center;gap:8px;font-weight:600;font-size:13px}
.pb-plat .pb-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;box-shadow:0 0 0 2px rgba(0,0,0,.08)}
.pb-plat i{font-size:12px;opacity:.6}
.pb-amt{text-align:right;font-variant-numeric:tabular-nums;font-size:13px;white-space:nowrap}
.pb-fee{color:#dc3545;font-size:12px}
.pb-bar-wrap{width:100%;height:6px;background:#eee;border-radius:3px;margin-top:4px;overflow:hidden}
.pb-bar{height:100%;border-radius:3px;transition:width .8s cubic-bezier(.4,0,.2,1)}
.pb-bal{font-weight:700;font-size:14px}
.pb-total td{background:linear-gradient(135deg,#f8f9fa,#eef1f5)!important;border-top:2px solid #333;font-weight:700;padding:12px 14px}
.pb-total td:first-child{border-radius:0 0 0 8px}
.pb-total td:last-child{border-radius:0 0 8px 0}
.pb-badge{display:inline-block;padding:2px 8px;border-radius:12px;font-size:10px;font-weight:700;color:#fff;letter-spacing:.3px}
@media(max-width:768px){
  .pb-table{font-size:11px}
  .pb-table th,.pb-table td{padding:6px 8px}
  .pb-hide-sm{display:none}
}
</style>
<div class="pb-wrap">
<table class="pb-table">
<thead>
<tr>
  <th style="text-align:left">Platform</th>
  <th style="text-align:right">Total Revenue</th>
  <th style="text-align:right" class="pb-hide-sm">Pesapal (3.5%)</th>
  <th style="text-align:right">After Fees</th>
  <th style="text-align:right">Withdrawn</th>
  <th style="text-align:right">Balance</th>
</tr>
</thead>
<tbody>';

        foreach ($platformData as $p) {
            $bClr = $p['balance'] >= 0 ? '#28a745' : '#dc3545';
            $barPct = $maxRevenue > 0 ? round(($p['revenue'] / $maxRevenue) * 100) : 0;
            $isUnassigned = $p['key'] === 'unassigned';

            $html .= '<tr class="pb-row">';
            $html .= '<td><div class="pb-plat"><span class="pb-dot" style="background:' . $p['color'] . '"></span><i class="fa ' . $p['icon'] . '" style="color:' . $p['color'] . '"></i> ' . htmlspecialchars($p['label']) . '</div>'
                . '<div class="pb-bar-wrap"><div class="pb-bar" style="width:' . $barPct . '%;background:' . $p['color'] . '"></div></div></td>';
            $html .= '<td class="pb-amt"><strong>UGX ' . number_format($p['revenue']) . '</strong></td>';
            $html .= '<td class="pb-amt pb-fee pb-hide-sm">- UGX ' . number_format($p['fee']) . '</td>';
            $html .= '<td class="pb-amt">UGX ' . number_format($p['after']) . '</td>';
            $html .= '<td class="pb-amt pb-fee">- UGX ' . number_format($p['withdrawn']) . '</td>';
            $html .= '<td class="pb-amt"><span class="pb-badge" style="background:' . $bClr . '">UGX ' . number_format($p['balance']) . '</span></td>';
            $html .= '</tr>';
        }

        // Grand total
        $tClr = $grandBalance >= 0 ? '#28a745' : '#dc3545';
        $html .= '<tr class="pb-total">';
        $html .= '<td><strong style="font-size:13px">TOTAL</strong></td>';
        $html .= '<td class="pb-amt"><strong>UGX ' . number_format($grandRevenue) . '</strong></td>';
        $html .= '<td class="pb-amt pb-fee pb-hide-sm"><strong>- UGX ' . number_format($grandPesapal) . '</strong></td>';
        $html .= '<td class="pb-amt"><strong>UGX ' . number_format($grandRevenue - $grandPesapal) . '</strong></td>';
        $html .= '<td class="pb-amt pb-fee"><strong>- UGX ' . number_format($grandWithdrawn) . '</strong></td>';
        $html .= '<td class="pb-amt"><span class="pb-badge" style="background:' . $tClr . ';font-size:12px;padding:3px 12px">UGX ' . number_format($grandBalance) . '</span></td>';
        $html .= '</tr>';

        $html .= '</tbody></table></div>';

        $box = new Box('📊 Platform Balance Summary', $html);
        $box->style('primary');
        $box->solid();

        return $box;
    }

    /**
     * Revenue chart box (last 7 days)
     */
    protected function revenueChartBox()
    {
        $days = [];
        $revenues = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $days[] = $date->format('M d');
            $revenues[] = SubscriptionTransaction::where('status', 'Completed')
                ->whereDate('created_at', $date)
                ->sum('amount');
        }

        $rows = [];
        $maxRevenue = max($revenues) ?: 1;
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
     * Payment methods breakdown box
     */
    protected function paymentMethodsBox()
    {
        $methods = SubscriptionTransaction::where('status', 'Completed')
            ->whereNotNull('payment_method')
            ->where('payment_method', '!=', '')
            ->selectRaw('payment_method, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('payment_method')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $rows = [];
        foreach ($methods as $method) {
            $rows[] = [
                ucfirst($method->payment_method ?? 'Unknown'),
                number_format($method->count),
                'UGX ' . number_format($method->total),
            ];
        }

        if (empty($rows)) {
            $rows[] = ['No data yet', '-', '-'];
        }

        $table = new Table(['Method', 'Txns', 'Total'], $rows);
        $box = new Box('💳 Payment Methods Breakdown', $table);
        $box->style('info');
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
        $grid = new Grid(new SubscriptionTransaction());
        $grid->model()->orderBy('id', 'desc');
        //check if status is not set by get
        if (!request()->has('status')) {
            $grid->model()->where('status', 'Completed');
        }

        // Quick filters
        $grid->quickSearch('pesapal_tracking_id', 'merchant_reference', 'confirmation_code');

        $grid->column('id', __('ID'))->sortable();

        $grid->column('created_at', __('Date'))
            ->display(function ($created_at) {
                return Carbon::parse($created_at)->format('M d, Y H:i');
            })->sortable();

        $grid->column('user_id', __('User'))
            ->display(function ($user_id) {
                $user = User::find($user_id);
                if ($user) {
                    return "<a href='/users/{$user->id}'><strong>{$user->name}</strong></a><br><small class='text-muted'>{$user->email}</small>";
                }
                return "<span class='text-danger'>User #{$user_id} not found</span>";
            });

        $grid->column('subscription_id', __('Subscription'))
            ->display(function ($subscription_id) {
                if ($subscription_id) {
                    return "<a href='/subscriptions/{$subscription_id}'>#{$subscription_id}</a>";
                }
                return '-';
            })->sortable();

        $grid->column('transaction_type', __('Type'))
            ->display(function ($type) {
                $icons = [
                    'Initial' => '🆕',
                    'Renewal' => '🔄',
                    'Upgrade' => '⬆️',
                    'Refund' => '↩️',
                    'Withdrawal' => '💸',
                ];
                $icon = $icons[$type] ?? '📝';
                return "{$icon} {$type}";
            })
            ->label([
                'Initial' => 'primary',
                'Renewal' => 'success',
                'Upgrade' => 'info',
                'Refund' => 'warning',
                'Withdrawal' => 'danger',
            ]);

        $grid->column('platform', __('Platform'))
            ->display(function ($platform) {
                $labels = [
                    'lugaflix' => '🎬 LugaFlix',
                    'muno_app' => '📱 Muno',
                    'ugflix' => '🎥 UgFlix',
                    'web' => '🌐 Web',
                ];
                return $labels[$platform] ?? ($platform ?: '-');
            })
            ->label([
                'lugaflix' => 'success',
                'muno_app' => 'primary',
                'ugflix' => 'info',
                'web' => 'default',
            ])
            ->sortable();

        $grid->column('amount', __('Amount'))
            ->display(function ($amount) {
                $currency = $this->currency ?? 'UGX';
                if ($amount < 0) {
                    return "<strong style='color:red'>- {$currency} " . number_format(abs($amount)) . "</strong>";
                }
                $color = $this->status === 'Completed' ? 'green' : ($this->status === 'Failed' ? 'red' : 'orange');
                return "<strong style='color:{$color}'>{$currency} " . number_format($amount) . "</strong>";
            })->sortable()
            ->totalRow(function ($amount) {
                return '<strong>Total: UGX ' . number_format($amount) . '</strong>';
            });

        $grid->column('status', __('Status'))
            ->display(function ($status) {
                $icons = [
                    'Pending' => '⏳',
                    'Completed' => '✅',
                    'Failed' => '❌',
                    'Refunded' => '↩️',
                ];
                return ($icons[$status] ?? '') . ' ' . $status;
            })
            ->label([
                'Pending' => 'warning',
                'Completed' => 'success',
                'Failed' => 'danger',
                'Refunded' => 'info',
            ])
            ->sortable();

        $grid->column('payment_method', __('Method'))
            ->display(function ($method) {
                if (!$method) return '-';
                $lower = strtolower($method);
                if (strpos($lower, 'mtn') !== false) return '📱 MTN';
                if (strpos($lower, 'airtel') !== false) return '📱 Airtel';
                if (strpos($lower, 'mobile') !== false) return '📱 Mobile';
                if (strpos($lower, 'visa') !== false) return '💳 Visa';
                if (strpos($lower, 'mastercard') !== false) return '💳 MC';
                if (strpos($lower, 'card') !== false) return '💳 Card';
                return ucfirst($method);
            })->sortable();

        $grid->column('pesapal_tracking_id', __('Tracking ID'))
            ->copyable()
            ->display(function ($id) {
                return $id ? "<code>{$id}</code>" : '-';
            });

        $grid->column('confirmation_code', __('Conf. Code'))
            ->copyable()
            ->display(function ($code) {
                return $code ? "<code class='text-success'>{$code}</code>" : '-';
            });

        $grid->column('error_message', __('Error'))
            ->display(function ($error) {
                if (!$error) return '-';
                $short = mb_substr($error, 0, 40);
                return "<span class='text-danger' title='" . htmlspecialchars($error) . "'>{$short}...</span>";
            })->hide();

        // Filters
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();

            $filter->column(1 / 3, function ($filter) {
                $filter->equal('status', 'Status')->select([
                    'Pending' => 'Pending',
                    'Completed' => 'Completed',
                    'Failed' => 'Failed',
                    'Refunded' => 'Refunded',
                ]);

                $filter->equal('transaction_type', 'Type')->select([
                    'Initial' => 'Initial',
                    'Renewal' => 'Renewal',
                    'Upgrade' => 'Upgrade',
                    'Refund' => 'Refund',
                    'Withdrawal' => 'Withdrawal',
                ]);

                $filter->equal('platform', 'Platform')->select([
                    'lugaflix' => 'LugaFlix',
                    'muno_app' => 'Muno App',
                    'ugflix' => 'UgFlix',
                    'web' => 'Web (Katogo)',
                ]);
            });

            $filter->column(1 / 3, function ($filter) {
                $filter->equal('user_id', 'User ID');
                $filter->equal('subscription_id', 'Subscription ID');
                $filter->like('pesapal_tracking_id', 'Tracking ID');
            });

            $filter->column(1 / 3, function ($filter) {
                $filter->like('confirmation_code', 'Confirmation Code');
                $filter->like('payment_method', 'Payment Method');
                $filter->between('created_at', 'Date Range')->datetime();
            });

            $filter->between('amount', 'Amount Range');
        });

        // Export
        $grid->export(function ($export) {
            $export->filename('Transactions_' . date('Y-m-d_H-i'));
            $export->except(['actions']);
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
        $show = new Show(SubscriptionTransaction::findOrFail($id));

        $show->panel()->title('Transaction Details');

        $show->field('id', __('Transaction ID'));
        $show->field('created_at', __('Date'))->as(function ($date) {
            return Carbon::parse($date)->format('F j, Y \a\t H:i:s');
        });

        $show->divider();

        $show->field('user_id', __('User'))->as(function ($user_id) {
            $user = User::find($user_id);
            return $user ? "{$user->name} ({$user->email})" : "User #{$user_id} not found";
        });
        $show->field('subscription_id', __('Subscription ID'));

        $show->divider();

        $show->field('transaction_type', __('Transaction Type'));
        $show->field('platform', __('Platform'))->as(function ($platform) {
            $labels = [
                'lugaflix' => 'LugaFlix',
                'muno_app' => 'Muno App',
                'ugflix' => 'UgFlix',
                'web' => 'Web (Katogo)',
            ];
            return $labels[$platform] ?? ($platform ?: 'N/A');
        });
        $show->field('amount', __('Amount'))->as(function ($amount) {
            $currency = $this->currency ?? 'UGX';
            return "{$currency} " . number_format($amount, 2);
        });
        $show->field('currency', __('Currency'));
        $show->field('status', __('Status'));

        $show->divider();

        $show->field('payment_method', __('Payment Method'));
        $show->field('pesapal_tracking_id', __('Pesapal Tracking ID'));
        $show->field('merchant_reference', __('Merchant Reference'));
        $show->field('confirmation_code', __('Confirmation Code'));
        $show->field('payment_account', __('Payment Account'));

        $show->divider();

        $show->field('error_message', __('Error Message'));
        $show->field('request_payload', __('Request Payload'));
        $show->field('response_payload', __('Response Payload'));

        $show->divider();

        $show->field('ip_address', __('IP Address'));
        $show->field('user_agent', __('User Agent'));

        $show->divider();

        $show->field('refunded_by', __('Refunded By'));
        $show->field('refunded_at', __('Refunded At'));
        $show->field('refund_reason', __('Refund Reason'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new SubscriptionTransaction());

        $isEditing = $form->isEditing();

        $form->display('id', __('Transaction ID'));
        $form->display('created_at', __('Date'));

        $form->divider('Transaction Details');

        if ($isEditing) {
            $form->number('subscription_id', __('Subscription ID'));
            $form->number('user_id', __('User ID'));

            $form->select('transaction_type', __('Transaction Type'))->options([
                'Initial' => 'Initial',
                'Renewal' => 'Renewal',
                'Upgrade' => 'Upgrade',
                'Refund' => 'Refund',
                'Withdrawal' => 'Withdrawal',
            ])->default('Initial');

            $form->select('platform', __('Platform'))->options([
                'lugaflix' => 'LugaFlix',
                'muno_app' => 'Muno App',
                'ugflix' => 'UgFlix',
                'web' => 'Web (Katogo)',
            ]);

            $form->decimal('amount', __('Amount'));
            $form->text('currency', __('Currency'))->default('UGX');

            $form->select('status', __('Status'))->options([
                'Pending' => 'Pending',
                'Completed' => 'Completed',
                'Failed' => 'Failed',
                'Refunded' => 'Refunded',
            ])->default('Pending');

            $form->divider('Payment Details');

            $form->text('payment_method', __('Payment Method'));
            $form->text('pesapal_tracking_id', __('Pesapal Tracking ID'));
            $form->text('merchant_reference', __('Merchant Reference'));
            $form->text('confirmation_code', __('Confirmation Code'));
            $form->text('payment_account', __('Payment Account'));

            $form->divider('Refund (Admin Only)');

            $form->textarea('refund_reason', __('Refund Reason'));
            $form->datetime('refunded_at', __('Refunded At'));
        } else {
            // CREATE MODE: simplified withdrawal form
            $form->html('<div style="background:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:12px;margin-bottom:15px">' .
                '<strong>💸 Record a Withdrawal</strong><br>' .
                '<small>This records money taken out of the platform (e.g. bank transfer, cash out). ' .
                'The amount will be stored as negative and subtracted from total revenue.</small></div>');

            $form->select('platform', __('Platform'))
                ->options([
                    'lugaflix' => 'LugaFlix',
                    'muno_app' => 'Muno App',
                    'ugflix' => 'UgFlix',
                    'web' => 'Web (Katogo)',
                ])
                ->required()
                ->rules('required')
                ->help('Which platform is this withdrawal from?');

            $form->decimal('amount', __('Withdrawal Amount (UGX)'))
                ->required()
                ->rules('required|numeric|min:1')
                ->help('Enter the amount withdrawn (as a positive number — it will be saved as negative)');

            $form->text('currency', __('Currency'))->default('UGX')->readonly();

            $form->textarea('refund_reason', __('Withdrawal Note / Reason'))
                ->required()
                ->rules('required')
                ->help('Describe what this withdrawal was for (e.g. "Bank transfer to account XXX", "Cash out for expenses")');

            $form->hidden('transaction_type')->default('Withdrawal');
            $form->hidden('status')->default('Completed');
            $form->hidden('payment_method')->default('Admin Withdrawal');
            $form->hidden('subscription_id')->default(0);
            $form->hidden('user_id')->default(\Admin::user()->id);
            $form->hidden('merchant_reference')->default('WD-' . date('YmdHis') . '-' . \Admin::user()->id);
        }

        $form->saving(function (Form $form) {
            // Handle withdrawal: store amount as negative
            if ($form->transaction_type === 'Withdrawal' && !$form->isEditing()) {
                $amount = abs((float) $form->amount);
                $form->amount = -$amount;
                $form->user_id = \Admin::user()->id;
                $form->subscription_id = 0;
                $form->merchant_reference = 'WD-' . date('YmdHis') . '-' . \Admin::user()->id;
            }

            if ($form->status === 'Refunded' && !$form->model()->refunded_by) {
                $form->refunded_by = \Admin::user()->id;
                if (!$form->refunded_at) {
                    $form->refunded_at = now();
                }
            }
        });

        return $form;
    }
}
