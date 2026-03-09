<?php

namespace App\Admin\Controllers;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionTransaction;
use App\Models\User;
use App\Services\PaymentStatusChecker;
use Carbon\Carbon;
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
            ->description('Revenue analytics, subscription tracking & admin controls')
            ->row($this->buildStatsCards())
            ->row($this->buildChartsSection())
            ->row(function (Row $row) {
                $row->column(6, $this->appPlatformBreakdownBox());
                $row->column(6, $this->expiringSubscriptionsBox());
            })
            ->body($this->grid());
    }

    // ─── COMPACT STAT CARDS ─────────────────────────────────────────────
    protected function buildStatsCards()
    {
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

        $prefix = config('admin.route.prefix', 'admin');
        $base = $prefix ? "/{$prefix}/subscriptions" : "/subscriptions";

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
.sub-apps{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px}
.sub-app{background:#fff;border-radius:6px;padding:14px 16px;box-shadow:0 1px 4px rgba(0,0,0,.06);border-top:3px solid #ddd}
.sub-app .app-head{display:flex;align-items:center;gap:8px;margin-bottom:10px}
.sub-app .app-head i{font-size:14px}
.sub-app .app-head span{font-weight:700;font-size:13px;color:#333}
.sub-app .app-row{display:flex;justify-content:space-between;font-size:11px;padding:3px 0;color:#555}
.sub-app .app-row b{color:#222}
</style>
<div class="sub-stats">
  <a href="{$base}?payment_status=Completed" style="text-decoration:none;color:inherit" class="sub-stat" style="border-color:#28a745">
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
  <a href="{$base}?status=Active" style="text-decoration:none;color:inherit" class="sub-stat" style="border-color:#007bff">
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
  <a href="{$base}?payment_status=Pending" style="text-decoration:none;color:inherit" class="sub-stat" style="border-color:#dc3545">
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
            $dailyMap[$d] = ['muno_app' => ['rev' => 0, 'cnt' => 0], 'lugaflix' => ['rev' => 0, 'cnt' => 0], 'ugflix' => ['rev' => 0, 'cnt' => 0]];
        }
        foreach ($dailyRaw as $row) {
            if (isset($dailyMap[$row->d][$row->app_type])) {
                $dailyMap[$row->d][$row->app_type] = ['rev' => (float)$row->rev, 'cnt' => (int)$row->cnt];
            }
        }
        $dMunoRev = []; $dLgRev = []; $dUgRev = [];
        $dMunoCnt = []; $dLgCnt = []; $dUgCnt = [];
        $dTotalRev = []; $dTotalCnt = [];
        foreach ($dailyMap as $vals) {
            $dMunoRev[] = $vals['muno_app']['rev']; $dLgRev[] = $vals['lugaflix']['rev']; $dUgRev[] = $vals['ugflix']['rev'];
            $dMunoCnt[] = $vals['muno_app']['cnt']; $dLgCnt[] = $vals['lugaflix']['cnt']; $dUgCnt[] = $vals['ugflix']['cnt'];
            $dTotalRev[] = $vals['muno_app']['rev'] + $vals['lugaflix']['rev'] + $vals['ugflix']['rev'];
            $dTotalCnt[] = $vals['muno_app']['cnt'] + $vals['lugaflix']['cnt'] + $vals['ugflix']['cnt'];
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
            $weeklyMap[$ywMysql] = ['muno_app' => ['rev' => 0, 'cnt' => 0], 'lugaflix' => ['rev' => 0, 'cnt' => 0], 'ugflix' => ['rev' => 0, 'cnt' => 0]];
        }
        foreach ($weeklyRaw as $row) {
            $yw = (string)$row->yw;
            if (isset($weeklyMap[$yw][$row->app_type])) {
                $weeklyMap[$yw][$row->app_type] = ['rev' => (float)$row->rev, 'cnt' => (int)$row->cnt];
            }
        }
        $wMunoRev = []; $wLgRev = []; $wUgRev = [];
        $wMunoCnt = []; $wLgCnt = []; $wUgCnt = [];
        foreach ($weeklyMap as $vals) {
            $wMunoRev[] = $vals['muno_app']['rev']; $wLgRev[] = $vals['lugaflix']['rev']; $wUgRev[] = $vals['ugflix']['rev'];
            $wMunoCnt[] = $vals['muno_app']['cnt']; $wLgCnt[] = $vals['lugaflix']['cnt']; $wUgCnt[] = $vals['ugflix']['cnt'];
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
            $monthlyMap[$ym] = ['muno_app' => ['rev' => 0, 'cnt' => 0], 'lugaflix' => ['rev' => 0, 'cnt' => 0], 'ugflix' => ['rev' => 0, 'cnt' => 0]];
        }
        foreach ($monthlyRaw as $row) {
            if (isset($monthlyMap[$row->ym][$row->app_type])) {
                $monthlyMap[$row->ym][$row->app_type] = ['rev' => (float)$row->rev, 'cnt' => (int)$row->cnt];
            }
        }
        $mMunoRev = []; $mLgRev = []; $mUgRev = [];
        $mMunoCnt = []; $mLgCnt = []; $mUgCnt = [];
        foreach ($monthlyMap as $vals) {
            $mMunoRev[] = $vals['muno_app']['rev']; $mLgRev[] = $vals['lugaflix']['rev']; $mUgRev[] = $vals['ugflix']['rev'];
            $mMunoCnt[] = $vals['muno_app']['cnt']; $mLgCnt[] = $vals['lugaflix']['cnt']; $mUgCnt[] = $vals['ugflix']['cnt'];
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

        // JSON encode
        $j = fn($v) => json_encode($v);

        $chartData = json_encode([
            'daily'   => ['labels' => $dailyLabels, 'muno_rev' => $dMunoRev, 'lg_rev' => $dLgRev, 'ug_rev' => $dUgRev,
                          'muno_cnt' => $dMunoCnt, 'lg_cnt' => $dLgCnt, 'ug_cnt' => $dUgCnt,
                          'total_rev' => $dTotalRev, 'total_cnt' => $dTotalCnt],
            'weekly'  => ['labels' => $weeklyLabels, 'muno_rev' => $wMunoRev, 'lg_rev' => $wLgRev, 'ug_rev' => $wUgRev,
                          'muno_cnt' => $wMunoCnt, 'lg_cnt' => $wLgCnt, 'ug_cnt' => $wUgCnt],
            'monthly' => ['labels' => $monthlyLabels, 'muno_rev' => $mMunoRev, 'lg_rev' => $mLgRev, 'ug_rev' => $mUgRev,
                          'muno_cnt' => $mMunoCnt, 'lg_cnt' => $mLgCnt, 'ug_cnt' => $mUgCnt],
            'plans'   => ['labels' => $plans->pluck('name'), 'counts' => $plans->pluck('count'), 'revenues' => $plans->pluck('total')],
            'payment' => ['completed' => $payBreakdown['Completed'] ?? 0, 'pending' => $payBreakdown['Pending'] ?? 0,
                          'processing' => $payBreakdown['Processing'] ?? 0, 'failed' => $payBreakdown['Failed'] ?? 0],
            'status'  => ['active' => $statusBreakdown['Active'] ?? 0, 'expired' => $statusBreakdown['Expired'] ?? 0,
                          'pending' => $statusBreakdown['Pending'] ?? 0, 'cancelled' => $statusBreakdown['Cancelled'] ?? 0],
            'platform_rev' => ['muno' => $munoTotal, 'lg' => $lgTotal, 'ug' => $ugTotal],
        ]);

        // Platform comparison table
        $munoCount = Subscription::where('app_type', 'muno_app')->where('payment_status', 'Completed')->count();
        $lgCount   = Subscription::where('app_type', 'lugaflix')->where('payment_status', 'Completed')->count();
        $ugCount   = Subscription::where('app_type', 'ugflix')->where('payment_status', 'Completed')->count();

        $platformTable = '';
        $platforms = [
            ['Muno',    $munoCount, $munoTotal, '#e74c3c'],
            ['LugaFlix',$lgCount,   $lgTotal,   '#3498db'],
            ['UGFlix',  $ugCount,   $ugTotal,   '#2ecc71'],
        ];
        foreach ($platforms as [$pn, $pc, $pr, $pcol]) {
            $avg = $pc > 0 ? round($pr / $pc) : 0;
            $platformTable .= "<tr><td><b style='color:{$pcol}'>{$pn}</b></td><td><b>" . number_format($pc) . "</b></td><td><b>UGX " . number_format($pr) . "</b></td><td>UGX " . number_format($avg) . "</td></tr>";
        }
        $grandTotal = $munoTotal + $lgTotal + $ugTotal;
        $grandCount = $munoCount + $lgCount + $ugCount;
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
        ugflix:  {bg:'rgba(46,204,113,.12)', border:'#2ecc71', bar:'rgba(46,204,113,.7)'}
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
    _scInstances.push(new Chart(document.getElementById('scRevLine'),{type:'line',data:{labels:_scData.daily.labels,datasets:[lds('Muno',_scData.daily.muno_rev,'muno',1),lds('LugaFlix',_scData.daily.lg_rev,'lugaflix',1),lds('UGFlix',_scData.daily.ug_rev,'ugflix',1)]},options:o1}));

    // 2. Count Line
    _scInstances.push(new Chart(document.getElementById('scCntLine'),{type:'line',data:{labels:_scData.daily.labels,datasets:[lds('Muno',_scData.daily.muno_cnt,'muno',1),lds('LugaFlix',_scData.daily.lg_cnt,'lugaflix',1),lds('UGFlix',_scData.daily.ug_cnt,'ugflix',1)]},options:cloneOpts(defOpts)}));

    // 3. Total Bar (dual axis)
    _scInstances.push(new Chart(document.getElementById('scTotalBar'),{type:'bar',data:{labels:_scData.daily.labels,datasets:[{label:'Revenue (UGX)',data:_scData.daily.total_rev,backgroundColor:'rgba(243,156,18,.7)',borderColor:'#f39c12',borderWidth:1,yAxisID:'y-rev',barPercentage:.7},{label:'Subscriptions',data:_scData.daily.total_cnt,type:'line',borderColor:'#007bff',backgroundColor:'rgba(0,123,255,.1)',pointBackgroundColor:'#007bff',pointRadius:2,borderWidth:2,lineTension:.35,fill:true,yAxisID:'y-cnt'}]},options:{responsive:true,maintainAspectRatio:false,legend:{position:'top',labels:{usePointStyle:true,padding:12,fontSize:10}},tooltips:{mode:'index',intersect:false},hover:{mode:'nearest',intersect:false},scales:{xAxes:[{gridLines:{color:gc,drawBorder:false},ticks:{fontSize:9,maxRotation:45}}],yAxes:[{id:'y-rev',position:'left',gridLines:{color:gc,drawBorder:false},ticks:{fontSize:9,beginAtZero:true,callback:function(v){return 'UGX '+v.toLocaleString();}}},{id:'y-cnt',position:'right',gridLines:{drawOnChartArea:false},ticks:{fontSize:9,beginAtZero:true}}]}}}));

    // 4. Weekly Revenue Area
    var o4=cloneOpts(defOpts);o4.scales.yAxes[0].ticks.callback=function(v){return 'UGX '+v.toLocaleString();};
    _scInstances.push(new Chart(document.getElementById('scWeekRev'),{type:'line',data:{labels:_scData.weekly.labels,datasets:[lds('Muno',_scData.weekly.muno_rev,'muno',1),lds('LugaFlix',_scData.weekly.lg_rev,'lugaflix',1),lds('UGFlix',_scData.weekly.ug_rev,'ugflix',1)]},options:o4}));

    // 5. Monthly Rev (grouped bar)
    var o5=cloneOpts(defOpts);o5.scales.yAxes[0].ticks.callback=function(v){return 'UGX '+v.toLocaleString();};
    _scInstances.push(new Chart(document.getElementById('scMonthRev'),{type:'bar',data:{labels:_scData.monthly.labels,datasets:[{label:'Muno',data:_scData.monthly.muno_rev,backgroundColor:P.muno.bar},{label:'LugaFlix',data:_scData.monthly.lg_rev,backgroundColor:P.lugaflix.bar},{label:'UGFlix',data:_scData.monthly.ug_rev,backgroundColor:P.ugflix.bar}]},options:o5}));

    // 6. Weekly Count (stacked bar)
    var o6=cloneOpts(defOpts);o6.scales.xAxes[0].stacked=true;o6.scales.yAxes[0].stacked=true;
    _scInstances.push(new Chart(document.getElementById('scWeekCnt'),{type:'bar',data:{labels:_scData.weekly.labels,datasets:[{label:'Muno',data:_scData.weekly.muno_cnt,backgroundColor:P.muno.bar},{label:'LugaFlix',data:_scData.weekly.lg_cnt,backgroundColor:P.lugaflix.bar},{label:'UGFlix',data:_scData.weekly.ug_cnt,backgroundColor:P.ugflix.bar}]},options:o6}));

    // Doughnut defaults
    var donutOpts = {responsive:true,maintainAspectRatio:false,cutoutPercentage:55,legend:{position:'bottom',labels:{usePointStyle:true,padding:8,fontSize:9}}};

    // 7. Revenue by Platform
    var do7=JSON.parse(JSON.stringify(donutOpts));do7.tooltips={callbacks:{label:function(item,data){return data.labels[item.index]+': UGX '+data.datasets[0].data[item.index].toLocaleString();}}};
    _scInstances.push(new Chart(document.getElementById('scRevPie'),{type:'doughnut',data:{labels:['Muno','LugaFlix','UGFlix'],datasets:[{data:[_scData.platform_rev.muno,_scData.platform_rev.lg,_scData.platform_rev.ug],backgroundColor:[P.muno.bar,P.lugaflix.bar,P.ugflix.bar],borderWidth:2,borderColor:'#fff',hoverBorderColor:'#fff'}]},options:do7}));

    // 8. Plan
    var planColors = ['#e74c3c','#3498db','#2ecc71','#f39c12','#9b59b6','#1abc9c','#e67e22','#34495e'];
    _scInstances.push(new Chart(document.getElementById('scPlanPie'),{type:'doughnut',data:{labels:_scData.plans.labels,datasets:[{data:_scData.plans.counts,backgroundColor:planColors.slice(0,_scData.plans.labels.length),borderWidth:2,borderColor:'#fff'}]},options:JSON.parse(JSON.stringify(donutOpts))}));

    // 9. Payment Status
    _scInstances.push(new Chart(document.getElementById('scPayPie'),{type:'doughnut',data:{labels:['Completed','Pending','Processing','Failed'],datasets:[{data:[_scData.payment.completed,_scData.payment.pending,_scData.payment.processing,_scData.payment.failed],backgroundColor:['rgba(40,167,69,.8)','rgba(255,193,7,.8)','rgba(23,162,184,.8)','rgba(220,53,69,.8)'],borderWidth:2,borderColor:'#fff'}]},options:JSON.parse(JSON.stringify(donutOpts))}));

    // 10. Sub Status
    _scInstances.push(new Chart(document.getElementById('scStatPie'),{type:'doughnut',data:{labels:['Active','Expired','Pending','Cancelled'],datasets:[{data:[_scData.status.active,_scData.status.expired,_scData.status.pending,_scData.status.cancelled],backgroundColor:['rgba(40,167,69,.8)','rgba(220,53,69,.8)','rgba(255,193,7,.8)','rgba(108,117,125,.8)'],borderWidth:2,borderColor:'#fff'}]},options:JSON.parse(JSON.stringify(donutOpts))}));

    // 11. Monthly Subs Count
    _scInstances.push(new Chart(document.getElementById('scMonthCnt'),{type:'bar',data:{labels:_scData.monthly.labels,datasets:[{label:'Muno',data:_scData.monthly.muno_cnt,backgroundColor:P.muno.bar},{label:'LugaFlix',data:_scData.monthly.lg_cnt,backgroundColor:P.lugaflix.bar},{label:'UGFlix',data:_scData.monthly.ug_cnt,backgroundColor:P.ugflix.bar}]},options:cloneOpts(defOpts)}));
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
        $prefix = config('admin.route.prefix', 'admin');
        $base = $prefix ? "/{$prefix}/subscriptions" : "/subscriptions";
        $grid->header(function () use ($base) {
            $filters = [
                ['All',       $base,                              'default'],
                ['Active',    $base . '?status=Active',                     'success'],
                ['Pending',   $base . '?payment_status=Pending',            'warning'],
                ['Failed',    $base . '?payment_status=Failed',             'danger'],
                ['Expired',   $base . '?status=Expired',                    'default'],
                ['Cancelled', $base . '?status=Cancelled',                  'default'],
                ['Completed', $base . '?payment_status=Completed',          'success'],
            ];
            $html = '<div style="margin-bottom:12px">';
            foreach ($filters as [$label, $url, $type]) {
                $html .= "<a href='{$url}' class='btn btn-sm btn-{$type}' style='margin-right:4px;margin-bottom:4px'>{$label}</a>";
            }
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
                    'ugflix' => 'UGFlix', 'lugaflix' => 'LugaFlix', 'muno_app' => 'Muno App',
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
                                $u->where('id', (int)$q);
                            } else {
                                $u->where('name', 'like', "%{$q}%")
                                   ->orWhere('email', 'like', "%{$q}%");
                            }
                        });
                    }
                }, 'User (name/email/ID)');
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
        })->sortable();

        $grid->column('user.name', 'Subscriber')->display(function () {
            if ($this->user) {
                $prefix = config('admin.route.prefix', 'admin');
                $userUrl = $prefix ? "/{$prefix}/users/{$this->user->id}" : "/users/{$this->user->id}";
                $name = e($this->user->name);
                $email = e($this->user->email);
                return "<a href='{$userUrl}'><b>{$name}</b></a><br><small class='text-muted'>{$email}</small>";
            }
            return '<span class="text-danger">User not found</span>';
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
        });

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
        })->sortable();

        $grid->column('pesapal_tracking_id', 'Pesapal ID')->display(function ($v) {
            if (!$v) return '-';
            $short = Str::limit($v, 16);
            return "<small title='" . e($v) . "'>{$short}</small>";
        })->hide();

        // Export
        $grid->export(function ($export) {
            $export->filename('Subscriptions_' . date('Y-m-d'));
            $export->except(['actions']);
        });

        $grid->batchActions(function ($batch) {
            $batch->disableDelete();
        });

        return $grid;
    }

    // ─── DETAIL / SHOW ──────────────────────────────────────────────────
    protected function detail($id)
    {
        $subscription = Subscription::with(['user', 'plan', 'transactions'])->findOrFail($id);
        $show = new Show($subscription);
        $prefix = config('admin.route.prefix', 'admin');
        $show->resource($prefix ? "/{$prefix}/subscriptions" : '/subscriptions');

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
        $prefix = config('admin.route.prefix', 'admin');
        $userSearchUrl = $prefix ? "/{$prefix}/api/users" : "/api/users";

        $form->tab('Subscription', function ($form) use ($userSearchUrl) {
            $form->select('user_id', 'User')
                ->options(function ($id) {
                    $user = User::find($id);
                    if ($user) return [$user->id => $user->name . ' (' . $user->email . ')'];
                    return [];
                })
                ->ajax($userSearchUrl)
                ->rules('required')
                ->help('Search by name, email, or ID');

            $form->select('plan_id', 'Plan')
                ->options(SubscriptionPlan::pluck('name', 'id'))
                ->rules('required');

            $form->decimal('amount_paid', 'Amount Paid')
                ->rules('required|numeric|min:0');
            $form->text('currency', 'Currency')->default('UGX')->rules('required');
        });

        $form->tab('Status & Payment', function ($form) {
            $form->radio('status', 'Status')->options([
                'Pending' => 'Pending', 'Active' => 'Active',
                'Expired' => 'Expired', 'Cancelled' => 'Cancelled', 'Failed' => 'Failed',
            ])->default('Pending')->rules('required');

            $form->radio('payment_status', 'Payment Status')->options([
                'Pending' => 'Pending', 'Processing' => 'Processing',
                'Completed' => 'Completed', 'Failed' => 'Failed', 'Refunded' => 'Refunded',
            ])->default('Pending')->rules('required');

            $form->textarea('payment_failure_reason', 'Failure Reason')->rows(2);

            $form->select('app_type', 'App Type')->options([
                'ugflix' => 'UGFlix', 'lugaflix' => 'LugaFlix', 'muno_app' => 'Muno App',
            ]);
            $form->select('platform', 'Platform')->options([
                'android' => 'Android', 'ios' => 'iOS',
            ]);
        });

        $form->tab('Dates', function ($form) {
            $form->datetime('start_date_time', 'Start Date');
            $form->datetime('end_date_time', 'End Date');
        });

        $form->tab('Payment Reference', function ($form) {
            $form->text('pesapal_tracking_id', 'Pesapal Tracking ID');
            $form->text('pesapal_merchant_reference', 'Merchant Reference');
        });

        $form->saving(function (Form $form) {
            if ($form->status === 'Active' && !$form->model()->start_date_time) {
                $form->start_date_time = Carbon::now();
                $plan = SubscriptionPlan::find($form->plan_id);
                if ($plan) {
                    $form->end_date_time = Carbon::now()->addDays($plan->duration_days);
                }
            }
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
                $subscription->status = 'Active';
                $subscription->payment_status = 'Completed';
                if (!$subscription->start_date_time) {
                    $subscription->start_date_time = Carbon::now();
                }
                if (!$subscription->end_date_time) {
                    $plan = $subscription->plan;
                    $days = $plan ? $plan->duration_days : ($subscription->days ?: 30);
                    $subscription->end_date_time = Carbon::now()->addDays($days);
                }
                $subscription->payment_confirmed_at = Carbon::now();
                $subscription->save();
                return response()->json(['success' => true, 'message' => 'Subscription activated successfully.']);

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
                $subscription->status = 'Active';
                $subscription->payment_status = 'Completed';
                $subscription->payment_method = 'admin_grant';
                $subscription->start_date_time = Carbon::now();
                $subscription->end_date_time = Carbon::now()->addDays($days);
                $subscription->days = $days;
                $subscription->payment_confirmed_at = Carbon::now();
                $subscription->save();
                return response()->json([
                    'success' => true,
                    'message' => "Granted {$days} free days. Active until {$subscription->end_date_time}",
                ]);

            default:
                return response()->json(['success' => false, 'message' => 'Unknown action: ' . $action]);
        }
    }

    // ─── HELPERS ────────────────────────────────────────────────────────
    private function fmt($num)
    {
        return number_format($num);
    }
}
