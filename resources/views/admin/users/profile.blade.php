<div class="content-wrapper">
<style>
/* ═══════════════════════════════════════════════════════
   USER PROFILE 360° VIEW — Self-contained styles
   ═══════════════════════════════════════════════════════ */
:root {
    --up-primary: #4361ee;
    --up-success: #2ec4b6;
    --up-warning: #f77f00;
    --up-danger: #ef233c;
    --up-info: #4cc9f0;
    --up-muted: #8d99ae;
    --up-dark: #2b2d42;
    --up-light: #f8f9fa;
    --up-card-shadow: 0 2px 12px rgba(0,0,0,.06);
    --up-radius: 10px;
}

.up-page { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif; color: #333; font-size: 13px; }

/* ── Header hero ──────────────────────────────────── */
.up-hero {
    background: linear-gradient(135deg, var(--up-dark) 0%, #3a3d5c 50%, var(--up-primary) 100%);
    border-radius: var(--up-radius);
    padding: 28px 32px;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 24px;
    margin-bottom: 20px;
    position: relative;
    overflow: hidden;
}
.up-hero::after {
    content: '';
    position: absolute; right: -80px; top: -80px;
    width: 260px; height: 260px;
    background: rgba(255,255,255,.04);
    border-radius: 50%;
}
.up-avatar-wrap {
    flex-shrink: 0;
    width: 96px; height: 96px;
    border-radius: 50%;
    border: 3px solid rgba(255,255,255,.3);
    overflow: hidden;
    background: rgba(255,255,255,.1);
    display: flex; align-items: center; justify-content: center;
}
.up-avatar-wrap img { width: 100%; height: 100%; object-fit: cover; }
.up-avatar-wrap .up-initials { font-size: 32px; font-weight: 700; color: rgba(255,255,255,.7); }
.up-hero-info h1 { margin: 0 0 4px; font-size: 22px; font-weight: 700; }
.up-hero-info p { margin: 0; font-size: 13px; color: rgba(255,255,255,.65); }
.up-hero-badges { display: flex; gap: 6px; margin-top: 8px; flex-wrap: wrap; }

/* ── Badges ───────────────────────────────────────── */
.up-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600;
    white-space: nowrap;
}
.up-badge-fill { color: #fff; }
.up-badge-outline { background: transparent; border: 1px solid rgba(255,255,255,.3); color: rgba(255,255,255,.8); }

/* ── Hero quick stats ─────────────────────────────── */
.up-hero-stats {
    margin-left: auto;
    display: flex; gap: 20px; z-index: 1;
}
.up-hero-stat { text-align: center; }
.up-hero-stat .val { font-size: 22px; font-weight: 800; }
.up-hero-stat .lbl { font-size: 10px; text-transform: uppercase; letter-spacing: .5px; color: rgba(255,255,255,.55); }

/* ── Cards ────────────────────────────────────────── */
.up-card {
    background: #fff;
    border-radius: var(--up-radius);
    box-shadow: var(--up-card-shadow);
    margin-bottom: 16px;
    overflow: hidden;
}
.up-card-head {
    padding: 14px 18px;
    border-bottom: 1px solid #eee;
    display: flex; align-items: center; justify-content: space-between;
}
.up-card-head h3 {
    margin: 0; font-size: 14px; font-weight: 700; color: var(--up-dark);
    display: flex; align-items: center; gap: 8px;
}
.up-card-head h3 i { font-size: 16px; opacity: .5; }
.up-card-head .up-card-count {
    background: var(--up-light); border-radius: 12px; padding: 2px 10px;
    font-size: 11px; font-weight: 700; color: var(--up-muted);
}
.up-card-body { padding: 16px 18px; }

/* ── KPI mini cards ───────────────────────────────── */
.up-kpi-row { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
.up-kpi {
    flex: 1; min-width: 120px;
    background: #fff; border-radius: var(--up-radius);
    box-shadow: var(--up-card-shadow);
    padding: 14px 16px;
    border-left: 4px solid var(--up-muted);
    transition: transform .15s, box-shadow .15s;
}
.up-kpi:hover { transform: translateY(-2px); box-shadow: 0 4px 16px rgba(0,0,0,.1); }
.up-kpi .k-val { font-size: 24px; font-weight: 800; line-height: 1; }
.up-kpi .k-lbl { font-size: 10px; text-transform: uppercase; letter-spacing: .4px; color: var(--up-muted); margin-top: 4px; }
.up-kpi .k-sub { font-size: 10px; color: var(--up-muted); margin-top: 2px; }

/* ── Info grid ────────────────────────────────────── */
.up-info-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 0; }
.up-info-item {
    padding: 10px 16px;
    border-bottom: 1px solid #f0f0f0;
    display: flex; justify-content: space-between; align-items: center;
}
.up-info-item:nth-child(odd) { background: #fafbfc; }
.up-info-lbl { font-size: 11px; text-transform: uppercase; letter-spacing: .3px; color: var(--up-muted); font-weight: 600; }
.up-info-val { font-size: 13px; font-weight: 600; color: var(--up-dark); text-align: right; max-width: 60%; word-break: break-all; }

/* ── Tables ───────────────────────────────────────── */
.up-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.up-table th {
    text-align: left; padding: 8px 12px; font-size: 10px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .5px; color: var(--up-muted);
    background: var(--up-light); border-bottom: 2px solid #eee;
}
.up-table td { padding: 8px 12px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
.up-table tr:hover td { background: #f8f9ff; }
.up-table .up-thumb {
    width: 36px; height: 36px; border-radius: 6px; object-fit: cover;
    background: #eee; vertical-align: middle;
}

/* ── Status badge ─────────────────────────────────── */
.up-status { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 10px; font-weight: 700; }
.up-status-active { background: #d4edda; color: #155724; }
.up-status-expired { background: #f8d7da; color: #721c24; }
.up-status-pending { background: #fff3cd; color: #856404; }
.up-status-free { background: #e2e3e5; color: #383d41; }
.up-status-banned { background: #f8d7da; color: #721c24; }
.up-status-online { background: #d4edda; color: #155724; }
.up-status-offline { background: #e2e3e5; color: #383d41; }
.up-status-guest { background: #fff3cd; color: #856404; }

/* ── Bar chart ────────────────────────────────────── */
.up-bar-chart { display: flex; align-items: flex-end; gap: 3px; height: 80px; padding-top: 10px; }
.up-bar {
    flex: 1; border-radius: 3px 3px 0 0;
    background: var(--up-primary); min-width: 12px;
    position: relative; cursor: default;
    transition: opacity .15s;
}
.up-bar:hover { opacity: .8; }
.up-bar-tooltip {
    display: none; position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%);
    background: var(--up-dark); color: #fff; padding: 3px 8px; border-radius: 4px;
    font-size: 10px; white-space: nowrap; margin-bottom: 4px; z-index: 10;
}
.up-bar:hover .up-bar-tooltip { display: block; }
.up-bar-labels { display: flex; gap: 3px; margin-top: 4px; }
.up-bar-labels span { flex: 1; text-align: center; font-size: 9px; color: var(--up-muted); min-width: 12px; }

/* ── Genre bars ───────────────────────────────────── */
.up-genre-bar-wrap { margin-bottom: 6px; }
.up-genre-row { display: flex; align-items: center; gap: 8px; }
.up-genre-name { width: 80px; font-size: 11px; font-weight: 600; text-align: right; color: var(--up-dark); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.up-genre-track { flex: 1; height: 18px; background: #f0f0f0; border-radius: 9px; overflow: hidden; }
.up-genre-fill { height: 100%; border-radius: 9px; display: flex; align-items: center; padding-left: 8px; font-size: 10px; font-weight: 700; color: #fff; min-width: 24px; }

/* ── Tabs ─────────────────────────────────────────── */
.up-tabs { display: flex; border-bottom: 2px solid #eee; margin-bottom: 0; padding: 0 18px; overflow-x: auto; gap: 0; }
.up-tab {
    padding: 10px 18px; font-size: 12px; font-weight: 600; color: var(--up-muted);
    border-bottom: 2px solid transparent; margin-bottom: -2px; cursor: pointer;
    white-space: nowrap; transition: color .2s, border-color .2s;
    background: none; border-top: none; border-left: none; border-right: none;
}
.up-tab:hover { color: var(--up-primary); }
.up-tab.active { color: var(--up-primary); border-bottom-color: var(--up-primary); }
.up-tab-panel { display: none; }
.up-tab-panel.active { display: block; }

/* ── Progress bar ─────────────────────────────────── */
.up-progress { height: 6px; background: #eee; border-radius: 3px; overflow: hidden; }
.up-progress-fill { height: 100%; border-radius: 3px; }

/* ── Consent dots ─────────────────────────────────── */
.up-consent-yes { color: var(--up-success); }
.up-consent-no { color: var(--up-danger); }
.up-consent-na { color: var(--up-muted); }

/* ── Responsive ───────────────────────────────────── */
@media (max-width: 768px) {
    .up-hero { flex-direction: column; text-align: center; padding: 20px; }
    .up-hero-stats { margin-left: 0; margin-top: 12px; }
    .up-hero-badges { justify-content: center; }
    .up-kpi-row { flex-direction: column; }
    .up-info-grid { grid-template-columns: 1fr; }
}
</style>

<div class="content-header">
    <div class="container-fluid">
        <div style="display:flex;align-items:center;gap:12px">
            <a href="{{ admin_url('users') }}" style="color:#666;font-size:18px" title="Back to Users"><i class="fa fa-arrow-left"></i></a>
            <h1 style="margin:0;font-size:18px">User Profile</h1>
            <span style="color:#999;font-size:13px">ID: {{ $user->id }}</span>
            <a href="{{ admin_url('users/' . $user->id . '/edit') }}" class="btn btn-sm btn-primary" style="margin-left:auto;border-radius:6px">
                <i class="fa fa-pencil"></i> Edit User
            </a>
        </div>
    </div>
</div>

<section class="content">
<div class="container-fluid up-page">

{{-- ═══════════════════════════════════════════════════════
     HERO HEADER
     ═══════════════════════════════════════════════════════ --}}
<div class="up-hero">
    <div class="up-avatar-wrap">
        @if($user->avatar)
            <img src="{{ $user->avatar }}" alt="Avatar">
        @else
            <span class="up-initials">{{ strtoupper(substr($user->name ?? '?', 0, 2)) }}</span>
        @endif
    </div>
    <div class="up-hero-info">
        <h1>{{ $user->name ?? 'No Name' }}</h1>
        <p>{{ $user->email ?? 'No email' }} &middot; {{ $user->phone_number ?? 'No phone' }}</p>
        <div class="up-hero-badges">
            @php
                $appColor = match($user->app_type) { 'ugflix' => '#ef233c', 'lugaflix' => '#4361ee', default => '#666' };
                $platIcon = $user->platform === 'ios' ? 'fa-apple' : 'fa-android';
                $subLabel = $activeSub ? 'Premium' : 'Free';
                $subColor = $activeSub ? '#2ec4b6' : '#8d99ae';
            @endphp
            <span class="up-badge up-badge-fill" style="background:{{ $appColor }}">{{ ucfirst($user->app_type ?? '?') }}</span>
            <span class="up-badge up-badge-fill" style="background:{{ $user->platform === 'ios' ? '#555' : '#28a745' }}"><i class="fa {{ $platIcon }}"></i> {{ ucfirst($user->platform ?? '?') }}</span>
            <span class="up-badge up-badge-fill" style="background:{{ $subColor }}">{{ $subLabel }}</span>
            @if($user->is_guest === 'Yes')
                <span class="up-badge up-badge-fill" style="background:var(--up-warning)">Guest</span>
            @endif
            @if($user->status === 'banned')
                <span class="up-badge up-badge-fill" style="background:var(--up-danger)">Banned</span>
            @elseif($user->status !== 'active')
                <span class="up-badge up-badge-outline">{{ ucfirst($user->status ?? 'Unknown') }}</span>
            @endif
            @if($user->google_id)
                <span class="up-badge up-badge-outline"><i class="fa fa-google"></i> Google</span>
            @endif
        </div>
    </div>
    <div class="up-hero-stats">
        <div class="up-hero-stat"><div class="val">{{ number_format($totalViews) }}</div><div class="lbl">Views</div></div>
        <div class="up-hero-stat"><div class="val">{{ $totalWatchHours }}h</div><div class="lbl">Watch Time</div></div>
        <div class="up-hero-stat"><div class="val">{{ number_format($totalDownloads) }}</div><div class="lbl">Downloads</div></div>
        <div class="up-hero-stat"><div class="val">{{ $accountAge }}</div><div class="lbl">Account Age</div></div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════
     KPI CARDS ROW
     ═══════════════════════════════════════════════════════ --}}
<div class="up-kpi-row">
    <div class="up-kpi" style="border-color:var(--up-primary)">
        <div class="k-val" style="color:var(--up-primary)">{{ number_format($totalViews) }}</div>
        <div class="k-lbl">Total Views</div>
        <div class="k-sub">{{ $avgViewsPerDay }}/day avg</div>
    </div>
    <div class="up-kpi" style="border-color:var(--up-info)">
        <div class="k-val" style="color:var(--up-info)">{{ $totalWatchHours }}h</div>
        <div class="k-lbl">Watch Hours</div>
    </div>
    <div class="up-kpi" style="border-color:var(--up-success)">
        <div class="k-val" style="color:var(--up-success)">{{ number_format($totalDownloads) }}</div>
        <div class="k-lbl">Downloads</div>
        <div class="k-sub">{{ $inAppDownloads }} in-app &middot; {{ $galleryDownloads }} gallery</div>
    </div>
    <div class="up-kpi" style="border-color:var(--up-warning)">
        <div class="k-val" style="color:var(--up-warning)">{{ number_format($totalLikes) }}</div>
        <div class="k-lbl">Likes</div>
    </div>
    <div class="up-kpi" style="border-color:#e83e8c">
        <div class="k-val" style="color:#e83e8c">{{ number_format($totalWishlist) }}</div>
        <div class="k-lbl">Wishlist</div>
    </div>
    <div class="up-kpi" style="border-color:#6f42c1">
        <div class="k-val" style="color:#6f42c1">{{ number_format($sentMessages + $receivedMessages) }}</div>
        <div class="k-lbl">Messages</div>
        <div class="k-sub">{{ $uniqueChats }} chats</div>
    </div>
    @if($totalSpent > 0)
    <div class="up-kpi" style="border-color:var(--up-success)">
        <div class="k-val" style="color:var(--up-success)">{{ number_format($totalSpent, 0) }}</div>
        <div class="k-lbl">Total Spent (UGX)</div>
    </div>
    @endif
</div>

{{-- ═══════════════════════════════════════════════════════
     MAIN TABBED CONTENT
     ═══════════════════════════════════════════════════════ --}}
<div class="up-card">
    <div class="up-tabs">
        <button class="up-tab active" data-tab="overview">Overview</button>
        <button class="up-tab" data-tab="activity">Watch Activity</button>
        <button class="up-tab" data-tab="downloads">Downloads</button>
        <button class="up-tab" data-tab="subscription">Subscription</button>
        <button class="up-tab" data-tab="social">Social & Chat</button>
        <button class="up-tab" data-tab="preferences">Preferences</button>
        <button class="up-tab" data-tab="security">Security & Privacy</button>
    </div>

    {{-- ─── TAB: Overview ─────────────────────────────── --}}
    <div class="up-tab-panel active" id="tab-overview">
        <div class="up-card-body">
            <div class="row">
                {{-- Left: User Info --}}
                <div class="col-md-6">
                    <div class="up-card" style="box-shadow:none;border:1px solid #eee">
                        <div class="up-card-head"><h3><i class="fa fa-user"></i> Personal Information</h3></div>
                        <div class="up-info-grid">
                            <div class="up-info-item"><span class="up-info-lbl">Full Name</span><span class="up-info-val">{{ $user->first_name }} {{ $user->last_name }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Username</span><span class="up-info-val">{{ $user->username }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Email</span><span class="up-info-val">{{ $user->email ?? '—' }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Phone</span><span class="up-info-val">{{ $user->phone_number ?? '—' }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Phone 2</span><span class="up-info-val">{{ $user->phone_number_2 ?? '—' }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Sex</span><span class="up-info-val">{{ $user->sex ?? '—' }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Date of Birth</span><span class="up-info-val">{{ $user->dob ?? '—' }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Occupation</span><span class="up-info-val">{{ $user->occupation ?? '—' }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Education</span><span class="up-info-val">{{ $user->education_level ?? '—' }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Religion</span><span class="up-info-val">{{ $user->religion ?? '—' }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Languages</span><span class="up-info-val">{{ $user->languages_spoken ?? '—' }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Bio</span><span class="up-info-val">{{ Str::limit($user->bio ?? '—', 120) }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Tagline</span><span class="up-info-val">{{ $user->tagline ?? '—' }}</span></div>
                        </div>
                    </div>
                </div>

                {{-- Right: Location + Account + Genre chart --}}
                <div class="col-md-6">
                    <div class="up-card" style="box-shadow:none;border:1px solid #eee">
                        <div class="up-card-head"><h3><i class="fa fa-map-marker"></i> Location & Account</h3></div>
                        <div class="up-info-grid">
                            <div class="up-info-item"><span class="up-info-lbl">Country</span><span class="up-info-val">{{ $user->country ?? '—' }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">State</span><span class="up-info-val">{{ $user->state ?? '—' }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">City</span><span class="up-info-val">{{ $user->city ?? '—' }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Address</span><span class="up-info-val">{{ $user->address ?? '—' }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Coordinates</span><span class="up-info-val">{{ $user->latitude && $user->longitude ? $user->latitude.', '.$user->longitude : '—' }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Status</span><span class="up-info-val"><span class="up-status up-status-{{ $user->status ?? 'active' }}">{{ ucfirst($user->status ?? 'Active') }}</span></span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Online</span><span class="up-info-val"><span class="up-status up-status-{{ strtolower($user->online_status ?? 'offline') }}">{{ $user->online_status ?? 'Offline' }}</span></span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Last Online</span><span class="up-info-val">{{ $user->last_online_at ? \Carbon\Carbon::parse($user->last_online_at)->format('d M Y H:i') : '—' }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Registered</span><span class="up-info-val">{{ $user->created_at ? $user->created_at->format('d M Y H:i') : '—' }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Google ID</span><span class="up-info-val">{{ $user->google_id ?? '—' }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Is Guest</span><span class="up-info-val">{{ $user->is_guest }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Profile %</span><span class="up-info-val">
                                <div class="up-progress" style="width:100px;display:inline-block;vertical-align:middle">
                                    <div class="up-progress-fill" style="width:{{ min($user->completed_profile_pct ?? 0, 100) }}%;background:var(--up-primary)"></div>
                                </div>
                                {{ $user->completed_profile_pct ?? 0 }}%
                            </span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Profile Views</span><span class="up-info-val">{{ number_format($user->profile_views ?? 0) }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Game Coins</span><span class="up-info-val">{{ number_format($user->game_coins_balance ?? 0) }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Games Played</span><span class="up-info-val">{{ number_format($user->total_games_played ?? 0) }} ({{ $user->total_games_won ?? 0 }} won)</span></div>
                        </div>
                    </div>

                    @if(count($genreBreakdown) > 0)
                    <div class="up-card" style="box-shadow:none;border:1px solid #eee;margin-top:16px">
                        <div class="up-card-head"><h3><i class="fa fa-bar-chart"></i> Favourite Genres</h3></div>
                        <div class="up-card-body">
                            @php $maxGenre = max(array_values($genreBreakdown)); $genreColors = ['#4361ee','#ef233c','#2ec4b6','#f77f00','#e83e8c','#6f42c1','#4cc9f0','#20c997','#fd7e14','#6610f2']; @endphp
                            @foreach($genreBreakdown as $genre => $cnt)
                                @php $pct = round(($cnt / $maxGenre) * 100); $clr = $genreColors[$loop->index % count($genreColors)]; @endphp
                                <div class="up-genre-bar-wrap">
                                    <div class="up-genre-row">
                                        <span class="up-genre-name">{{ $genre }}</span>
                                        <div class="up-genre-track">
                                            <div class="up-genre-fill" style="width:{{ $pct }}%;background:{{ $clr }}">{{ $cnt }}</div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Activity chart (30-day views) --}}
            @if(count($viewsByDay) > 0)
            <div class="up-card" style="box-shadow:none;border:1px solid #eee;margin-top:16px">
                <div class="up-card-head"><h3><i class="fa fa-line-chart"></i> Viewing Activity — Last 30 Days</h3></div>
                <div class="up-card-body">
                    @php
                        $chartDays = [];
                        for ($i = 29; $i >= 0; $i--) {
                            $d = \Carbon\Carbon::today()->subDays($i)->format('Y-m-d');
                            $chartDays[$d] = $viewsByDay[$d] ?? 0;
                        }
                        $maxBar = max(array_values($chartDays) ?: [1]);
                    @endphp
                    <div class="up-bar-chart">
                        @foreach($chartDays as $date => $cnt)
                            @php $h = $maxBar > 0 ? max(round(($cnt / $maxBar) * 70), ($cnt > 0 ? 4 : 1)) : 1; @endphp
                            <div class="up-bar" style="height:{{ $h }}px;{{ $cnt === 0 ? 'background:#e9ecef;' : '' }}">
                                <span class="up-bar-tooltip">{{ \Carbon\Carbon::parse($date)->format('d M') }}: {{ $cnt }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="up-bar-labels">
                        @foreach($chartDays as $date => $cnt)
                            <span>@if($loop->index % 5 === 0){{ \Carbon\Carbon::parse($date)->format('d') }}@endif</span>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- ─── TAB: Watch Activity ───────────────────────── --}}
    <div class="up-tab-panel" id="tab-activity">
        <div class="up-card-body">
            <h4 style="font-size:14px;font-weight:700;margin:0 0 12px">Recent Watch History <span style="color:var(--up-muted);font-weight:400">({{ $totalViews }} total)</span></h4>
            @if($recentViews->isEmpty())
                <p style="color:var(--up-muted);text-align:center;padding:30px 0">No watch history found</p>
            @else
            <table class="up-table">
                <thead><tr><th></th><th>Title</th><th>Genre</th><th>Type</th><th>Progress</th><th>Last Watched</th></tr></thead>
                <tbody>
                @foreach($recentViews as $v)
                    @php $m = $viewMovies[$v->movie_model_id] ?? null; @endphp
                    <tr>
                        <td><img class="up-thumb" src="{{ $m->thumbnail_url ?? '' }}" alt="" onerror="this.style.display='none'"></td>
                        <td style="font-weight:600">{{ $m->title ?? 'ID: '.$v->movie_model_id }}</td>
                        <td><span style="font-size:11px;color:var(--up-muted)">{{ Str::limit($m->genre ?? '—', 30) }}</span></td>
                        <td><span class="up-badge up-badge-fill" style="background:{{ ($m->type ?? '') === 'Series' ? '#6f42c1' : '#4361ee' }};font-size:10px">{{ $m->type ?? '?' }}</span></td>
                        <td>
                            @php $prog = round(($v->progress ?? 0) / 60, 1); @endphp
                            {{ $prog }}m
                        </td>
                        <td><small style="color:var(--up-muted)">{{ $v->updated_at ? $v->updated_at->format('d M Y H:i') : '—' }}</small></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>

    {{-- ─── TAB: Downloads ────────────────────────────── --}}
    <div class="up-tab-panel" id="tab-downloads">
        <div class="up-card-body">
            <div class="up-kpi-row" style="margin-bottom:20px">
                <div class="up-kpi" style="border-color:var(--up-success);box-shadow:none;border:1px solid #eee">
                    <div class="k-val" style="color:var(--up-success)">{{ $totalDownloads }}</div><div class="k-lbl">Total Downloads</div>
                </div>
                <div class="up-kpi" style="border-color:var(--up-primary);box-shadow:none;border:1px solid #eee">
                    <div class="k-val" style="color:var(--up-primary)">{{ $inAppDownloads }}</div><div class="k-lbl">In-App Downloads</div>
                </div>
                <div class="up-kpi" style="border-color:var(--up-warning);box-shadow:none;border:1px solid #eee">
                    <div class="k-val" style="color:var(--up-warning)">{{ $galleryDownloads }}</div><div class="k-lbl">Gallery Downloads</div>
                </div>
            </div>

            <h4 style="font-size:14px;font-weight:700;margin:0 0 12px">Recent Downloads</h4>
            @if($recentDownloads->isEmpty())
                <p style="color:var(--up-muted);text-align:center;padding:30px 0">No downloads found</p>
            @else
            <table class="up-table">
                <thead><tr><th>Title</th><th>Type</th><th>Status</th><th>Size</th><th>Date</th></tr></thead>
                <tbody>
                @foreach($recentDownloads as $dl)
                    <tr>
                        <td style="font-weight:600">{{ Str::limit($dl->title ?? 'ID: '.$dl->movie_model_id, 45) }}</td>
                        <td>
                            <span class="up-badge up-badge-fill" style="background:{{ $dl->download_type === 'gallery' ? 'var(--up-warning)' : 'var(--up-primary)' }};font-size:10px">
                                {{ $dl->download_type === 'gallery' ? 'Gallery' : 'In-App' }}
                            </span>
                        </td>
                        <td><span style="font-size:11px">{{ $dl->status ?? '—' }}</span></td>
                        <td><small style="color:var(--up-muted)">{{ $dl->file_size ? round($dl->file_size / 1048576, 1).'MB' : '—' }}</small></td>
                        <td><small style="color:var(--up-muted)">{{ $dl->created_at ? $dl->created_at->format('d M Y H:i') : '—' }}</small></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>

    {{-- ─── TAB: Subscription ─────────────────────────── --}}
    <div class="up-tab-panel" id="tab-subscription">
        <div class="up-card-body">
            {{-- Active sub card --}}
            <div style="background:{{ $activeSub ? 'linear-gradient(135deg,#d4edda,#c3e6cb)' : '#f8f9fa' }};border-radius:8px;padding:16px 20px;margin-bottom:20px;border:1px solid {{ $activeSub ? '#c3e6cb' : '#dee2e6' }}">
                <div style="display:flex;align-items:center;gap:12px">
                    <i class="fa {{ $activeSub ? 'fa-check-circle' : 'fa-times-circle' }}" style="font-size:28px;color:{{ $activeSub ? '#155724' : '#999' }}"></i>
                    <div>
                        <div style="font-size:16px;font-weight:700;color:{{ $activeSub ? '#155724' : '#666' }}">
                            {{ $activeSub ? 'Active Subscription' : 'No Active Subscription' }}
                        </div>
                        @if($activeSub)
                            <div style="font-size:12px;color:#155724">
                                {{ $activeSub->days ?? '?' }} days plan &middot;
                                Expires {{ $activeSub->end_date_time ? $activeSub->end_date_time->format('d M Y') : '?' }}
                                ({{ $activeSub->end_date_time ? $activeSub->end_date_time->diffForHumans() : '' }})
                                &middot; {{ ($activeSub->currency ?? 'UGX') . ' ' . number_format($activeSub->amount_paid ?? 0) }}
                            </div>
                        @else
                            <div style="font-size:12px;color:#999">This user has {{ $subscriptions->count() > 0 ? 'had ' . $subscriptions->count() . ' subscription(s) previously' : 'never subscribed' }}</div>
                        @endif
                    </div>
                    @if($totalSpent > 0)
                    <div style="margin-left:auto;text-align:right">
                        <div style="font-size:20px;font-weight:800;color:#155724">UGX {{ number_format($totalSpent, 0) }}</div>
                        <div style="font-size:10px;color:#666;text-transform:uppercase">Lifetime Spend</div>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Subscription history table --}}
            <h4 style="font-size:14px;font-weight:700;margin:0 0 12px">Subscription History <span style="color:var(--up-muted);font-weight:400">({{ $subscriptions->count() }})</span></h4>
            @if($subscriptions->isEmpty())
                <p style="color:var(--up-muted);text-align:center;padding:30px 0">No subscription records</p>
            @else
            <table class="up-table">
                <thead><tr><th>#</th><th>Status</th><th>Days</th><th>Start</th><th>End</th><th>Amount</th><th>Payment</th><th>Auto</th><th>Created</th></tr></thead>
                <tbody>
                @foreach($subscriptions as $i => $sub)
                    @php
                        $sClr = match($sub->status) { 'Active' => 'active', 'Expired' => 'expired', 'Pending' => 'pending', default => 'free' };
                    @endphp
                    <tr style="{{ $i === 0 && $sub->status === 'Active' ? 'background:#f0fff0' : '' }}">
                        <td>{{ $i + 1 }}</td>
                        <td><span class="up-status up-status-{{ $sClr }}">{{ $sub->status }}</span></td>
                        <td>{{ $sub->days ?? '—' }}</td>
                        <td><small>{{ $sub->start_date_time ? $sub->start_date_time->format('d M Y') : '—' }}</small></td>
                        <td><small>{{ $sub->end_date_time ? $sub->end_date_time->format('d M Y') : '—' }}</small></td>
                        <td style="font-weight:600">{{ $sub->amount_paid ? ($sub->currency ?? 'UGX').' '.number_format($sub->amount_paid, 0) : '—' }}</td>
                        <td>
                            {{ $sub->payment_method ?? '—' }}
                            @if($sub->payment_status)
                                <br><small style="color:{{ in_array($sub->payment_status, ['completed','Completed']) ? 'var(--up-success)' : 'var(--up-muted)' }}">{{ $sub->payment_status }}</small>
                            @endif
                        </td>
                        <td style="text-align:center">
                            @if($sub->auto_renew) <span style="color:var(--up-success)">&#10003;</span> @else <span style="color:#ccc">&#10007;</span> @endif
                        </td>
                        <td><small style="color:var(--up-muted)">{{ $sub->created_at ? $sub->created_at->format('d M Y H:i') : '—' }}</small></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            @endif

            {{-- Payment transactions --}}
            @if($transactions->count() > 0)
            <h4 style="font-size:14px;font-weight:700;margin:20px 0 12px">Payment Transactions <span style="color:var(--up-muted);font-weight:400">({{ $transactions->count() }})</span></h4>
            <table class="up-table">
                <thead><tr><th>ID</th><th>Status</th><th>Amount</th><th>Phone</th><th>Method</th><th>Reference</th><th>Date</th></tr></thead>
                <tbody>
                @foreach($transactions as $tx)
                    <tr>
                        <td>{{ $tx->id }}</td>
                        <td><span class="up-status up-status-{{ strtolower($tx->status ?? 'pending') === 'completed' ? 'active' : (strtolower($tx->status ?? '') === 'failed' ? 'expired' : 'pending') }}">{{ $tx->status }}</span></td>
                        <td style="font-weight:600">{{ ($tx->currency ?? 'UGX').' '.number_format($tx->amount ?? 0) }}</td>
                        <td><small>{{ $tx->phone_number ?? '—' }}</small></td>
                        <td><small>{{ $tx->payment_method ?? '—' }}</small></td>
                        <td><small style="color:var(--up-muted)">{{ Str::limit($tx->pesapal_merchant_reference ?? $tx->pesapal_tracking_id ?? '—', 24) }}</small></td>
                        <td><small style="color:var(--up-muted)">{{ $tx->created_at ? $tx->created_at->format('d M Y H:i') : '—' }}</small></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>

    {{-- ─── TAB: Social & Chat ────────────────────────── --}}
    <div class="up-tab-panel" id="tab-social">
        <div class="up-card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="up-card" style="box-shadow:none;border:1px solid #eee">
                        <div class="up-card-head"><h3><i class="fa fa-comments"></i> Chat Activity</h3></div>
                        <div class="up-info-grid" style="grid-template-columns:1fr">
                            <div class="up-info-item"><span class="up-info-lbl">Messages Sent</span><span class="up-info-val">{{ number_format($sentMessages) }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Messages Received</span><span class="up-info-val">{{ number_format($receivedMessages) }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Total Messages</span><span class="up-info-val" style="font-weight:800">{{ number_format($sentMessages + $receivedMessages) }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Unique Conversations</span><span class="up-info-val">{{ number_format($uniqueChats) }}</span></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="up-card" style="box-shadow:none;border:1px solid #eee">
                        <div class="up-card-head"><h3><i class="fa fa-shield"></i> Moderation</h3></div>
                        <div class="up-info-grid" style="grid-template-columns:1fr">
                            <div class="up-info-item"><span class="up-info-lbl">Reports Filed</span><span class="up-info-val">{{ $reportsMade }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Reports Against</span><span class="up-info-val" style="{{ $reportsAgainst > 0 ? 'color:var(--up-danger)' : '' }}">{{ $reportsAgainst }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Users Blocked</span><span class="up-info-val">{{ $blockedUsers }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Blocked By Others</span><span class="up-info-val" style="{{ $blockedBy > 0 ? 'color:var(--up-danger)' : '' }}">{{ $blockedBy }}</span></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Likes --}}
            <div class="up-card" style="box-shadow:none;border:1px solid #eee;margin-top:16px">
                <div class="up-card-head"><h3><i class="fa fa-heart"></i> Liked Movies</h3><span class="up-card-count">{{ $totalLikes }}</span></div>
                @if($recentLikes->isEmpty())
                    <div class="up-card-body"><p style="color:var(--up-muted);text-align:center;padding:12px 0">No likes</p></div>
                @else
                <div class="up-card-body" style="display:flex;flex-wrap:wrap;gap:8px">
                    @foreach($recentLikes as $lk)
                        @php $lm = $likeMovies[$lk->movie_model_id] ?? null; @endphp
                        <div style="display:flex;align-items:center;gap:6px;background:var(--up-light);padding:4px 10px 4px 4px;border-radius:6px;font-size:11px">
                            @if($lm && $lm->thumbnail_url)<img src="{{ $lm->thumbnail_url }}" style="width:24px;height:24px;border-radius:4px;object-fit:cover" onerror="this.style.display='none'">@endif
                            <span style="font-weight:600">{{ Str::limit($lm->title ?? 'ID:'.$lk->movie_model_id, 30) }}</span>
                        </div>
                    @endforeach
                </div>
                @endif
            </div>

            {{-- Wishlist --}}
            <div class="up-card" style="box-shadow:none;border:1px solid #eee;margin-top:16px">
                <div class="up-card-head"><h3><i class="fa fa-bookmark"></i> Wishlist</h3><span class="up-card-count">{{ $totalWishlist }}</span></div>
                @if($recentWishlist->isEmpty())
                    <div class="up-card-body"><p style="color:var(--up-muted);text-align:center;padding:12px 0">Empty wishlist</p></div>
                @else
                <div class="up-card-body" style="display:flex;flex-wrap:wrap;gap:8px">
                    @foreach($recentWishlist as $wl)
                        @php $wm = $wishMovies[$wl->movie_model_id] ?? null; @endphp
                        <div style="display:flex;align-items:center;gap:6px;background:var(--up-light);padding:4px 10px 4px 4px;border-radius:6px;font-size:11px">
                            @if($wm && $wm->thumbnail_url)<img src="{{ $wm->thumbnail_url }}" style="width:24px;height:24px;border-radius:4px;object-fit:cover" onerror="this.style.display='none'">@endif
                            <span style="font-weight:600">{{ Str::limit($wm->title ?? 'ID:'.$wl->movie_model_id, 30) }}</span>
                        </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ─── TAB: Preferences ──────────────────────────── --}}
    <div class="up-tab-panel" id="tab-preferences">
        <div class="up-card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="up-card" style="box-shadow:none;border:1px solid #eee">
                        <div class="up-card-head"><h3><i class="fa fa-sliders"></i> App Settings</h3></div>
                        <div class="up-info-grid" style="grid-template-columns:1fr">
                            <div class="up-info-item"><span class="up-info-lbl">Safe Mode</span><span class="up-info-val">{{ $user->safe_mode ?? '—' }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Content Filtering</span><span class="up-info-val">{{ $user->content_filtering ?? '—' }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Profile Visibility</span><span class="up-info-val">{{ $user->profile_visibility ?? '—' }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Push Notifications</span><span class="up-info-val">{{ $user->push_notifications ?? '—' }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Email Notifications</span><span class="up-info-val">{{ $user->email_notifications ?? '—' }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Location Sharing</span><span class="up-info-val">{{ $user->location_sharing ?? '—' }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Notification Prefs</span><span class="up-info-val">{{ $user->notification_preferences ?? '—' }}</span></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="up-card" style="box-shadow:none;border:1px solid #eee">
                        <div class="up-card-head"><h3><i class="fa fa-heart-o"></i> Matching Preferences</h3></div>
                        <div class="up-info-grid" style="grid-template-columns:1fr">
                            <div class="up-info-item"><span class="up-info-lbl">Looking For</span><span class="up-info-val">{{ $user->looking_for ?? '—' }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Interested In</span><span class="up-info-val">{{ $user->interested_in ?? '—' }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Age Range</span><span class="up-info-val">{{ ($user->age_range_min ?? '?') . ' – ' . ($user->age_range_max ?? '?') }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Max Distance</span><span class="up-info-val">{{ $user->max_distance_km ? $user->max_distance_km.'km' : '—' }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Body Type</span><span class="up-info-val">{{ $user->body_type ?? '—' }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Height</span><span class="up-info-val">{{ $user->height_cm ? $user->height_cm.'cm' : '—' }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Smoking</span><span class="up-info-val">{{ $user->smoking_habit ?? '—' }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Drinking</span><span class="up-info-val">{{ $user->drinking_habit ?? '—' }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Pet Preference</span><span class="up-info-val">{{ $user->pet_preference ?? '—' }}</span></div>
                        </div>
                    </div>

                    @if($user->is_imported === 'Yes')
                    <div class="up-card" style="box-shadow:none;border:1px solid #eee;margin-top:16px">
                        <div class="up-card-head"><h3><i class="fa fa-download"></i> Import Info</h3></div>
                        <div class="up-info-grid" style="grid-template-columns:1fr">
                            <div class="up-info-item"><span class="up-info-lbl">Import Source</span><span class="up-info-val">{{ $user->import_source ?? '—' }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">External URL</span><span class="up-info-val">{{ Str::limit($user->external_profile_url ?? '—', 40) }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Imported At</span><span class="up-info-val">{{ $user->imported_at ? \Carbon\Carbon::parse($user->imported_at)->format('d M Y H:i') : '—' }}</span></div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ─── TAB: Security & Privacy ───────────────────── --}}
    <div class="up-tab-panel" id="tab-security">
        <div class="up-card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="up-card" style="box-shadow:none;border:1px solid #eee">
                        <div class="up-card-head"><h3><i class="fa fa-lock"></i> Security</h3></div>
                        <div class="up-info-grid" style="grid-template-columns:1fr">
                            <div class="up-info-item"><span class="up-info-lbl">Email Verified</span><span class="up-info-val"><span class="{{ $user->email_verified ? 'up-consent-yes' : 'up-consent-no' }}"><i class="fa {{ $user->email_verified ? 'fa-check-circle' : 'fa-times-circle' }}"></i> {{ $user->email_verified ? 'Yes' : 'No' }}</span></span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Phone Verified</span><span class="up-info-val"><span class="{{ $user->phone_verified ? 'up-consent-yes' : 'up-consent-no' }}"><i class="fa {{ $user->phone_verified ? 'fa-check-circle' : 'fa-times-circle' }}"></i> {{ $user->phone_verified ? 'Yes' : 'No' }}</span></span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Failed Logins</span><span class="up-info-val" style="{{ ($user->failed_login_attempts ?? 0) > 5 ? 'color:var(--up-danger)' : '' }}">{{ $user->failed_login_attempts ?? 0 }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Last Password Change</span><span class="up-info-val">{{ $user->last_password_change ? \Carbon\Carbon::parse($user->last_password_change)->format('d M Y') : 'Never' }}</span></div>
                            <div class="up-info-item"><span class="up-info-lbl">Google Linked</span><span class="up-info-val"><span class="{{ $user->google_id ? 'up-consent-yes' : 'up-consent-na' }}"><i class="fa {{ $user->google_id ? 'fa-check-circle' : 'fa-minus-circle' }}"></i> {{ $user->google_id ? 'Yes' : 'No' }}</span></span></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="up-card" style="box-shadow:none;border:1px solid #eee">
                        <div class="up-card-head"><h3><i class="fa fa-file-text-o"></i> Legal Consent</h3></div>
                        <div class="up-info-grid" style="grid-template-columns:1fr">
                            @php
                                $consents = [
                                    ['Terms of Service', $user->terms_of_service_accepted, $user->terms_accepted_date],
                                    ['Privacy Policy', $user->privacy_policy_accepted, $user->privacy_accepted_date],
                                    ['Community Guidelines', $user->community_guidelines_accepted, $user->guidelines_accepted_date],
                                    ['Marketing Emails', $user->marketing_emails_consent, null],
                                    ['Data Processing', $user->data_processing_consent, null],
                                    ['Content Moderation', $user->content_moderation_consent, null],
                                    ['Analytics', $user->analytics_consent, null],
                                    ['Crash Reporting', $user->crash_reporting, null],
                                ];
                            @endphp
                            @foreach($consents as [$label, $val, $date])
                                <div class="up-info-item">
                                    <span class="up-info-lbl">{{ $label }}</span>
                                    <span class="up-info-val">
                                        @if($val === 'Yes')
                                            <span class="up-consent-yes"><i class="fa fa-check-circle"></i> Yes</span>
                                            @if($date) <small style="color:var(--up-muted);margin-left:4px">{{ \Carbon\Carbon::parse($date)->format('d M Y') }}</small> @endif
                                        @elseif($val === 'No')
                                            <span class="up-consent-no"><i class="fa fa-times-circle"></i> No</span>
                                        @else
                                            <span class="up-consent-na"><i class="fa fa-minus-circle"></i> —</span>
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            {{-- Notification tracking --}}
            <div class="up-card" style="box-shadow:none;border:1px solid #eee;margin-top:16px">
                <div class="up-card-head"><h3><i class="fa fa-bell"></i> Notification Tracking</h3></div>
                <div class="up-info-grid">
                    <div class="up-info-item"><span class="up-info-lbl">Last Trending Notification</span><span class="up-info-val">{{ $user->last_trending_notification_sent ? \Carbon\Carbon::parse($user->last_trending_notification_sent)->format('d M Y H:i') : 'Never' }}</span></div>
                    <div class="up-info-item"><span class="up-info-lbl">Trending Period</span><span class="up-info-val">{{ $user->last_trending_notification_period ?? '—' }}</span></div>
                    <div class="up-info-item"><span class="up-info-lbl">Today's Notifications</span><span class="up-info-val">{{ $user->trending_notifications_today ?? 0 }} / {{ $user->max_trending_notifications_per_day ?? 4 }}</span></div>
                </div>
            </div>
        </div>
    </div>
</div>{{-- end card --}}

</div>{{-- end container --}}
</section>
</div>{{-- end content-wrapper --}}

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.up-tab').forEach(function(tab) {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.up-tab').forEach(function(t) { t.classList.remove('active'); });
            document.querySelectorAll('.up-tab-panel').forEach(function(p) { p.classList.remove('active'); });
            tab.classList.add('active');
            document.getElementById('tab-' + tab.getAttribute('data-tab')).classList.add('active');
        });
    });
});
</script>
