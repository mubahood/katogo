<?php

namespace App\Admin\Controllers;

use App\Models\User;
use App\Models\Subscription;
use App\Models\MovieView;
use Carbon\Carbon;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Encore\Admin\Layout\Content;
use Illuminate\Support\Facades\DB;

class UserController extends AdminController
{
    protected $title = 'Users';

    /**
     * Override show to use custom profile blade
     */
    public function show($id, Content $content)
    {
        return app(UserProfileController::class)->show($id);
    }

    /**
     * Index with analytics dashboard + grid
     */
    public function index(Content $content)
    {
        return $content
            ->title('Users')
            ->description('User management & analytics')
            ->body($this->dashboard())
            ->body($this->grid());
    }

    /**
     * Compact stats dashboard
     */
    protected function dashboard()
    {
        $totalUsers   = User::count();
        $todayUsers   = User::whereDate('created_at', Carbon::today())->count();
        $weekUsers    = User::where('created_at', '>=', Carbon::now()->subDays(7))->count();
        $monthUsers   = User::where('created_at', '>=', Carbon::now()->subDays(30))->count();

        // App type breakdown
        $ugflixUsers   = User::where('app_type', 'ugflix')->count();
        $lugaflixUsers = User::where('app_type', 'lugaflix')->count();
        $otherUsers    = $totalUsers - $ugflixUsers - $lugaflixUsers;

        // Platform
        $androidUsers = User::where('platform', 'android')->count();
        $iosUsers     = User::where('platform', 'ios')->count();

        // Subscription stats
        $activeSubUsers = DB::table('subscriptions')
            ->where('status', 'Active')
            ->where('end_date_time', '>=', Carbon::now())
            ->distinct('user_id')->count('user_id');
        $guestUsers = User::where('is_guest', 'Yes')->count();
        $verifiedUsers = User::where('email_verified', 1)->count();

        // Activity
        $activeViewers = MovieView::where('created_at', '>=', Carbon::now()->subDays(7))
            ->distinct('user_id')->count('user_id');
        $totalViews = MovieView::count();
        $totalWatchHours = round(MovieView::sum('progress') / 3600, 1);

        // Avg views per user
        $avgViewsPerUser = $totalUsers > 0 ? round($totalViews / $totalUsers, 1) : 0;

        // Daily signups last 7 days
        $dailySignups = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = Carbon::today()->subDays($i);
            $dailySignups[] = ['label' => $d->format('D'), 'count' => User::whereDate('created_at', $d)->count()];
        }
        $maxDaily = max(array_column($dailySignups, 'count') ?: [1]);

        // Pie percentages
        $pT = max($ugflixUsers + $lugaflixUsers + $otherUsers, 1);
        $ugPct = round(($ugflixUsers / $pT) * 100);
        $lgPct = round(($lugaflixUsers / $pT) * 100);
        $dT = max($androidUsers + $iosUsers, 1);
        $anPct = round(($androidUsers / $dT) * 100);

