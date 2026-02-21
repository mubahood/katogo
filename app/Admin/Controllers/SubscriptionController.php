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
                $row->column(4, $this->lugaflixStatsBox());
                // UGFlix Stats
                $row->column(4, $this->ugflixStatsBox());
                // Muno App Stats
                $row->column(4, $this->munoAppStatsBox());
            })
            ->row($this->buildChartsSection())
            ->row(function (Row $row) {
                $row->column(6, $this->appTypeBreakdownBox());
                $row->column(6, $this->expiringSubscriptionsBox());
            })
            ->body($this->grid());
    }

    /**
     * Build Charts & Analytics section with Chart.js
     */
    protected function buildChartsSection()
    {
        // ── Gather data for charts ──

        // Daily revenue per platform (last 30 days)
        $dailyData = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = Carbon::today()->subDays($i);
            $dailyData[] = [
                'label' => $d->format('d M'),
                'muno_rev'     => Subscription::where('app_type', 'muno_app')->where('payment_status', 'Completed')->whereDate('created_at', $d)->sum('amount_paid'),
                'lugaflix_rev' => Subscription::where('app_type', 'lugaflix')->where('payment_status', 'Completed')->whereDate('created_at', $d)->sum('amount_paid'),
                'ugflix_rev'   => Subscription::where('app_type', 'ugflix')->where('payment_status', 'Completed')->whereDate('created_at', $d)->sum('amount_paid'),
                'muno_count'     => Subscription::where('app_type', 'muno_app')->where('payment_status', 'Completed')->whereDate('created_at', $d)->count(),
                'lugaflix_count' => Subscription::where('app_type', 'lugaflix')->where('payment_status', 'Completed')->whereDate('created_at', $d)->count(),
                'ugflix_count'   => Subscription::where('app_type', 'ugflix')->where('payment_status', 'Completed')->whereDate('created_at', $d)->count(),
                'total_rev'   => Subscription::where('payment_status', 'Completed')->whereDate('created_at', $d)->sum('amount_paid'),
                'total_count' => Subscription::where('payment_status', 'Completed')->whereDate('created_at', $d)->count(),
            ];
        }

        // Weekly data (last 12 weeks)
        $weeklyData = [];
        for ($i = 11; $i >= 0; $i--) {
            $ws = Carbon::now()->subWeeks($i)->startOfWeek();
            $we = Carbon::now()->subWeeks($i)->endOfWeek();
            $weeklyData[] = [
                'label' => $ws->format('d M'),
                'muno_rev'     => Subscription::where('app_type', 'muno_app')->where('payment_status', 'Completed')->whereBetween('created_at', [$ws, $we])->sum('amount_paid'),
                'lugaflix_rev' => Subscription::where('app_type', 'lugaflix')->where('payment_status', 'Completed')->whereBetween('created_at', [$ws, $we])->sum('amount_paid'),
                'ugflix_rev'   => Subscription::where('app_type', 'ugflix')->where('payment_status', 'Completed')->whereBetween('created_at', [$ws, $we])->sum('amount_paid'),
                'muno_count'     => Subscription::where('app_type', 'muno_app')->where('payment_status', 'Completed')->whereBetween('created_at', [$ws, $we])->count(),
                'lugaflix_count' => Subscription::where('app_type', 'lugaflix')->where('payment_status', 'Completed')->whereBetween('created_at', [$ws, $we])->count(),
                'ugflix_count'   => Subscription::where('app_type', 'ugflix')->where('payment_status', 'Completed')->whereBetween('created_at', [$ws, $we])->count(),
            ];
        }

        // Monthly data (last 6 months)
        $monthlyData = [];
        for ($i = 5; $i >= 0; $i--) {
            $ms = Carbon::now()->subMonths($i)->startOfMonth();
            $me = Carbon::now()->subMonths($i)->endOfMonth();
            $monthlyData[] = [
                'label' => $ms->format('M Y'),
                'muno_rev'     => Subscription::where('app_type', 'muno_app')->where('payment_status', 'Completed')->whereBetween('created_at', [$ms, $me])->sum('amount_paid'),
                'lugaflix_rev' => Subscription::where('app_type', 'lugaflix')->where('payment_status', 'Completed')->whereBetween('created_at', [$ms, $me])->sum('amount_paid'),
                'ugflix_rev'   => Subscription::where('app_type', 'ugflix')->where('payment_status', 'Completed')->whereBetween('created_at', [$ms, $me])->sum('amount_paid'),
                'muno_count'     => Subscription::where('app_type', 'muno_app')->where('payment_status', 'Completed')->whereBetween('created_at', [$ms, $me])->count(),
                'lugaflix_count' => Subscription::where('app_type', 'lugaflix')->where('payment_status', 'Completed')->whereBetween('created_at', [$ms, $me])->count(),
                'ugflix_count'   => Subscription::where('app_type', 'ugflix')->where('payment_status', 'Completed')->whereBetween('created_at', [$ms, $me])->count(),
            ];
        }

        // Plan breakdown
        $plans = Subscription::where('payment_status', 'Completed')
            ->join('subscription_plans', 'subscriptions.plan_id', '=', 'subscription_plans.id')
            ->selectRaw('subscription_plans.name, COUNT(*) as count, SUM(subscriptions.amount_paid) as total')
            ->groupBy('subscription_plans.name')
            ->orderByDesc('total')
            ->get();
        $planLabels = $plans->pluck('name')->toArray();
        $planCounts = $plans->pluck('count')->toArray();
        $planRevenues = $plans->pluck('total')->toArray();

        // Platform totals
        $munoTotal   = Subscription::where('app_type', 'muno_app')->where('payment_status', 'Completed')->sum('amount_paid');
        $lgTotal     = Subscription::where('app_type', 'lugaflix')->where('payment_status', 'Completed')->sum('amount_paid');
        $ugTotal     = Subscription::where('app_type', 'ugflix')->where('payment_status', 'Completed')->sum('amount_paid');
        $munoCount   = Subscription::where('app_type', 'muno_app')->where('payment_status', 'Completed')->count();
        $lgCount     = Subscription::where('app_type', 'lugaflix')->where('payment_status', 'Completed')->count();
        $ugCount     = Subscription::where('app_type', 'ugflix')->where('payment_status', 'Completed')->count();

        // Payment status breakdown
        $statCompleted  = Subscription::where('payment_status', 'Completed')->count();
        $statPending    = Subscription::where('payment_status', 'Pending')->count();
        $statProcessing = Subscription::where('payment_status', 'Processing')->count();
        $statFailed     = Subscription::where('payment_status', 'Failed')->count();

        // Status breakdown
        $stActive   = Subscription::where('status', 'Active')->count();
        $stExpired  = Subscription::where('status', 'Expired')->count();
        $stPending  = Subscription::where('status', 'Pending')->count();
        $stCancelled = Subscription::where('status', 'Cancelled')->count();

        // JSON encode for Chart.js
        $dailyLabels      = json_encode(array_column($dailyData, 'label'));
        $dMunoRev         = json_encode(array_column($dailyData, 'muno_rev'));
        $dLugaflixRev     = json_encode(array_column($dailyData, 'lugaflix_rev'));
        $dUgflixRev       = json_encode(array_column($dailyData, 'ugflix_rev'));
        $dMunoCount       = json_encode(array_column($dailyData, 'muno_count'));
        $dLugaflixCount   = json_encode(array_column($dailyData, 'lugaflix_count'));
        $dUgflixCount     = json_encode(array_column($dailyData, 'ugflix_count'));
        $dTotalRev        = json_encode(array_column($dailyData, 'total_rev'));
        $dTotalCount      = json_encode(array_column($dailyData, 'total_count'));

        $weeklyLabels     = json_encode(array_column($weeklyData, 'label'));
        $wMunoRev         = json_encode(array_column($weeklyData, 'muno_rev'));
        $wLugaflixRev     = json_encode(array_column($weeklyData, 'lugaflix_rev'));
        $wUgflixRev       = json_encode(array_column($weeklyData, 'ugflix_rev'));
        $wMunoCount       = json_encode(array_column($weeklyData, 'muno_count'));
        $wLugaflixCount   = json_encode(array_column($weeklyData, 'lugaflix_count'));
        $wUgflixCount     = json_encode(array_column($weeklyData, 'ugflix_count'));

        $monthlyLabels    = json_encode(array_column($monthlyData, 'label'));
        $mMunoRev         = json_encode(array_column($monthlyData, 'muno_rev'));
        $mLugaflixRev     = json_encode(array_column($monthlyData, 'lugaflix_rev'));
        $mUgflixRev       = json_encode(array_column($monthlyData, 'ugflix_rev'));
        $mMunoCount       = json_encode(array_column($monthlyData, 'muno_count'));
        $mLugaflixCount   = json_encode(array_column($monthlyData, 'lugaflix_count'));
        $mUgflixCount     = json_encode(array_column($monthlyData, 'ugflix_count'));

        $planLabelsJson   = json_encode($planLabels);
        $planCountsJson   = json_encode($planCounts);
        $planRevenuesJson = json_encode($planRevenues);

        // ── Build HTML ──
        $html = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
        $html .= '<style>
