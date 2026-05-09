<?php

namespace App\Admin\Controllers;

use App\Models\SubscriptionTransaction;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\PaymentStatusChecker;
use App\Services\SubscriptionActivationService;
use App\Services\SubscriptionFlutterwaveService;
use App\Services\SubscriptionPesapalService;
use Carbon\Carbon;
use Encore\Admin\Facades\Admin;
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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
     * Consolidated stats query — cached 5 min to avoid N+1 per box (P11-04)
     */
    private function getStats(): array
    {
        return Cache::remember('sub_txn_stats', 300, function () {
            $row = SubscriptionTransaction::selectRaw("
                SUM(CASE WHEN status='Completed' AND transaction_type != 'Withdrawal' THEN amount ELSE 0 END) as total_revenue,
                SUM(CASE WHEN status='Completed' AND transaction_type != 'Withdrawal' AND DATE(created_at)=CURDATE() THEN amount ELSE 0 END) as today_revenue,
                SUM(CASE WHEN status='Pending' THEN amount ELSE 0 END) as pending_amount,
                COUNT(CASE WHEN status='Pending' THEN 1 END) as pending_count,
                COUNT(CASE WHEN status='Failed' THEN 1 END) as failed_count,
                COUNT(CASE WHEN status='Completed' THEN 1 END) as completed_count,
                SUM(CASE WHEN status='Completed' AND transaction_type='Withdrawal' THEN amount ELSE 0 END) as withdrawal_amount,
                COUNT(CASE WHEN status='Completed' AND transaction_type='Withdrawal' THEN 1 END) as withdrawal_count,
                SUM(CASE WHEN status='Completed' THEN amount ELSE 0 END) as net_amount,
                SUM(CASE WHEN status='Refunded' THEN amount ELSE 0 END) as refunded_amount,
                COUNT(CASE WHEN status='Refunded' THEN 1 END) as refunded_count
            ")->first();
            return [
                'total_revenue'     => (float) ($row->total_revenue     ?? 0),
                'today_revenue'     => (float) ($row->today_revenue     ?? 0),
                'pending_amount'    => (float) ($row->pending_amount    ?? 0),
                'pending_count'     => (int)   ($row->pending_count     ?? 0),
                'failed_count'      => (int)   ($row->failed_count      ?? 0),
                'completed_count'   => (int)   ($row->completed_count   ?? 0),
                'withdrawal_amount' => (float) ($row->withdrawal_amount ?? 0),
                'withdrawal_count'  => (int)   ($row->withdrawal_count  ?? 0),
                'net_amount'        => (float) ($row->net_amount        ?? 0),
                'refunded_amount'   => (float) ($row->refunded_amount   ?? 0),
                'refunded_count'    => (int)   ($row->refunded_count    ?? 0),
            ];
        });
    }

    /**
     * Total revenue info box
     */
    protected function totalRevenueBox()
    {
        $s = $this->getStats();
        return new InfoBox('Total Revenue', 'money', 'green', '/subscription-transactions?status=Completed', 'UGX ' . number_format($s['total_revenue']));
    }

    /**
     * Today's revenue info box
     */
    protected function todayRevenueBox()
    {
        $s = $this->getStats();
        return new InfoBox("Today's Revenue", 'calendar', 'aqua', '#', 'UGX ' . number_format($s['today_revenue']));
    }

    /**
     * Pending payments info box
     */
    protected function pendingPaymentsBox()
    {
        $s = $this->getStats();
        return new InfoBox("Pending ({$s['pending_count']})", 'clock-o', 'yellow', '/subscription-transactions?status=Pending', 'UGX ' . number_format($s['pending_amount']));
    }

    /**
     * Failed payments info box
     */
    protected function failedPaymentsBox()
    {
        $s = $this->getStats();
        return new InfoBox('Failed Payments', 'times-circle', 'red', '/subscription-transactions?status=Failed', $s['failed_count']);
    }

    /**
     * Completed count info box
     */
    protected function completedCountBox()
    {
        $s = $this->getStats();
        return new InfoBox('Completed Txns', 'check-circle', 'green', '/subscription-transactions?status=Completed', number_format($s['completed_count']));
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
        $s = $this->getStats();
        return new InfoBox("Withdrawn ({$s['withdrawal_count']})", 'arrow-circle-up', 'maroon', '/subscription-transactions?transaction_type=Withdrawal&status=all', 'UGX ' . number_format(abs($s['withdrawal_amount'])));
    }

    /**
     * Net revenue info box (revenue minus withdrawals)
     */
    protected function netRevenueBox()
    {
        $s = $this->getStats();
        $color = $s['net_amount'] >= 0 ? 'green' : 'red';
        return new InfoBox('Net Balance', 'balance-scale', $color, '#', 'UGX ' . number_format($s['net_amount']));
    }

    /**
     * Refunded info box
     */
    protected function refundedBox()
    {
        $s = $this->getStats();
        return new InfoBox("Refunded ({$s['refunded_count']})", 'undo', 'gray', '/subscription-transactions?status=Refunded', 'UGX ' . number_format($s['refunded_amount']));
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
        $grid->model()->with([
            'user:id,name,email,phone_number,app_type,platform',
            'subscription:id,plan_id,status,payment_status,start_date_time,end_date_time,grace_period_end',
            'subscription.plan:id,name,price,currency,duration_days',
        ]);
        $grid->model()->orderBy('id', 'desc');

        // Quick filters
        $grid->quickSearch('pesapal_tracking_id', 'merchant_reference', 'confirmation_code', 'payment_account', 'error_message', 'ip_address');

        $grid->column('id', __('ID'))->sortable();

        $grid->column('created_at', __('Date'))
            ->display(function ($created_at) {
                return Carbon::parse($created_at)->format('M d, Y H:i');
            })->sortable();

        $grid->column('user_id', __('User'))
            ->display(function ($user_id) {
                $user = $this->user;
                if ($user) {
                    $phone = trim((string) ($user->phone_number ?? ''));
                    $appType = trim((string) ($user->app_type ?? ''));
                    $platform = trim((string) ($user->platform ?? ''));
                    $meta = [];
                    if ($phone !== '') {
                        $meta[] = "<span class='text-muted'>{$phone}</span>";
                    }
                    if ($appType !== '') {
                        $meta[] = "<span class='label label-default'>{$appType}</span>";
                    }
                    if ($platform !== '') {
                        $meta[] = "<span class='label label-info'>{$platform}</span>";
                    }

                    return "<a href='/users/{$user->id}'><strong>{$user->name}</strong></a><br><small class='text-muted'>{$user->email}</small>"
                        . (!empty($meta) ? "<br>" . implode(' ', $meta) : '');
                }
                return "<span class='text-danger'>User #{$user_id} not found</span>";
            });

        $grid->column('subscription_id', __('Subscription'))
            ->display(function ($subscription_id) {
                if (!$subscription_id) {
                    return '-';
                }

                $subscription = $this->subscription;
                $html = "<a href='/subscriptions/{$subscription_id}'>#{$subscription_id}</a>";

                if ($subscription) {
                    $subStatus = $subscription->status ?? 'N/A';
                    $payStatus = $subscription->payment_status ?? 'N/A';
                    $start = $subscription->start_date_time ? Carbon::parse($subscription->start_date_time)->format('d M Y') : '-';
                    $end = $subscription->end_date_time ? Carbon::parse($subscription->end_date_time)->format('d M Y') : '-';
                    $html .= "<br><small class='text-muted'>{$start} -> {$end}</small>";
                    $html .= "<br><small><span class='label label-primary'>{$subStatus}</span> <span class='label label-default'>{$payStatus}</span></small>";
                }

                return $html;
            })->sortable();

        $grid->column('subscription.plan.name', __('Plan'))
            ->display(function ($value) {
                $plan = optional($this->subscription)->plan;
                if (!$plan) {
                    return '-';
                }

                $price = number_format((float) ($plan->price ?? 0));
                $currency = $plan->currency ?? 'UGX';
                $days = (int) ($plan->duration_days ?? 0);

                return "<strong>{$plan->name}</strong><br><small class='text-muted'>{$currency} {$price}" . ($days > 0 ? " / {$days} days" : '') . "</small>";
            })->hide();

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
            })->sortable()->label();

        $grid->column('pesapal_tracking_id', __('Tracking ID'))
            ->copyable()
            ->display(function ($id) {
                $html = $id ? "<code>{$id}</code>" : '-';
                if ($this->merchant_reference) {
                    $html .= "<br><small class='text-muted'>Ref: <code>{$this->merchant_reference}</code></small>";
                }
                return $html;
            });

        $grid->column('confirmation_code', __('Conf. Code'))
            ->copyable()
            ->display(function ($code) {
                $html = $code ? "<code class='text-success'>{$code}</code>" : '-';
                if ($this->payment_account) {
                    $account = htmlspecialchars((string) $this->payment_account, ENT_QUOTES, 'UTF-8');
                    $html .= "<br><small class='text-muted' title='{$account}'>Acct: " . mb_substr($account, 0, 36) . (mb_strlen($account) > 36 ? '...' : '') . "</small>";
                }
                return $html;
            });

        $grid->column('number_of_times_checked', __('Checks'))
            ->display(function ($checks) {
                $checks = (int) ($checks ?? 0);
                return "<span class='label label-default'>{$checks}</span>";
            })
            ->sortable()
            ->hide();

        $grid->column('ip_address', __('Source'))
            ->display(function ($ip) {
                $agent = trim((string) ($this->user_agent ?? ''));
                $uaShort = $agent === '' ? '' : mb_substr($agent, 0, 44) . (mb_strlen($agent) > 44 ? '...' : '');
                $out = $ip ?: '-';
                if ($uaShort !== '') {
                    $out .= "<br><small class='text-muted' title='" . htmlspecialchars($agent, ENT_QUOTES, 'UTF-8') . "'>{$uaShort}</small>";
                }
                return $out;
            })
            ->hide();

        $grid->column('error_message', __('Error'))
            ->display(function ($error) {
                if (!$error) return '-';
                $short = mb_substr($error, 0, 80);
                return "<span class='text-danger' title='" . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . "'>{$short}" . (mb_strlen($error) > 80 ? '...' : '') . "</span>";
            });

        $grid->column('record_actions', __('Fix'))
            ->display(function () {
                $ref = trim((string) ($this->pesapal_tracking_id ?: $this->merchant_reference ?: ''));
                $gateway = strtolower((string) ($this->payment_method ?? ''));
                $hint = str_contains($gateway, 'flutter') ? 'flutterwave' : 'pesapal';
                $safeRef = htmlspecialchars($ref, ENT_QUOTES, 'UTF-8');
                $safeGateway = htmlspecialchars($hint, ENT_QUOTES, 'UTF-8');

                return "<button type='button' class='btn btn-xs btn-warning js-subtx-fix' "
                    . "data-id='" . (int) $this->id . "' "
                    . "data-ref='{$safeRef}' "
                    . "data-gateway='{$safeGateway}'>"
                    . "<i class='fa fa-wrench'></i> Fix</button>";
            });

        // Filters
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();

            $filter->column(1 / 4, function ($filter) {
                $filter->equal('status', 'Status')->select([
                    'Pending' => 'Pending',
                    'Processing' => 'Processing',
                    'Completed' => 'Completed',
                    'Failed' => 'Failed',
                    'Refunded' => 'Refunded',
                ]);

                $filter->equal('transaction_type', 'Type')->select([
                    'Initial' => 'Initial',
                    'Renewal' => 'Renewal',
                    'Upgrade' => 'Upgrade',
                    'Downgrade' => 'Downgrade',
                    'Refund' => 'Refund',
                    'Withdrawal' => 'Withdrawal',
                ]);

                $filter->equal('platform', 'Platform')->select([
                    'lugaflix' => 'LugaFlix',
                    'muno_app' => 'Muno App',
                    'ugflix' => 'UgFlix',
                    'web' => 'Web (Katogo)',
                ]);

                $filter->equal('currency', 'Currency')->select([
                    'UGX' => 'UGX',
                    'KES' => 'KES',
                    'USD' => 'USD',
                ]);
            });

            $filter->column(1 / 4, function ($filter) {
                $filter->equal('user_id', 'User ID');
                $filter->equal('subscription_id', 'Subscription ID');
                $filter->like('pesapal_tracking_id', 'Tracking ID');
                $filter->like('merchant_reference', 'Merchant Reference');

                $filter->where(function ($query) {
                    $value = trim((string) $this->input);
                    if ($value !== '') {
                        $query->whereHas('user', function ($q) use ($value) {
                            $q->where('phone_number', 'like', "%{$value}%");
                        });
                    }
                }, 'Phone Number');
            });

            $filter->column(1 / 4, function ($filter) {
                $filter->like('confirmation_code', 'Confirmation Code');
                $filter->like('payment_method', 'Payment Method');
                $filter->like('payment_account', 'Payment Account');
                $filter->like('ip_address', 'IP Address');

                $filter->where(function ($query) {
                    $value = trim((string) $this->input);
                    if ($value !== '') {
                        $query->whereHas('user', function ($q) use ($value) {
                            $q->where('name', 'like', "%{$value}%")
                                ->orWhere('email', 'like', "%{$value}%");
                        });
                    }
                }, 'User Name / Email');
            });

            $filter->column(1 / 4, function ($filter) {
                $filter->between('created_at', 'Created Date')->datetime();
                $filter->between('refunded_at', 'Refunded Date')->datetime();

                $filter->where(function ($query) {
                    if ($this->input === '1') {
                        $query->whereNotNull('error_message')->where('error_message', '!=', '');
                    }
                    if ($this->input === '0') {
                        $query->where(function ($q) {
                            $q->whereNull('error_message')->orWhere('error_message', '');
                        });
                    }
                }, 'Has Error')->select([
                    '1' => 'Yes',
                    '0' => 'No',
                ]);

                $filter->where(function ($query) {
                    if ($this->input === '1') {
                        $query->where(function ($q) {
                            $q->whereNotNull('request_payload')->orWhereNotNull('response_payload');
                        });
                    }
                    if ($this->input === '0') {
                        $query->whereNull('request_payload')->whereNull('response_payload');
                    }
                }, 'Has Payload')->select([
                    '1' => 'Yes',
                    '0' => 'No',
                ]);

                $filter->where(function ($query) {
                    if ($this->input === '1') {
                        $query->whereNotNull('confirmation_code')->where('confirmation_code', '!=', '');
                    }
                    if ($this->input === '0') {
                        $query->where(function ($q) {
                            $q->whereNull('confirmation_code')->orWhere('confirmation_code', '');
                        });
                    }
                }, 'Has Confirmation')->select([
                    '1' => 'Yes',
                    '0' => 'No',
                ]);
            });

            $filter->between('amount', 'Amount Range');
        });

        // Export
        $grid->export(function ($export) {
            $export->filename('Transactions_' . date('Y-m-d_H-i'));
            $export->except(['actions']);
        });

        $inspectUrl = admin_url('api/subscription-transactions/debug/inspect');
        $applyFixUrl = admin_url('api/subscription-transactions/debug/apply-fix');
        $csrf = csrf_token();
        $fixConfigJs = json_encode([
            'inspectUrl' => $inspectUrl,
            'applyFixUrl' => $applyFixUrl,
            'token' => $csrf,
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

                Admin::html(<<<HTML
<style>
    #subTxFixModal .modal-dialog{width:96vw;max-width:96vw;margin:2vh auto}
    #subTxFixModal .modal-content{height:96vh}
    #subTxFixModal .stx-grid{display:grid;gap:10px}
    #subTxFixModal .stx-grid-3{grid-template-columns:1fr 1fr 1fr}
    #subTxFixModal .stx-grid-2{grid-template-columns:1fr 1fr}
    #subTxFixModal .stx-card{background:#11162a;border:1px solid #2f3957;padding:8px 10px;min-height:74px}
    #subTxFixModal .stx-title{font-size:10px;text-transform:uppercase;color:#93a4c7;margin-bottom:4px;letter-spacing:.4px}
    #subTxFixModal .stx-val{font-size:12px;color:#dbe7ff;line-height:1.5;word-break:break-word}
    #subTxFixModal .stx-pill{display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;background:#1f365b;color:#b7dcff}
    #subTxFixModal .stx-scroller{max-height:30vh;overflow:auto}
    @media (max-width:1100px){
        #subTxFixModal .stx-grid-3{grid-template-columns:1fr}
        #subTxFixModal .stx-grid-2{grid-template-columns:1fr}
    }
</style>

<div class="modal fade" id="subTxFixModal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:0;border:1px solid #1f1f34;overflow:hidden">
            <div class="modal-header" style="background:#0f1220;color:#dbe7ff;border-bottom:1px solid #2a2f45;padding:10px 14px">
                <button type="button" class="close" data-dismiss="modal" style="color:#dbe7ff;opacity:1">&times;</button>
                <h4 class="modal-title" style="font-size:15px;font-weight:700;margin:0"><i class="fa fa-wrench"></i> Subscription Transaction Fix Lab</h4>
            </div>
            <div class="modal-body" style="background:#0b0e1a;color:#c8d4ef;padding:12px 14px;overflow:auto">
                <div class="stx-grid stx-grid-3" style="margin-bottom:10px">
                    <div style="grid-column: span 2;display:flex;gap:8px;flex-wrap:wrap;align-items:end">
                        <div style="flex:1;min-width:260px">
                            <label class="stx-title" style="display:block">Payment Reference</label>
                            <input type="text" id="subTxFixRef" class="form-control" placeholder="order_tracking_id / merchant_reference / tx_ref" style="border-radius:0;background:#12172a;color:#dbe7ff;border:1px solid #2f3957" />
                        </div>
                        <div style="width:190px">
                            <label class="stx-title" style="display:block">Gateway</label>
                            <select id="subTxFixGateway" class="form-control" style="border-radius:0;background:#12172a;color:#dbe7ff;border:1px solid #2f3957">
                                <option value="auto">Auto Detect</option>
                                <option value="pesapal">Pesapal</option>
                                <option value="flutterwave">Flutterwave</option>
                            </select>
                        </div>
                        <div style="width:190px">
                            <label class="stx-title" style="display:block">Actions</label>
                            <button type="button" class="btn btn-sm btn-info" id="subTxInspectBtn" style="width:100%;border-radius:0"><i class="fa fa-search"></i> Inspect Gateway</button>
                        </div>
                    </div>
                    <div class="stx-card">
                        <div class="stx-title">Normalized Outcome</div>
                        <div id="subTxOutcome" class="stx-val">Not inspected yet.</div>
                    </div>
                </div>

                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px">
                    <button type="button" class="btn btn-sm btn-warning" id="subTxForceVerifyBtn" style="border-radius:0"><i class="fa fa-refresh"></i> Force Verify</button>
                    <button type="button" class="btn btn-sm btn-success" id="subTxActivateBtn" style="border-radius:0"><i class="fa fa-bolt"></i> Force Activate</button>
                    <button type="button" class="btn btn-sm btn-primary" id="subTxMarkCompletedBtn" style="border-radius:0"><i class="fa fa-check"></i> Mark Tx Completed</button>
                    <button type="button" class="btn btn-sm btn-danger" id="subTxMarkFailedBtn" style="border-radius:0"><i class="fa fa-times"></i> Mark Tx Failed</button>
                </div>

                <div id="subTxFixSummary" class="stx-card" style="font-size:12px;min-height:50px;margin-bottom:10px">Ready. Select a transaction row and run Inspect Gateway.</div>

                <div class="stx-grid stx-grid-3" style="margin-bottom:10px">
                    <div class="stx-card">
                        <div class="stx-title">Subscription</div>
                        <div id="subTxSubCard" class="stx-val">-</div>
                    </div>
                    <div class="stx-card">
                        <div class="stx-title">User Snippet</div>
                        <div id="subTxUserCard" class="stx-val">-</div>
                    </div>
                    <div class="stx-card">
                        <div class="stx-title">Transaction</div>
                        <div id="subTxTxnCard" class="stx-val">-</div>
                    </div>
                </div>

                <div class="stx-grid stx-grid-2">
                    <div class="stx-card">
                        <div class="stx-title">Log</div>
                        <pre id="subTxFixLog" class="stx-scroller" style="margin:0;padding:8px;color:#9ad1ff;background:#060814;border:1px solid #2f3957;font-size:11px;line-height:1.5"></pre>
                    </div>
                    <div class="stx-card">
                        <div class="stx-title">Gateway Raw Response</div>
                        <pre id="subTxFixRaw" class="stx-scroller" style="margin:0;padding:8px;color:#b5f7c8;background:#060814;border:1px solid #2f3957;font-size:11px;line-height:1.5"></pre>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="background:#0f1220;border-top:1px solid #2a2f45;padding:8px 14px">
                <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
HTML);

        Admin::script("window.SubTxFixConfig = {$fixConfigJs};");
        Admin::js('/assets/subtx-fix-modal.js');

        return $grid;
    }

    public function debugInspect(Request $request)
    {
        try {
            $ctx = $this->resolveDebugContext(
                $request->input('transaction_id'),
                (string) $request->input('reference', ''),
                (string) $request->input('gateway', 'auto')
            );

            $raw = $this->fetchGatewayRawStatus($ctx['gateway'], $ctx['reference']);
            $normalized = $this->normalizeGatewayResponse($ctx['gateway'], $raw);

            return response()->json([
                'success' => true,
                'data' => [
                    'gateway' => $ctx['gateway'],
                    'reference' => $ctx['reference'],
                    'subscription_id' => $ctx['subscription']?->id,
                    'transaction_id' => $ctx['transaction']?->id,
                    'subscription_status' => $ctx['subscription']?->status,
                    'payment_status' => $ctx['subscription']?->payment_status,
                    'subscription' => $this->buildSubscriptionSnippet($ctx['subscription']),
                    'user' => $this->buildUserSnippet($ctx['subscription'], $ctx['transaction']),
                    'transaction' => $this->buildTransactionSnippet($ctx['transaction']),
                    'normalized' => $normalized,
                    'raw_gateway_response' => $raw,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Subscription transaction debugInspect failed', [
                'error' => $e->getMessage(),
                'transaction_id' => $request->input('transaction_id'),
                'reference' => $request->input('reference'),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function debugApplyFix(Request $request)
    {
        $action = (string) $request->input('action', '');

        try {
            $ctx = $this->resolveDebugContext(
                $request->input('transaction_id'),
                (string) $request->input('reference', ''),
                (string) $request->input('gateway', 'auto')
            );

            $subscription = $ctx['subscription'];
            $transaction = $ctx['transaction'];
            if (!$subscription && in_array($action, ['force_verify', 'force_activate'], true)) {
                throw new \RuntimeException('No subscription found for this reference.');
            }

            $raw = null;

            switch ($action) {
                case 'force_verify':
                    $raw = $this->runForceVerify($ctx['gateway'], $subscription, $ctx['reference']);
                    if (!$raw) {
                        $raw = $this->fetchGatewayRawStatus($ctx['gateway'], $ctx['reference']);
                    }
                    break;

                case 'force_activate':
                    /** @var SubscriptionActivationService $activationService */
                    $activationService = app(SubscriptionActivationService::class);
                    $activated = $activationService->activatePaidSubscription($subscription, 'admin_debug_force_activate');
                    $activated->payment_status = 'Completed';
                    $activated->payment_confirmed_at = $activated->payment_confirmed_at ?: now();
                    $activated->save();

                    if ($transaction) {
                        $transaction->status = 'Completed';
                        $transaction->error_message = null;
                        $transaction->save();
                    }
                    break;

                case 'mark_tx_completed':
                    if (!$transaction) {
                        throw new \RuntimeException('Transaction not found. Open fix from a row or provide matching reference.');
                    }
                    $transaction->status = 'Completed';
                    $transaction->error_message = null;
                    $transaction->save();
                    break;

                case 'mark_tx_failed':
                    if (!$transaction) {
                        throw new \RuntimeException('Transaction not found. Open fix from a row or provide matching reference.');
                    }
                    $transaction->status = 'Failed';
                    $transaction->error_message = $transaction->error_message ?: 'Marked failed via admin debug tool';
                    $transaction->save();
                    break;

                default:
                    throw new \RuntimeException('Unknown fix action: ' . $action);
            }

            $subscription?->refresh();
            $normalized = $raw ? $this->normalizeGatewayResponse($ctx['gateway'], $raw) : null;

            return response()->json([
                'success' => true,
                'message' => 'Action executed: ' . $action,
                'data' => [
                    'gateway' => $ctx['gateway'],
                    'reference' => $ctx['reference'],
                    'subscription_id' => $subscription?->id,
                    'transaction_id' => $transaction?->id,
                    'subscription_status' => $subscription?->status,
                    'payment_status' => $subscription?->payment_status,
                    'subscription' => $this->buildSubscriptionSnippet($subscription),
                    'user' => $this->buildUserSnippet($subscription, $transaction),
                    'transaction' => $this->buildTransactionSnippet($transaction),
                    'normalized' => $normalized,
                    'raw_gateway_response' => $raw,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Subscription transaction debugApplyFix failed', [
                'error' => $e->getMessage(),
                'action' => $action,
                'transaction_id' => $request->input('transaction_id'),
                'reference' => $request->input('reference'),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    private function resolveDebugContext($transactionId, string $reference, string $gateway): array
    {
        $transaction = null;
        $subscription = null;

        if (!empty($transactionId)) {
            $transaction = SubscriptionTransaction::find((int) $transactionId);
            if ($transaction && $transaction->subscription_id) {
                $subscription = Subscription::find((int) $transaction->subscription_id);
            }
            if ($reference === '' && $transaction) {
                $reference = (string) ($transaction->pesapal_tracking_id ?: $transaction->merchant_reference ?: '');
            }
        }

        if ($reference !== '' && !$subscription) {
            $subscription = Subscription::query()
                ->where('pesapal_tracking_id', $reference)
                ->orWhere('pesapal_merchant_reference', $reference)
                ->orWhere('flutterwave_reference', $reference)
                ->first();
        }

        if ($subscription) {
            $subscription->loadMissing(['user', 'plan']);
        }

        if ($reference !== '' && !$transaction) {
            $transaction = SubscriptionTransaction::query()
                ->where('pesapal_tracking_id', $reference)
                ->orWhere('merchant_reference', $reference)
                ->first();
        }

        if ($reference === '') {
            throw new \RuntimeException('Payment reference is required.');
        }

        $resolvedGateway = $this->resolveGateway($gateway, $subscription, $transaction, $reference);

        return [
            'gateway' => $resolvedGateway,
            'reference' => $reference,
            'transaction' => $transaction,
            'subscription' => $subscription,
        ];
    }

    private function resolveGateway(string $gateway, ?Subscription $subscription, ?SubscriptionTransaction $transaction, string $reference): string
    {
        $candidate = strtolower(trim($gateway));
        if (in_array($candidate, ['pesapal', 'flutterwave'], true)) {
            return $candidate;
        }

        $subGateway = strtolower((string) ($subscription?->payment_gateway ?: $subscription?->payment_method ?: ''));
        if (str_contains($subGateway, 'flutter')) {
            return 'flutterwave';
        }
        if (str_contains($subGateway, 'pesapal')) {
            return 'pesapal';
        }

        $txMethod = strtolower((string) ($transaction?->payment_method ?: ''));
        if (str_contains($txMethod, 'flutter')) {
            return 'flutterwave';
        }

        if (str_starts_with(strtolower($reference), 'flw')) {
            return 'flutterwave';
        }

        return 'pesapal';
    }

    private function fetchGatewayRawStatus(string $gateway, string $reference): array
    {
        if ($gateway === 'flutterwave') {
            /** @var SubscriptionFlutterwaveService $flutterwave */
            $flutterwave = app(SubscriptionFlutterwaveService::class);
            return $flutterwave->verifyByReference($reference);
        }

        /** @var SubscriptionPesapalService $pesapal */
        $pesapal = app(SubscriptionPesapalService::class);
        return $pesapal->getTransactionStatus($reference);
    }

    private function runForceVerify(string $gateway, Subscription $subscription, string $reference): array
    {
        if ($gateway === 'flutterwave') {
            /** @var SubscriptionFlutterwaveService $flutterwave */
            $flutterwave = app(SubscriptionFlutterwaveService::class);
            $txRef = $subscription->flutterwave_reference ?: $subscription->pesapal_merchant_reference ?: $reference;
            return $flutterwave->processCallback($txRef);
        }

        /** @var PaymentStatusChecker $checker */
        $checker = app(PaymentStatusChecker::class);
        return $checker->forceVerifyPayment($subscription);
    }

    private function normalizeGatewayResponse(string $gateway, array $raw): array
    {
        if ($gateway === 'flutterwave') {
            $body = $raw['data'] ?? $raw;
            $statusRaw = strtolower((string) ($body['status'] ?? $raw['status'] ?? 'unknown'));
            $mapped = in_array($statusRaw, ['successful', 'success', 'completed'], true)
                ? 'completed'
                : (in_array($statusRaw, ['failed', 'cancelled', 'canceled', 'error'], true) ? 'failed' : 'pending');

            return [
                'gateway' => 'flutterwave',
                'status_raw' => $statusRaw,
                'status_normalized' => $mapped,
                'gateway_reference' => (string) ($body['tx_ref'] ?? $body['txRef'] ?? ''),
                'merchant_reference' => (string) ($body['tx_ref'] ?? $body['txRef'] ?? ''),
                'tracking_reference' => (string) ($body['flw_ref'] ?? ''),
                'amount' => $body['amount'] ?? null,
                'currency' => $body['currency'] ?? null,
                'message' => (string) ($raw['message'] ?? $body['processor_response'] ?? ''),
                'error_code' => (string) ($raw['code'] ?? ''),
            ];
        }

        $body = $raw['data'] ?? [];
        $statusCode = (int) ($body['status_code'] ?? -1);
        $statusDesc = strtoupper((string) ($body['payment_status_description'] ?? $body['status'] ?? 'UNKNOWN'));
        $mapped = ($statusCode === 1 || $statusDesc === 'COMPLETED')
            ? 'completed'
            : (($statusDesc === 'INVALID' || $statusCode === 0) ? 'pending' : 'failed');

        return [
            'gateway' => 'pesapal',
            'status_raw' => $statusDesc,
            'status_normalized' => $mapped,
            'gateway_reference' => (string) ($body['order_tracking_id'] ?? ''),
            'merchant_reference' => (string) ($body['merchant_reference'] ?? ''),
            'tracking_reference' => (string) ($body['order_tracking_id'] ?? ''),
            'amount' => $body['amount'] ?? null,
            'currency' => $body['currency'] ?? null,
            'message' => (string) (($body['error']['message'] ?? null) ?: ($body['message'] ?? '')),
            'error_code' => (string) (($body['error']['code'] ?? null) ?: ($body['status_code'] ?? '')),
        ];
    }

    private function buildSubscriptionSnippet(?Subscription $subscription): ?array
    {
        if (!$subscription) {
            return null;
        }

        return [
            'id' => $subscription->id,
            'status' => (string) ($subscription->status ?? ''),
            'payment_status' => (string) ($subscription->payment_status ?? ''),
            'app_type' => (string) ($subscription->app_type ?? ''),
            'platform' => (string) ($subscription->platform ?? ''),
            'plan' => (string) ($subscription->plan?->name ?? ''),
            'amount_paid' => $subscription->amount_paid,
            'currency' => (string) ($subscription->currency ?? 'UGX'),
            'start_date_time' => optional($subscription->start_date_time)->toDateTimeString(),
            'end_date_time' => optional($subscription->end_date_time)->toDateTimeString(),
            'pesapal_tracking_id' => (string) ($subscription->pesapal_tracking_id ?? ''),
            'merchant_reference' => (string) ($subscription->pesapal_merchant_reference ?? ''),
            'flutterwave_reference' => (string) ($subscription->flutterwave_reference ?? ''),
        ];
    }

    private function buildUserSnippet(?Subscription $subscription, ?SubscriptionTransaction $transaction): ?array
    {
        $user = $subscription?->user;
        if (!$user && $transaction?->user_id) {
            $user = User::find((int) $transaction->user_id);
        }

        if (!$user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => (string) ($user->name ?? ''),
            'email' => (string) ($user->email ?? ''),
            'phone_number' => (string) ($user->phone_number ?? ''),
            'app_type' => (string) ($user->app_type ?? ''),
            'platform' => (string) ($user->platform ?? ''),
            'account_state' => (string) ($user->account_state ?? ''),
            'created_at' => optional($user->created_at)->toDateTimeString(),
            'last_online_at' => optional($user->last_online_at)->toDateTimeString(),
        ];
    }

    private function buildTransactionSnippet(?SubscriptionTransaction $transaction): ?array
    {
        if (!$transaction) {
            return null;
        }

        return [
            'id' => $transaction->id,
            'status' => (string) ($transaction->status ?? ''),
            'transaction_type' => (string) ($transaction->transaction_type ?? ''),
            'amount' => $transaction->amount,
            'currency' => (string) ($transaction->currency ?? 'UGX'),
            'payment_method' => (string) ($transaction->payment_method ?? ''),
            'merchant_reference' => (string) ($transaction->merchant_reference ?? ''),
            'tracking_id' => (string) ($transaction->pesapal_tracking_id ?? ''),
            'confirmation_code' => (string) ($transaction->confirmation_code ?? ''),
            'error_message' => (string) ($transaction->error_message ?? ''),
            'created_at' => optional($transaction->created_at)->toDateTimeString(),
        ];
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