        $html = '<style>
.uc-wrap{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;font-size:12px}
.uc-row{display:flex;gap:8px;margin-bottom:8px;flex-wrap:wrap}
.uc-card{flex:1;min-width:115px;background:#fff;border-radius:6px;padding:10px 12px;box-shadow:0 1px 3px rgba(0,0,0,.06);border-left:3px solid #ddd;position:relative;transition:box-shadow .15s}
.uc-card:hover{box-shadow:0 2px 8px rgba(0,0,0,.12)}
.uc-card a{color:inherit;text-decoration:none;display:block}
.uc-card .uc-val{font-size:20px;font-weight:700;line-height:1.1}
.uc-card .uc-lbl{font-size:10px;color:#888;margin-top:2px;text-transform:uppercase;letter-spacing:.3px}
.uc-card .uc-icon{position:absolute;right:10px;top:10px;font-size:16px;opacity:.35}
.uc-box{background:#fff;border-radius:6px;padding:12px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
.uc-box-title{font-size:11px;font-weight:700;color:#555;margin-bottom:8px;text-transform:uppercase;letter-spacing:.4px}
.uc-pie{width:80px;height:80px;border-radius:50%;margin:0 auto 6px}
.uc-legend{font-size:10px;text-align:center}
.uc-legend span{display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:3px}
.uc-bar-wrap{display:flex;align-items:flex-end;gap:4px;height:60px}
.uc-bar-lbl{text-align:center;font-size:9px;color:#888;margin-top:2px}
</style>';

        $html .= '<div class="uc-wrap">';

        // Row 1: KPIs
        $html .= '<div class="uc-row">';
        $cards = [
            ['Total Users', number_format($totalUsers), '#007bff', 'fa-users'],
            ['Today', number_format($todayUsers), '#28a745', 'fa-user-plus'],
            ['This Week', number_format($weekUsers), '#6f42c1', 'fa-calendar'],
            ['This Month', number_format($monthUsers), '#17a2b8', 'fa-calendar-o'],
            ['Active Subs', number_format($activeSubUsers), '#f39c12', 'fa-star'],
            ['Guests', number_format($guestUsers), '#95a5a6', 'fa-user-secret'],
            ['Verified', number_format($verifiedUsers), '#28a745', 'fa-check-circle'],
            ['Active Viewers (7d)', number_format($activeViewers), '#e83e8c', 'fa-eye'],
        ];
        foreach ($cards as [$lbl, $val, $clr, $ico]) {
            $html .= "<div class='uc-card' style='border-left-color:{$clr}'><div class='uc-val' style='color:{$clr}'>{$val}</div><div class='uc-lbl'>{$lbl}</div><i class='fa {$ico} uc-icon'></i></div>";
        }
        $html .= '</div>';

        // Row 2: Platform cards
        $html .= '<div class="uc-row">';
        $p2 = [
            ['Ugflix', number_format($ugflixUsers), '#e74c3c', 'fa-play-circle'],
            ['Lugaflix', number_format($lugaflixUsers), '#3498db', 'fa-play-circle-o'],
            ['Android', number_format($androidUsers), '#28a745', 'fa-android'],
            ['iOS', number_format($iosUsers), '#555', 'fa-apple'],
            ['Total Views', number_format($totalViews), '#007bff', 'fa-eye'],
            ['Watch Hours', number_format($totalWatchHours) . 'h', '#e83e8c', 'fa-clock-o'],
            ['Avg Views/User', $avgViewsPerUser, '#fd7e14', 'fa-bar-chart'],
        ];
        foreach ($p2 as [$lbl, $val, $clr, $ico]) {
            $html .= "<div class='uc-card' style='border-left-color:{$clr}'><div class='uc-val' style='color:{$clr}'>{$val}</div><div class='uc-lbl'>{$lbl}</div><i class='fa {$ico} uc-icon'></i></div>";
        }
        $html .= '</div>';

        // Row 3: Charts
        $html .= '<div class="uc-row">';

        // 7-day signups bar
        $html .= '<div class="uc-box" style="flex:2;min-width:220px">';
        $html .= '<div class="uc-box-title">New Users — Last 7 Days</div>';
        $html .= '<div class="uc-bar-wrap">';
        foreach ($dailySignups as $ds) {
            $h = $maxDaily > 0 ? round(($ds['count'] / $maxDaily) * 55) : 0;
            $html .= "<div style='flex:1;text-align:center'><div style='height:{$h}px;background:#007bff;border-radius:2px 2px 0 0;margin:0 auto;width:80%'></div><div class='uc-bar-lbl'>{$ds['label']}<br><b>{$ds['count']}</b></div></div>";
        }
        $html .= '</div></div>';

        // App pie
        $html .= '<div class="uc-box" style="flex:1;min-width:140px;text-align:center">';
        $html .= '<div class="uc-box-title">App Type</div>';
        $ugDeg = round(($ugPct / 100) * 360);
        $lgDeg = round(($lgPct / 100) * 360);
        $html .= "<div class='uc-pie' style='background:conic-gradient(#e74c3c 0deg {$ugDeg}deg, #3498db {$ugDeg}deg " . ($ugDeg + $lgDeg) . "deg, #bdc3c7 " . ($ugDeg + $lgDeg) . "deg 360deg)'></div>";
        $html .= "<div class='uc-legend'><span style='background:#e74c3c'></span>Ugflix {$ugPct}% <span style='background:#3498db;margin-left:4px'></span>Lugaflix {$lgPct}%</div>";
        $html .= '</div>';

        // Device pie
        $html .= '<div class="uc-box" style="flex:1;min-width:140px;text-align:center">';
        $html .= '<div class="uc-box-title">Device OS</div>';
        $anDeg = round(($anPct / 100) * 360);
        $html .= "<div class='uc-pie' style='background:conic-gradient(#28a745 0deg {$anDeg}deg, #555 {$anDeg}deg 360deg)'></div>";
        $html .= "<div class='uc-legend'><span style='background:#28a745'></span>Android {$anPct}% <span style='background:#555;margin-left:4px'></span>iOS " . (100 - $anPct) . "%</div>";
        $html .= '</div>';

        $html .= '</div>'; // row 3
        $html .= '</div>'; // uc-wrap

        return $html;
    }

    /**
     * Grid with important columns, clean layout
     */
    protected function grid()
    {
        $grid = new Grid(new User());
        $grid->model()->orderBy('id', 'desc');

        $grid->quickSearch('name', 'username', 'email', 'phone_number');

        $grid->filter(function ($filter) {
            $filter->disableIdFilter();
            $filter->column(1/4, function ($filter) {
                $filter->like('name', 'Name');
                $filter->like('username', 'Username');
            });
            $filter->column(1/4, function ($filter) {
                $filter->like('email', 'Email');
                $filter->like('phone_number', 'Phone');
            });
            $filter->column(1/4, function ($filter) {
                $filter->equal('app_type', 'App Type')->select(['ugflix' => 'Ugflix', 'lugaflix' => 'Lugaflix']);
                $filter->equal('platform', 'Platform')->select(['android' => 'Android', 'ios' => 'iOS']);
            });
            $filter->column(1/4, function ($filter) {
                $filter->equal('is_guest', 'Guest')->select(['Yes' => 'Yes', 'No' => 'No']);
                $filter->equal('status', 'Status')->select(['active' => 'Active', 'inactive' => 'Inactive', 'banned' => 'Banned']);
            });
            $filter->between('created_at', 'Registered')->datetime();
        });

        $grid->column('id', 'ID')->width(50)->sortable();

        $grid->column('name', 'User')->display(function () {
            $avatar = $this->avatar ? "<img src='" . htmlspecialchars($this->avatar) . "' style='width:28px;height:28px;border-radius:50%;margin-right:6px;vertical-align:middle;object-fit:cover'>" : '';
            $name = htmlspecialchars($this->name ?? 'N/A');
            $email = htmlspecialchars($this->email ?? '');
            return "{$avatar}<strong>{$name}</strong><br><small class='text-muted'>{$email}</small>";
        })->sortable();

        $grid->column('phone_number', 'Phone')->display(function () {
            return htmlspecialchars($this->phone_number ?? $this->phone_number_2 ?? '—');
        });

        $grid->column('app_type', 'App')->sortable()
            ->filter(['ugflix' => 'Ugflix', 'lugaflix' => 'Lugaflix'])
            ->display(function ($v) {
                $colors = ['ugflix' => '#e74c3c', 'lugaflix' => '#3498db'];
                $clr = $colors[$v] ?? '#999';
                return "<span style='display:inline-block;padding:2px 8px;border-radius:3px;font-size:10px;font-weight:600;color:#fff;background:{$clr}'>" . ($v ?: '?') . "</span>";
            })
            ->editable('select', ['ugflix' => 'Ugflix', 'lugaflix' => 'Lugaflix']);

        $grid->column('platform', 'Device')->sortable()
            ->filter(['android' => 'Android', 'ios' => 'iOS'])
            ->display(function ($v) {
                $icon = $v === 'android' ? 'fa-android' : ($v === 'ios' ? 'fa-apple' : 'fa-mobile');
                $clr  = $v === 'android' ? '#28a745' : ($v === 'ios' ? '#555' : '#999');
                return "<i class='fa {$icon}' style='color:{$clr}'></i> " . ($v ?: '?');
            });

        // Subscription status
        $grid->column('subscription', 'Subscription')->display(function () {
            $sub = Subscription::where('user_id', $this->id)->orderBy('end_date_time', 'desc')->first();
            if (!$sub) {
                return "<span style='display:inline-block;padding:2px 8px;border-radius:3px;font-size:10px;font-weight:600;color:#fff;background:#95a5a6'>Free</span>";
            }
            $colors = ['Active' => '#28a745', 'Expired' => '#dc3545', 'Cancelled' => '#6c757d', 'Pending' => '#ffc107', 'Failed' => '#dc3545'];
            $clr = $colors[$sub->status] ?? '#999';
            $end = $sub->end_date_time ? Carbon::parse($sub->end_date_time)->format('d-M-y') : '';
            return "<span style='display:inline-block;padding:2px 8px;border-radius:3px;font-size:10px;font-weight:600;color:#fff;background:{$clr}'>{$sub->status}</span>"
                 . ($end ? "<br><small class='text-muted'>{$end}</small>" : '');
        });

        // View count
        $grid->column('views', 'Views')->display(function () {
            $cnt = MovieView::where('user_id', $this->id)->count();
            return $cnt > 0 ? "<span class='label label-info'>{$cnt}</span>" : '<small class="text-muted">0</small>';
        });

        $grid->column('is_guest', 'Guest')->display(function ($v) {
            return $v === 'Yes'
                ? '<span class="label label-warning" style="font-size:9px">Guest</span>'
                : '<span class="label label-success" style="font-size:9px">Reg</span>';
        });

        $grid->column('country', 'Location')->display(function () {
            $parts = array_filter([$this->city, $this->country]);
            return $parts ? implode(', ', $parts) : '<small class="text-muted">—</small>';
        });

        $grid->column('created_at', 'Registered')->sortable()->display(function ($v) {
            if (!$v) return '—';
            $d = Carbon::parse($v);
            return $d->format('d-M-Y') . '<br><small class="text-muted">' . $d->diffForHumans() . '</small>';
        });

        $grid->column('last_online_at', 'Last Active')->sortable()->display(function ($v) {
            if (!$v) return '<small class="text-muted">Never</small>';
            $d = Carbon::parse($v);
            return $d->format('d-M H:i') . '<br><small class="text-muted">' . $d->diffForHumans() . '</small>';
        });

        // Expand details + subscription history
        $grid->column('_detail', 'More')->expand(function ($model) {
            $subs = Subscription::where('user_id', $model->id)->orderBy('end_date_time', 'desc')->get();
            $viewCount = MovieView::where('user_id', $model->id)->count();
            $watchHrs = round(MovieView::where('user_id', $model->id)->sum('progress') / 3600, 1);

            $html = '<div style="padding:12px;background:#f9f9f9;border-radius:6px;font-size:12px">';

            // --- User Info Table ---
            $html .= '<table class="table table-bordered table-condensed" style="margin-bottom:10px">';
            $html .= '<tr><td style="width:140px;font-weight:bold">Username</td><td>' . htmlspecialchars($model->username ?? 'N/A') . '</td><td style="width:140px;font-weight:bold">Email Verified</td><td>' . ($model->email_verified ? 'Yes' : 'No') . '</td></tr>';
            $html .= '<tr><td style="font-weight:bold">Status</td><td>' . ($model->status ?? 'N/A') . '</td><td style="font-weight:bold">Safe Mode</td><td>' . ($model->safe_mode ?? 'N/A') . '</td></tr>';
            $html .= '<tr><td style="font-weight:bold">Views / Watch Hrs</td><td>' . $viewCount . ' / ' . $watchHrs . 'h</td><td style="font-weight:bold">Bio</td><td>' . htmlspecialchars(mb_substr($model->bio ?? '', 0, 80)) . '</td></tr>';
            $html .= '<tr><td style="font-weight:bold">Google ID</td><td>' . ($model->google_id ?? '—') . '</td><td style="font-weight:bold">Profile %</td><td>' . ($model->completed_profile_pct ?? 0) . '%</td></tr>';
            $html .= '</table>';

            // --- Subscription History ---
            $html .= '<div style="margin-top:6px;margin-bottom:6px;font-weight:700;font-size:13px;color:#333">📋 Subscription History (' . $subs->count() . ')</div>';
            if ($subs->isEmpty()) {
                $html .= '<div style="padding:8px 12px;background:#fff;border:1px dashed #ccc;border-radius:4px;color:#888;text-align:center">No subscriptions found for this user</div>';
            } else {
                $html .= '<table class="table table-bordered table-condensed" style="background:#fff">';
                $html .= '<thead><tr style="background:#eee;font-weight:700;font-size:11px">';
                $html .= '<th>#</th><th>Status</th><th>Plan</th><th>Days</th><th>Start</th><th>End</th><th>Amount</th><th>Payment</th><th>Auto-Renew</th><th>Created</th>';
                $html .= '</tr></thead><tbody>';
                foreach ($subs as $i => $sub) {
                    $sClr = [
                        'Active'    => '#28a745',
                        'Expired'   => '#dc3545',
                        'Cancelled' => '#fd7e14',
                        'Pending'   => '#ffc107',
                        'Failed'    => '#6c757d',
                    ][$sub->status] ?? '#999';
                    $sTxt = ($sub->status === 'Pending') ? '#333' : '#fff';
                    $badge = '<span style="display:inline-block;padding:2px 8px;border-radius:3px;font-size:10px;font-weight:600;color:' . $sTxt . ';background:' . $sClr . '">' . ($sub->status ?? '?') . '</span>';
                    $start = $sub->start_date_time ? Carbon::parse($sub->start_date_time)->format('M d, Y') : '—';
                    $end   = $sub->end_date_time ? Carbon::parse($sub->end_date_time)->format('M d, Y') : '—';
                    $amt   = $sub->amount_paid ? ($sub->currency ?? 'UGX') . ' ' . number_format($sub->amount_paid, 0) : '—';
                    $pay   = $sub->payment_method ?? '—';
                    if ($sub->payment_status) {
                        $pClr = ($sub->payment_status === 'completed' || $sub->payment_status === 'Completed') ? '#28a745' : '#888';
                        $pay .= ' <span style="color:' . $pClr . ';font-size:10px">(' . $sub->payment_status . ')</span>';
                    }
                    $renew = $sub->auto_renew ? '<span style="color:#28a745">✓</span>' : '<span style="color:#ccc">✗</span>';
                    $created = $sub->created_at ? Carbon::parse($sub->created_at)->format('M d, Y H:i') : '—';
                    $rowBg = ($i === 0 && $sub->status === 'Active') ? 'background:#f0fff0;' : '';
                    $html .= '<tr style="font-size:11px;' . $rowBg . '">';
                    $html .= '<td>' . ($i + 1) . '</td>';
                    $html .= '<td>' . $badge . '</td>';
                    $html .= '<td>' . ($sub->plan_id ?? '—') . '</td>';
                    $html .= '<td>' . ($sub->days ?? '—') . '</td>';
                    $html .= '<td>' . $start . '</td>';
                    $html .= '<td>' . $end . '</td>';
                    $html .= '<td style="font-weight:600">' . $amt . '</td>';
                    $html .= '<td>' . $pay . '</td>';
                    $html .= '<td style="text-align:center">' . $renew . '</td>';
                    $html .= '<td><small class="text-muted">' . $created . '</small></td>';
                    $html .= '</tr>';
                }
                $html .= '</tbody></table>';
            }

            $html .= '</div>';
            return $html;
        });

        $grid->export(function ($export) {
            $export->filename('Users_' . date('Y-m-d_H-i'));
        });

        return $grid;
    }

    /**
     * Show page
     */
    protected function detail($id)
    {
        $show = new Show(User::findOrFail($id));

        $show->divider('Account');
        $show->field('id', 'ID');
        $show->field('username', 'Username');
        $show->field('name', 'Name');
        $show->field('email', 'Email');
        $show->field('phone_number', 'Phone');
        $show->field('avatar', 'Avatar')->image();
        $show->field('app_type', 'App Type');
        $show->field('platform', 'Platform');
        $show->field('status', 'Status');
        $show->field('is_guest', 'Guest');
        $show->field('google_id', 'Google ID');

        $show->divider('Location');
        $show->field('country', 'Country');
        $show->field('state', 'State');
        $show->field('city', 'City');

        $show->divider('Profile');
        $show->field('first_name', 'First Name');
        $show->field('last_name', 'Last Name');
        $show->field('sex', 'Sex');
        $show->field('dob', 'DOB');
        $show->field('bio', 'Bio');
        $show->field('occupation', 'Occupation');
        $show->field('completed_profile_pct', 'Profile %');

        $show->divider('Subscription');
        $show->field('subscription_tier', 'Tier');
        $show->field('subscription_expires', 'Expires');

        $show->divider('Privacy & Consent');
        $show->field('safe_mode', 'Safe Mode');
        $show->field('content_filtering', 'Content Filtering');
        $show->field('terms_of_service_accepted', 'ToS Accepted');
        $show->field('privacy_policy_accepted', 'Privacy Accepted');
        $show->field('email_verified', 'Email Verified');
        $show->field('phone_verified', 'Phone Verified');

        $show->divider('Activity');
        $show->field('last_online_at', 'Last Online');
        $show->field('online_status', 'Online Status');
        $show->field('profile_views', 'Profile Views');
        $show->field('created_at', 'Registered');
        $show->field('updated_at', 'Updated');

        return $show;
    }

    /**
     * Form
     */
    protected function form()
    {
        $form = new Form(new User());

        $form->tab('Account', function ($form) {
            $form->select('app_type', 'App Type')->options(['ugflix' => 'Ugflix', 'lugaflix' => 'Lugaflix'])->default('ugflix');
            $form->text('username', 'Username');
            $form->text('name', 'Name');
            $form->email('email', 'Email');
            $form->password('password', 'Password');
            $form->image('avatar', 'Avatar');
            $form->text('status', 'Status')->default('active');
            $form->text('is_guest', 'Guest')->default('No');
            $form->text('google_id', 'Google ID');
        });

        $form->tab('Personal', function ($form) {
            $form->text('first_name', 'First Name');
            $form->text('last_name', 'Last Name');
            $form->text('phone_number', 'Phone');
            $form->text('phone_number_2', 'Phone 2');
            $form->text('sex', 'Sex');
            $form->date('dob', 'Date of Birth')->default(date('Y-m-d'));
            $form->textarea('bio', 'Bio');
            $form->text('tagline', 'Tagline');
            $form->text('occupation', 'Occupation');
            $form->text('education_level', 'Education');
        });

        $form->tab('Location', function ($form) {
            $form->text('country', 'Country');
            $form->text('state', 'State');
            $form->text('city', 'City');
            $form->text('address', 'Address');
            $form->decimal('latitude', 'Latitude');
            $form->decimal('longitude', 'Longitude');
        });

        $form->tab('Settings', function ($form) {
            $form->text('safe_mode', 'Safe Mode')->default('On');
            $form->text('content_filtering', 'Content Filtering')->default('On');
            $form->text('profile_visibility', 'Profile Visibility')->default('Public');
            $form->switch('email_verified', 'Email Verified');
            $form->switch('phone_verified', 'Phone Verified');
            $form->text('push_notifications', 'Push Notifications');
            $form->text('email_notifications', 'Email Notifications');
        });

        $form->tab('Subscription', function ($form) {
            $form->text('subscription_tier', 'Tier');
            $form->datetime('subscription_expires', 'Expires')->default(date('Y-m-d H:i:s'));
            $form->number('credits_balance', 'Credits');
        });

        return $form;
    }
}