.sub-chart-wrap{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;font-size:12px}
.sub-row{display:flex;gap:12px;margin-bottom:12px;flex-wrap:wrap}
.sub-box{background:#fff;border-radius:8px;padding:16px;box-shadow:0 2px 8px rgba(0,0,0,.06);border:1px solid #f0f0f0;transition:box-shadow .2s}
.sub-box:hover{box-shadow:0 4px 16px rgba(0,0,0,.1)}
.sub-box-title{font-size:11px;font-weight:700;color:#444;margin-bottom:12px;text-transform:uppercase;letter-spacing:.5px;display:flex;align-items:center;gap:6px}
.sub-box-title i{font-size:14px}
.sub-section{margin:8px 0 6px;font-size:12px;font-weight:700;color:#333;border-bottom:2px solid #f39c12;padding-bottom:4px;text-transform:uppercase;letter-spacing:.5px}
.sub-tbl{width:100%;border-collapse:collapse;font-size:11px}
.sub-tbl th{text-align:left;padding:6px 8px;border-bottom:2px solid #eee;font-weight:700;color:#555;font-size:10px;text-transform:uppercase}
.sub-tbl td{padding:6px 8px;border-bottom:1px solid #f5f5f5}
.sub-badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:9px;font-weight:600;color:#fff}
.sub-metric{text-align:center;padding:8px}
.sub-metric .val{font-size:22px;font-weight:700;line-height:1.2}
.sub-metric .lbl{font-size:9px;color:#888;text-transform:uppercase;margin-top:2px}
</style>';

        $html .= '<div class="sub-chart-wrap">';

        // ── SECTION: Charts & Analytics ──
        $html .= '<div class="sub-section"><i class="fa fa-line-chart" style="color:#f39c12;margin-right:4px"></i> Revenue & Subscription Analytics</div>';

        // ROW 1: Revenue Line Charts (30 days per platform + total)
        $html .= '<div class="sub-row">';

        $html .= '<div class="sub-box" style="flex:1;min-width:480px">';
        $html .= '<div class="sub-box-title"><i class="fa fa-line-chart" style="color:#f39c12"></i> Daily Revenue by Platform — Last 30 Days (UGX)</div>';
        $html .= '<div style="position:relative;height:340px"><canvas id="subRevenueLineChart"></canvas></div>';
        $html .= '</div>';

        $html .= '<div class="sub-box" style="flex:1;min-width:480px">';
        $html .= '<div class="sub-box-title"><i class="fa fa-shopping-cart" style="color:#28a745"></i> Daily Subscriptions by Platform — Last 30 Days</div>';
        $html .= '<div style="position:relative;height:340px"><canvas id="subCountLineChart"></canvas></div>';
        $html .= '</div>';

        $html .= '</div>'; // row 1

        // ROW 2: Total Revenue bar + Weekly Revenue stacked area
        $html .= '<div class="sub-row">';

        $html .= '<div class="sub-box" style="flex:1;min-width:480px">';
        $html .= '<div class="sub-box-title"><i class="fa fa-bar-chart" style="color:#007bff"></i> Total Daily Revenue & Subscriptions — 30 Days</div>';
        $html .= '<div style="position:relative;height:320px"><canvas id="subTotalBarChart"></canvas></div>';
        $html .= '</div>';

        $html .= '<div class="sub-box" style="flex:1;min-width:480px">';
        $html .= '<div class="sub-box-title"><i class="fa fa-area-chart" style="color:#e83e8c"></i> Weekly Revenue Trend — Last 12 Weeks (UGX)</div>';
        $html .= '<div style="position:relative;height:320px"><canvas id="subWeeklyRevenueChart"></canvas></div>';
        $html .= '</div>';

        $html .= '</div>'; // row 2

        // ROW 3: Monthly Revenue comparison + Weekly Subscriptions stacked bar
        $html .= '<div class="sub-row">';

        $html .= '<div class="sub-box" style="flex:1;min-width:400px">';
        $html .= '<div class="sub-box-title"><i class="fa fa-calendar" style="color:#6f42c1"></i> Monthly Revenue by Platform — Last 6 Months</div>';
        $html .= '<div style="position:relative;height:300px"><canvas id="subMonthlyRevenueChart"></canvas></div>';
        $html .= '</div>';

        $html .= '<div class="sub-box" style="flex:1;min-width:400px">';
        $html .= '<div class="sub-box-title"><i class="fa fa-users" style="color:#17a2b8"></i> Weekly Subscriptions by Platform — 12 Weeks</div>';
        $html .= '<div style="position:relative;height:300px"><canvas id="subWeeklyCountChart"></canvas></div>';
        $html .= '</div>';

        $html .= '</div>'; // row 3

        // ROW 4: Doughnut Charts
        $html .= '<div class="sub-row">';

        // Revenue by Platform doughnut
        $html .= '<div class="sub-box" style="flex:1;min-width:220px;text-align:center">';
        $html .= '<div class="sub-box-title" style="justify-content:center"><i class="fa fa-pie-chart" style="color:#e74c3c"></i> Revenue by Platform</div>';
        $html .= '<div style="position:relative;height:240px"><canvas id="subRevenuePieChart"></canvas></div>';
        $html .= '</div>';

        // Plan Breakdown doughnut
        $html .= '<div class="sub-box" style="flex:1;min-width:220px;text-align:center">';
        $html .= '<div class="sub-box-title" style="justify-content:center"><i class="fa fa-tags" style="color:#6f42c1"></i> Subscriptions by Plan</div>';
        $html .= '<div style="position:relative;height:240px"><canvas id="subPlanPieChart"></canvas></div>';
        $html .= '</div>';

        // Payment Status doughnut
        $html .= '<div class="sub-box" style="flex:1;min-width:220px;text-align:center">';
        $html .= '<div class="sub-box-title" style="justify-content:center"><i class="fa fa-credit-card" style="color:#28a745"></i> Payment Status</div>';
        $html .= '<div style="position:relative;height:240px"><canvas id="subPaymentPieChart"></canvas></div>';
        $html .= '</div>';

        // Subscription Status doughnut
        $html .= '<div class="sub-box" style="flex:1;min-width:220px;text-align:center">';
        $html .= '<div class="sub-box-title" style="justify-content:center"><i class="fa fa-heartbeat" style="color:#dc3545"></i> Subscription Status</div>';
        $html .= '<div style="position:relative;height:240px"><canvas id="subStatusPieChart"></canvas></div>';
        $html .= '</div>';

        $html .= '</div>'; // row 4

        // ROW 5: Monthly subscriptions count chart + Platform Comparison table + Plan Revenue table
        $html .= '<div class="sub-row">';

        $html .= '<div class="sub-box" style="flex:1;min-width:380px">';
        $html .= '<div class="sub-box-title"><i class="fa fa-bar-chart" style="color:#fd7e14"></i> Monthly Subscriptions by Platform — 6 Months</div>';
        $html .= '<div style="position:relative;height:280px"><canvas id="subMonthlyCountChart"></canvas></div>';
        $html .= '</div>';

        // Platform Comparison Table
        $html .= '<div class="sub-box" style="flex:1;min-width:340px">';
        $html .= '<div class="sub-box-title"><i class="fa fa-trophy" style="color:#f39c12"></i> Platform Comparison</div>';
        $html .= '<table class="sub-tbl">';
        $html .= '<tr><th>Platform</th><th>Subs</th><th>Revenue</th><th>Avg/Sub</th></tr>';
        $platformCompare = [
            ['Muno', $munoCount, $munoTotal, '#e74c3c', 'fa-fire'],
            ['LugaFlix', $lgCount, $lgTotal, '#3498db', 'fa-star'],
            ['UG Flix', $ugCount, $ugTotal, '#2ecc71', 'fa-bolt'],
        ];
        foreach ($platformCompare as [$pName, $pCount, $pRev, $pColor, $pIcon]) {
            $avg = $pCount > 0 ? round($pRev / $pCount) : 0;
            $html .= "<tr><td><i class=\"fa {$pIcon}\" style=\"color:{$pColor};margin-right:4px\"></i><b style=\"color:{$pColor}\">{$pName}</b></td>";
            $html .= "<td><b>" . number_format($pCount) . "</b></td>";
            $html .= "<td><b>UGX " . number_format($pRev) . "</b></td>";
            $html .= "<td><span class=\"sub-badge\" style=\"background:{$pColor}\">UGX " . number_format($avg) . "</span></td></tr>";
        }
        $grandTotal = $munoTotal + $lgTotal + $ugTotal;
        $grandCount = $munoCount + $lgCount + $ugCount;
        $grandAvg = $grandCount > 0 ? round($grandTotal / $grandCount) : 0;
        $html .= "<tr style=\"border-top:2px solid #ddd\"><td><b>Total</b></td><td><b>" . number_format($grandCount) . "</b></td><td><b style=\"color:#f39c12\">UGX " . number_format($grandTotal) . "</b></td><td><b>UGX " . number_format($grandAvg) . "</b></td></tr>";
        $html .= '</table>';

        // Plan Revenue breakdown below
        $html .= '<div class="sub-box-title" style="margin-top:16px"><i class="fa fa-list" style="color:#6f42c1"></i> Revenue by Plan</div>';
        $html .= '<table class="sub-tbl">';
        $html .= '<tr><th>Plan</th><th>Subscribers</th><th>Revenue</th></tr>';
        if (count($planLabels) > 0) {
            foreach ($plans as $plan) {
                $html .= "<tr><td><b>{$plan->name}</b></td><td>" . number_format($plan->count) . "</td><td><b>UGX " . number_format($plan->total) . "</b></td></tr>";
            }
        } else {
            $html .= '<tr><td colspan="3" style="text-align:center;color:#999">No data yet</td></tr>';
        }
        $html .= '</table>';
        $html .= '</div>';

        $html .= '</div>'; // row 5

        // ════════ Chart.js Scripts ════════
        $planColors = json_encode(['#e74c3c','#3498db','#2ecc71','#f39c12','#9b59b6','#1abc9c','#e67e22','#34495e']);

        $html .= '<script>
document.addEventListener("DOMContentLoaded", function() {
    const pColors = {
        muno: { bg: "rgba(231,76,60,0.15)", border: "#e74c3c", point: "#c0392b", bar: "rgba(231,76,60,0.7)" },
        lugaflix: { bg: "rgba(52,152,219,0.15)", border: "#3498db", point: "#2980b9", bar: "rgba(52,152,219,0.7)" },
        ugflix: { bg: "rgba(46,204,113,0.15)", border: "#2ecc71", point: "#27ae60", bar: "rgba(46,204,113,0.7)" }
    };
    const cFont = { family: "-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif" };
    const gColor = "rgba(0,0,0,0.04)";
    const defOpts = {
        responsive: true, maintainAspectRatio: false,
        plugins: {
            legend: { position: "top", labels: { usePointStyle: true, pointStyle: "circle", padding: 14, font: { size: 11, ...cFont } } },
            tooltip: { mode: "index", intersect: false, backgroundColor: "rgba(0,0,0,0.85)", titleFont: { size: 12 }, bodyFont: { size: 11 }, padding: 10, cornerRadius: 6, displayColors: true,
                callbacks: { label: function(ctx) { return ctx.dataset.label + ": " + (ctx.parsed.y >= 1000 ? "UGX " + ctx.parsed.y.toLocaleString() : ctx.parsed.y); } }
            }
        },
        interaction: { mode: "nearest", axis: "x", intersect: false },
        scales: {
            x: { grid: { color: gColor, drawBorder: false }, ticks: { font: { size: 9, ...cFont }, maxRotation: 45 } },
            y: { beginAtZero: true, grid: { color: gColor, drawBorder: false }, ticks: { font: { size: 10, ...cFont } } }
        }
    };
    function lineDS(label, data, key, dashed) {
        return {
            label: label, data: data,
            borderColor: pColors[key].border, backgroundColor: pColors[key].bg,
            pointBackgroundColor: pColors[key].point,
            pointRadius: 3, pointHoverRadius: 6,
            borderWidth: 2.5, tension: 0.35, fill: true,
            borderDash: dashed ? [5,3] : []
        };
    }

    // 1. Revenue Line Chart (30 days per platform)
    new Chart(document.getElementById("subRevenueLineChart"), {
        type: "line",
        data: {
            labels: ' . $dailyLabels . ',
            datasets: [
                lineDS("Muno", ' . $dMunoRev . ', "muno", false),
                lineDS("LugaFlix", ' . $dLugaflixRev . ', "lugaflix", false),
                lineDS("UG Flix", ' . $dUgflixRev . ', "ugflix", false)
            ]
        },
        options: { ...defOpts, scales: { ...defOpts.scales, y: { ...defOpts.scales.y, ticks: { ...defOpts.scales.y.ticks, callback: v => "UGX " + v.toLocaleString() } } } }
    });

    // 2. Subscriptions Count Line Chart (30 days)
    new Chart(document.getElementById("subCountLineChart"), {
        type: "line",
        data: {
            labels: ' . $dailyLabels . ',
            datasets: [
                lineDS("Muno", ' . $dMunoCount . ', "muno", false),
                lineDS("LugaFlix", ' . $dLugaflixCount . ', "lugaflix", false),
                lineDS("UG Flix", ' . $dUgflixCount . ', "ugflix", false)
            ]
        },
        options: defOpts
    });

    // 3. Total Daily Revenue + Count (dual axis bar)
    new Chart(document.getElementById("subTotalBarChart"), {
        type: "bar",
        data: {
            labels: ' . $dailyLabels . ',
            datasets: [
                { label: "Revenue (UGX)", data: ' . $dTotalRev . ', backgroundColor: "rgba(243,156,18,0.7)", borderColor: "#f39c12", borderWidth: 1, borderRadius: 4, yAxisID: "y", barPercentage: 0.7 },
                { label: "Subscriptions", data: ' . $dTotalCount . ', type: "line", borderColor: "#007bff", backgroundColor: "rgba(0,123,255,0.1)", pointBackgroundColor: "#007bff", pointRadius: 3, borderWidth: 2.5, tension: 0.35, fill: true, yAxisID: "y1" }
            ]
        },
        options: {
            ...defOpts,
            scales: {
                x: defOpts.scales.x,
                y: { ...defOpts.scales.y, position: "left", ticks: { ...defOpts.scales.y.ticks, callback: v => "UGX " + v.toLocaleString() } },
                y1: { beginAtZero: true, position: "right", grid: { drawOnChartArea: false }, ticks: { font: { size: 10, ...cFont } } }
            }
        }
    });

    // 4. Weekly Revenue Area
    new Chart(document.getElementById("subWeeklyRevenueChart"), {
        type: "line",
        data: {
            labels: ' . $weeklyLabels . ',
            datasets: [
                { ...lineDS("Muno", ' . $wMunoRev . ', "muno", false), fill: "origin", backgroundColor: "rgba(231,76,60,0.12)" },
                { ...lineDS("LugaFlix", ' . $wLugaflixRev . ', "lugaflix", false), fill: "origin", backgroundColor: "rgba(52,152,219,0.12)" },
                { ...lineDS("UG Flix", ' . $wUgflixRev . ', "ugflix", false), fill: "origin", backgroundColor: "rgba(46,204,113,0.12)" }
            ]
        },
        options: { ...defOpts, elements: { line: { tension: 0.4 } }, scales: { ...defOpts.scales, y: { ...defOpts.scales.y, ticks: { ...defOpts.scales.y.ticks, callback: v => "UGX " + v.toLocaleString() } } } }
    });

    // 5. Monthly Revenue (grouped bar)
    new Chart(document.getElementById("subMonthlyRevenueChart"), {
        type: "bar",
        data: {
            labels: ' . $monthlyLabels . ',
            datasets: [
                { label: "Muno", data: ' . $mMunoRev . ', backgroundColor: pColors.muno.bar, borderRadius: 4, barPercentage: 0.8 },
                { label: "LugaFlix", data: ' . $mLugaflixRev . ', backgroundColor: pColors.lugaflix.bar, borderRadius: 4, barPercentage: 0.8 },
                { label: "UG Flix", data: ' . $mUgflixRev . ', backgroundColor: pColors.ugflix.bar, borderRadius: 4, barPercentage: 0.8 }
            ]
        },
        options: { ...defOpts, scales: { ...defOpts.scales, y: { ...defOpts.scales.y, ticks: { ...defOpts.scales.y.ticks, callback: v => "UGX " + v.toLocaleString() } } } }
    });

    // 6. Weekly Subscriptions (stacked bar)
    new Chart(document.getElementById("subWeeklyCountChart"), {
        type: "bar",
        data: {
            labels: ' . $weeklyLabels . ',
            datasets: [
                { label: "Muno", data: ' . $wMunoCount . ', backgroundColor: pColors.muno.bar, borderRadius: 3, barPercentage: 0.8 },
                { label: "LugaFlix", data: ' . $wLugaflixCount . ', backgroundColor: pColors.lugaflix.bar, borderRadius: 3, barPercentage: 0.8 },
                { label: "UG Flix", data: ' . $wUgflixCount . ', backgroundColor: pColors.ugflix.bar, borderRadius: 3, barPercentage: 0.8 }
            ]
        },
        options: { ...defOpts, scales: { ...defOpts.scales, x: { ...defOpts.scales.x, stacked: true }, y: { ...defOpts.scales.y, stacked: true } } }
    });

    // 7. Revenue by Platform Doughnut
    new Chart(document.getElementById("subRevenuePieChart"), {
        type: "doughnut",
        data: {
            labels: ["Muno", "LugaFlix", "UG Flix"],
            datasets: [{ data: [' . $munoTotal . ', ' . $lgTotal . ', ' . $ugTotal . '],
                backgroundColor: [pColors.muno.bar, pColors.lugaflix.bar, pColors.ugflix.bar],
                borderWidth: 2, borderColor: "#fff", hoverOffset: 10 }]
        },
        options: { responsive: true, maintainAspectRatio: false, cutout: "55%",
            plugins: { legend: { position: "bottom", labels: { usePointStyle: true, pointStyle: "circle", padding: 10, font: { size: 10, ...cFont } } },
                tooltip: { callbacks: { label: function(ctx) { return ctx.label + ": UGX " + ctx.parsed.toLocaleString(); } } } } }
    });

    // 8. Plan Breakdown Doughnut
    const planColors = ' . $planColors . ';
    new Chart(document.getElementById("subPlanPieChart"), {
        type: "doughnut",
        data: {
            labels: ' . $planLabelsJson . ',
            datasets: [{ data: ' . $planCountsJson . ',
                backgroundColor: planColors.slice(0, ' . count($planLabels) . '),
                borderWidth: 2, borderColor: "#fff", hoverOffset: 10 }]
        },
        options: { responsive: true, maintainAspectRatio: false, cutout: "55%",
            plugins: { legend: { position: "bottom", labels: { usePointStyle: true, pointStyle: "circle", padding: 8, font: { size: 9, ...cFont } } } } }
    });

    // 9. Payment Status Doughnut
    new Chart(document.getElementById("subPaymentPieChart"), {
        type: "doughnut",
        data: {
            labels: ["Completed", "Pending", "Processing", "Failed"],
            datasets: [{ data: [' . $statCompleted . ', ' . $statPending . ', ' . $statProcessing . ', ' . $statFailed . '],
                backgroundColor: ["rgba(40,167,69,0.8)", "rgba(255,193,7,0.8)", "rgba(23,162,184,0.8)", "rgba(220,53,69,0.8)"],
                borderWidth: 2, borderColor: "#fff", hoverOffset: 10 }]
        },
        options: { responsive: true, maintainAspectRatio: false, cutout: "55%",
            plugins: { legend: { position: "bottom", labels: { usePointStyle: true, pointStyle: "circle", padding: 8, font: { size: 10, ...cFont } } } } }
    });

    // 10. Subscription Status Doughnut
    new Chart(document.getElementById("subStatusPieChart"), {
        type: "doughnut",
        data: {
            labels: ["Active", "Expired", "Pending", "Cancelled"],
            datasets: [{ data: [' . $stActive . ', ' . $stExpired . ', ' . $stPending . ', ' . $stCancelled . '],
                backgroundColor: ["rgba(40,167,69,0.8)", "rgba(220,53,69,0.8)", "rgba(255,193,7,0.8)", "rgba(108,117,125,0.8)"],
                borderWidth: 2, borderColor: "#fff", hoverOffset: 10 }]
        },
        options: { responsive: true, maintainAspectRatio: false, cutout: "55%",
            plugins: { legend: { position: "bottom", labels: { usePointStyle: true, pointStyle: "circle", padding: 8, font: { size: 10, ...cFont } } } } }
    });

    // 11. Monthly Subs Count (grouped bar)
    new Chart(document.getElementById("subMonthlyCountChart"), {
        type: "bar",
        data: {
            labels: ' . $monthlyLabels . ',
            datasets: [
                { label: "Muno", data: ' . $mMunoCount . ', backgroundColor: pColors.muno.bar, borderRadius: 4 },
                { label: "LugaFlix", data: ' . $mLugaflixCount . ', backgroundColor: pColors.lugaflix.bar, borderRadius: 4 },
                { label: "UG Flix", data: ' . $mUgflixCount . ', backgroundColor: pColors.ugflix.bar, borderRadius: 4 }
            ]
        },
        options: defOpts
    });
});
</script>';

        $html .= '</div>'; // sub-chart-wrap

        return $html;
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
     * Muno App stats box
     */
    protected function munoAppStatsBox()
    {
        $completed = Subscription::where('app_type', 'muno_app')
            ->where('payment_status', 'Completed');
        
        $totalRevenue = (clone $completed)->sum('amount_paid');
        $totalSubs = (clone $completed)->count();
        $activeSubs = Subscription::where('app_type', 'muno_app')
            ->where('status', 'Active')
            ->where('payment_status', 'Completed')
            ->count();
        
        $thisMonth = Subscription::where('app_type', 'muno_app')
            ->where('payment_status', 'Completed')
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->sum('amount_paid');
        
        $today = Subscription::where('app_type', 'muno_app')
            ->where('payment_status', 'Completed')
            ->whereDate('created_at', Carbon::today())
            ->sum('amount_paid');
        
        $todayCount = Subscription::where('app_type', 'muno_app')
            ->where('payment_status', 'Completed')
            ->whereDate('created_at', Carbon::today())
            ->count();
        
        $pending = Subscription::where('app_type', 'muno_app')
            ->where('payment_status', 'Pending')
            ->count();
        
        $processing = Subscription::where('app_type', 'muno_app')
            ->where('payment_status', 'Processing')
            ->count();
        
        $failed = Subscription::where('app_type', 'muno_app')
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
        $box = new Box('📺 Muno App Stats', $table);
        $box->style('info');
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
                    'muno_app' => 'Muno App',
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
                    'muno_app' => '📺 Muno App',
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
            $icons = ['ugflix' => '🎬', 'lugaflix' => '🎭', 'muno_app' => '📺'];
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
                    'muno_app' => '📺 Muno App',
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
