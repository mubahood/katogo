<?php

namespace App\Admin\Controllers;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionTransaction;
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
use Encore\Admin\Widgets\Box;
use Encore\Admin\Widgets\Table;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SubscriptionController extends AdminController
{
    protected $title = 'Subscription Management';

    // ─── INDEX ───────────────────────────────────────────────────────────
    public function index(Content $content)
    {
        return $content
            ->title($this->title())
            ->description('Subscription tracking & admin controls')
            ->body($this->grid());
    }

    public function analytics(Content $content)
    {
        return $content
            ->title('Subscription Data Analytics')
            ->description('Data-only subscription insights and trends')
            ->row($this->buildStatsCards())
            ->row($this->buildChartsSection())
            ->row(function (Row $row) {
                $row->column(12, '<div style="margin:8px 0 6px 0"><span class="label label-default" style="font-size:11px;padding:6px 8px">Secondary Subscription Statistics</span></div>');
                $row->column(6, $this->appPlatformBreakdownBox());
                $row->column(6, $this->expiringSubscriptionsBox());
            });
    }

    // ─── COMPACT STAT CARDS ─────────────────────────────────────────────
    protected function buildStatsCards()
    {
        return Cache::remember('subscription_stats_cards', 300, function () {
        $now = Carbon::now();

        // Gather all stats in fewer queries
        $totalRevenue = Subscription::where('payment_status', 'Completed')->sum('amount_paid');
        $totalWithdrawals = SubscriptionTransaction::where('status', 'Completed')
            ->where('transaction_type', 'Withdrawal')
            ->sum('amount');
        $netBalance = $totalRevenue + $totalWithdrawals; // withdrawals are negative
        $activeCount = Subscription::where('status', 'Active')->count();
        $pendingCount = Subscription::where('payment_status', 'Pending')->count();

        $thisMonthRevenue = Subscription::where('payment_status', 'Completed')
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->sum('amount_paid');
        $lastMonthRevenue = Subscription::where('payment_status', 'Completed')
            ->whereMonth('created_at', $now->copy()->subMonth()->month)
            ->whereYear('created_at', $now->copy()->subMonth()->year)
            ->sum('amount_paid');
        $monthTrend = $lastMonthRevenue > 0
            ? round((($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1)
            : ($thisMonthRevenue > 0 ? 100 : 0);

        $todayRevenue = Subscription::where('payment_status', 'Completed')
            ->whereDate('created_at', $now->toDateString())
            ->sum('amount_paid');
        $todaySales = Subscription::where('payment_status', 'Completed')
            ->whereDate('created_at', $now->toDateString())
            ->count();

        $expiringToday = Subscription::where('status', 'Active')
            ->whereDate('end_date_time', $now->toDateString())
            ->count();
        $newThisWeek = Subscription::where('payment_status', 'Completed')
            ->where('created_at', '>=', $now->copy()->startOfWeek())
            ->count();

        $totalSubs = Subscription::count() ?: 1;
        $churned = Subscription::whereIn('status', ['Expired', 'Cancelled'])->count();
        $churnRate = round(($churned / $totalSubs) * 100, 1);

        // Per-app revenue
        $appRevenue = Subscription::where('payment_status', 'Completed')
            ->selectRaw("app_type, SUM(amount_paid) as revenue, COUNT(*) as cnt")
            ->groupBy('app_type')
            ->pluck('revenue', 'app_type')
            ->toArray();
        $appCounts = Subscription::where('payment_status', 'Completed')
            ->selectRaw("app_type, COUNT(*) as cnt")
            ->groupBy('app_type')
            ->pluck('cnt', 'app_type')
            ->toArray();
        $appActive = Subscription::where('status', 'Active')
            ->where('payment_status', 'Completed')
            ->selectRaw("app_type, COUNT(*) as cnt")
            ->groupBy('app_type')
            ->pluck('cnt', 'app_type')
            ->toArray();

        $trendIcon = $monthTrend > 0 ? 'fa-arrow-up' : ($monthTrend < 0 ? 'fa-arrow-down' : 'fa-minus');
        $trendColor = $monthTrend > 0 ? '#28a745' : ($monthTrend < 0 ? '#dc3545' : '#6c757d');
        $churnColor = $churnRate > 30 ? '#dc3545' : ($churnRate > 15 ? '#f39c12' : '#28a745');

        $urlCompleted = admin_url('subscriptions?payment_status=Completed');
        $urlActive    = admin_url('subscriptions?status=Active');
        $urlPending   = admin_url('subscriptions?payment_status=Pending');

        $html = <<<HTML
<style>
.sub-stats{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin-bottom:16px}
.sub-stat{background:#fff;border-radius:6px;padding:14px 16px;box-shadow:0 1px 4px rgba(0,0,0,.06);border-left:3px solid #ddd;display:flex;align-items:center;gap:12px;transition:box-shadow .2s}
.sub-stat:hover{box-shadow:0 3px 12px rgba(0,0,0,.1)}
.sub-stat .icon{width:38px;height:38px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.sub-stat .icon i{font-size:16px;color:#fff}
.sub-stat .info{flex:1;min-width:0}
.sub-stat .info .val{font-size:18px;font-weight:700;color:#222;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sub-stat .info .lbl{font-size:10px;color:#888;text-transform:uppercase;letter-spacing:.3px;margin-top:2px}
.sub-apps{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin-bottom:16px}
.sub-app{background:#fff;border-radius:6px;padding:14px 16px;box-shadow:0 1px 4px rgba(0,0,0,.06);border-top:3px solid #ddd}
.sub-app .app-head{display:flex;align-items:center;gap:8px;margin-bottom:10px}
.sub-app .app-head i{font-size:14px}
.sub-app .app-head span{font-weight:700;font-size:13px;color:#333}
.sub-app .app-row{display:flex;justify-content:space-between;font-size:11px;padding:3px 0;color:#555}
.sub-app .app-row b{color:#222}
</style>
<div class="sub-stats">
  <a href="{$urlCompleted}" style="text-decoration:none;color:inherit" class="sub-stat" style="border-color:#28a745">
    <div class="icon" style="background:#28a745"><i class="fa fa-money"></i></div>
    <div class="info"><div class="val">UGX {$this->fmt($totalRevenue)}</div><div class="lbl">Gross Revenue</div></div>
  </a>
  <div class="sub-stat" style="border-color:#dc3545">
    <div class="icon" style="background:#dc3545"><i class="fa fa-arrow-circle-up"></i></div>
    <div class="info"><div class="val">UGX {$this->fmt(abs($totalWithdrawals))}</div><div class="lbl">Withdrawn</div></div>
  </div>
  <div class="sub-stat" style="border-color:#17a2b8">
    <div class="icon" style="background:#17a2b8"><i class="fa fa-balance-scale"></i></div>
    <div class="info"><div class="val">UGX {$this->fmt($netBalance)}</div><div class="lbl">Net Balance</div></div>
  </div>
  <a href="{$urlActive}" style="text-decoration:none;color:inherit" class="sub-stat" style="border-color:#007bff">
    <div class="icon" style="background:#007bff"><i class="fa fa-check-circle"></i></div>
    <div class="info"><div class="val">{$this->fmt($activeCount)}</div><div class="lbl">Active Subscriptions</div></div>
  </a>
  <div class="sub-stat" style="border-color:#f39c12">
    <div class="icon" style="background:#f39c12"><i class="fa fa-calendar"></i></div>
    <div class="info">
      <div class="val">UGX {$this->fmt($thisMonthRevenue)}</div>
      <div class="lbl">This Month <i class="fa {$trendIcon}" style="color:{$trendColor};font-size:9px"></i> {$monthTrend}%</div>
    </div>
  </div>
  <a href="{$urlPending}" style="text-decoration:none;color:inherit" class="sub-stat" style="border-color:#dc3545">
    <div class="icon" style="background:#dc3545"><i class="fa fa-clock-o"></i></div>
    <div class="info"><div class="val">{$pendingCount}</div><div class="lbl">Pending Payments</div></div>
  </a>
  <div class="sub-stat" style="border-color:#6f42c1">
    <div class="icon" style="background:#6f42c1"><i class="fa fa-star"></i></div>
    <div class="info"><div class="val">UGX {$this->fmt($todayRevenue)}</div><div class="lbl">Today ({$todaySales} sales)</div></div>
  </div>
  <div class="sub-stat" style="border-color:#fd7e14">
    <div class="icon" style="background:#fd7e14"><i class="fa fa-exclamation-triangle"></i></div>
    <div class="info"><div class="val">{$expiringToday}</div><div class="lbl">Expiring Today</div></div>
  </div>
  <div class="sub-stat" style="border-color:#007bff">
    <div class="icon" style="background:#007bff"><i class="fa fa-plus-circle"></i></div>
    <div class="info"><div class="val">{$newThisWeek}</div><div class="lbl">New This Week</div></div>
  </div>
  <div class="sub-stat" style="border-color:{$churnColor}">
    <div class="icon" style="background:{$churnColor}"><i class="fa fa-line-chart"></i></div>
    <div class="info"><div class="val">{$churnRate}%</div><div class="lbl">Churn Rate</div></div>
  </div>
</div>
<div class="sub-apps">
HTML;

        $appConfigs = [
            'lugaflix'  => ['LugaFlix', '#3498db', 'fa-film'],
            'ugflix'    => ['UGFlix',   '#2ecc71', 'fa-play-circle'],
            'muno_app'  => ['Muno App', '#e74c3c', 'fa-television'],
            'web'       => ['Web',      '#9b59b6', 'fa-globe'],
        ];
        foreach ($appConfigs as $key => [$name, $color, $icon]) {
            $rev = $appRevenue[$key] ?? 0;
            $cnt = $appCounts[$key] ?? 0;
            $act = $appActive[$key] ?? 0;
            $html .= <<<HTML
  <div class="sub-app" style="border-color:{$color}">
    <div class="app-head"><i class="fa {$icon}" style="color:{$color}"></i><span>{$name}</span></div>
    <div class="app-row"><span>Revenue</span><b>UGX {$this->fmt($rev)}</b></div>
    <div class="app-row"><span>Completed</span><b>{$this->fmt($cnt)}</b></div>
    <div class="app-row"><span>Active Now</span><b>{$act}</b></div>
  </div>
HTML;
        }

        $html .= '</div>';
        return $html;
        }); // end Cache::remember subscription_stats_cards
    }

    // ─── CHARTS (PJAX-SAFE) ─────────────────────────────────────────────
    protected function buildChartsSection()
    {
        // Daily revenue (last 30 days) — single query
        $dailyRaw = Subscription::where('payment_status', 'Completed')
            ->where('created_at', '>=', Carbon::today()->subDays(29))
            ->selectRaw("DATE(created_at) as d, app_type, SUM(amount_paid) as rev, COUNT(*) as cnt")
            ->groupBy('d', 'app_type')
            ->get();

        $dailyLabels = [];
        $dailyMap = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = Carbon::today()->subDays($i)->format('Y-m-d');
            $dailyLabels[] = Carbon::parse($d)->format('d M');
            $dailyMap[$d] = ['muno_app' => ['rev' => 0, 'cnt' => 0], 'lugaflix' => ['rev' => 0, 'cnt' => 0], 'ugflix' => ['rev' => 0, 'cnt' => 0], 'web' => ['rev' => 0, 'cnt' => 0]];
        }
        foreach ($dailyRaw as $row) {
            if (isset($dailyMap[$row->d][$row->app_type])) {
                $dailyMap[$row->d][$row->app_type] = ['rev' => (float)$row->rev, 'cnt' => (int)$row->cnt];
            }
        }
        $dMunoRev = []; $dLgRev = []; $dUgRev = []; $dWebRev = [];
        $dMunoCnt = []; $dLgCnt = []; $dUgCnt = []; $dWebCnt = [];
        $dTotalRev = []; $dTotalCnt = [];
        foreach ($dailyMap as $vals) {
            $dMunoRev[] = $vals['muno_app']['rev']; $dLgRev[] = $vals['lugaflix']['rev']; $dUgRev[] = $vals['ugflix']['rev']; $dWebRev[] = $vals['web']['rev'];
            $dMunoCnt[] = $vals['muno_app']['cnt']; $dLgCnt[] = $vals['lugaflix']['cnt']; $dUgCnt[] = $vals['ugflix']['cnt']; $dWebCnt[] = $vals['web']['cnt'];
            $dTotalRev[] = $vals['muno_app']['rev'] + $vals['lugaflix']['rev'] + $vals['ugflix']['rev'] + $vals['web']['rev'];
            $dTotalCnt[] = $vals['muno_app']['cnt'] + $vals['lugaflix']['cnt'] + $vals['ugflix']['cnt'] + $vals['web']['cnt'];
        }

        // Weekly (last 12 weeks) — single query
        $weekStart = Carbon::now()->subWeeks(11)->startOfWeek();
        $weeklyRaw = Subscription::where('payment_status', 'Completed')
            ->where('created_at', '>=', $weekStart)
            ->selectRaw("YEARWEEK(created_at, 1) as yw, app_type, SUM(amount_paid) as rev, COUNT(*) as cnt")
            ->groupBy('yw', 'app_type')
            ->get();

        $weeklyLabels = []; $weeklyMap = [];
        for ($i = 11; $i >= 0; $i--) {
            $ws = Carbon::now()->subWeeks($i)->startOfWeek();
            $yw = $ws->format('oW');
            // Use YEARWEEK format matching MySQL
            $ywMysql = (string)$ws->isoWeekYear . str_pad($ws->isoWeek, 2, '0', STR_PAD_LEFT);
            $weeklyLabels[] = $ws->format('d M');
            $weeklyMap[$ywMysql] = ['muno_app' => ['rev' => 0, 'cnt' => 0], 'lugaflix' => ['rev' => 0, 'cnt' => 0], 'ugflix' => ['rev' => 0, 'cnt' => 0], 'web' => ['rev' => 0, 'cnt' => 0]];
        }
        foreach ($weeklyRaw as $row) {
            $yw = (string)$row->yw;
            if (isset($weeklyMap[$yw][$row->app_type])) {
                $weeklyMap[$yw][$row->app_type] = ['rev' => (float)$row->rev, 'cnt' => (int)$row->cnt];
            }
        }
        $wMunoRev = []; $wLgRev = []; $wUgRev = []; $wWebRev = [];
        $wMunoCnt = []; $wLgCnt = []; $wUgCnt = []; $wWebCnt = [];
        foreach ($weeklyMap as $vals) {
            $wMunoRev[] = $vals['muno_app']['rev']; $wLgRev[] = $vals['lugaflix']['rev']; $wUgRev[] = $vals['ugflix']['rev']; $wWebRev[] = $vals['web']['rev'];
            $wMunoCnt[] = $vals['muno_app']['cnt']; $wLgCnt[] = $vals['lugaflix']['cnt']; $wUgCnt[] = $vals['ugflix']['cnt']; $wWebCnt[] = $vals['web']['cnt'];
        }

        // Monthly (last 6 months) — single query
        $monthStart = Carbon::now()->subMonths(5)->startOfMonth();
        $monthlyRaw = Subscription::where('payment_status', 'Completed')
            ->where('created_at', '>=', $monthStart)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, app_type, SUM(amount_paid) as rev, COUNT(*) as cnt")
            ->groupBy('ym', 'app_type')
            ->get();

        $monthlyLabels = []; $monthlyMap = [];
        for ($i = 5; $i >= 0; $i--) {
            $ms = Carbon::now()->subMonths($i)->startOfMonth();
            $ym = $ms->format('Y-m');
            $monthlyLabels[] = $ms->format('M Y');
            $monthlyMap[$ym] = ['muno_app' => ['rev' => 0, 'cnt' => 0], 'lugaflix' => ['rev' => 0, 'cnt' => 0], 'ugflix' => ['rev' => 0, 'cnt' => 0], 'web' => ['rev' => 0, 'cnt' => 0]];
        }
        foreach ($monthlyRaw as $row) {
            if (isset($monthlyMap[$row->ym][$row->app_type])) {
                $monthlyMap[$row->ym][$row->app_type] = ['rev' => (float)$row->rev, 'cnt' => (int)$row->cnt];
            }
        }
        $mMunoRev = []; $mLgRev = []; $mUgRev = []; $mWebRev = [];
        $mMunoCnt = []; $mLgCnt = []; $mUgCnt = []; $mWebCnt = [];
        foreach ($monthlyMap as $vals) {
            $mMunoRev[] = $vals['muno_app']['rev']; $mLgRev[] = $vals['lugaflix']['rev']; $mUgRev[] = $vals['ugflix']['rev']; $mWebRev[] = $vals['web']['rev'];
            $mMunoCnt[] = $vals['muno_app']['cnt']; $mLgCnt[] = $vals['lugaflix']['cnt']; $mUgCnt[] = $vals['ugflix']['cnt']; $mWebCnt[] = $vals['web']['cnt'];
        }

        // Plan breakdown
        $plans = Subscription::where('payment_status', 'Completed')
            ->join('subscription_plans', 'subscriptions.plan_id', '=', 'subscription_plans.id')
            ->selectRaw('subscription_plans.name, COUNT(*) as count, SUM(subscriptions.amount_paid) as total')
            ->groupBy('subscription_plans.name')
            ->orderByDesc('total')
            ->get();

        // Payment/Status breakdown (2 queries)
        $payBreakdown = Subscription::selectRaw("payment_status, COUNT(*) as cnt")->groupBy('payment_status')->pluck('cnt', 'payment_status');
        $statusBreakdown = Subscription::selectRaw("status, COUNT(*) as cnt")->groupBy('status')->pluck('cnt', 'status');

        // Platform totals for doughnut
        $munoTotal = $appRevenue['muno_app'] ?? Subscription::where('app_type', 'muno_app')->where('payment_status', 'Completed')->sum('amount_paid');
        $lgTotal   = $appRevenue['lugaflix'] ?? Subscription::where('app_type', 'lugaflix')->where('payment_status', 'Completed')->sum('amount_paid');
        $ugTotal   = $appRevenue['ugflix'] ?? Subscription::where('app_type', 'ugflix')->where('payment_status', 'Completed')->sum('amount_paid');

        // Re-fetch if not set (these were computed in buildStatsCards but not stored)
        $appRevenue = Subscription::where('payment_status', 'Completed')
            ->selectRaw("app_type, SUM(amount_paid) as revenue")
            ->groupBy('app_type')
            ->pluck('revenue', 'app_type')->toArray();
        $munoTotal = (float)($appRevenue['muno_app'] ?? 0);
        $lgTotal   = (float)($appRevenue['lugaflix'] ?? 0);
        $ugTotal   = (float)($appRevenue['ugflix'] ?? 0);
        $webTotal  = (float)($appRevenue['web'] ?? 0);

        // JSON encode
        $j = fn($v) => json_encode($v);

        $chartData = json_encode([
            'daily'   => ['labels' => $dailyLabels, 'muno_rev' => $dMunoRev, 'lg_rev' => $dLgRev, 'ug_rev' => $dUgRev, 'web_rev' => $dWebRev,
                          'muno_cnt' => $dMunoCnt, 'lg_cnt' => $dLgCnt, 'ug_cnt' => $dUgCnt, 'web_cnt' => $dWebCnt,
                          'total_rev' => $dTotalRev, 'total_cnt' => $dTotalCnt],
            'weekly'  => ['labels' => $weeklyLabels, 'muno_rev' => $wMunoRev, 'lg_rev' => $wLgRev, 'ug_rev' => $wUgRev, 'web_rev' => $wWebRev,
                          'muno_cnt' => $wMunoCnt, 'lg_cnt' => $wLgCnt, 'ug_cnt' => $wUgCnt, 'web_cnt' => $wWebCnt],
            'monthly' => ['labels' => $monthlyLabels, 'muno_rev' => $mMunoRev, 'lg_rev' => $mLgRev, 'ug_rev' => $mUgRev, 'web_rev' => $mWebRev,
                          'muno_cnt' => $mMunoCnt, 'lg_cnt' => $mLgCnt, 'ug_cnt' => $mUgCnt, 'web_cnt' => $mWebCnt],
            'plans'   => ['labels' => $plans->pluck('name'), 'counts' => $plans->pluck('count'), 'revenues' => $plans->pluck('total')],
            'payment' => ['completed' => $payBreakdown['Completed'] ?? 0, 'pending' => $payBreakdown['Pending'] ?? 0,
                          'processing' => $payBreakdown['Processing'] ?? 0, 'failed' => $payBreakdown['Failed'] ?? 0],
            'status'  => ['active' => $statusBreakdown['Active'] ?? 0, 'expired' => $statusBreakdown['Expired'] ?? 0,
                          'pending' => $statusBreakdown['Pending'] ?? 0, 'cancelled' => $statusBreakdown['Cancelled'] ?? 0],
            'platform_rev' => ['muno' => $munoTotal, 'lg' => $lgTotal, 'ug' => $ugTotal, 'web' => $webTotal],
        ]);

        // Platform comparison table
        $munoCount = Subscription::where('app_type', 'muno_app')->where('payment_status', 'Completed')->count();
        $lgCount   = Subscription::where('app_type', 'lugaflix')->where('payment_status', 'Completed')->count();
        $ugCount   = Subscription::where('app_type', 'ugflix')->where('payment_status', 'Completed')->count();
        $webCount  = Subscription::where('app_type', 'web')->where('payment_status', 'Completed')->count();

        $platformTable = '';
        $platforms = [
            ['Muno',    $munoCount, $munoTotal, '#e74c3c'],
            ['LugaFlix',$lgCount,   $lgTotal,   '#3498db'],
            ['UGFlix',  $ugCount,   $ugTotal,   '#2ecc71'],
            ['Web',     $webCount,  $webTotal,  '#9b59b6'],
        ];
        foreach ($platforms as [$pn, $pc, $pr, $pcol]) {
            $avg = $pc > 0 ? round($pr / $pc) : 0;
            $platformTable .= "<tr><td><b style='color:{$pcol}'>{$pn}</b></td><td><b>" . number_format($pc) . "</b></td><td><b>UGX " . number_format($pr) . "</b></td><td>UGX " . number_format($avg) . "</td></tr>";
        }
        $grandTotal = $munoTotal + $lgTotal + $ugTotal + $webTotal;
        $grandCount = $munoCount + $lgCount + $ugCount + $webCount;
        $grandAvg = $grandCount > 0 ? round($grandTotal / $grandCount) : 0;
        $platformTable .= "<tr style='border-top:2px solid #ddd;font-weight:700'><td>Total</td><td>" . number_format($grandCount) . "</td><td style='color:#f39c12'>UGX " . number_format($grandTotal) . "</td><td>UGX " . number_format($grandAvg) . "</td></tr>";

        // Plan Revenue table
        $planTable = '';
        foreach ($plans as $plan) {
            $planTable .= "<tr><td><b>{$plan->name}</b></td><td>" . number_format($plan->count) . "</td><td><b>UGX " . number_format($plan->total) . "</b></td></tr>";
        }
        if ($plans->isEmpty()) {
            $planTable = '<tr><td colspan="3" style="text-align:center;color:#999">No data</td></tr>';
        }

        $html = <<<'HTML'
<style>
.sc-wrap{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;font-size:12px}
.sc-row{display:flex;gap:12px;margin-bottom:12px;flex-wrap:wrap}
.sc-box{background:#fff;border-radius:6px;padding:14px;box-shadow:0 1px 4px rgba(0,0,0,.06);border:1px solid #f0f0f0}
.sc-title{font-size:11px;font-weight:700;color:#555;margin-bottom:10px;text-transform:uppercase;letter-spacing:.4px;display:flex;align-items:center;gap:6px}
.sc-title i{font-size:13px}
.sc-tbl{width:100%;border-collapse:collapse;font-size:11px}
.sc-tbl th{text-align:left;padding:5px 8px;border-bottom:2px solid #eee;font-weight:700;color:#666;font-size:10px;text-transform:uppercase}
.sc-tbl td{padding:5px 8px;border-bottom:1px solid #f5f5f5}
</style>
<div class="sc-wrap">
HTML;

        // Row 1: Revenue line + Subscriptions line
        $html .= '<div class="sc-row">';
        $html .= '<div class="sc-box" style="flex:1;min-width:460px"><div class="sc-title"><i class="fa fa-line-chart" style="color:#f39c12"></i> Daily Revenue by Platform — 30 Days (UGX)</div><div style="position:relative;height:300px"><canvas id="scRevLine"></canvas></div></div>';
        $html .= '<div class="sc-box" style="flex:1;min-width:460px"><div class="sc-title"><i class="fa fa-users" style="color:#28a745"></i> Daily Subscriptions by Platform — 30 Days</div><div style="position:relative;height:300px"><canvas id="scCntLine"></canvas></div></div>';
        $html .= '</div>';

        // Row 2: Total bar + Weekly revenue area
        $html .= '<div class="sc-row">';
        $html .= '<div class="sc-box" style="flex:1;min-width:460px"><div class="sc-title"><i class="fa fa-bar-chart" style="color:#007bff"></i> Total Daily Revenue & Subscriptions — 30 Days</div><div style="position:relative;height:280px"><canvas id="scTotalBar"></canvas></div></div>';
        $html .= '<div class="sc-box" style="flex:1;min-width:460px"><div class="sc-title"><i class="fa fa-area-chart" style="color:#e83e8c"></i> Weekly Revenue — 12 Weeks (UGX)</div><div style="position:relative;height:280px"><canvas id="scWeekRev"></canvas></div></div>';
        $html .= '</div>';

        // Row 3: Monthly revenue + Weekly subs
        $html .= '<div class="sc-row">';
        $html .= '<div class="sc-box" style="flex:1;min-width:380px"><div class="sc-title"><i class="fa fa-calendar" style="color:#6f42c1"></i> Monthly Revenue — 6 Months</div><div style="position:relative;height:260px"><canvas id="scMonthRev"></canvas></div></div>';
        $html .= '<div class="sc-box" style="flex:1;min-width:380px"><div class="sc-title"><i class="fa fa-bar-chart" style="color:#17a2b8"></i> Weekly Subscriptions — 12 Weeks</div><div style="position:relative;height:260px"><canvas id="scWeekCnt"></canvas></div></div>';
        $html .= '</div>';

        // Row 4: 4 Doughnuts
        $html .= '<div class="sc-row">';
        $html .= '<div class="sc-box" style="flex:1;min-width:200px;text-align:center"><div class="sc-title" style="justify-content:center"><i class="fa fa-pie-chart" style="color:#e74c3c"></i> Revenue by Platform</div><div style="position:relative;height:220px"><canvas id="scRevPie"></canvas></div></div>';
        $html .= '<div class="sc-box" style="flex:1;min-width:200px;text-align:center"><div class="sc-title" style="justify-content:center"><i class="fa fa-tags" style="color:#6f42c1"></i> By Plan</div><div style="position:relative;height:220px"><canvas id="scPlanPie"></canvas></div></div>';
        $html .= '<div class="sc-box" style="flex:1;min-width:200px;text-align:center"><div class="sc-title" style="justify-content:center"><i class="fa fa-credit-card" style="color:#28a745"></i> Payment Status</div><div style="position:relative;height:220px"><canvas id="scPayPie"></canvas></div></div>';
        $html .= '<div class="sc-box" style="flex:1;min-width:200px;text-align:center"><div class="sc-title" style="justify-content:center"><i class="fa fa-heartbeat" style="color:#dc3545"></i> Sub Status</div><div style="position:relative;height:220px"><canvas id="scStatPie"></canvas></div></div>';
        $html .= '</div>';

        // Row 5: Monthly subs count + comparison tables
        $html .= '<div class="sc-row">';
        $html .= '<div class="sc-box" style="flex:1;min-width:360px"><div class="sc-title"><i class="fa fa-bar-chart" style="color:#fd7e14"></i> Monthly Subscriptions — 6 Months</div><div style="position:relative;height:250px"><canvas id="scMonthCnt"></canvas></div></div>';
        $html .= '<div class="sc-box" style="flex:1;min-width:320px">';
        $html .= '<div class="sc-title"><i class="fa fa-trophy" style="color:#f39c12"></i> Platform Comparison</div>';
        $html .= '<table class="sc-tbl"><tr><th>Platform</th><th>Subs</th><th>Revenue</th><th>Avg</th></tr>' . $platformTable . '</table>';
        $html .= '<div class="sc-title" style="margin-top:14px"><i class="fa fa-list" style="color:#6f42c1"></i> Revenue by Plan</div>';
        $html .= '<table class="sc-tbl"><tr><th>Plan</th><th>Subs</th><th>Revenue</th></tr>' . $planTable . '</table>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '</div>'; // sc-wrap

        // Chart.js 2.x is already included by Laravel Admin (Chart.bundle.min.js)
        $html .= "<script>
(function(){
var _scData = {$chartData};
var _scInstances = [];
function _scDestroy(){ _scInstances.forEach(function(c){ try{c.destroy();}catch(e){} }); _scInstances = []; }
function _scInit(){
    _scDestroy();
    if(!document.getElementById('scRevLine')) return;
    var P = {
        muno:    {bg:'rgba(231,76,60,.12)',  border:'#e74c3c', bar:'rgba(231,76,60,.7)'},
        lugaflix:{bg:'rgba(52,152,219,.12)', border:'#3498db', bar:'rgba(52,152,219,.7)'},
        ugflix:  {bg:'rgba(46,204,113,.12)', border:'#2ecc71', bar:'rgba(46,204,113,.7)'},
        web:     {bg:'rgba(155,89,182,.12)', border:'#9b59b6', bar:'rgba(155,89,182,.7)'}
    };
    var gc = 'rgba(0,0,0,.04)';
    var defOpts = {
        responsive:true, maintainAspectRatio:false,
        legend:{position:'top',labels:{usePointStyle:true,padding:12,fontSize:10}},
        tooltips:{mode:'index',intersect:false,backgroundColor:'rgba(0,0,0,.85)',titleFontSize:11,bodyFontSize:10,cornerRadius:5,
            callbacks:{label:function(item,data){var lbl=data.datasets[item.datasetIndex].label||'';var v=item.yLabel;return lbl+': '+(v>=1000?'UGX '+v.toLocaleString():v);}}},
        hover:{mode:'nearest',intersect:false},
        scales:{xAxes:[{gridLines:{color:gc,drawBorder:false},ticks:{fontSize:9,maxRotation:45}}],yAxes:[{gridLines:{color:gc,drawBorder:false},ticks:{fontSize:9,beginAtZero:true}}]}
    };
    function lds(lbl,data,key,fill){return{label:lbl,data:data,borderColor:P[key].border,backgroundColor:fill?P[key].bg:'transparent',pointBackgroundColor:P[key].border,pointRadius:2,pointHoverRadius:5,borderWidth:2,lineTension:.35,fill:!!fill};}
    function cloneOpts(o){return JSON.parse(JSON.stringify(o));}

    // 1. Revenue Line
    var o1=cloneOpts(defOpts);o1.scales.yAxes[0].ticks.callback=function(v){return 'UGX '+v.toLocaleString();};
    _scInstances.push(new Chart(document.getElementById('scRevLine'),{type:'line',data:{labels:_scData.daily.labels,datasets:[lds('Muno',_scData.daily.muno_rev,'muno',1),lds('LugaFlix',_scData.daily.lg_rev,'lugaflix',1),lds('UGFlix',_scData.daily.ug_rev,'ugflix',1),lds('Web',_scData.daily.web_rev,'web',1)]},options:o1}));

    // 2. Count Line
    _scInstances.push(new Chart(document.getElementById('scCntLine'),{type:'line',data:{labels:_scData.daily.labels,datasets:[lds('Muno',_scData.daily.muno_cnt,'muno',1),lds('LugaFlix',_scData.daily.lg_cnt,'lugaflix',1),lds('UGFlix',_scData.daily.ug_cnt,'ugflix',1),lds('Web',_scData.daily.web_cnt,'web',1)]},options:cloneOpts(defOpts)}));

    // 3. Total Bar (dual axis)
    _scInstances.push(new Chart(document.getElementById('scTotalBar'),{type:'bar',data:{labels:_scData.daily.labels,datasets:[{label:'Revenue (UGX)',data:_scData.daily.total_rev,backgroundColor:'rgba(243,156,18,.7)',borderColor:'#f39c12',borderWidth:1,yAxisID:'y-rev',barPercentage:.7},{label:'Subscriptions',data:_scData.daily.total_cnt,type:'line',borderColor:'#007bff',backgroundColor:'rgba(0,123,255,.1)',pointBackgroundColor:'#007bff',pointRadius:2,borderWidth:2,lineTension:.35,fill:true,yAxisID:'y-cnt'}]},options:{responsive:true,maintainAspectRatio:false,legend:{position:'top',labels:{usePointStyle:true,padding:12,fontSize:10}},tooltips:{mode:'index',intersect:false},hover:{mode:'nearest',intersect:false},scales:{xAxes:[{gridLines:{color:gc,drawBorder:false},ticks:{fontSize:9,maxRotation:45}}],yAxes:[{id:'y-rev',position:'left',gridLines:{color:gc,drawBorder:false},ticks:{fontSize:9,beginAtZero:true,callback:function(v){return 'UGX '+v.toLocaleString();}}},{id:'y-cnt',position:'right',gridLines:{drawOnChartArea:false},ticks:{fontSize:9,beginAtZero:true}}]}}}));

    // 4. Weekly Revenue Area
    var o4=cloneOpts(defOpts);o4.scales.yAxes[0].ticks.callback=function(v){return 'UGX '+v.toLocaleString();};
    _scInstances.push(new Chart(document.getElementById('scWeekRev'),{type:'line',data:{labels:_scData.weekly.labels,datasets:[lds('Muno',_scData.weekly.muno_rev,'muno',1),lds('LugaFlix',_scData.weekly.lg_rev,'lugaflix',1),lds('UGFlix',_scData.weekly.ug_rev,'ugflix',1),lds('Web',_scData.weekly.web_rev,'web',1)]},options:o4}));

    // 5. Monthly Rev (grouped bar)
    var o5=cloneOpts(defOpts);o5.scales.yAxes[0].ticks.callback=function(v){return 'UGX '+v.toLocaleString();};
    _scInstances.push(new Chart(document.getElementById('scMonthRev'),{type:'bar',data:{labels:_scData.monthly.labels,datasets:[{label:'Muno',data:_scData.monthly.muno_rev,backgroundColor:P.muno.bar},{label:'LugaFlix',data:_scData.monthly.lg_rev,backgroundColor:P.lugaflix.bar},{label:'UGFlix',data:_scData.monthly.ug_rev,backgroundColor:P.ugflix.bar},{label:'Web',data:_scData.monthly.web_rev,backgroundColor:P.web.bar}]},options:o5}));

    // 6. Weekly Count (stacked bar)
    var o6=cloneOpts(defOpts);o6.scales.xAxes[0].stacked=true;o6.scales.yAxes[0].stacked=true;
    _scInstances.push(new Chart(document.getElementById('scWeekCnt'),{type:'bar',data:{labels:_scData.weekly.labels,datasets:[{label:'Muno',data:_scData.weekly.muno_cnt,backgroundColor:P.muno.bar},{label:'LugaFlix',data:_scData.weekly.lg_cnt,backgroundColor:P.lugaflix.bar},{label:'UGFlix',data:_scData.weekly.ug_cnt,backgroundColor:P.ugflix.bar},{label:'Web',data:_scData.weekly.web_cnt,backgroundColor:P.web.bar}]},options:o6}));

    // Doughnut defaults
    var donutOpts = {responsive:true,maintainAspectRatio:false,cutoutPercentage:55,legend:{position:'bottom',labels:{usePointStyle:true,padding:8,fontSize:9}}};

    // 7. Revenue by Platform
    var do7=JSON.parse(JSON.stringify(donutOpts));do7.tooltips={callbacks:{label:function(item,data){return data.labels[item.index]+': UGX '+data.datasets[0].data[item.index].toLocaleString();}}};
    _scInstances.push(new Chart(document.getElementById('scRevPie'),{type:'doughnut',data:{labels:['Muno','LugaFlix','UGFlix','Web'],datasets:[{data:[_scData.platform_rev.muno,_scData.platform_rev.lg,_scData.platform_rev.ug,_scData.platform_rev.web],backgroundColor:[P.muno.bar,P.lugaflix.bar,P.ugflix.bar,P.web.bar],borderWidth:2,borderColor:'#fff',hoverBorderColor:'#fff'}]},options:do7}));

    // 8. Plan
    var planColors = ['#e74c3c','#3498db','#2ecc71','#f39c12','#9b59b6','#1abc9c','#e67e22','#34495e'];
    _scInstances.push(new Chart(document.getElementById('scPlanPie'),{type:'doughnut',data:{labels:_scData.plans.labels,datasets:[{data:_scData.plans.counts,backgroundColor:planColors.slice(0,_scData.plans.labels.length),borderWidth:2,borderColor:'#fff'}]},options:JSON.parse(JSON.stringify(donutOpts))}));

    // 9. Payment Status
    _scInstances.push(new Chart(document.getElementById('scPayPie'),{type:'doughnut',data:{labels:['Completed','Pending','Processing','Failed'],datasets:[{data:[_scData.payment.completed,_scData.payment.pending,_scData.payment.processing,_scData.payment.failed],backgroundColor:['rgba(40,167,69,.8)','rgba(255,193,7,.8)','rgba(23,162,184,.8)','rgba(220,53,69,.8)'],borderWidth:2,borderColor:'#fff'}]},options:JSON.parse(JSON.stringify(donutOpts))}));

    // 10. Sub Status
    _scInstances.push(new Chart(document.getElementById('scStatPie'),{type:'doughnut',data:{labels:['Active','Expired','Pending','Cancelled'],datasets:[{data:[_scData.status.active,_scData.status.expired,_scData.status.pending,_scData.status.cancelled],backgroundColor:['rgba(40,167,69,.8)','rgba(220,53,69,.8)','rgba(255,193,7,.8)','rgba(108,117,125,.8)'],borderWidth:2,borderColor:'#fff'}]},options:JSON.parse(JSON.stringify(donutOpts))}));

    // 11. Monthly Subs Count
    _scInstances.push(new Chart(document.getElementById('scMonthCnt'),{type:'bar',data:{labels:_scData.monthly.labels,datasets:[{label:'Muno',data:_scData.monthly.muno_cnt,backgroundColor:P.muno.bar},{label:'LugaFlix',data:_scData.monthly.lg_cnt,backgroundColor:P.lugaflix.bar},{label:'UGFlix',data:_scData.monthly.ug_cnt,backgroundColor:P.ugflix.bar},{label:'Web',data:_scData.monthly.web_cnt,backgroundColor:P.web.bar}]},options:cloneOpts(defOpts)}));
}
// Init on first load
if(document.readyState === 'loading'){document.addEventListener('DOMContentLoaded',_scInit);}else{_scInit();}
// Re-init on PJAX navigation
$(document).on('pjax:end',_scInit);
})();
</script>";

        return $html;
    }

    // ─── APP/PLATFORM BREAKDOWN BOX ─────────────────────────────────────
    protected function appPlatformBreakdownBox()
    {
        $apps = Subscription::where('payment_status', 'Completed')
            ->selectRaw('app_type, platform, COUNT(*) as count, SUM(amount_paid) as total')
            ->groupBy('app_type', 'platform')
            ->orderByDesc('total')
            ->get();

        $rows = [];
        foreach ($apps as $app) {
            $platformIcon = strtolower($app->platform ?? '') === 'ios' ? '<i class="fa fa-apple"></i>' : '<i class="fa fa-android"></i>';
            $rows[] = [
                ucfirst($app->app_type ?? 'Unknown'),
                $platformIcon . ' ' . ucfirst($app->platform ?? 'Unknown'),
                number_format($app->count),
                'UGX ' . number_format($app->total),
            ];
        }
        if (empty($rows)) {
            $rows[] = ['No data', '-', '-', '-'];
        }

        $table = new Table(['App', 'Platform', 'Count', 'Revenue'], $rows);
        $box = new Box('<i class="fa fa-mobile"></i> App & Platform Breakdown', $table);
        $box->style('warning');
        $box->solid();
        return $box;
    }

    // ─── EXPIRING SUBSCRIPTIONS BOX ─────────────────────────────────────
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
            $daysLeft = (int)Carbon::now()->diffInDays(Carbon::parse($sub->end_date_time), false);
            $urgency = $daysLeft <= 1 ? 'danger' : ($daysLeft <= 3 ? 'warning' : 'info');
            $rows[] = [
                $user ? $user->name : 'Unknown',
                "<span class='label label-{$urgency}'>{$daysLeft} days</span>",
                Carbon::parse($sub->end_date_time)->format('M d, H:i'),
            ];
        }
        if (empty($rows)) {
            $rows[] = ['No expiring subscriptions', '-', '-'];
        }

        $table = new Table(['User', 'Days Left', 'Expires'], $rows);
        $box = new Box('<i class="fa fa-clock-o"></i> Expiring Soon (Next 7 Days)', $table);
        $box->style('danger');
        $box->solid();
        return $box;
    }

    // ─── GRID ───────────────────────────────────────────────────────────
    protected function grid()
    {
        $grid = new Grid(new Subscription());
        $grid->model()->with(['user', 'plan']);
        $grid->model()->orderBy('id', 'desc');

        // Quick-filter links above grid
        $grid->header(function () {
            $filters = [
                ['All',       admin_url('subscriptions'),                                    'default'],
                ['Active',    admin_url('subscriptions') . '?status=Active',                'success'],
                ['Pending',   admin_url('subscriptions') . '?payment_status=Pending',       'warning'],
                ['Failed',    admin_url('subscriptions') . '?payment_status=Failed',        'danger'],
                ['Expired',   admin_url('subscriptions') . '?status=Expired',               'default'],
                ['Cancelled', admin_url('subscriptions') . '?status=Cancelled',             'default'],
                ['Completed', admin_url('subscriptions') . '?payment_status=Completed',     'success'],
            ];
            $html = '<div style="margin-bottom:8px">';
            foreach ($filters as [$label, $url, $type]) {
                $html .= "<a href='{$url}' class='btn btn-sm btn-{$type}' style='margin-right:4px;margin-bottom:4px'>{$label}</a>";
            }
            $html .= "<a href='" . admin_url('subscriptions/analytics') . "' class='btn btn-sm btn-info' style='margin-right:4px;margin-bottom:4px'><i class='fa fa-line-chart'></i> Data Analytics</a>";
            $html .= '</div>';
            return $html;
        });

        // Quick search — searches across subscription fields AND user name/email
        $grid->quickSearch(function ($model, $query) {
            $q = trim($query);
            $model->where(function ($w) use ($q) {
                $w->where('pesapal_tracking_id', 'like', "%{$q}%")
                  ->orWhere('pesapal_merchant_reference', 'like', "%{$q}%")
                  ->orWhere('id', '=', is_numeric($q) ? (int)$q : 0)
                  ->orWhereHas('user', function ($u) use ($q) {
                      $u->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone_number', 'like', "%{$q}%")
                        ->orWhere('id', '=', is_numeric($q) ? (int)$q : 0);
                  });
                // Also search subscription_transactions by pesapal_tracking_id
                $txSubs = SubscriptionTransaction::where('pesapal_tracking_id', 'like', "%{$q}%")
                    ->orWhere('merchant_reference', 'like', "%{$q}%")
                    ->orWhere('confirmation_code', 'like', "%{$q}%")
                    ->pluck('subscription_id')
                    ->unique()
                    ->toArray();
                if (!empty($txSubs)) {
                    $w->orWhereIn('id', $txSubs);
                }
            });
        });

        // Filters
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();

            $filter->column(1/3, function ($filter) {
                $filter->equal('status', 'Status')->select([
                    'Pending' => 'Pending', 'Active' => 'Active',
                    'Expired' => 'Expired', 'Cancelled' => 'Cancelled', 'Failed' => 'Failed',
                ]);
                $filter->equal('payment_status', 'Payment Status')->select([
                    'Pending' => 'Pending', 'Processing' => 'Processing',
                    'Completed' => 'Completed', 'Failed' => 'Failed', 'Refunded' => 'Refunded',
                ]);
            });

            $filter->column(1/3, function ($filter) {
                $filter->equal('plan_id', 'Plan')->select(
                    SubscriptionPlan::pluck('name', 'id')->toArray()
                );
                $filter->equal('app_type', 'App Type')->select([
                    'ugflix' => 'UGFlix', 'lugaflix' => 'LugaFlix', 'muno_app' => 'Muno App', 'web' => 'Web',
                ]);
                $filter->equal('platform', 'Platform')->select([
                    'android' => 'Android', 'ios' => 'iOS',
                ]);
            });

            $filter->column(1/3, function ($filter) {
                $filter->where(function ($query) {
                    $q = trim($this->input);
                    if ($q !== '') {
                        $query->whereHas('user', function ($u) use ($q) {
                            if (is_numeric($q)) {
                                $u->where('id', (int)$q)
                                  ->orWhere('phone_number', 'like', "%{$q}%");
                            } else {
                                $u->where('name', 'like', "%{$q}%")
                                   ->orWhere('email', 'like', "%{$q}%")
                                   ->orWhere('phone_number', 'like', "%{$q}%");
                            }
                        });
                    }
                }, 'User (name/phone/email/ID)');
                $filter->between('created_at', 'Created')->datetime();
                $filter->between('amount_paid', 'Amount');
            });
        });

        // Columns
        $grid->column('id', 'ID')->sortable();

        $grid->column('app_type', 'App')->display(function ($type) {
            $map = ['ugflix' => '<i class="fa fa-play-circle" style="color:#2ecc71"></i> UGFlix',
                    'lugaflix' => '<i class="fa fa-film" style="color:#3498db"></i> LugaFlix',
                    'muno_app' => '<i class="fa fa-television" style="color:#e74c3c"></i> Muno'];
            return $map[strtolower($type ?? '')] ?? ucfirst($type ?? 'Unknown');
        })->sortable();

        $grid->column('platform', 'Platform')->display(function ($p) {
            $map = ['android' => '<i class="fa fa-android" style="color:#a4c639"></i> Android',
                    'ios' => '<i class="fa fa-apple" style="color:#555"></i> iOS'];
            return $map[strtolower($p ?? '')] ?? ucfirst($p ?? '-');
        })->sortable()->hide();

        $grid->column('user.name', 'Subscriber')->display(function () {
            if ($this->user) {
                $userUrl = admin_url('users/' . $this->user->id);
                $name = e($this->user->name);
                $phone = e($this->user->phone_number ?? '');
                $phoneLine = $phone !== '' ? "<small class='text-muted'>{$phone}</small>" : "<small class='text-muted' style='opacity:.5'>No phone</small>";
                return "<a href='{$userUrl}'><b>{$name}</b></a><br>{$phoneLine}";
            }
            return '<span class="text-danger">User not found</span>';
        });

        $grid->column('user.phone_number', 'Phone')->display(function ($phone) {
            $v = trim((string) $phone);
            if ($v === '') {
                return '<span class="text-muted">-</span>';
            }
            $digits = preg_replace('/\D+/', '', $v) ?? '';
            $wa = $digits !== '' ? ('https://wa.me/' . $digits) : '';
            $safe = e($v);
            if ($wa !== '') {
                return "<a href='{$wa}' target='_blank'><i class='fa fa-whatsapp text-success'></i> {$safe}</a>";
            }
            return $safe;
        });

        $grid->column('plan.name', 'Plan')->display(function () {
            if ($this->plan) {
                $name = e($this->plan->name);
                $days = $this->plan->duration_days ?? 0;
                if ($days >= 365) $badge = "<span class='label label-danger'>Yearly</span>";
                elseif ($days >= 30) $badge = "<span class='label label-primary'>Monthly</span>";
                elseif ($days >= 7) $badge = "<span class='label label-info'>Weekly</span>";
                else $badge = "<span class='label label-default'>{$days}d</span>";
                return "<b>{$name}</b><br>{$badge}";
            }
            return '<span class="text-muted">-</span>';
        })->hide();

        $grid->column('amount_paid', 'Amount')->display(function ($amt) {
            $cur = $this->currency ?? 'UGX';
            return "<b style='color:#28a745'>" . e($cur) . " " . number_format($amt, 0) . "</b>";
        })->sortable()->totalRow(function ($amount) {
            return "<b style='color:#28a745'>Total: UGX " . number_format($amount) . "</b>";
        });

        $grid->column('status', 'Status')->display(function ($s) {
            $map = ['Active' => 'success', 'Pending' => 'warning', 'Expired' => 'danger',
                    'Cancelled' => 'default', 'Failed' => 'danger'];
            $t = $map[$s] ?? 'info';
            return "<span class='label label-{$t}'>{$s}</span>";
        })->sortable();

        $grid->column('payment_status', 'Payment')->display(function ($ps) {
            $map = ['Completed' => 'success', 'Pending' => 'warning', 'Processing' => 'info',
                    'Failed' => 'danger', 'Refunded' => 'default'];
            $t = $map[$ps] ?? 'info';
            return "<span class='label label-{$t}'>{$ps}</span>";
        })->sortable();

        $grid->column('subscription_snapshot', 'Details')->display(function () {
            $status = e((string) ($this->status ?? '-'));
            $payment = e((string) ($this->payment_status ?? '-'));
            $plan = e((string) ($this->plan->name ?? 'No plan'));
            $start = $this->start_date_time ? Carbon::parse($this->start_date_time)->format('d M Y') : '-';
            $end = $this->end_date_time ? Carbon::parse($this->end_date_time)->format('d M Y') : '-';
            $amount = number_format((float) ($this->amount_paid ?? 0));
            $currency = e((string) ($this->currency ?? 'UGX'));
            return "<div style='font-size:11px;line-height:1.35'>"
                . "<div><span class='label label-info' style='margin-right:3px'>{$status}</span><span class='label label-default'>{$payment}</span></div>"
                . "<div style='margin-top:3px'><b>{$plan}</b> • {$currency} {$amount}</div>"
                . "<div class='text-muted' style='margin-top:2px'>Start: {$start} | End: {$end}</div>"
                . "</div>";
        })->width(260);

        $grid->column('days_remaining', 'Time Left')->display(function () {
            if ($this->status === 'Active' && $this->end_date_time) {
                $days = (int)Carbon::now()->diffInDays(Carbon::parse($this->end_date_time), false);
                if ($days > 7) return "<span class='label label-success'>{$days}d</span>";
                if ($days > 0) return "<span class='label label-warning'>{$days}d</span>";
                if ($days >= -3) return "<span class='label label-danger'>Grace</span>";
                return "<span class='label label-default'>Expired</span>";
            }
            return '-';
        });

        $grid->column('start_date_time', 'Start')->display(function ($d) {
            return $d ? Carbon::parse($d)->format('Y-m-d H:i') : '-';
        })->sortable();

        $grid->column('end_date_time', 'Expires')->display(function ($d) {
            if (!$d) return '-';
            $end = Carbon::parse($d);
            $color = $end->isPast() ? 'danger' : 'success';
            return "<span class='text-{$color}'>" . $end->format('Y-m-d H:i') . "</span>";
        })->sortable();

        $grid->column('created_at', 'Created')->display(function ($d) {
            return Carbon::parse($d)->format('Y-m-d H:i');
        })->sortable()->hide();

        $grid->column('pesapal_tracking_id', 'Pesapal ID')->display(function ($v) {
            if (!$v) return '-';
            $short = Str::limit($v, 16);
            return "<small title='" . e($v) . "'>{$short}</small>";
        })->hide();

        $grid->column('record_actions', 'Actions')->display(function () {
            $canRecheck = !empty($this->pesapal_tracking_id) && ($this->payment_status !== 'Completed');
            $fixAction = $canRecheck ? 'recheck-payment' : 'activate';
            $fixLabel = $canRecheck ? 'Trigger Recheck' : 'Trigger Activate';
            $fixClass = $canRecheck ? 'btn-warning' : 'btn-success';

            // Resolve payment reference hint for the Fix Lab button
            $ref     = htmlspecialchars((string) ($this->pesapal_tracking_id ?: $this->flutterwave_reference ?: $this->pesapal_merchant_reference ?: ''), ENT_QUOTES, 'UTF-8');
            $gwHint  = str_contains(strtolower((string) ($this->payment_gateway ?: $this->payment_method ?: '')), 'flutter') ? 'flutterwave' : 'pesapal';

            return "<div style='display:flex;flex-direction:column;gap:4px;align-items:flex-start'>"
                . "<button type='button' class='btn btn-xs btn-info js-sub-details' data-id='" . (int) $this->id . "'><i class='fa fa-eye'></i> Details</button>"
                . "<button type='button' class='btn btn-xs {$fixClass} js-sub-quick-fix' data-id='" . (int) $this->id . "' data-action='{$fixAction}'><i class='fa fa-wrench'></i> {$fixLabel}</button>"
                . "<button type='button' class='btn btn-xs btn-danger js-sub-fix-lab' "
                    . "data-id='" . (int) $this->id . "' "
                    . "data-ref='{$ref}' "
                    . "data-gateway='{$gwHint}'>"
                    . "<i class='fa fa-flask'></i> Fix Lab</button>"
                . "</div>";
        });

            $grid->column('fix_lab_action', 'Fix Lab')->display(function () {
                $ref = htmlspecialchars((string) ($this->pesapal_tracking_id ?: $this->flutterwave_reference ?: $this->pesapal_merchant_reference ?: ''), ENT_QUOTES, 'UTF-8');
                $gwHint = str_contains(strtolower((string) ($this->payment_gateway ?: $this->payment_method ?: '')), 'flutter') ? 'flutterwave' : 'pesapal';

                return "<button type='button' class='btn btn-xs btn-danger js-sub-fix-lab' "
                . "data-id='" . (int) $this->id . "' "
                . "data-ref='{$ref}' "
                . "data-gateway='{$gwHint}'>"
                . "<i class='fa fa-flask'></i> Fix Lab</button>";
            });

        // Export — clean, human-readable CSV (no HTML)
        $grid->export(function ($export) {
            $export->filename('Subscriptions_' . date('Y-m-d'));
            $export->except(['actions', 'days_remaining']);

            $export->column('app_type', function ($value, $original) {
                $raw = $original ?? strip_tags($value);
                $map = ['ugflix' => 'UgFlix', 'lugaflix' => 'LugaFlix', 'muno_app' => 'Muno App', 'web' => 'Web'];
                return $map[strtolower(trim($raw))] ?? ucfirst(trim($raw) ?: 'Unknown');
            });

            $export->column('platform', function ($value, $original) {
                $raw = $original ?? strip_tags($value);
                return ucfirst(trim($raw) ?: '-');
            });

            $export->column('user.name', function ($value, $original) {
                return strip_tags($value);
            });

            $export->column('plan.name', function ($value, $original) {
                return strip_tags($value);
            });

            $export->column('amount_paid', function ($value, $original) {
                return $original ?? 0;
            });

            $export->column('status', function ($value, $original) {
                return $original ?? strip_tags($value);
            });

            $export->column('payment_status', function ($value, $original) {
                return $original ?? strip_tags($value);
            });

            $export->column('start_date_time', function ($value, $original) {
                $raw = $original ?? strip_tags($value);
                return $raw ? Carbon::parse($raw)->format('Y-m-d H:i') : '';
            });

            $export->column('end_date_time', function ($value, $original) {
                $raw = $original ?? strip_tags($value);
                return $raw ? Carbon::parse($raw)->format('Y-m-d H:i') : '';
            });

            $export->column('created_at', function ($value, $original) {
                $raw = $original ?? strip_tags($value);
                return $raw ? Carbon::parse($raw)->format('Y-m-d H:i') : '';
            });

            $export->column('pesapal_tracking_id', function ($value, $original) {
                return $original ?? strip_tags($value);
            });
        });

        $grid->batchActions(function ($batch) {
            $batch->disableDelete();
        });

        $grid->tools(function ($tools) {
            $tools->append('<button type="button" class="btn btn-sm btn-danger js-sub-batch-fix-btn" style="margin-left:4px"><i class="fa fa-flask"></i> Batch Fix Selected</button>');
        });

        $ajaxActionUrlTemplate = admin_url('api/subscriptions/__ID__/action');
        $ajaxDetailsUrlTemplate = admin_url('subscriptions/__ID__/ajax-details');
        $csrf = csrf_token();

        Admin::html(<<<HTML
<!-- Subscription Details Modal -->
<div class="modal fade" id="subDetailsModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content" style="border-radius:0">
      <div class="modal-header" style="background:#2f3a4b;color:#fff;border-bottom:0;padding:8px 14px">
        <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:1">&times;</button>
        <h4 class="modal-title" style="font-size:15px;font-weight:700"><i class="fa fa-credit-card"></i> Subscription Details</h4>
      </div>
      <div class="modal-body" id="subDetailBody" style="background:#f5f7fa;padding:12px 14px;max-height:72vh;overflow-y:auto">
        <div class="text-center" style="padding:30px 0"><i class="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
      </div>
      <div class="modal-footer" style="background:#f5f7fa;border-top:1px solid #ddd;padding:8px 14px">
        <a id="subDetailFullLink" href="#" class="btn btn-sm btn-default" target="_blank"><i class="fa fa-external-link"></i> Full Page</a>
        <button type="button" class="btn btn-sm btn-default" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Auto-Fix / Activate Modal -->
<div class="modal fade" id="subFixModal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
  <div class="modal-dialog" role="document" style="max-width:520px">
    <div class="modal-content" style="border-radius:0">
      <div class="modal-header" id="subFixModalHeader" style="background:#2c6fad;color:#fff;border-bottom:0;padding:8px 14px">
        <h4 class="modal-title" id="subFixModalTitle" style="font-size:15px;font-weight:700"><i class="fa fa-wrench"></i> Auto Fix</h4>
      </div>
      <div class="modal-body" style="padding:16px">
        <div id="subFixProgress" style="margin-bottom:12px">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
            <i class="fa fa-spinner fa-spin fa-lg" id="subFixSpinner" style="color:#2c6fad"></i>
            <span id="subFixStatusText" style="font-weight:600;font-size:14px">Preparing...</span>
          </div>
          <div style="background:#f4f6f8;border:1px solid #e0e5ea;border-radius:4px;padding:10px;font-family:monospace;font-size:12px;max-height:220px;overflow-y:auto" id="subFixLog"></div>
        </div>
        <div id="subFixResult" style="display:none"></div>
      </div>
      <div class="modal-footer" style="padding:8px 14px;border-top:1px solid #eee">
        <button type="button" class="btn btn-sm btn-default" id="subFixCloseBtn" data-dismiss="modal" disabled>Close</button>
      </div>
    </div>
  </div>
</div>
HTML);

        Admin::script(<<<JS
(function () {
    var actionTpl = '{$ajaxActionUrlTemplate}';
    var detailsTpl = '{$ajaxDetailsUrlTemplate}';
    var token = '{$csrf}';

    // Details popup
    $(document).off('click.subdet', '.js-sub-details').on('click.subdet', '.js-sub-details', function () {
        var id = $(this).data('id');
        if (!id) return;
        var body = $('#subDetailBody');
        body.html('<div class="text-center" style="padding:30px 0"><i class="fa fa-spinner fa-spin fa-2x text-muted"></i></div>');
        $('#subDetailFullLink').attr('href', detailsTpl.replace('__ID__', id).replace('/ajax-details', ''));
        $('#subDetailsModal').modal('show');

        $.ajax({
            url: detailsTpl.replace('__ID__', encodeURIComponent(String(id))),
            type: 'GET',
            dataType: 'json',
            success: function (res) {
                if (!res || !res.success) {
                    body.html('<div class="alert alert-danger">' + (res && res.message ? res.message : 'Failed to load details.') + '</div>');
                    return;
                }
                var d = res.data;
                var s = d.subscription;
                var u = d.user;
                var txns = d.transactions || [];

                function badge(val, map) {
                    var cls = (map && map[val]) ? map[val] : 'default';
                    return '<span class="label label-' + cls + '">' + (val || '-') + '</span>';
                }

                var statusMap = { Active: 'success', Pending: 'warning', Expired: 'danger', Cancelled: 'default', Failed: 'danger' };
                var payMap = { Completed: 'success', Pending: 'warning', Processing: 'info', Failed: 'danger' };

                var txnRows = '';
                for (var i = 0; i < txns.length; i++) {
                    var t = txns[i];
                    txnRows += '<tr><td>' + (t.id || '-') + '</td><td>' + (t.amount || '0') + ' ' + (t.currency || '') + '</td>'
                        + '<td>' + badge(t.status, { success: 'success', pending: 'warning', failed: 'danger' }) + '</td>'
                        + '<td>' + (t.payment_method || '-') + '</td>'
                        + '<td style="font-size:11px">' + (t.created_at || '-') + '</td></tr>';
                }

                var html = '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:10px">';
                function cell(k, v) { return '<div style="background:#fff;border:1px solid #dde2e7;padding:7px 10px"><div style="font-size:10px;text-transform:uppercase;color:#6c7f8f;margin-bottom:2px">' + k + '</div><div style="font-size:13px;font-weight:700;color:#2d3a47">' + (v || '-') + '</div></div>'; }

                html += cell('Status', badge(s.status, statusMap));
                html += cell('Payment', badge(s.payment_status, payMap));
                html += cell('Plan', s.plan_name || '-');
                html += cell('Amount', (s.currency || 'UGX') + ' ' + (s.amount_paid ? Number(s.amount_paid).toLocaleString() : '0'));
                html += cell('Start', s.start_date_time || '-');
                html += cell('End', s.end_date_time || '-');
                html += cell('App', s.app_type || '-');
                html += cell('Platform', s.platform || '-');
                html += cell('Days', s.days || '-');
                html += '</div>';

                html += '<div style="background:#fff;border:1px solid #dde2e7;padding:8px 10px;margin-bottom:10px">';
                html += '<div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#5a6a78;margin-bottom:6px"><i class="fa fa-user"></i> Subscriber</div>';
                html += '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px">';
                html += cell('Name', u.name);
                html += cell('Phone', u.phone ? '<a href="https://wa.me/' + u.phone.replace(/\\D/g, '') + '" target="_blank">' + u.phone + '</a>' : '-');
                html += cell('Email', u.email);
                html += cell('Account State', u.account_state || '-');
                html += cell('Member Since', u.created_at || '-');
                html += cell('Last Online', u.last_online_at || '-');
                html += '</div></div>';

                if (s.pesapal_tracking_id) {
                    html += '<div style="background:#fff;border:1px solid #dde2e7;padding:7px 10px;margin-bottom:10px">';
                    html += '<div style="font-size:10px;text-transform:uppercase;color:#6c7f8f;margin-bottom:2px">Pesapal Tracking ID</div>';
                    html += '<code style="font-size:12px">' + s.pesapal_tracking_id + '</code></div>';
                }

                if (txns.length) {
                    html += '<div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#5a6a78;margin-bottom:4px"><i class="fa fa-exchange"></i> Transactions (' + txns.length + ')</div>';
                    html += '<div style="overflow-x:auto"><table class="table table-condensed table-bordered" style="background:#fff;font-size:12px;margin-bottom:0">';
                    html += '<thead><tr><th>#</th><th>Amount</th><th>Status</th><th>Method</th><th>Date</th></tr></thead>';
                    html += '<tbody>' + txnRows + '</tbody></table></div>';
                } else {
                    html += '<div class="text-muted" style="font-size:12px">No transactions recorded.</div>';
                }

                body.html(html);
            },
            error: function (xhr) {
                var msg = 'Failed to load details.';
                if (xhr && xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                body.html('<div class="alert alert-danger">' + msg + '</div>');
            }
        });
    });

    // Quick Fix
    $(document).off('click.subfix', '.js-sub-quick-fix').on('click.subfix', '.js-sub-quick-fix', function () {
        var btn = $(this);
        var id = btn.data('id');
        var action = String(btn.data('action') || '');
        if (!id || !action) return;

        var isRecheck = action === 'recheck-payment';
        var headerColor = isRecheck ? '#b07d00' : '#1a7a3c';
        var icon = isRecheck ? 'fa-refresh' : 'fa-bolt';
        var label = isRecheck ? 'Re-check Payment' : 'Activate Subscription';

        // Prepare modal
        $('#subFixModalHeader').css('background', headerColor);
        $('#subFixModalTitle').html('<i class="fa ' + icon + '"></i> ' + label + ' - #' + id);
        $('#subFixSpinner').show().css('color', headerColor);
        $('#subFixStatusText').text('Sending request...').css('color', '#333');
        $('#subFixLog').html('<span style="color:#888">> Initiating ' + action + ' for subscription #' + id + '...</span>');
        $('#subFixResult').hide().html('');
        $('#subFixCloseBtn').prop('disabled', true);
        $('#subFixModal').modal('show');

        function log(msg, color) {
            var ts = new Date().toTimeString().slice(0,8);
            $('#subFixLog').append('<br><span style="color:' + (color||'#444') + '">[' + ts + '] ' + msg + '</span>');
            var el = document.getElementById('subFixLog');
            el.scrollTop = el.scrollHeight;
        }

        log('Connected. Running: ' + action + '...');

        $.ajax({
            url: actionTpl.replace('__ID__', encodeURIComponent(String(id))),
            type: 'POST',
            dataType: 'json',
            data: { _token: token, action: action },
            success: function (res) {
                var ok = res && res.success;
                $('#subFixSpinner').hide();

                if (ok) {
                    log('[OK] ' + (res.message || 'Completed successfully.'), '#1a7a3c');
                    // Show any extra steps from response
                    if (res.steps && Array.isArray(res.steps)) {
                        for (var i = 0; i < res.steps.length; i++) {
                            log('  - ' + res.steps[i], '#2c6fad');
                        }
                    }
                    $('#subFixStatusText').text('Completed').css('color', '#1a7a3c');
                    $('#subFixResult').show().html(
                        '<div class="alert alert-success" style="margin:0;padding:8px 12px;font-size:13px">'
                        + '<i class="fa fa-check-circle"></i> ' + (res.message || 'Action completed successfully.')
                        + '</div>'
                    );
                    // Reload grid after short delay
                    setTimeout(function () {
                        $('#subFixModal').modal('hide');
                        location.reload();
                    }, 1800);
                } else {
                    log('[X] ' + (res && res.message ? res.message : 'Action failed.'), '#c0392b');
                    $('#subFixStatusText').text('Failed').css('color', '#c0392b');
                    $('#subFixResult').show().html(
                        '<div class="alert alert-danger" style="margin:0;padding:8px 12px;font-size:13px">'
                        + '<i class="fa fa-times-circle"></i> ' + (res && res.message ? res.message : 'Action failed.')
                        + '</div>'
                    );
                }
                $('#subFixCloseBtn').prop('disabled', false);
            },
            error: function (xhr) {
                var msg = 'Request failed.';
                if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                } else if (xhr && xhr.status) {
                    msg = 'HTTP ' + xhr.status + ' error.';
                }
                $('#subFixSpinner').hide();
                log('[X] ' + msg, '#c0392b');
                $('#subFixStatusText').text('Error').css('color', '#c0392b');
                $('#subFixResult').show().html(
                    '<div class="alert alert-danger" style="margin:0;padding:8px 12px;font-size:13px">'
                    + '<i class="fa fa-exclamation-triangle"></i> ' + msg
                    + '</div>'
                );
                $('#subFixCloseBtn').prop('disabled', false);
            }
        });
    });
}());
JS);

        // ── Subscription Fix Lab (debug lab modals + JS) ──────────────────
        Admin::html(<<<HTML
<!-- Subscription Fix Lab Modal (single row) -->
<div class="modal fade" id="subFixLabModal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
  <div class="modal-dialog" role="document" style="width:98%;max-width:1260px;margin:20px auto">
    <div class="modal-content" style="border-radius:0;background:#1a1e2a;color:#c9d1d9">
      <div class="modal-header" style="background:#161b22;border-bottom:1px solid #30363d;padding:12px 20px;display:flex;align-items:center;justify-content:space-between">
        <h4 class="modal-title" id="subFixLabTitle" style="font-size:16px;font-weight:700;color:#e6edf3;margin:0"><i class="fa fa-flask"></i> Subscription Fix Lab</h4>
        <button type="button" class="close" data-dismiss="modal" style="color:#8b949e;opacity:1;font-size:22px;line-height:1;background:none;border:0">&times;</button>
      </div>

      <!-- Two-column body -->
      <div class="modal-body" style="padding:0;display:flex;height:82vh;overflow:hidden">

        <!-- LEFT: Subscription summary + transaction list -->
        <div style="width:36%;border-right:1px solid #30363d;display:flex;flex-direction:column;overflow:hidden;background:#0d1117">

          <!-- Subscription summary card -->
          <div id="subFixLabSubSummary" style="padding:12px 14px;border-bottom:1px solid #30363d;font-size:12px;background:#161b22;flex-shrink:0">
            <div style="font-size:10px;text-transform:uppercase;color:#8b949e;margin-bottom:6px;letter-spacing:.5px"><i class="fa fa-credit-card"></i> Subscription</div>
            <div style="color:#4a5878">Loading&hellip;</div>
          </div>

          <!-- Transaction list header -->
          <div style="padding:6px 10px 6px 12px;border-bottom:1px solid #30363d;background:#161b22;flex-shrink:0;display:flex;align-items:center;justify-content:space-between">
            <span style="font-size:10px;text-transform:uppercase;color:#8b949e;letter-spacing:.5px"><i class="fa fa-exchange"></i> Payment Transactions <span id="subFixLabTxCount" style="color:#58a6ff"></span></span>
            <button id="subFixLabNewPayBtn" class="btn btn-xs btn-success" style="font-size:11px;padding:2px 8px"><i class="fa fa-plus"></i> New Payment</button>
          </div>

          <!-- Scrollable transaction list -->
          <div id="subFixLabTxList" style="flex:1;overflow-y:auto;padding:4px 0">
            <div style="color:#4a5878;font-size:12px;padding:24px;text-align:center">Loading&hellip;</div>
          </div>
        </div>

        <!-- RIGHT: Inspector + actions + log -->
        <div style="flex:1;display:flex;flex-direction:column;overflow:hidden">

          <!-- Gateway inspect result area -->
          <div id="subFixLabInspectArea" style="padding:12px 16px;border-bottom:1px solid #30363d;background:#0d1117;flex-shrink:0;min-height:48px;max-height:240px;overflow-y:auto">
            <div style="color:#4a5878;font-size:12px;text-align:center;padding:14px 0">Click a transaction on the left to inspect its gateway status</div>
          </div>

          <!-- Actions -->
          <div style="padding:10px 16px;border-bottom:1px solid #30363d;background:#161b22;flex-shrink:0">
            <div style="font-size:10px;text-transform:uppercase;color:#8b949e;margin-bottom:8px;letter-spacing:.5px">
              Actions <span id="subFixLabActiveTxBadge" style="margin-left:6px;font-size:10px;color:#58a6ff;font-weight:700"></span>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:6px">
              <button class="btn btn-xs btn-info js-subfix-action" data-action="force_verify" style="font-size:12px"><i class="fa fa-refresh"></i> Force Verify</button>
              <button class="btn btn-xs btn-success js-subfix-action" data-action="force_activate" style="font-size:12px"><i class="fa fa-bolt"></i> Force Activate Sub</button>
              <button class="btn btn-xs btn-warning js-subfix-action" data-action="mark_payment_completed" style="font-size:12px"><i class="fa fa-check"></i> Mark Payment Completed</button>
              <button class="btn btn-xs btn-danger js-subfix-action" data-action="mark_payment_failed" style="font-size:12px"><i class="fa fa-times"></i> Mark Payment Failed</button>
              <button class="btn btn-xs js-subfix-action" data-action="mark_subscription_active" style="font-size:12px;background:#238636;color:#fff;border:0;border-radius:3px;padding:1px 8px"><i class="fa fa-play"></i> Mark Sub Active</button>
              <button class="btn btn-xs btn-default js-subfix-action" data-action="mark_subscription_expired" style="font-size:12px"><i class="fa fa-clock-o"></i> Expire Sub</button>
              <button class="btn btn-xs btn-default js-subfix-action" data-action="mark_subscription_cancelled" style="font-size:12px"><i class="fa fa-ban"></i> Cancel Sub</button>
              <span style="display:flex;align-items:center;gap:4px">
                <input type="number" id="subFixLabExtendDays" min="1" max="3650" value="30" style="width:60px;height:24px;font-size:12px;padding:2px 6px;border:1px solid #444;background:#0d1117;color:#c9d1d9;border-radius:3px">
                <button class="btn btn-xs btn-primary js-subfix-action" data-action="extend_subscription" style="font-size:12px"><i class="fa fa-calendar-plus-o"></i> Extend Days</button>
              </span>
            </div>
          </div>

          <!-- Action log -->
          <div style="flex:1;display:flex;flex-direction:column;overflow:hidden;padding:10px 16px;min-height:0">
            <div style="font-size:10px;text-transform:uppercase;color:#8b949e;margin-bottom:6px;letter-spacing:.5px">Action Log</div>
            <div id="subFixLabLog" style="flex:1;background:#0d1117;border:1px solid #30363d;padding:8px 10px;font-family:monospace;font-size:12px;overflow-y:auto;color:#c9d1d9;white-space:pre-wrap;min-height:0"></div>
            <div id="subFixLabActionResult" style="margin-top:8px;font-size:12px;flex-shrink:0"></div>
          </div>
        </div>
      </div><!-- end two-column body -->

      <!-- Hidden state inputs -->
      <input type="hidden" id="subFixLabRef" value="">
      <input type="hidden" id="subFixLabGateway" value="auto">

      <div class="modal-footer" style="background:#161b22;border-top:1px solid #30363d;padding:8px 16px">
        <button type="button" class="btn btn-sm btn-info" id="subFixLabInspectBtn" style="font-size:12px"><i class="fa fa-search"></i> Re-Inspect Selected Tx</button>
        <button type="button" class="btn btn-sm btn-default" data-dismiss="modal" style="font-size:12px">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Initiate New Payment Modal (shown on top of Fix Lab) -->
<div class="modal fade" id="subInitPayModal" tabindex="-1" role="dialog" style="z-index:1080">
  <div class="modal-dialog" role="document" style="max-width:440px;margin:80px auto">
    <div class="modal-content" style="border-radius:4px;background:#1a1e2a;color:#c9d1d9;border:1px solid #30363d">
      <div class="modal-header" style="background:#161b22;border-bottom:1px solid #30363d;padding:12px 18px">
        <h4 class="modal-title" style="font-size:15px;font-weight:700;color:#e6edf3;margin:0"><i class="fa fa-credit-card"></i> Initiate New Payment</h4>
        <button type="button" class="close" data-dismiss="modal" style="color:#8b949e;opacity:1;font-size:22px;background:none;border:0">&times;</button>
      </div>
      <div class="modal-body" style="padding:18px 20px">
        <p style="font-size:12px;color:#8b949e;margin-bottom:14px">A new payment order will be created for this subscription via the selected gateway. The user must open the payment link to complete the payment.</p>
        <div style="margin-bottom:12px">
          <label style="font-size:12px;color:#9fb2d9;display:block;margin-bottom:4px">Phone Number (Mobile Money)</label>
          <input type="tel" id="subInitPayPhone" placeholder="e.g. 0772123456" style="width:100%;padding:7px 10px;background:#0d1117;border:1px solid #30363d;border-radius:4px;color:#e6edf3;font-size:13px">
          <div style="font-size:10px;color:#6e7681;margin-top:3px">Enter the phone number linked to the Mobile Money account</div>
        </div>
        <div style="margin-bottom:12px">
          <label style="font-size:12px;color:#9fb2d9;display:block;margin-bottom:4px">Gateway</label>
          <select id="subInitPayGateway" style="width:100%;padding:7px 10px;background:#0d1117;border:1px solid #30363d;border-radius:4px;color:#e6edf3;font-size:13px">
            <option value="flutterwave" selected>Flutterwave</option>
            <option value="pesapal">Pesapal</option>
          </select>
        </div>
        <div id="subInitPayResult" style="font-size:12px;min-height:20px"></div>
      </div>
      <div class="modal-footer" style="background:#161b22;border-top:1px solid #30363d;padding:8px 16px;display:flex;gap:8px;justify-content:flex-end">
        <button type="button" id="subInitPaySubmitBtn" class="btn btn-sm btn-success" style="font-size:12px"><i class="fa fa-paper-plane"></i> Initiate Payment</button>
        <button type="button" class="btn btn-sm btn-default" data-dismiss="modal" style="font-size:12px">Cancel</button>
      </div>
    </div>
  </div>
</div>

<!-- Subscription Batch Fix Modal -->
<div class="modal fade" id="subBatchFixModal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
  <div class="modal-dialog" role="document" style="width:98%;max-width:1200px;margin:20px auto">
    <div class="modal-content" style="border-radius:0;background:#1a1e2a;color:#c9d1d9">
      <div class="modal-header" style="background:#161b22;border-bottom:1px solid #30363d;padding:10px 16px">
        <h4 class="modal-title" style="font-size:15px;font-weight:700;color:#e6edf3;margin:0"><i class="fa fa-tasks"></i> Batch Fix Subscriptions</h4>
        <button type="button" class="close" data-dismiss="modal" style="color:#8b949e;opacity:1;font-size:20px;background:none;border:0">&times;</button>
      </div>
      <div class="modal-body" style="padding:14px 16px">
        <div id="subBatchSummaryBar" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:10px;font-size:13px"></div>
        <div style="background:#0d1117;height:8px;border-radius:4px;margin-bottom:10px;overflow:hidden">
          <div id="subBatchProgressBar" style="background:#238636;height:100%;width:0;transition:width .3s"></div>
        </div>
        <!-- Tabs -->
        <ul class="nav nav-tabs" style="border-color:#30363d;margin-bottom:0">
          <li class="active"><a href="#subBatchTabLog" data-toggle="tab" style="background:transparent;color:#8b949e;border-color:#30363d #30363d transparent;font-size:12px">Log</a></li>
          <li><a href="#subBatchTabDetails" data-toggle="tab" style="background:transparent;color:#8b949e;border-color:transparent;font-size:12px">Details</a></li>
          <li><a href="#subBatchTabSummary" data-toggle="tab" style="background:transparent;color:#8b949e;border-color:transparent;font-size:12px">Summary</a></li>
        </ul>
        <div class="tab-content" style="background:#0d1117;border:1px solid #30363d;border-top:0;min-height:300px;max-height:460px;overflow-y:auto">
          <div class="tab-pane active" id="subBatchTabLog" style="padding:8px 10px;font-family:monospace;font-size:12px;color:#c9d1d9"></div>
          <div class="tab-pane" id="subBatchTabDetails" style="padding:8px 10px"></div>
          <div class="tab-pane" id="subBatchTabSummary" style="padding:10px 14px;font-size:13px"></div>
        </div>
      </div>
      <div class="modal-footer" style="background:#161b22;border-top:1px solid #30363d;padding:8px 16px">
        <button type="button" class="btn btn-sm btn-default" data-dismiss="modal" id="subBatchCloseBtn" disabled style="font-size:12px">Close</button>
      </div>
    </div>
  </div>
</div>
HTML);

        $inspectUrl         = admin_url('api/subscriptions/debug/inspect');
        $applyFixUrl        = admin_url('api/subscriptions/debug/apply-fix');
        $batchFixSingleUrl  = admin_url('api/subscriptions/debug/batch-fix-single');
        $initiatePaymentUrl = admin_url('api/subscriptions/debug/initiate-payment');

        $fixConfigJs = json_encode([
            'inspectUrl'          => $inspectUrl,
            'applyFixUrl'         => $applyFixUrl,
            'batchFixSingleUrl'   => $batchFixSingleUrl,
            'initiatePaymentUrl'  => $initiatePaymentUrl,
            'token'               => csrf_token(),
        ]);

        Admin::script("window.SubFixConfig = {$fixConfigJs};");
        Admin::script(file_get_contents(public_path('assets/sub-fix-modal.js')));

        return $grid;
    }

    // ─── AJAX DETAILS POPUP ─────────────────────────────────────────────
    public function ajaxDetails(Request $request, int $id)
    {
        $subscription = Subscription::with(['user', 'plan', 'transactions'])->find($id);

        if (!$subscription) {
            return response()->json(['success' => false, 'message' => 'Subscription not found.'], 404);
        }

        $user = $subscription->user;
        $plan = $subscription->plan;

        $transactions = $subscription->transactions->map(function ($t) {
            return [
                'id'             => (int) $t->id,
                'amount'         => $t->amount ?? 0,
                'currency'       => (string) ($t->currency ?? 'UGX'),
                'status'         => (string) ($t->status ?? ''),
                'payment_method' => (string) ($t->payment_method ?? '-'),
                'created_at'     => optional($t->created_at)->format('Y-m-d H:i'),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data'    => [
                'subscription' => [
                    'id'                   => (int) $subscription->id,
                    'status'               => (string) ($subscription->status ?? ''),
                    'payment_status'       => (string) ($subscription->payment_status ?? ''),
                    'plan_name'            => (string) ($plan->name ?? '-'),
                    'amount_paid'          => $subscription->amount_paid ?? 0,
                    'currency'             => (string) ($subscription->currency ?? 'UGX'),
                    'days'                 => $subscription->days ?? '-',
                    'start_date_time'      => optional($subscription->start_date_time)->format('Y-m-d H:i'),
                    'end_date_time'        => optional($subscription->end_date_time)->format('Y-m-d H:i'),
                    'app_type'             => (string) ($subscription->app_type ?? ''),
                    'platform'             => (string) ($subscription->platform ?? ''),
                    'pesapal_tracking_id'  => (string) ($subscription->pesapal_tracking_id ?? ''),
                ],
                'user' => [
                    'name'          => (string) ($user->name ?? '-'),
                    'email'         => (string) ($user->email ?? ''),
                    'phone'         => (string) ($user->phone_number ?? ''),
                    'account_state' => (string) ($user->account_state ?? ''),
                    'created_at'    => optional($user->created_at)->format('Y-m-d'),
                    'last_online_at'=> optional($user->last_online_at)->format('Y-m-d H:i'),
                ],
                'transactions' => $transactions,
            ],
        ]);
    }

    // ─── DETAIL / SHOW ──────────────────────────────────────────────────
    protected function detail($id)
    {
        $subscription = Subscription::with(['user', 'plan', 'transactions'])->findOrFail($id);
        $show = new Show($subscription);
        $show->resource(admin_url('subscriptions'));

        $show->panel()->title('Subscription #' . $subscription->id);

        // ── Subscriber
        $show->divider('Subscriber');
        $show->field('user.name', 'Name');
        $show->field('user.email', 'Email');

        // ── Subscription
        $show->divider('Subscription Details');
        $show->field('plan.name', 'Plan');
        $show->field('days', 'Duration (days)');
        $show->field('amount_paid', 'Amount Paid')->as(function ($v) use ($subscription) {
            return ($subscription->currency ?? 'UGX') . ' ' . number_format($v ?? 0);
        });
        $show->field('status', 'Status')->label([
            'Active' => 'success', 'Pending' => 'warning', 'Expired' => 'danger',
            'Cancelled' => 'default', 'Failed' => 'danger',
        ]);
        $show->field('payment_status', 'Payment Status')->label([
            'Completed' => 'success', 'Pending' => 'warning', 'Processing' => 'info',
            'Failed' => 'danger', 'Refunded' => 'default',
        ]);
        $show->field('payment_failure_reason', 'Failure Reason')->as(function ($v) {
            return $v ?: '-';
        });
        $show->field('app_type', 'App')->as(function ($v) { return ucfirst($v ?? 'Unknown'); });
        $show->field('platform', 'Platform')->as(function ($v) { return ucfirst($v ?? 'Unknown'); });

        // ── Timeline
        $show->divider('Timeline');
        $show->field('start_date_time', 'Start')->as(function ($v) {
            return $v ? Carbon::parse($v)->format('Y-m-d H:i:s') : '-';
        });
        $show->field('end_date_time', 'End')->as(function ($v) {
            return $v ? Carbon::parse($v)->format('Y-m-d H:i:s') : '-';
        });
        $show->field('grace_period_end', 'Grace Period End')->as(function ($v) {
            return $v ? Carbon::parse($v)->format('Y-m-d H:i:s') : '-';
        });
        $show->field('payment_confirmed_at', 'Payment Confirmed')->as(function ($v) {
            return $v ? Carbon::parse($v)->format('Y-m-d H:i:s') : '-';
        });
        $show->field('cancelled_at', 'Cancelled At')->as(function ($v) {
            return $v ? Carbon::parse($v)->format('Y-m-d H:i:s') : '-';
        });
        $show->field('cancelled_reason', 'Cancel Reason');
        $show->field('failed_at', 'Failed At')->as(function ($v) {
            return $v ? Carbon::parse($v)->format('Y-m-d H:i:s') : '-';
        });
        $show->field('created_at', 'Created')->as(function ($v) {
            return Carbon::parse($v)->format('Y-m-d H:i:s');
        });
        $show->field('updated_at', 'Updated')->as(function ($v) {
            return Carbon::parse($v)->format('Y-m-d H:i:s');
        });

        // ── Pesapal / Payment
        $show->divider('Payment / Pesapal');
        $show->field('payment_method', 'Method');
        $show->field('pesapal_tracking_id', 'Tracking ID');
        $show->field('pesapal_merchant_reference', 'Merchant Reference');
        $show->field('pesapal_transaction_id', 'Transaction ID');
        $show->field('payment_url', 'Payment URL');

        // ── Linked Transactions
        $show->divider('Linked Transactions');
        $transBase = $prefix ? "/{$prefix}/subscription-transactions" : "/subscription-transactions";
        $show->field('transactions', 'Transactions')->as(function ($transactions) use ($transBase) {
            if (!$transactions || $transactions->isEmpty()) return 'No transactions found.';
            $html = '<table class="table table-bordered table-condensed" style="font-size:12px">';
            $html .= '<tr><th>ID</th><th>Type</th><th>Amount</th><th>Status</th><th>Pesapal ID</th><th>Confirmation</th><th>Date</th></tr>';
            foreach ($transactions as $tx) {
                $statusMap = ['Completed' => 'success', 'Pending' => 'warning', 'Failed' => 'danger'];
                $label = $statusMap[$tx->status] ?? 'default';
                $pesapalId = e($tx->pesapal_tracking_id ?? '-');
                $conf = e($tx->confirmation_code ?? '-');
                $html .= "<tr>";
                $html .= "<td><a href='{$transBase}/{$tx->id}'>{$tx->id}</a></td>";
                $html .= "<td>{$tx->transaction_type}</td>";
                $html .= "<td><b>" . ($tx->currency ?? 'UGX') . " " . number_format($tx->amount, 0) . "</b></td>";
                $html .= "<td><span class='label label-{$label}'>{$tx->status}</span></td>";
                $html .= "<td><small>{$pesapalId}</small></td>";
                $html .= "<td><small>{$conf}</small></td>";
                $html .= "<td>" . Carbon::parse($tx->created_at)->format('Y-m-d H:i') . "</td>";
                $html .= "</tr>";
            }
            $html .= '</table>';
            return $html;
        })->unescape();

        // ── Admin Actions Panel
        $show->divider('Admin Actions');
        $actionBase = $prefix ? "/{$prefix}/api/subscriptions/{$id}/action" : "/api/subscriptions/{$id}/action";
        $token = csrf_token();
        $show->field('id', 'Actions')->as(function () use ($subscription, $actionBase, $token) {
            $status = $subscription->status;
            $payStatus = $subscription->payment_status;
            $hasTracking = !empty($subscription->pesapal_tracking_id);

            $html = '<div style="display:flex;flex-wrap:wrap;gap:8px;margin:8px 0">';

            // Recheck Payment (if has tracking ID and not completed)
            if ($hasTracking && $payStatus !== 'Completed') {
                $html .= "<button class='btn btn-info btn-sm sub-action-btn' data-action='recheck-payment'><i class='fa fa-refresh'></i> Recheck Pesapal</button>";
            }

            // Activate (if not active)
            if ($status !== 'Active') {
                $html .= "<button class='btn btn-success btn-sm sub-action-btn' data-action='activate'><i class='fa fa-check'></i> Force Activate</button>";
            }

            // Extend
            $html .= "<button class='btn btn-primary btn-sm sub-action-btn' data-action='extend' data-prompt='days'><i class='fa fa-calendar-plus-o'></i> Extend</button>";

            // Mark Expired
            if ($status === 'Active') {
                $html .= "<button class='btn btn-warning btn-sm sub-action-btn' data-action='mark-expired'><i class='fa fa-clock-o'></i> Mark Expired</button>";
            }

            // Cancel
            if ($status !== 'Cancelled') {
                $html .= "<button class='btn btn-danger btn-sm sub-action-btn' data-action='cancel' data-prompt='reason'><i class='fa fa-ban'></i> Cancel</button>";
            }

            // Grant Free
            $html .= "<button class='btn btn-default btn-sm sub-action-btn' data-action='grant-free' data-prompt='days'><i class='fa fa-gift'></i> Grant Free Days</button>";

            $html .= '</div>';

            // JS for action buttons
            $html .= "<script>
$(function(){
    $('.sub-action-btn').off('click').on('click', function(){
        var btn = $(this);
        var action = btn.data('action');
        var promptType = btn.data('prompt');
        var extraData = {};

        if(promptType === 'days'){
            var days = prompt('Enter number of days:');
            if(!days || isNaN(days) || parseInt(days) <= 0){ return; }
            extraData.days = parseInt(days);
        } else if(promptType === 'reason'){
            var reason = prompt('Enter reason:');
            if(reason === null) return;
            extraData.reason = reason;
        }

        if(!confirm('Are you sure you want to: ' + action + '?')) return;

        btn.prop('disabled', true).html('<i class=\"fa fa-spinner fa-spin\"></i> Processing...');

        $.ajax({
            url: '{$actionBase}',
            type: 'POST',
            data: Object.assign({action: action, _token: '{$token}'}, extraData),
            success: function(res){
                if(res.success){
                    toastr.success(res.message || 'Action completed');
                    setTimeout(function(){ location.reload(); }, 1000);
                } else {
                    toastr.error(res.message || 'Action failed');
                    btn.prop('disabled', false).html(btn.html());
                }
            },
            error: function(xhr){
                var msg = 'Error';
                try { msg = JSON.parse(xhr.responseText).message || msg; } catch(e){}
                toastr.error(msg);
                btn.prop('disabled', false);
            }
        });
    });
});
</script>";

            return $html;
        })->unescape();

        return $show;
    }

    // ─── FORM ───────────────────────────────────────────────────────────
    protected function form()
    {
        $form = new Form(new Subscription());
        $userSearchUrl = admin_url('api/users');
        $planOptions = SubscriptionPlan::query()
            ->orderBy('sort_order')
            ->orderBy('price')
            ->get()
            ->mapWithKeys(function (SubscriptionPlan $plan) {
                $price = $plan->currency ? $plan->currency . ' ' . number_format((float) $plan->getActualPrice(), 0) : number_format((float) $plan->getActualPrice(), 0);
                return [
                    $plan->id => sprintf('%s - %s - %s days', $plan->name, $price, (int) $plan->duration_days),
                ];
            })
            ->all();

        $form->divider('Subscriber & Plan');

        $form->select('user_id', 'User')
            ->options(function ($id) {
                $user = User::find($id);
                if ($user) {
                    return [$user->id => $user->name . ' (' . $user->email . ')'];
                }

                return [];
            })
            ->ajax($userSearchUrl)
            ->rules('required')
            ->help('Required. Search by name, email, phone number, or user ID.');

        $form->select('plan_id', 'Plan')
            ->options($planOptions)
            ->rules('required')
            ->help('Required. Duration and recommended price are shown in the option label.');

        $form->hidden('days');
        $form->hidden('payment_method');
        $form->hidden('payment_confirmed_at');
        $form->hidden('failed_at');

        $form->decimal('amount_paid', 'Amount Paid')
            ->rules('nullable|numeric|min:0')
            ->help('Optional. Leave empty to use the selected plan price automatically.');

        $form->text('currency', 'Currency')
            ->default('UGX')
            ->help('Optional. Leave empty to use the plan currency or UGX.');

        $form->divider('Status');

        $form->radio('status', 'Status')->options([
            'Pending' => 'Pending', 'Active' => 'Active',
            'Expired' => 'Expired', 'Cancelled' => 'Cancelled', 'Failed' => 'Failed',
        ])->default('Active')->rules('required');

        $form->radio('payment_status', 'Payment Status')->options([
            'Pending' => 'Pending', 'Processing' => 'Processing',
            'Completed' => 'Completed', 'Failed' => 'Failed', 'Refunded' => 'Refunded',
        ])->default('Completed')->rules('required');

        $form->textarea('payment_failure_reason', 'Failure Reason')
            ->rows(2)
            ->help('Optional. Only fill this when the payment status is Failed.');

        $form->divider('Optional Overrides');

        $form->select('app_type', 'App Type')->options([
            'ugflix' => 'UGFlix', 'lugaflix' => 'LugaFlix', 'muno_app' => 'Muno App', 'web' => 'Web',
        ])->help('Optional. Leave empty to use the selected user\'s app.');

        $form->select('platform', 'Platform')->options([
            'android' => 'Android', 'ios' => 'iOS',
        ])->help('Optional. Leave empty to use the selected user\'s platform.');

        $form->datetime('start_date_time', 'Start Date')
            ->help('Optional. Leave empty and the system will set it when the subscription becomes active.');

        $form->datetime('end_date_time', 'End Date')
            ->help('Optional. Leave empty and the system will calculate it from the selected plan.');

        $form->text('pesapal_tracking_id', 'Pesapal Tracking ID')
            ->help('Optional. Only fill this if you already have an external payment tracking ID.');

        $form->text('pesapal_merchant_reference', 'Merchant Reference')
            ->help('Optional. Leave empty to auto-generate a reference.');

        $form->saving(function (Form $form) {
            $subscription = $form->model();
            $user = $form->user_id ? User::find($form->user_id) : null;
            $plan = $form->plan_id ? SubscriptionPlan::find($form->plan_id) : null;
            $resolvedDays = !empty($form->days) ? (int) $form->days : (int) ($plan->duration_days ?? $subscription->days ?? 0);

            if ($resolvedDays > 0) {
                $form->days = $resolvedDays;
                $subscription->days = $resolvedDays;
            }

            if (empty($form->amount_paid) && $plan) {
                $form->amount_paid = $plan->getActualPrice();
            }

            if (empty($form->currency)) {
                $form->currency = $plan->currency ?? 'UGX';
            }

            if (empty($form->app_type) && $user && !empty($user->app_type)) {
                $form->app_type = $user->app_type;
            }

            if (empty($form->platform) && $user && !empty($user->platform)) {
                $form->platform = $user->platform;
            }

            if (empty($form->pesapal_merchant_reference)) {
                $prefix = 'SUB';
                if (!empty($form->app_type)) {
                    $prefix = match ((string) $form->app_type) {
                        'lugaflix' => 'LUG',
                        'muno_app' => 'MUN',
                        'web' => 'WEB',
                        default => 'SUB',
                    };
                } elseif (!empty($subscription->pesapal_merchant_reference)) {
                    $form->pesapal_merchant_reference = $subscription->pesapal_merchant_reference;
                }

                if (empty($form->pesapal_merchant_reference) && !empty($form->user_id)) {
                    $form->pesapal_merchant_reference = $prefix . '-' . $form->user_id . '-' . time();
                }
            }

            if ($form->status === 'Active' && empty($form->start_date_time)) {
                $form->start_date_time = Carbon::now();
            }

            if ($form->status === 'Active' && empty($form->end_date_time) && $plan) {
                $start = $form->start_date_time ? Carbon::parse($form->start_date_time) : Carbon::now();
                $form->end_date_time = $start->copy()->addDays((int) $plan->duration_days);
            }

            if (empty($form->payment_method)) {
                $form->payment_method = 'admin_form';
            }

            $subscription->payment_method = $form->payment_method;

            if ($form->payment_status === 'Completed' && empty($form->payment_confirmed_at)) {
                $form->payment_confirmed_at = Carbon::now();
                $form->payment_failure_reason = null;
                $form->failed_at = null;
            }

            if (!empty($form->payment_confirmed_at)) {
                $subscription->payment_confirmed_at = $form->payment_confirmed_at;
            }

            if (in_array($form->payment_status, ['Failed', 'Refunded'], true) && empty($form->failed_at)) {
                $form->failed_at = Carbon::now();
            }

            $subscription->failed_at = $form->failed_at ?: null;

            if ($form->payment_status !== 'Failed') {
                $form->payment_failure_reason = null;
            }
        });

        $form->saved(function (Form $form) {
            /** @var Subscription $subscription */
            $subscription = $form->model()->fresh();

            if (!$subscription) {
                return;
            }

            DB::transaction(function () use ($subscription) {
                $this->syncAdminFormTransaction($subscription);
            });
        });

        return $form;
    }

    // ─── ADMIN ACTION HANDLER ───────────────────────────────────────────
    public function adminAction(Request $request, $id)
    {
        $subscription = Subscription::findOrFail($id);
        $action = $request->input('action');

        Log::info("Admin subscription action: {$action}", [
            'subscription_id' => $id,
            'admin' => \Encore\Admin\Facades\Admin::user()->name ?? 'Unknown',
        ]);

        switch ($action) {
            case 'recheck-payment':
                if (!$subscription->pesapal_tracking_id) {
                    return response()->json(['success' => false, 'message' => 'No Pesapal tracking ID on this subscription.']);
                }
                $checker = app(PaymentStatusChecker::class);
                $result = $checker->forceVerifyPayment($subscription);
                if ($result['success'] ?? false) {
                    $subscription->refresh();
                    return response()->json([
                        'success' => true,
                        'message' => "Pesapal status rechecked. Payment: {$subscription->payment_status}, Status: {$subscription->status}",
                    ]);
                }
                return response()->json([
                    'success' => false,
                    'message' => 'Pesapal recheck failed: ' . ($result['error'] ?? 'Unknown error'),
                ]);

            case 'activate':
                $activationResult = DB::transaction(function () use ($subscription) {
                    /** @var SubscriptionActivationService $activationService */
                    $activationService = app(SubscriptionActivationService::class);
                    $result = $activationService->activatePaidSubscriptionWithAudit(
                        $subscription,
                        'admin_activate'
                    );

                    /** @var Subscription $activated */
                    $activated = $result['subscription'];
                    $audit = $result['audit'];

                    $this->syncCompletedAdminTransaction($activated, 'admin_activate');

                    return [
                        'activated' => $activated,
                        'audit' => $audit,
                    ];
                });

                /** @var Subscription $activated */
                $activated = $activationResult['activated'];
                $audit = $activationResult['audit'];

                Cache::forget("sub_pending_{$subscription->user_id}");
                Cache::forget("active_sub_{$subscription->user_id}");
                Cache::forget("v2_pay_check_{$subscription->user_id}");

                return response()->json([
                    'success' => true,
                    'message' => 'Subscription activated successfully.',
                    'audit' => $audit,
                    'steps' => [
                        'Anchor source: ' . ($audit['anchor_source'] ?? 'unknown'),
                        'Stacking used: ' . (($audit['used_stacking'] ?? false) ? 'yes' : 'no'),
                        'Start: ' . ($activated->start_date_time?->toIso8601String() ?? '-'),
                        'End: ' . ($activated->end_date_time?->toIso8601String() ?? '-'),
                    ],
                ]);

            case 'extend':
                $days = (int)$request->input('days', 0);
                if ($days <= 0) {
                    return response()->json(['success' => false, 'message' => 'Days must be greater than 0.']);
                }
                $currentEnd = $subscription->end_date_time
                    ? Carbon::parse($subscription->end_date_time)
                    : Carbon::now();
                // If already expired, extend from now
                if ($currentEnd->isPast()) {
                    $currentEnd = Carbon::now();
                }
                $subscription->end_date_time = $currentEnd->addDays($days);
                $subscription->status = 'Active';
                $subscription->days = ($subscription->days ?? 0) + $days;
                $subscription->save();
                return response()->json([
                    'success' => true,
                    'message' => "Extended by {$days} days. New end: {$subscription->end_date_time}",
                ]);

            case 'mark-expired':
                $subscription->status = 'Expired';
                $subscription->save();
                return response()->json(['success' => true, 'message' => 'Subscription marked as expired.']);

            case 'cancel':
                $reason = $request->input('reason', 'Cancelled by admin');
                $subscription->status = 'Cancelled';
                $subscription->cancelled_at = Carbon::now();
                $subscription->cancelled_reason = $reason;
                $subscription->cancelled_by = \Encore\Admin\Facades\Admin::user()->id ?? null;
                $subscription->save();
                return response()->json(['success' => true, 'message' => 'Subscription cancelled.']);

            case 'grant-free':
                $days = (int)$request->input('days', 0);
                if ($days <= 0) {
                    return response()->json(['success' => false, 'message' => 'Days must be greater than 0.']);
                }

                DB::transaction(function () use ($subscription, $days) {
                    $subscription->status = 'Active';
                    $subscription->payment_status = 'Completed';
                    $subscription->payment_method = 'admin_grant';
                    $subscription->start_date_time = Carbon::now();
                    $subscription->end_date_time = Carbon::now()->addDays($days);
                    $subscription->days = $days;
                    $subscription->payment_confirmed_at = Carbon::now();
                    $subscription->save();

                    $this->syncCompletedAdminTransaction($subscription, 'admin_grant');
                });

                Cache::forget("sub_pending_{$subscription->user_id}");
                Cache::forget("active_sub_{$subscription->user_id}");
                Cache::forget("v2_pay_check_{$subscription->user_id}");

                return response()->json([
                    'success' => true,
                    'message' => "Granted {$days} free days. Active until {$subscription->end_date_time}",
                ]);

            default:
                return response()->json(['success' => false, 'message' => 'Unknown action: ' . $action]);
        }
    }

    // ─── HELPERS ────────────────────────────────────────────────────────
    private function syncCompletedAdminTransaction(Subscription $subscription, string $source): void
    {
        $transaction = SubscriptionTransaction::query()
            ->where('subscription_id', $subscription->id)
            ->where('transaction_type', '!=', 'Withdrawal')
            ->orderByDesc('id')
            ->first();

        $payload = [
            'admin_completion' => [
                'source' => $source,
                'synced_at' => Carbon::now()->toIso8601String(),
                'admin_id' => Admin::user()->id ?? null,
            ],
        ];

        if ($transaction) {
            $transaction->status = 'Completed';
            $transaction->payment_method = $subscription->payment_method ?: $transaction->payment_method;
            $transaction->pesapal_tracking_id = $subscription->pesapal_tracking_id ?: $transaction->pesapal_tracking_id;
            $transaction->merchant_reference = $subscription->pesapal_merchant_reference ?: $transaction->merchant_reference;
            $transaction->error_message = null;
            $transaction->response_payload = array_merge($transaction->response_payload ?? [], $payload);
            $transaction->save();
            return;
        }

        SubscriptionTransaction::create([
            'subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
            'transaction_type' => $subscription->is_extension ? 'Renewal' : 'Initial',
            'platform' => $subscription->app_type,
            'amount' => $subscription->amount_paid,
            'currency' => $subscription->currency,
            'status' => 'Completed',
            'pesapal_tracking_id' => $subscription->pesapal_tracking_id,
            'merchant_reference' => $subscription->pesapal_merchant_reference,
            'payment_method' => $subscription->payment_method,
            'response_payload' => $payload,
        ]);
    }

    private function syncAdminFormTransaction(Subscription $subscription): void
    {
        $transaction = SubscriptionTransaction::query()
            ->where('subscription_id', $subscription->id)
            ->where('transaction_type', '!=', 'Withdrawal')
            ->orderByDesc('id')
            ->first();

        $payload = [
            'admin_form_sync' => [
                'synced_at' => Carbon::now()->toIso8601String(),
                'admin_id' => Admin::user()->id ?? null,
                'subscription_status' => (string) ($subscription->status ?? ''),
                'payment_status' => (string) ($subscription->payment_status ?? ''),
            ],
        ];

        $txStatus = $this->mapSubscriptionPaymentStatusToTransactionStatus((string) $subscription->payment_status);

        $attributes = [
            'user_id' => $subscription->user_id,
            'transaction_type' => $subscription->is_extension ? 'Renewal' : 'Initial',
            'platform' => $subscription->app_type,
            'amount' => $subscription->amount_paid,
            'currency' => $subscription->currency,
            'status' => $txStatus,
            'pesapal_tracking_id' => $subscription->pesapal_tracking_id,
            'merchant_reference' => $subscription->pesapal_merchant_reference,
            'payment_method' => $subscription->payment_method ?: 'admin_form',
            'error_message' => $txStatus === 'Failed' ? ($subscription->payment_failure_reason ?: 'Marked failed from admin form') : null,
        ];

        if ($transaction) {
            $transaction->fill($attributes);
            $transaction->response_payload = array_merge($transaction->response_payload ?? [], $payload);
            $transaction->save();
            return;
        }

        SubscriptionTransaction::create(array_merge($attributes, [
            'subscription_id' => $subscription->id,
            'response_payload' => $payload,
        ]));
    }

    private function mapSubscriptionPaymentStatusToTransactionStatus(string $paymentStatus): string
    {
        return match ($paymentStatus) {
            'Completed' => 'Completed',
            'Processing' => 'Processing',
            'Failed' => 'Failed',
            'Refunded' => 'Refunded',
            default => 'Pending',
        };
    }

    private function fmt($num)
    {
        return number_format($num);
    }

    // ─── DEBUG / FIX LAB ────────────────────────────────────────────────

    public function debugInspect(Request $request)
    {
        try {
            $transactionId   = $request->input('transaction_id');
            $referenceRaw    = (string) $request->input('reference', '');   // what the caller sent
            $reference       = $referenceRaw;
            $gateway         = (string) $request->input('gateway', 'auto');
            $subscriptionId  = $request->input('subscription_id');

            // Track whether the caller explicitly asked for a gateway call:
            // Only call gateway when a transaction_id or non-empty reference was supplied.
            $callerWantsGateway = ($transactionId !== null && $transactionId !== '') || $referenceRaw !== '';

            // If a specific transaction ID is given, derive reference + gateway from it
            if ($transactionId) {
                $tx = SubscriptionTransaction::find((int) $transactionId);
                if ($tx) {
                    if ($reference === '') {
                        $reference = (string) ($tx->pesapal_tracking_id ?: $tx->merchant_reference ?: '');
                    }
                    if ($gateway === 'auto') {
                        $gwHint  = strtolower((string) ($tx->payment_method ?? ''));
                        $gateway = str_contains($gwHint, 'flutter') ? 'flutterwave' : 'pesapal';
                    }
                    if (!$subscriptionId) {
                        $subscriptionId = $tx->subscription_id;
                    }
                }
            }

            $ctx = $this->resolveSubDebugContext($subscriptionId, $reference, $gateway);

            // Fetch all transactions linked to this subscription
            $allTransactions = $this->subBuildAllTransactions($ctx['subscription']);

            // Only call gateway when the caller explicitly provided a tx_id or reference.
            // Never auto-call gateway on the initial "list-only" load (avoids timeout).
            $raw        = null;
            $normalized = null;
            if ($callerWantsGateway && $ctx['reference'] !== '') {
                $raw        = $this->subFetchGatewayRaw($ctx['gateway'], $ctx['reference']);
                $normalized = $this->subNormalizeGateway($ctx['gateway'], $raw);
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'gateway'             => $ctx['gateway'],
                    'reference'           => $ctx['reference'],
                    'transaction_id'      => $transactionId ? (int) $transactionId : null,
                    'subscription_id'     => $ctx['subscription']?->id,
                    'subscription_status' => $ctx['subscription']?->status,
                    'payment_status'      => $ctx['subscription']?->payment_status,
                    'subscription'        => $this->subBuildSubscriptionSnippet($ctx['subscription']),
                    'user'                => $this->subBuildUserSnippet($ctx['subscription']),
                    'all_transactions'    => $allTransactions,
                    'normalized'          => $normalized,
                    'raw_gateway_response' => $raw,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Subscription debugInspect failed', [
                'error'           => $e->getMessage(),
                'subscription_id' => $request->input('subscription_id'),
                'transaction_id'  => $request->input('transaction_id'),
                'reference'       => $request->input('reference'),
            ]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function debugApplyFix(Request $request)
    {
        $action        = (string) $request->input('action', '');
        $transactionId = $request->input('transaction_id');
        $reference     = (string) $request->input('reference', '');
        $gateway       = (string) $request->input('gateway', 'auto');
        $subscriptionId = $request->input('subscription_id');

        // If transaction_id given, resolve reference/gateway/subscription from that tx
        if ($transactionId) {
            $txRow = SubscriptionTransaction::find((int) $transactionId);
            if ($txRow) {
                if ($reference === '') {
                    $reference = (string) ($txRow->pesapal_tracking_id ?: $txRow->merchant_reference ?: '');
                }
                if ($gateway === 'auto') {
                    $gwHint  = strtolower((string) ($txRow->payment_method ?? ''));
                    $gateway = str_contains($gwHint, 'flutter') ? 'flutterwave' : 'pesapal';
                }
                if (!$subscriptionId) {
                    $subscriptionId = $txRow->subscription_id;
                }
            }
        }

        try {
            $ctx = $this->resolveSubDebugContext($subscriptionId, $reference, $gateway);

            $subscription = $ctx['subscription'];
            if (!$subscription && in_array($action, ['force_verify', 'force_activate'], true)) {
                throw new \RuntimeException('No subscription found for this reference/ID.');
            }

            $raw = null;

            switch ($action) {
                case 'force_verify':
                    $raw = $this->subRunForceVerify($ctx['gateway'], $subscription, $ctx['reference']);
                    if (!$raw) {
                        $raw = $this->subFetchGatewayRaw($ctx['gateway'], $ctx['reference']);
                    }
                    break;

                case 'force_activate':
                    /** @var SubscriptionActivationService $svc */
                    $svc       = app(SubscriptionActivationService::class);
                    $activated = $svc->activatePaidSubscription($subscription, 'admin_debug_force_activate');
                    $activated->payment_status        = 'Completed';
                    $activated->payment_confirmed_at  = $activated->payment_confirmed_at ?: now();
                    $activated->save();
                    $this->syncCompletedAdminTransaction($activated, 'admin_debug_force_activate');
                    Cache::forget("sub_pending_{$subscription->user_id}");
                    Cache::forget("active_sub_{$subscription->user_id}");
                    $subscription = $activated;
                    break;

                case 'mark_payment_completed':
                    if (!$subscription) throw new \RuntimeException('Subscription not found.');
                    $subscription->payment_status       = 'Completed';
                    $subscription->payment_confirmed_at = $subscription->payment_confirmed_at ?: now();
                    $subscription->save();
                    $this->syncCompletedAdminTransaction($subscription, 'admin_debug_mark_completed');
                    // Also update the specific transaction record if provided
                    if ($transactionId) {
                        SubscriptionTransaction::where('id', (int) $transactionId)->update([
                            'status'         => 'Completed',
                            'is_fixed'       => true,
                            'fix_time'       => now(),
                            'fix_successful' => 'Yes',
                        ]);
                    }
                    break;

                case 'mark_payment_failed':
                    if (!$subscription) throw new \RuntimeException('Subscription not found.');
                    $subscription->payment_status = 'Failed';
                    $subscription->save();
                    if ($transactionId) {
                        SubscriptionTransaction::where('id', (int) $transactionId)->update(['status' => 'Failed']);
                    }
                    break;

                case 'mark_subscription_active':
                    if (!$subscription) throw new \RuntimeException('Subscription not found.');
                    $subscription->status = 'Active';
                    $subscription->save();
                    break;

                case 'mark_subscription_expired':
                    if (!$subscription) throw new \RuntimeException('Subscription not found.');
                    $subscription->status = 'Expired';
                    $subscription->save();
                    break;

                case 'mark_subscription_cancelled':
                    if (!$subscription) throw new \RuntimeException('Subscription not found.');
                    $subscription->status = 'Cancelled';
                    $subscription->save();
                    break;

                case 'extend_subscription':
                    if (!$subscription) throw new \RuntimeException('Subscription not found.');
                    $days = max(1, (int) $request->input('days', 30));
                    $base = $subscription->end_date_time ? Carbon::parse($subscription->end_date_time) : Carbon::now();
                    if ($base->isPast()) $base = Carbon::now();
                    $subscription->end_date_time = $base->addDays($days);
                    $subscription->days          = ($subscription->days ?? 0) + $days;
                    $subscription->status        = 'Active';
                    $subscription->save();
                    break;

                default:
                    throw new \RuntimeException('Unknown fix action: ' . $action);
            }

            $subscription?->refresh();
            $normalized = $raw ? $this->subNormalizeGateway($ctx['gateway'], $raw) : null;

            return response()->json([
                'success' => true,
                'message' => 'Action executed: ' . $action,
                'data'    => [
                    'gateway'             => $ctx['gateway'],
                    'reference'           => $ctx['reference'],
                    'subscription_id'     => $subscription?->id,
                    'subscription_status' => $subscription?->status,
                    'payment_status'      => $subscription?->payment_status,
                    'subscription'        => $this->subBuildSubscriptionSnippet($subscription),
                    'user'                => $this->subBuildUserSnippet($subscription),
                    'all_transactions'    => $this->subBuildAllTransactions($subscription),
                    'normalized'          => $normalized,
                    'raw_gateway_response' => $raw,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Subscription debugApplyFix failed', [
                'error'           => $e->getMessage(),
                'action'          => $action,
                'subscription_id' => $subscriptionId,
                'transaction_id'  => $transactionId,
                'reference'       => $reference,
            ]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function batchFixSingle(Request $request)
    {
        $subId = (int) $request->input('subscription_id');

        if (!$subId) {
            return response()->json([
                'success'         => false,
                'subscription_id' => $subId,
                'message'         => 'Invalid subscription ID',
                'result'          => 'error',
            ], 200);
        }

        try {
            $subscription = Subscription::with(['plan', 'user'])->find($subId);
            if (!$subscription) {
                return response()->json([
                    'success'         => false,
                    'subscription_id' => $subId,
                    'message'         => 'Subscription not found',
                    'result'          => 'error',
                ], 200);
            }

            // Resolve payment reference
            $reference = (string) ($subscription->pesapal_tracking_id
                ?: $subscription->flutterwave_reference
                ?: $subscription->pesapal_merchant_reference
                ?: '');

            if ($reference === '') {
                return response()->json([
                    'success'         => false,
                    'subscription_id' => $subId,
                    'message'         => 'No payment reference on subscription',
                    'result'          => 'no_reference',
                ], 200);
            }

            // Snapshot before
            $subBefore = [
                'id'              => $subscription->id,
                'status'          => $subscription->status,
                'payment_status'  => $subscription->payment_status,
                'start_date_time' => $subscription->start_date_time?->toDateTimeString(),
                'end_date_time'   => $subscription->end_date_time?->toDateTimeString(),
                'plan'            => $subscription->plan->name ?? null,
                'days'            => $subscription->days,
                'app_type'        => $subscription->app_type,
            ];

            $gateway    = $this->subResolveGateway('auto', $subscription, $reference);
            $raw        = $this->subFetchGatewayRaw($gateway, $reference);
            $normalized = $this->subNormalizeGateway($gateway, $raw);
            $rawJson    = mb_substr(json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), 0, 65535);

            $fixSuccessful = 'No';
            $resultLabel   = 'unchanged';

            switch ($normalized['status_normalized']) {
                case 'completed':
                    /** @var SubscriptionActivationService $svc */
                    $svc    = app(SubscriptionActivationService::class);
                    $result = $svc->activatePaidSubscriptionWithAudit($subscription, 'batch_fix', ['force_start_now' => true]);
                    $subscription = $result['subscription'];
                    $subscription->payment_status       = 'Completed';
                    $subscription->payment_confirmed_at = $subscription->payment_confirmed_at ?: now();
                    $subscription->save();
                    $this->syncCompletedAdminTransaction($subscription, 'batch_fix');
                    Cache::forget("sub_pending_{$subscription->user_id}");
                    Cache::forget("active_sub_{$subscription->user_id}");
                    $fixSuccessful = 'Yes';
                    $resultLabel   = 'activated';
                    break;

                case 'failed':
                    if ($subscription->status === 'Pending') {
                        $subscription->status         = 'Failed';
                        $subscription->payment_status = 'Failed';
                        $subscription->failed_at      = $subscription->failed_at ?: now();
                        $subscription->payment_failure_reason = $normalized['message'] ?? 'Gateway confirmed failed';
                        $subscription->save();
                    }
                    $fixSuccessful = 'No';
                    $resultLabel   = 'confirmed_failed';
                    break;

                default:
                    $fixSuccessful = 'No';
                    $resultLabel   = 'still_pending';
                    break;
            }

            $subscription->refresh();
            $subAfter = [
                'id'              => $subscription->id,
                'status'          => $subscription->status,
                'payment_status'  => $subscription->payment_status,
                'start_date_time' => $subscription->start_date_time?->toDateTimeString(),
                'end_date_time'   => $subscription->end_date_time?->toDateTimeString(),
                'plan'            => $subscription->plan->name ?? null,
                'days'            => $subscription->days,
            ];

            return response()->json([
                'success'             => true,
                'subscription_id'     => $subId,
                'result'              => $resultLabel,
                'fix_successful'      => $fixSuccessful,
                'gateway'             => $gateway,
                'reference'           => $reference,
                'normalized_status'   => $normalized['status_normalized'],
                'message'             => "[{$gateway}] {$resultLabel} — " . ($normalized['status_raw'] ?? ''),
                'subscription_before' => $subBefore,
                'subscription_after'  => $subAfter,
                'raw_gateway_response' => $raw,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Subscription batchFixSingle failed', ['sub_id' => $subId, 'error' => $e->getMessage()]);
            return response()->json([
                'success'         => false,
                'subscription_id' => $subId,
                'message'         => $e->getMessage(),
                'result'          => 'error',
                'fix_successful'  => 'No',
            ], 200);
        }
    }

    private function resolveSubDebugContext($subscriptionId, string $reference, string $gateway): array
    {
        $subscription = null;

        if (!empty($subscriptionId)) {
            $subscription = Subscription::with(['user', 'plan'])->find((int) $subscriptionId);
            // Only auto-fill reference from subscription if caller didn't supply one
            if ($subscription && $reference === '') {
                $reference = (string) ($subscription->pesapal_tracking_id
                    ?: $subscription->flutterwave_reference
                    ?: $subscription->pesapal_merchant_reference
                    ?: '');
            }
        }

        if ($reference !== '' && !$subscription) {
            $subscription = Subscription::with(['user', 'plan'])
                ->where('pesapal_tracking_id', $reference)
                ->orWhere('pesapal_merchant_reference', $reference)
                ->orWhere('flutterwave_reference', $reference)
                ->first();
        }

        if ($reference === '' && !$subscription) {
            throw new \RuntimeException('Provide a subscription ID or payment reference.');
        }

        // Allow empty reference (subscription found but no reference yet) — caller skips gateway call
        $resolvedGateway = $this->subResolveGateway($gateway, $subscription, $reference);

        return [
            'gateway'      => $resolvedGateway,
            'reference'    => $reference,
            'subscription' => $subscription,
        ];
    }

    private function subResolveGateway(string $gateway, ?Subscription $subscription, string $reference): string
    {
        $candidate = strtolower(trim($gateway));
        if (in_array($candidate, ['pesapal', 'flutterwave'], true)) {
            return $candidate;
        }

        $gw = strtolower((string) ($subscription?->payment_gateway ?: $subscription?->payment_method ?: ''));
        if (str_contains($gw, 'flutter')) return 'flutterwave';
        if (str_contains($gw, 'pesapal')) return 'pesapal';
        if (str_starts_with(strtolower($reference), 'flw')) return 'flutterwave';

        return 'pesapal';
    }

    private function subFetchGatewayRaw(string $gateway, string $reference): array
    {
        if ($gateway === 'flutterwave') {
            return app(SubscriptionFlutterwaveService::class)->verifyByReference($reference);
        }
        return app(SubscriptionPesapalService::class)->getTransactionStatus($reference);
    }

    private function subRunForceVerify(string $gateway, Subscription $subscription, string $reference): array
    {
        if ($gateway === 'flutterwave') {
            $ref = $subscription->flutterwave_reference ?: $subscription->pesapal_merchant_reference ?: $reference;
            return app(SubscriptionFlutterwaveService::class)->processCallback($ref);
        }
        return app(PaymentStatusChecker::class)->forceVerifyPayment($subscription);
    }

    private function subNormalizeGateway(string $gateway, array $raw): array
    {
        if ($gateway === 'flutterwave') {
            $body      = $raw['data'] ?? $raw;
            $statusRaw = strtolower((string) ($body['status'] ?? $raw['status'] ?? 'unknown'));
            $mapped    = in_array($statusRaw, ['successful', 'success', 'completed'], true) ? 'completed'
                : (in_array($statusRaw, ['failed', 'cancelled', 'canceled', 'error'], true) ? 'failed' : 'pending');
            return [
                'gateway'            => 'flutterwave',
                'status_raw'         => $statusRaw,
                'status_normalized'  => $mapped,
                'amount'             => $body['amount'] ?? null,
                'currency'           => $body['currency'] ?? null,
                'message'            => (string) ($raw['message'] ?? $body['processor_response'] ?? ''),
                'error_code'         => (string) ($raw['code'] ?? ''),
                'gateway_reference'  => (string) ($body['tx_ref'] ?? ''),
                'tracking_reference' => (string) ($body['flw_ref'] ?? ''),
            ];
        }

        $body       = $raw['data'] ?? [];
        $statusCode = (int) ($body['status_code'] ?? -1);
        $statusDesc = strtoupper((string) ($body['payment_status_description'] ?? $body['status'] ?? 'UNKNOWN'));
        $mapped     = ($statusCode === 1 || $statusDesc === 'COMPLETED') ? 'completed'
            : (($statusDesc === 'INVALID' || $statusCode === 0) ? 'pending' : 'failed');

        return [
            'gateway'            => 'pesapal',
            'status_raw'         => $statusDesc,
            'status_normalized'  => $mapped,
            'amount'             => $body['amount'] ?? null,
            'currency'           => $body['currency'] ?? null,
            'message'            => (string) (($body['error']['message'] ?? null) ?: ($body['message'] ?? '')),
            'error_code'         => (string) (($body['error']['code'] ?? null) ?: ($body['status_code'] ?? '')),
            'gateway_reference'  => (string) ($body['order_tracking_id'] ?? ''),
            'tracking_reference' => (string) ($body['order_tracking_id'] ?? ''),
        ];
    }

    private function subBuildSubscriptionSnippet(?Subscription $subscription): ?array
    {
        if (!$subscription) return null;
        return [
            'id'                   => $subscription->id,
            'status'               => (string) ($subscription->status ?? ''),
            'payment_status'       => (string) ($subscription->payment_status ?? ''),
            'app_type'             => (string) ($subscription->app_type ?? ''),
            'platform'             => (string) ($subscription->platform ?? ''),
            'plan'                 => (string) ($subscription->plan?->name ?? ''),
            'amount_paid'          => $subscription->amount_paid,
            'currency'             => (string) ($subscription->currency ?? 'UGX'),
            'start_date_time'      => optional($subscription->start_date_time)->toDateTimeString(),
            'end_date_time'        => optional($subscription->end_date_time)->toDateTimeString(),
            'days'                 => $subscription->days,
            'pesapal_tracking_id'  => (string) ($subscription->pesapal_tracking_id ?? ''),
            'merchant_reference'   => (string) ($subscription->pesapal_merchant_reference ?? ''),
            'flutterwave_reference' => (string) ($subscription->flutterwave_reference ?? ''),
            'payment_gateway'      => (string) ($subscription->payment_gateway ?? $subscription->payment_method ?? ''),
        ];
    }

    private function subBuildUserSnippet(?Subscription $subscription): ?array
    {
        $user = $subscription?->user;
        if (!$user && $subscription?->user_id) {
            $user = User::find((int) $subscription->user_id);
        }
        if (!$user) return null;
        return [
            'id'            => $user->id,
            'name'          => (string) ($user->name ?? ''),
            'email'         => (string) ($user->email ?? ''),
            'phone_number'  => (string) ($user->phone_number ?? ''),
            'app_type'      => (string) ($user->app_type ?? ''),
            'account_state' => (string) ($user->account_state ?? ''),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Admin Fix Lab: Initiate a new payment for a subscription
    //  POST api/subscriptions/debug/initiate-payment
    //  Body: { subscription_id, phone, gateway }
    // ─────────────────────────────────────────────────────────────────────────
    public function debugInitiatePayment(Request $request)
    {
        $subscriptionId = $request->input('subscription_id');
        $phone          = trim((string) $request->input('phone', ''));
        $gateway        = strtolower(trim((string) $request->input('gateway', 'pesapal')));

        if (!$subscriptionId) {
            return response()->json(['success' => false, 'message' => 'subscription_id is required.'], 422);
        }
        if ($phone === '') {
            return response()->json(['success' => false, 'message' => 'Phone number is required.'], 422);
        }
        if (!in_array($gateway, ['pesapal', 'flutterwave'], true)) {
            return response()->json(['success' => false, 'message' => 'Gateway must be pesapal or flutterwave.'], 422);
        }

        try {
            $subscription = Subscription::with(['user', 'plan'])->find((int) $subscriptionId);
            if (!$subscription) {
                throw new \RuntimeException('Subscription #' . $subscriptionId . ' not found.');
            }
            if (!$subscription->user) {
                throw new \RuntimeException('Subscription has no associated user.');
            }
            if (!$subscription->plan) {
                throw new \RuntimeException('Subscription has no associated plan.');
            }

            // Ensure merchant reference exists (required by both gateways)
            if (empty($subscription->pesapal_merchant_reference)) {
                $subscription->pesapal_merchant_reference = 'SUB-' . $subscription->id . '-' . strtoupper(substr(hash('sha256', $subscription->id . time()), 0, 8));
                $subscription->save();
            }

            if ($gateway === 'pesapal') {
                $svc    = app(\App\Services\SubscriptionPesapalService::class);
                $result = $svc->initializePayment($subscription, null, $phone);
            } else {
                $svc    = app(\App\Services\SubscriptionFlutterwaveService::class);
                $result = $svc->initializePayment($subscription, null, $phone);
            }

            $subscription->refresh();

            return response()->json([
                'success'         => true,
                'message'         => 'Payment initiated via ' . $gateway . '. Redirect the user to the payment link.',
                'gateway'         => $gateway,
                'redirect_url'    => $result['redirect_url']    ?? ($result['payment_message'] ?? null),
                'payment_message' => $result['payment_message'] ?? null,
                'tracking_id'     => $result['order_tracking_id'] ?? null,
                'merchant_ref'    => $result['merchant_reference'] ?? null,
                'data'            => [
                    'subscription'     => $this->subBuildSubscriptionSnippet($subscription),
                    'user'             => $this->subBuildUserSnippet($subscription),
                    'all_transactions' => $this->subBuildAllTransactions($subscription),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Subscription debugInitiatePayment failed', [
                'subscription_id' => $subscriptionId,
                'phone'           => substr($phone, 0, 6) . '***',
                'gateway'         => $gateway,
                'error'           => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    private function subBuildAllTransactions(?Subscription $subscription): array
    {
        if (!$subscription) return [];
        $subTrackingId   = (string) ($subscription->pesapal_tracking_id    ?? '');
        $subFwRef        = (string) ($subscription->flutterwave_reference   ?? '');
        $subPaymentUrl   = (string) ($subscription->payment_url             ?? '');
        return SubscriptionTransaction::where('subscription_id', $subscription->id)
            ->orderByDesc('id')
            ->get()
            ->map(function ($tx) use ($subTrackingId, $subFwRef, $subPaymentUrl) {
                $txTracking = (string) ($tx->pesapal_tracking_id ?? '');
                $txMref     = (string) ($tx->merchant_reference  ?? '');
                // Attach subscription's stored payment_url to the tx that owns the current tracking/reference
                $paymentUrl = '';
                if ($subPaymentUrl !== '') {
                    if (($txTracking !== '' && $txTracking === $subTrackingId)
                        || ($txMref  !== '' && $txMref  === $subFwRef)) {
                        $paymentUrl = $subPaymentUrl;
                    }
                }
                return [
                    'id'                  => $tx->id,
                    'status'              => (string) ($tx->status ?? ''),
                    'transaction_type'    => (string) ($tx->transaction_type ?? ''),
                    'amount'              => $tx->amount,
                    'currency'            => (string) ($tx->currency ?? 'UGX'),
                    'payment_method'      => (string) ($tx->payment_method ?? ''),
                    'pesapal_tracking_id' => $txTracking,
                    'merchant_reference'  => $txMref,
                    'confirmation_code'   => (string) ($tx->confirmation_code ?? ''),
                    'error_message'       => (string) ($tx->error_message ?? ''),
                    'is_fixed'            => (bool) ($tx->is_fixed ?? false),
                    'fix_successful'      => (string) ($tx->fix_successful ?? ''),
                    'payment_url'         => $paymentUrl,
                    'created_at'          => optional($tx->created_at)->format('Y-m-d H:i'),
                ];
            })->toArray();
    }

    private function subBuildTransactionSnippet(?Subscription $subscription): ?array
    {
        if (!$subscription) return null;
        $tx = SubscriptionTransaction::where('subscription_id', $subscription->id)
            ->where('transaction_type', '!=', 'Withdrawal')
            ->orderByDesc('id')
            ->first();
        if (!$tx) return null;
        return [
            'id'               => $tx->id,
            'status'           => (string) ($tx->status ?? ''),
            'transaction_type' => (string) ($tx->transaction_type ?? ''),
            'amount'           => $tx->amount,
            'currency'         => (string) ($tx->currency ?? 'UGX'),
            'payment_method'   => (string) ($tx->payment_method ?? ''),
            'confirmation_code' => (string) ($tx->confirmation_code ?? ''),
            'error_message'    => (string) ($tx->error_message ?? ''),
            'created_at'       => optional($tx->created_at)->toDateTimeString(),
        ];
    }
}
