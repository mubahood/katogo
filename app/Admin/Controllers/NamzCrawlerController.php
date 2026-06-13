<?php

namespace App\Admin\Controllers;

use App\Models\MovieModel;
use App\Models\MyCounter;
use App\Models\NamzCrawlLog;
use App\Models\NamzCrawlSession;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Column;
use Encore\Admin\Layout\Content;
use Encore\Admin\Layout\Row;
use Encore\Admin\Widgets\Box;
use Encore\Admin\Widgets\Table;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NamzCrawlerController extends AdminController
{
    protected $title = 'Namz Crawler';

    const NAMZ_SITE = 'https://namzentertainment.com';

    // ── Shared styles/modals injected once per page ───────────────────────────

    private function sharedAssets(bool $isRunning = false): string
    {
        $jsIsRunning = $isRunning ? 'true' : 'false';

        Admin::style(<<<'CSS'
.kt-stat-row{display:flex;flex-wrap:wrap;gap:12px;margin-bottom:18px}
.kt-stat{flex:1 1 160px;background:#fff;border-radius:6px;padding:14px 18px;border-left:4px solid #2980b9;box-shadow:0 1px 4px rgba(0,0,0,.08)}
.kt-stat .val{font-size:26px;font-weight:700;color:#2c3e50}
.kt-stat .lbl{font-size:12px;color:#7f8c8d;margin-top:2px}
.kt-tbtn{display:inline-block;padding:6px 14px;border-radius:4px;color:#fff!important;font-size:13px;text-decoration:none!important;margin-right:6px;margin-bottom:6px;cursor:pointer;border:none;line-height:1.5}
.kt-tbtn-green{background:#27ae60}.kt-tbtn-red{background:#e74c3c}.kt-tbtn-blue{background:#2980b9}
.kt-tbtn-orange{background:#e67e22}.kt-tbtn-purple{background:#8e44ad}.kt-tbtn-gray{background:#7f8c8d}.kt-tbtn-dark{background:#34495e}
.kt-tbtn:hover{opacity:.85}
.kt-prog{height:10px;border-radius:5px;background:#ecf0f1;overflow:hidden;margin:6px 0}
.kt-prog-fill{height:100%;background:linear-gradient(90deg,#2980b9,#27ae60);transition:width .5s}
.kt-thumb{width:54px;height:38px;object-fit:cover;border-radius:3px;vertical-align:middle}
.kt-overlay{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.65);z-index:9990;align-items:center;justify-content:center}
.kt-overlay.show{display:flex}
.kt-modal{background:#fff;border-radius:8px;padding:24px;position:relative;width:90%;max-width:660px;max-height:88vh;overflow-y:auto;box-shadow:0 10px 40px rgba(0,0,0,.35)}
.kt-modal-vid{background:#111;max-width:820px;padding:0}
.kt-modal-vid video{width:100%;max-height:460px;display:block}
.kt-modal-vid .vid-head{padding:10px 14px;color:#eee;display:flex;justify-content:space-between;align-items:center}
.kt-mclose{position:absolute;top:10px;right:14px;font-size:24px;cursor:pointer;color:#999;background:none;border:none;line-height:1}
.probe-out{background:#1e1e1e;color:#d4d4d4;border-radius:4px;padding:12px;font-family:monospace;font-size:12px;white-space:pre-wrap;max-height:280px;overflow-y:auto;margin-top:10px;display:none}
.pending-tbl td{vertical-align:middle!important;font-size:13px}
.pending-tbl .thumb-cell{width:60px}
.kt-badge{padding:2px 8px;border-radius:3px;color:#fff;font-size:11px;font-weight:600}
.kt-badge-green{background:#27ae60}.kt-badge-orange{background:#e67e22}.kt-badge-red{background:#e74c3c}.kt-badge-blue{background:#2980b9}
.detail-poster{width:110px;height:155px;object-fit:cover;border-radius:5px;flex-shrink:0}
.detail-meta p{margin:4px 0;font-size:13px}
CSS);

        $js = <<<'JSBLOCK'
// Move overlays to document.body — escapes any parent CSS constraints
document.querySelectorAll('body > .kt-overlay').forEach(function(el){ el.remove(); });
document.querySelectorAll('.kt-overlay').forEach(function(el){ document.body.appendChild(el); });

var _setUrlMovieId = null;
window.playMovie = function(url, title) {
  document.getElementById('vidTitle').textContent = title || '';
  document.getElementById('vidSrc').src = url;
  document.getElementById('vidPlayer').load();
  document.getElementById('vidOverlay').classList.add('show');
};
window.closeVid = function() {
  document.getElementById('vidPlayer').pause();
  document.getElementById('vidOverlay').classList.remove('show');
};
window.showDetail = function(movieId) {
  document.getElementById('detailBody').innerHTML = '<div style="text-align:center;padding:30px"><i class="fa fa-spinner fa-spin fa-2x"></i></div>';
  document.getElementById('detailOverlay').classList.add('show');
  fetch('/namz-crawler/movie-info/' + movieId)
    .then(r => r.json())
    .then(window.renderDetail)
    .catch(e => { document.getElementById('detailBody').innerHTML = '<p class="text-danger">Error: ' + e + '</p>'; });
};
window.renderDetail = function(m) {
  var safeTitle = (m.title || '—').replace(/'/g, "\\'");
  var urlRow = m.url
    ? '<p><b>URL:</b> <a href="' + m.url + '" target="_blank" style="word-break:break-all;font-size:11px">' + m.url.substr(0,70) + (m.url.length>70?'…':'') + '</a>'
      + ' <button onclick="window.playMovie(\'' + m.url.replace(/'/g,"\\'").replace(/"/g,'&quot;') + '\',\'' + safeTitle + '\')" class="btn btn-xs btn-success" style="margin-left:6px">&#9654; Play</button></p>'
    : '<p><b>URL:</b> <em style="color:#e74c3c">Not found — pending</em></p>';
  var poster = m.thumbnail
    ? '<img src="' + m.thumbnail + '" class="detail-poster" onerror="this.style.display=\'none\'">'
    : '<div style="width:110px;height:155px;background:#ecf0f1;border-radius:5px;flex-shrink:0;display:flex;align-items:center;justify-content:center;color:#bbb"><i class="fa fa-film fa-2x"></i></div>';
  var statusBadge = m.status === 'Active' ? 'kt-badge-green' : 'kt-badge-orange';
  var urlStatusBadge = m.namz_url_status === 'found' ? 'kt-badge-green' : 'kt-badge-orange';
  var descHtml = m.description ? '<p style="font-size:12px;color:#666;margin-top:6px">' + m.description.substr(0,240) + (m.description.length>240?'…':'') + '</p>' : '';
  document.getElementById('detailBody').innerHTML =
    '<div style="display:flex;gap:16px;margin-bottom:16px">'
    + poster
    + '<div class="detail-meta" style="flex:1;min-width:0">'
    +   '<h4 style="margin-top:0;margin-bottom:8px">' + (m.title||'—') + '</h4>'
    +   '<p><b>Status:</b> <span class="kt-badge ' + statusBadge + '">' + (m.status||'—') + '</span>'
    +   ' &nbsp;<b>Type:</b> ' + (m.type||'—') + '</p>'
    +   '<p><b>Genre:</b> ' + (m.genre||'—') + ' &nbsp;<b>Year:</b> ' + (m.year||'—') + ' &nbsp;<b>Duration:</b> ' + (m.duration?m.duration+' min':'—') + '</p>'
    +   '<p><b>Platform:</b> ' + (m.platform_type||'—') + ' &nbsp;<b>Namz ID:</b> <a href="https://namzentertainment.com/prev.php?id=' + m.namz_id + '" target="_blank">#' + m.namz_id + ' &#8599;</a></p>'
    +   '<p><b>URL Status:</b> <span class="kt-badge ' + urlStatusBadge + '">' + (m.namz_url_status||'—') + '</span></p>'
    +   urlRow
    +   descHtml
    +   '<div style="margin-top:12px;display:flex;flex-wrap:wrap;gap:6px">'
    +     '<button onclick="window.openSetUrl(' + m.id + ',\'' + safeTitle + '\')" class="btn btn-xs btn-primary">&#9998; Set URL</button>'
    +     '<button onclick="window.runProbeFor(' + m.namz_id + ')" class="btn btn-xs btn-warning">&#128269; Probe URL</button>'
    +     '<a href="https://namzentertainment.com/prev.php?id=' + m.namz_id + '" target="_blank" class="btn btn-xs btn-default">Open on Namz &#8599;</a>'
    +     '<a href="/movie-models/' + m.id + '/edit" target="_blank" class="btn btn-xs btn-default">Edit Movie</a>'
    +   '</div>'
    + '</div>'
    + '</div>';
};
window.closeDetail = function() { document.getElementById('detailOverlay').classList.remove('show'); };
window.openSetUrl = function(movieId, title) {
  _setUrlMovieId = movieId;
  document.getElementById('setUrlMovieName').textContent = title || ('Movie #' + movieId);
  document.getElementById('setUrlInput').value = '';
  document.getElementById('setUrlMsg').textContent = '';
  document.getElementById('setUrlOverlay').classList.add('show');
};
window.closeSetUrl = function() { document.getElementById('setUrlOverlay').classList.remove('show'); };
window.saveUrlAjax = function() {
  var url = document.getElementById('setUrlInput').value.trim();
  if (!url) { alert('Please enter a URL'); return; }
  var msg = document.getElementById('setUrlMsg');
  msg.textContent = 'Saving…';
  var token = document.querySelector('meta[name=csrf-token]') ? document.querySelector('meta[name=csrf-token]').content : '';
  fetch('/namz-crawler/set-url-ajax/' + _setUrlMovieId, {
    method: 'POST',
    headers: {'Content-Type':'application/json','X-CSRF-TOKEN': token || window.getCsrfToken()},
    body: JSON.stringify({video_url: url})
  })
  .then(r => r.json())
  .then(function(data) {
    if (data.success) {
      msg.innerHTML = '<span style="color:#27ae60">&#10003; Saved &amp; activated!</span>';
      setTimeout(function() { window.closeSetUrl(); location.reload(); }, 1200);
    } else {
      msg.innerHTML = '<span style="color:#e74c3c">Error: ' + data.message + '</span>';
    }
  })
  .catch(function(e) { msg.innerHTML = '<span style="color:#e74c3c">Error: ' + e + '</span>'; });
};
window.getCsrfToken = function() {
  var m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
  return m ? decodeURIComponent(m[1]) : '';
};
window.runProbe = function() {
  var inp = document.getElementById('probeIdInput');
  if (!inp || !inp.value) { alert('Enter a Namz ID'); return; }
  window.runProbeFor(parseInt(inp.value));
};
window.runProbeFor = function(namzId) {
  var out = document.getElementById('probeOut');
  var btn = document.getElementById('probeBtn');
  var inp = document.getElementById('probeIdInput');
  if (inp) inp.value = namzId;
  if (out) { out.style.display = 'block'; out.textContent = 'Probing ID ' + namzId + '…'; }
  if (btn) btn.disabled = true;
  fetch('/namz-crawler/probe-ajax/' + namzId)
    .then(r => r.json())
    .then(function(data) {
      if (out) out.textContent = JSON.stringify(data, null, 2);
      if (btn) btn.disabled = false;
      var found = data.found_url;
      if (found && data.movie_id) {
        if (confirm('URL found: ' + found + '\n\nSave and activate this movie?')) {
          window.openSetUrl(data.movie_id, data.title || '');
          document.getElementById('setUrlInput').value = found;
        }
      }
    })
    .catch(function(e) { if (out) out.textContent = 'Error: ' + e; if (btn) btn.disabled = false; });
};
window.openMoviePlayer = function(movieId) {
  fetch('/namz-crawler/movie-info/' + movieId)
    .then(r => r.json())
    .then(function(m) {
      if (m.url) { window.playMovie(m.url, m.title || ''); }
      else { alert('No video URL found for this movie.'); }
    })
    .catch(function(e) { alert('Error loading movie: ' + e); });
};
window.toggleActiveMovies = function() {
  var s = document.getElementById('activeNamzSection');
  if (s) s.style.display = s.style.display === 'none' ? 'block' : 'none';
};
(function() {
  var isRunning = __ISRUNNING__;
  if (!isRunning) return;
  function setLive(id, val) { var el = document.getElementById(id); if (el) el.textContent = val; }
  function refresh() {
    fetch('/namz-crawler/live').then(r => r.json()).then(function(d) {
      if (!d.running) return;
      setLive('liveCurrentId', d.id_current + ' / ' + d.id_to);
      setLive('liveProgress', d.progress + '%');
      setLive('liveProcessed', d.total_processed);
      setLive('liveSuccess', d.total_success);
      setLive('liveFailed', d.total_failed);
      setLive('liveNew', d.total_new);
      setLive('livePending', d.url_pending);
      setLive('liveDuration', d.duration);
      var fill = document.getElementById('liveProgressFill');
      if (fill) fill.style.width = d.progress + '%';
    }).catch(function(){});
  }
  setInterval(refresh, 12000);
})();
JSBLOCK;
        Admin::script(str_replace('__ISRUNNING__', $jsIsRunning, $js));

        return <<<'HTML'
<!-- ── VIDEO PLAYER MODAL ─────────────────────────────────────── -->
<div id="vidOverlay" class="kt-overlay" onclick="if(event.target===this)window.closeVid()">
  <div class="kt-modal kt-modal-vid">
    <div class="vid-head">
      <span id="vidTitle" style="font-weight:600"></span>
      <button onclick="window.closeVid()" style="background:none;border:none;color:#ccc;font-size:22px;cursor:pointer">&times;</button>
    </div>
    <video id="vidPlayer" controls autoplay>
      <source id="vidSrc" src="" type="video/mp4">
    </video>
  </div>
</div>

<!-- ── MOVIE DETAIL MODAL ─────────────────────────────────────── -->
<div id="detailOverlay" class="kt-overlay" onclick="if(event.target===this)window.closeDetail()">
  <div class="kt-modal" style="max-width:700px">
    <button class="kt-mclose" onclick="window.closeDetail()">&times;</button>
    <div id="detailBody">Loading…</div>
  </div>
</div>

<!-- ── SET URL MODAL ──────────────────────────────────────────── -->
<div id="setUrlOverlay" class="kt-overlay" onclick="if(event.target===this)window.closeSetUrl()">
  <div class="kt-modal" style="max-width:540px">
    <button class="kt-mclose" onclick="window.closeSetUrl()">&times;</button>
    <h4 style="margin-top:0">Set Video URL</h4>
    <p id="setUrlMovieName" style="font-weight:600;margin-bottom:12px"></p>
    <p style="font-size:12px;color:#666;margin-bottom:10px">
      The video URL cannot be automatically extracted (site uses obfuscated JS).<br>
      Open the namz page, play the video, then use browser DevTools &rarr; Network tab &rarr; filter by "mp4" or "m3u8" to find the direct URL.
    </p>
    <div class="form-group">
      <label>Direct Video URL (.mp4 / .m3u8)</label>
      <input type="url" id="setUrlInput" class="form-control" placeholder="https://...">
    </div>
    <div style="display:flex;align-items:center;gap:10px;margin-top:12px">
      <button onclick="window.saveUrlAjax()" class="btn btn-success">Save &amp; Activate</button>
      <button onclick="window.closeSetUrl()" class="btn btn-default">Cancel</button>
      <span id="setUrlMsg" style="font-size:12px"></span>
    </div>
  </div>
</div>

<!-- ── START CRAWL MODAL ──────────────────────────────────────── -->
<div id="startOverlay" class="kt-overlay" onclick="if(event.target===this)document.getElementById('startOverlay').classList.remove('show')">
  <div class="kt-modal" style="max-width:480px">
    <button class="kt-mclose" onclick="document.getElementById('startOverlay').classList.remove('show')">&times;</button>
    <h4 style="margin-top:0"><i class="fa fa-play"></i> Start New Crawl Session</h4>
    <form method="GET" action="/namz-crawler/start" onsubmit="return confirm('Start crawl now?')">
      <div class="form-group">
        <label>From ID <small>(first namz ID to process)</small></label>
        <input type="number" name="from" value="47" class="form-control" min="1">
      </div>
      <div class="form-group">
        <label>To ID</label>
        <input type="number" name="to" value="9300" class="form-control" min="1">
      </div>
      <div class="form-group">
        <label>Delay between requests (ms)</label>
        <input type="number" name="delay" value="800" class="form-control" min="200" max="5000">
      </div>
      <div class="form-group">
        <label>Batch size (IDs before extra pause)</label>
        <input type="number" name="batch" value="50" class="form-control" min="10" max="200">
      </div>
      <button type="submit" class="btn btn-success btn-block" style="margin-top:14px">
        <i class="fa fa-play"></i> Start Crawl
      </button>
    </form>
  </div>
</div>
HTML;
    }

    // ── Dashboard ─────────────────────────────────────────────────────────────

    public function dashboard(Content $content): Content
    {
        $session  = NamzCrawlSession::activeSession();
        $lastSess = NamzCrawlSession::lastSession();

        $totalNamz     = MovieModel::where('platform_type', 'namz')->count();
        $activeNamz    = MovieModel::where('platform_type', 'namz')->where('status', 'Active')->count();
        $urlPending    = MovieModel::where('namz_url_status', 'pending')->count();
        $urlFound      = MovieModel::where('namz_url_status', 'found')->count();
        $totalSessions = NamzCrawlSession::count();
        $failedLogs    = NamzCrawlLog::where('status', 'failed')->count();
        $isRunning     = $session !== null;
        $pctFound      = $totalNamz > 0 ? round(($urlFound / $totalNamz) * 100) : 0;

        // ── Why no URLs? explanation box ────────────────────────────────────
        $urlExplain = "
        <div style='background:#fff8e1;border:1px solid #ffe082;border-radius:5px;padding:12px 16px;margin-bottom:16px'>
          <h5 style='margin:0 0 6px;color:#f57f17'><i class='fa fa-info-circle'></i> Why most movies show \"Video: Not Found\"</h5>
          <p style='margin:0;font-size:13px;color:#555'>
            <b>namzentertainment.com intentionally hides video URLs.</b> The page HTML always has empty <code>&lt;source src=\"\"&gt;</code>.
            The actual video URL is loaded at runtime by a heavily obfuscated JavaScript file (<code>set.js</code>) that makes
            authenticated API calls the browser handles dynamically. PHP cannot execute this JS.<br><br>
            <b>Solutions available below:</b><br>
            1. <b>Probe Tool</b> — tests ~12 common API endpoint patterns with your authenticated session to try to discover the URL automatically.<br>
            2. <b>Set URL manually</b> — open the movie on Namz in a browser, play it, then copy the video URL from DevTools → Network → filter by <code>.mp4</code> or <code>.m3u8</code>.<br>
            3. The <b>Activate Movies With URL</b> button activates any namz movies where a URL was previously saved.
          </p>
        </div>";

        // ── Stat cards ──────────────────────────────────────────────────────
        $stats = "
        <div class='kt-stat-row'>
          <div class='kt-stat' style='border-color:#27ae60'><div class='val'>{$totalNamz}</div><div class='lbl'>Total Namz Movies</div></div>
          <div class='kt-stat' style='border-color:#2980b9'><div class='val'>{$activeNamz}</div><div class='lbl'>Active (URL Found)</div></div>
          <div class='kt-stat' style='border-color:#e67e22'><div class='val'>{$urlPending}</div><div class='lbl'>URL Pending</div></div>
          <div class='kt-stat' style='border-color:#16a085'><div class='val'>{$pctFound}%</div><div class='lbl'>URL Resolution Rate</div></div>
          <div class='kt-stat' style='border-color:#8e44ad'><div class='val'>{$totalSessions}</div><div class='lbl'>Crawl Sessions</div></div>
          <div class='kt-stat' style='border-color:#c0392b'><div class='val'>{$failedLogs}</div><div class='lbl'>Failed Log Entries</div></div>
        </div>
        <div style='margin-bottom:16px'>
          <div style='display:flex;justify-content:space-between;font-size:12px;color:#7f8c8d;margin-bottom:4px'>
            <span>URL Resolution Progress</span><span>{$urlFound} / {$totalNamz} movies resolved</span>
          </div>
          <div class='kt-prog'><div class='kt-prog-fill' style='width:{$pctFound}%'></div></div>
        </div>";

        // ── Active session live block ────────────────────────────────────────
        $sessionHtml = '';
        if ($isRunning && $session) {
            $pct = $session->progress_percent;
            $sessionHtml = "
            <div class='box box-info' style='margin-bottom:16px'>
              <div class='box-header with-border'>
                <h3 class='box-title'><i class='fa fa-spin fa-circle-o-notch'></i> Crawl Running — Session #{$session->id}</h3>
              </div>
              <div class='box-body'>
                <div style='display:flex;gap:18px;flex-wrap:wrap;font-size:13px;margin-bottom:8px'>
                  <div><b>ID:</b> <span id='liveCurrentId'>{$session->id_current} / {$session->id_to}</span></div>
                  <div><b>Progress:</b> <span id='liveProgress'>{$pct}%</span></div>
                  <div><b>Processed:</b> <span id='liveProcessed'>{$session->total_processed}</span></div>
                  <div><b>Success:</b> <span id='liveSuccess'>{$session->total_success}</span></div>
                  <div><b>Failed:</b> <span id='liveFailed'>{$session->total_failed}</span></div>
                  <div><b>New Movies:</b> <span id='liveNew'>{$session->total_new_movies}</span></div>
                  <div><b>URL Pending:</b> <span id='livePending'>{$session->total_url_pending}</span></div>
                  <div><b>Duration:</b> <span id='liveDuration'>{$session->duration}</span></div>
                </div>
                <div class='kt-prog'><div class='kt-prog-fill' id='liveProgressFill' style='width:{$pct}%'></div></div>
                <p style='font-size:11px;color:#7f8c8d;margin:6px 0 0'>Auto-refreshes every 12 seconds</p>
              </div>
            </div>";
        } elseif ($lastSess) {
            $badge = $lastSess->status_badge;
            $sessionHtml = "
            <div class='box box-default' style='margin-bottom:16px'>
              <div class='box-header'><h3 class='box-title'>Last Session #{$lastSess->id} — {$badge}</h3></div>
              <div class='box-body'>
                <div style='display:flex;gap:18px;flex-wrap:wrap;font-size:13px'>
                  <div><b>Range:</b> {$lastSess->id_from} → {$lastSess->id_to}</div>
                  <div><b>Processed:</b> {$lastSess->total_processed}</div>
                  <div><b>Success:</b> {$lastSess->total_success}</div>
                  <div><b>New Movies:</b> {$lastSess->total_new_movies}</div>
                  <div><b>URL Pending:</b> {$lastSess->total_url_pending}</div>
                  <div><b>Duration:</b> {$lastSess->duration}</div>
                </div>
              </div>
            </div>";
        }

        // ── Action toolbar ────────────────────────────────────────────────────
        $startDisabled = $isRunning ? 'opacity:.45;pointer-events:none' : '';
        $stopDisabled  = !$isRunning ? 'opacity:.45;pointer-events:none' : '';
        $toolbar = "
        <div style='margin-bottom:18px'>
          <b>Actions:</b><br><br>
          <button onclick=\"document.getElementById('startOverlay').classList.add('show')\" class='kt-tbtn kt-tbtn-green' style='{$startDisabled}'>
            <i class='fa fa-play'></i> Start Crawl
          </button>
          <a href='/namz-crawler/resume' class='kt-tbtn kt-tbtn-blue' style='{$startDisabled}' onclick=\"return confirm('Resume last stopped session?')\">
            <i class='fa fa-step-forward'></i> Resume Last
          </a>
          <a href='/namz-crawler/stop' class='kt-tbtn kt-tbtn-red' style='{$stopDisabled}' onclick=\"return confirm('Send stop signal to running crawl?')\">
            <i class='fa fa-stop'></i> Stop Crawl
          </a>
          <a href='/namz-crawler/activate-with-url' class='kt-tbtn kt-tbtn-purple' data-pjax='0'>
            <i class='fa fa-check'></i> Activate Movies With URL
          </a>
          <a href='/namz-crawler/bulk-retry-failed' class='kt-tbtn kt-tbtn-orange' onclick=\"return confirm('Re-queue all failed log entries?')\">
            <i class='fa fa-refresh'></i> Retry All Failed
          </a>
          <a href='/namz-crawler/backfill-urls' class='kt-tbtn kt-tbtn-gray' onclick=\"return confirm('Backfill old namz records?')\">
            <i class='fa fa-database'></i> Backfill Old Records
          </a>
          <a href='/namz-crawler/test-login' class='kt-tbtn kt-tbtn-dark'>
            <i class='fa fa-key'></i> Test Login
          </a>
          <a href='/namz-crawler/pending-movies' class='kt-tbtn' style='background:#1abc9c'>
            <i class='fa fa-clock-o'></i> Pending Movies Grid
          </a>
          <a href='/namz-crawl-logs' class='kt-tbtn kt-tbtn-dark'>
            <i class='fa fa-list'></i> View All Logs
          </a>
        </div>";

        // ── URL Probe Tool ────────────────────────────────────────────────────
        $probeTool = "
        <div style='background:#f8f9fa;border:1px solid #dee2e6;border-radius:6px;padding:16px;margin-bottom:18px'>
          <h5 style='margin:0 0 6px'><i class='fa fa-search'></i> URL Probe Tool</h5>
          <p style='font-size:12px;color:#666;margin-bottom:10px'>
            Enter a Namz ID and click Probe. The tool will login with your credentials and test ~12 API endpoint patterns
            (twoline.php, play.php, get.php, stream.php…) to find where the video URL is actually served. Results appear below.
          </p>
          <div style='display:flex;gap:8px;align-items:center;flex-wrap:wrap'>
            <input type='number' id='probeIdInput' class='form-control' style='width:170px' placeholder='Namz ID (e.g. 9073)'>
            <button id='probeBtn' onclick='runProbe()' class='btn btn-warning'><i class='fa fa-search'></i> Probe</button>
            <a href='/namz-crawler/probe-full' class='btn btn-default btn-sm'>Open Probe Page</a>
          </div>
          <div id='probeOut' class='probe-out'></div>
        </div>";

        // ── Pending movies quick view ─────────────────────────────────────────
        $pendingMovies = MovieModel::where('namz_url_status', 'pending')
            ->whereNotNull('namz_id')->where('namz_id', '>', 0)
            ->orderByDesc('id')->limit(25)->get();

        $pendingRows = '';
        foreach ($pendingMovies as $m) {
            $thumb = $m->thumbnail_url
                ? "<img src='" . e($m->thumbnail_url) . "' class='kt-thumb' onerror=\"this.style.display='none'\">"
                : "<div style='width:54px;height:38px;background:#ecf0f1;border-radius:3px;display:inline-flex;align-items:center;justify-content:center;color:#ccc;font-size:10px'>No img</div>";
            $namzLink = "<a href='https://namzentertainment.com/prev.php?id={$m->namz_id}' target='_blank'>#{$m->namz_id} ↗</a>";
            $title = e(mb_strimwidth($m->title ?? '', 0, 55, '…'));
            $pendingRows .= "
            <tr class='pending-row'>
              <td class='thumb-cell'>{$thumb}</td>
              <td>
                <a href='#' onclick=\"showDetail({$m->id});return false\" style='font-weight:600'>{$title}</a>
                <br><small style='color:#999'>" . e($m->genre ?? '') . " " . e($m->year ?? '') . "</small>
              </td>
              <td>{$namzLink}</td>
              <td style='white-space:nowrap'>
                <button onclick=\"openSetUrl({$m->id},'" . addslashes(e($m->title ?? '')) . "')\" class='btn btn-xs btn-primary' title='Set video URL'>✎ URL</button>
                <button onclick='runProbeFor({$m->namz_id})' class='btn btn-xs btn-warning' title='Probe URL endpoints'>🔍</button>
              </td>
            </tr>";
        }
        $pendingSection = $pendingRows
            ? "<table class='table table-hover pending-tbl' style='margin-bottom:0'>
                 <thead><tr><th>Poster</th><th>Title</th><th>Namz ID</th><th>Actions</th></tr></thead>
                 <tbody>{$pendingRows}</tbody>
               </table>
               <p style='font-size:12px;color:#7f8c8d;margin-top:8px;padding:8px'>
                 Showing last 25 pending. <a href='/namz-crawler/pending-movies'>View all →</a>
               </p>"
            : "<p style='color:#27ae60;padding:10px'><i class='fa fa-check'></i> No pending movies — all resolved!</p>";

        // ── Recent sessions table ────────────────────────────────────────────
        $sessions = NamzCrawlSession::latest()->limit(8)->get();
        $sessRows = [];
        foreach ($sessions as $s) {
            $sessRows[] = [
                "#{$s->id}",
                $s->status_badge,
                "{$s->id_from} → {$s->id_to} (at {$s->id_current})",
                $s->total_success . ' / ' . $s->total_processed,
                $s->total_new_movies . ' new | ' . $s->total_url_pending . ' pending',
                $s->duration,
                $s->started_at ? $s->started_at->format('m-d H:i') : '—',
                $s->triggered_by,
                "<a href='/namz-crawler/sessions/{$s->id}' class='btn btn-xs btn-info'>Detail</a>",
            ];
        }
        $sessTable = new Table(['ID','Status','Range / Current','Success/Total','New | Pending','Duration','Started','By','Action'], $sessRows);

        // ── Recent log entries ────────────────────────────────────────────────
        $logs = NamzCrawlLog::latest()->limit(15)->get();
        $logRows = [];
        foreach ($logs as $l) {
            $movieLink = $l->movie_id
                ? "<a href='#' onclick=\"showDetail({$l->movie_id});return false\">#{$l->movie_id}</a>"
                : '—';
            $logRows[] = [
                "<a href='https://namzentertainment.com/prev.php?id={$l->namz_id}' target='_blank'>#{$l->namz_id}</a>",
                e(mb_strimwidth($l->title ?? '—', 0, 45, '…')),
                $l->status_badge,
                $l->video_status_badge,
                $movieLink,
                $l->error_message ? '<small style="color:#e74c3c">' . e(substr($l->error_message, 0, 55)) . '</small>' : '—',
                $l->created_at ? $l->created_at->format('m-d H:i:s') : '—',
            ];
        }
        $logTable = new Table(['Namz ID','Title','Status','Video','Movie','Error','At'], $logRows);

        $assets = $this->sharedAssets($isRunning);

        return $content
            ->title('Namz Entertainment Crawler')
            ->description('Monitor, control, and investigate the namzentertainment.com content crawler')
            ->row(function (Row $row) use ($assets, $urlExplain, $stats, $sessionHtml, $toolbar, $probeTool, $pendingSection, $sessTable, $logTable) {
                $row->column(12, function (Column $col) use ($assets, $urlExplain, $stats, $sessionHtml, $toolbar, $probeTool, $pendingSection, $sessTable, $logTable) {
                    $col->append(new Box('Overview', $assets . $urlExplain . $stats . $sessionHtml . $toolbar . $probeTool));
                    $col->append(new Box('Pending Movies — Awaiting URL Resolution', $pendingSection));
                    $col->append(new Box('Recent Sessions', $sessTable->render()));
                    $col->append(new Box('Recent Log Entries', $logTable->render()));
                });
            });
    }

    // ── Log Grid Index Page (overridden for proper asset injection) ───────────

    public function index(Content $content): Content
    {
        // ── Stats ───────────────────────────────────────────────────────────────
        $totalLogs   = NamzCrawlLog::count();
        $successLogs = NamzCrawlLog::where('status', 'success')->count();
        $failedLogs  = NamzCrawlLog::where('status', 'failed')->count();
        $newMovies   = NamzCrawlLog::whereIn('result', ['new_movie', 'new_series'])->count();
        $totalNamz   = MovieModel::where('platform_type', 'namz')->count();
        $activeNamz  = MovieModel::where('platform_type', 'namz')->where('status', 'Active')->count();
        $urlPending  = MovieModel::where('platform_type', 'namz')->where('namz_url_status', 'pending')->count();
        $pctActive   = $totalNamz > 0 ? round($activeNamz / $totalNamz * 100) : 0;

        $assets = $this->sharedAssets();

        $stats = "
        <div class='kt-stat-row'>
          <div class='kt-stat' style='border-color:#27ae60'>
            <div class='val'>{$successLogs}</div><div class='lbl'>Crawl Successes</div>
          </div>
          <div class='kt-stat' style='border-color:#e74c3c'>
            <div class='val'>{$failedLogs}</div><div class='lbl'>Crawl Failures</div>
          </div>
          <div class='kt-stat' style='border-color:#8e44ad'>
            <div class='val'>{$newMovies}</div><div class='lbl'>New Movies Found</div>
          </div>
          <div class='kt-stat' style='border-color:#2980b9'>
            <div class='val'>{$activeNamz}</div><div class='lbl'>Active Namz Movies</div>
          </div>
          <div class='kt-stat' style='border-color:#e67e22'>
            <div class='val'>{$urlPending}</div><div class='lbl'>URL Still Pending</div>
          </div>
          <div class='kt-stat' style='border-color:#16a085'>
            <div class='val'>{$pctActive}%</div><div class='lbl'>Activation Rate</div>
          </div>
        </div>
        <div style='margin-bottom:18px'>
          <div style='display:flex;justify-content:space-between;font-size:12px;color:#7f8c8d;margin-bottom:4px'>
            <span>Namz Movie Activation Progress</span><span>{$activeNamz} / {$totalNamz} movies activated</span>
          </div>
          <div class='kt-prog'><div class='kt-prog-fill' style='width:{$pctActive}%'></div></div>
        </div>";

        $toolbar = "
        <div style='margin-bottom:18px'>
          <b style='font-size:13px;display:block;margin-bottom:8px'><i class='fa fa-bolt'></i> Quick Actions:</b>
          <a href='/namz-crawler' class='kt-tbtn kt-tbtn-dark'>
            <i class='fa fa-tachometer'></i> Crawler Dashboard
          </a>
          <a href='/namz-crawler/pending-movies' class='kt-tbtn kt-tbtn-orange'>
            <i class='fa fa-clock-o'></i> Pending Movies ({$urlPending})
          </a>
          <a href='/namz-crawler/activate-with-url' class='kt-tbtn kt-tbtn-purple' data-pjax='0'>
            <i class='fa fa-check'></i> Activate With URL
          </a>
          <a href='#' onclick='toggleActiveMovies();return false' class='kt-tbtn kt-tbtn-green'>
            <i class='fa fa-film'></i> View Active Namz ({$activeNamz})
          </a>
          <a href='/namz-crawler/probe-full' class='kt-tbtn kt-tbtn-blue'>
            <i class='fa fa-search'></i> Probe URL Tool
          </a>
          <a href='/namz-crawler/bulk-retry-failed' class='kt-tbtn kt-tbtn-red' onclick=\"return confirm('Re-queue all failed entries?')\">
            <i class='fa fa-refresh'></i> Retry Failed ({$failedLogs})
          </a>
        </div>";

        // ── Active Namz Movies card grid ─────────────────────────────────────────
        $activeMovies = MovieModel::where('platform_type', 'namz')
            ->where('status', 'Active')
            ->orderByDesc('id')
            ->limit(24)
            ->get();

        $cards = '';
        foreach ($activeMovies as $m) {
            $thumb = $m->thumbnail_url
                ? '<img src="' . e($m->thumbnail_url) . '" style="width:100%;height:110px;object-fit:cover;border-radius:4px 4px 0 0;display:block" onerror="this.style.display=\'none\'">'
                : '<div style="width:100%;height:110px;background:#ecf0f1;border-radius:4px 4px 0 0;display:flex;align-items:center;justify-content:center;color:#bbb"><i class="fa fa-film fa-2x"></i></div>';
            $title  = e(mb_strimwidth($m->title ?? '', 0, 40, '…'));
            $genre  = e($m->genre ?? '—');
            $year   = e($m->year ?? '');
            $hasUrl = !empty($m->url);
            $badge  = $hasUrl
                ? '<span class="kt-badge kt-badge-green" style="font-size:10px">URL ✓</span>'
                : '<span class="kt-badge kt-badge-orange" style="font-size:10px">No URL</span>';
            $playBtn = $hasUrl
                ? '<button data-url="' . e($m->url) . '" data-title="' . e($m->title ?? '') . '" onclick="playMovie(this.dataset.url,this.dataset.title)" class="btn btn-xs btn-success" title="Play video">&#9654;</button>'
                : '';
            $cards .= "
            <div style='flex:1 1 180px;max-width:200px;background:#fff;border-radius:5px;border:1px solid #e5e5e5;box-shadow:0 1px 3px rgba(0,0,0,.06);overflow:hidden'>
              {$thumb}
              <div style='padding:8px'>
                <div style='font-size:12px;font-weight:600;line-height:1.3;margin-bottom:4px'>
                  <a href='#' onclick='showDetail({$m->id});return false'>{$title}</a>
                </div>
                <div style='font-size:11px;color:#999;margin-bottom:6px'>{$genre} {$year}</div>
                <div style='display:flex;gap:4px;flex-wrap:wrap;align-items:center'>
                  {$badge} {$playBtn}
                  <button onclick='showDetail({$m->id})' class='btn btn-xs btn-default' title='Details'>&#8505;</button>
                </div>
              </div>
            </div>";
        }

        $activeSectionInner = $cards
            ? "<div style='display:flex;flex-wrap:wrap;gap:10px'>{$cards}</div>"
            : '<p style="color:#7f8c8d;padding:20px 0">No active namz movies yet. Use the crawler to import movies, then activate ones with a URL.</p>';

        $activeSection = "
        <div id='activeNamzSection' style='display:none;border-top:1px solid #e9e9e9;padding-top:18px;margin-top:4px'>
          <div style='display:flex;justify-content:space-between;align-items:center;margin-bottom:14px'>
            <h5 style='margin:0'><i class='fa fa-film'></i> Active Namz Movies
              <span style='font-size:12px;font-weight:normal;color:#7f8c8d;margin-left:6px'>last 24 of {$activeNamz} total</span>
            </h5>
            <a href='#' onclick='toggleActiveMovies();return false' style='font-size:12px;color:#7f8c8d'>&#9650; Hide</a>
          </div>
          {$activeSectionInner}
          <p style='font-size:12px;color:#7f8c8d;margin-top:12px'>
            <a href='/namz-crawler/pending-movies'>View all pending →</a>
            &nbsp;|&nbsp;
            <a href='/namz-crawler/activate-with-url' data-pjax='0'>Activate movies with URL →</a>
          </p>
        </div>";

        return $content
            ->title('Namz Crawl Logs')
            ->description($totalLogs . ' total log entries')
            ->row(function (Row $row) use ($assets, $stats, $toolbar, $activeSection) {
                $row->column(12, function (Column $col) use ($assets, $stats, $toolbar, $activeSection) {
                    $col->append(new Box('Overview & Quick Actions', $assets . $stats . $toolbar . $activeSection));
                });
            })
            ->body($this->grid());
    }

    // ── Log Grid ─────────────────────────────────────────────────────────────

    protected function grid(): Grid
    {
        $grid = new Grid(new NamzCrawlLog());
        $grid->model()->orderByDesc('id');
        $grid->disableCreateButton();
        $grid->disableExport();

        $grid->filter(function (Grid\Filter $f) {
            $f->disableIdFilter();
            $f->equal('status')->select(['success' => 'Success','skipped' => 'Skipped','failed' => 'Failed','pending' => 'Pending']);
            $f->equal('video_status', 'Video Status')->select(['found' => 'Found','not_found' => 'Not Found','pending' => 'Pending']);
            $f->equal('result')->select(['new_movie' => 'New Movie','new_series' => 'New Series','existing' => 'Existing','no_title' => 'No Title','error' => 'Error']);
            $f->equal('session_id', 'Session ID');
            $f->between('namz_id', 'Namz ID Range');
            $f->like('title', 'Title');
            $f->between('created_at', 'Date Range')->datetime();
        });

        $grid->column('id', '#')->sortable();
        $grid->column('namz_id', 'Namz ID')->sortable()->display(function () {
            return "<a href='https://namzentertainment.com/prev.php?id={$this->namz_id}' target='_blank' title='Open on Namz'>{$this->namz_id} ↗</a>";
        });
        $grid->column('thumb', 'Poster')->display(function () {
            if (!$this->movie_id) return '—';
            $movie = MovieModel::find($this->movie_id);
            if ($movie && $movie->thumbnail_url) {
                $esc = e($movie->thumbnail_url);
                return "<img src='{$esc}' style='width:50px;height:35px;object-fit:cover;border-radius:3px' onerror=\"this.style.display='none'\">";
            }
            return '—';
        });
        $grid->column('title', 'Title')->display(function () {
            $t = e(mb_strimwidth($this->title ?? '—', 0, 50, '…'));
            if ($this->movie_id) {
                return "<a href='#' onclick=\"showDetail({$this->movie_id});return false\" title='View details'>{$t}</a>";
            }
            return $t;
        });
        $grid->column('status', 'Status')->display(function () { return $this->status_badge; });
        $grid->column('result', 'Result')->display(function () {
            if (!$this->result) return '—';
            $colors = ['new_movie' => '#27ae60','new_series' => '#8e44ad','existing' => '#7f8c8d','no_title' => '#95a5a6','error' => '#e74c3c','dry_run' => '#2980b9'];
            $c = $colors[$this->result] ?? '#95a5a6';
            return "<span style='background:{$c};color:#fff;padding:2px 7px;border-radius:3px;font-size:11px'>" . str_replace('_', ' ', ucfirst($this->result)) . "</span>";
        });
        $grid->column('video_status', 'Video URL')->display(function () {
            $badge = $this->video_status_badge;
            if ($this->video_status === 'found' && $this->movie_id) {
                $badge .= ' <button onclick="openMoviePlayer(' . $this->movie_id . ')" class="btn btn-xs btn-success" title="Play video" style="margin-left:4px">&#9654;</button>';
            }
            return $badge;
        });
        $grid->column('movie_id', 'Movie')->display(function () {
            if (!$this->movie_id && !$this->series_id) return '—';
            $id = $this->movie_id ?? $this->series_id;
            if ($this->movie_id) {
                return "<a href='#' onclick=\"showDetail({$id});return false\">#{$id}</a>";
            }
            return "<a href='/series-movies/{$id}' target='_blank'>#{$id}</a>";
        });
        $grid->column('session_id', 'Session')->display(function () {
            return $this->session_id ? "<a href='/namz-crawler/sessions/{$this->session_id}'>#{$this->session_id}</a>" : '—';
        });
        $grid->column('error_message', 'Error')->display(function () {
            if (!$this->error_message) return '—';
            return '<small style="color:#e74c3c">' . e(substr($this->error_message, 0, 80)) . '</small>';
        });
        $grid->column('created_at', 'At')->sortable()->display(function () {
            return $this->created_at ? $this->created_at->format('Y-m-d H:i') : '—';
        });

        $grid->actions(function (Grid\Displayers\Actions $actions) {
            $row = $actions->row;
            if ($row->video_status === 'found' && $row->movie_id) {
                $actions->append("<a href='#' onclick=\"openMoviePlayer({$row->movie_id});return false\" class='btn btn-xs btn-success' title='Play video'>&#9654;</a> ");
            }
            if ($row->movie_id) {
                $actions->append("<a href='#' onclick=\"showDetail({$row->movie_id});return false\" class='btn btn-xs btn-info' title='Details'>&#8505;</a> ");
                $actions->append("<a href='#' onclick=\"openSetUrl({$row->movie_id},'');return false\" class='btn btn-xs btn-primary' title='Set URL'>&#9998;</a> ");
            }
            if ($row->status === 'failed') {
                $actions->append("<a href='/namz-crawler/retry/{$row->namz_id}' class='btn btn-xs btn-warning' title='Retry crawl'>&#8635;</a> ");
            }
            if ($row->video_status === 'not_found' && $row->namz_id) {
                $actions->append("<a href='#' onclick=\"runProbeFor({$row->namz_id});return false\" class='btn btn-xs btn-warning' title='Probe URL endpoints'>&#128269;</a> ");
            }
            $actions->disableDelete();
            $actions->disableEdit();
            $actions->disableView();
        });

        return $grid;
    }

    // ── Pending Movies Grid ───────────────────────────────────────────────────

    public function pendingMovies(Content $content): Content
    {
        $perPage = 50;
        $page    = (int) request('page', 1);
        $search  = request('q', '');

        $query = MovieModel::where('namz_url_status', 'pending')
            ->whereNotNull('namz_id')->where('namz_id', '>', 0);
        if ($search) $query->where('title', 'like', "%{$search}%");

        $total   = $query->count();
        $movies  = $query->orderByDesc('id')->offset(($page - 1) * $perPage)->limit($perPage)->get();
        $pages   = ceil($total / $perPage);

        $tableRows = [];
        foreach ($movies as $m) {
            $thumb = $m->thumbnail_url
                ? "<img src='" . e($m->thumbnail_url) . "' style='width:60px;height:42px;object-fit:cover;border-radius:3px' onerror=\"this.style.display='none'\">"
                : '—';
            $namzLink = "<a href='https://namzentertainment.com/prev.php?id={$m->namz_id}' target='_blank'>#{$m->namz_id} ↗</a>";
            $title = "<a href='#' onclick=\"showDetail({$m->id});return false\" style='font-weight:600'>" . e(mb_strimwidth($m->title ?? '', 0, 55, '…')) . "</a>";
            $actions = "
              <a href='/namz-crawler/set-url/{$m->id}' class='btn btn-xs btn-primary'>Set URL</a>
              <button onclick='runProbeFor({$m->namz_id})' class='btn btn-xs btn-warning' title='Probe'>🔍 Probe</button>
              <a href='https://namzentertainment.com/prev.php?id={$m->namz_id}' target='_blank' class='btn btn-xs btn-default'>Open ↗</a>";
            $tableRows[] = [$thumb, $title, e($m->genre ?? '—'), e($m->year ?? '—'), $namzLink, $actions];
        }
        $table = new Table(['Poster','Title','Genre','Year','Namz ID','Actions'], $tableRows);

        $pagerHtml = '';
        for ($i = 1; $i <= min($pages, 20); $i++) {
            $active = $i === $page ? 'active' : '';
            $pagerHtml .= "<li class='{$active}'><a href='?page={$i}&q=" . urlencode($search) . "'>{$i}</a></li>";
        }

        $html = $this->sharedAssets() . "
        <div style='display:flex;gap:8px;align-items:center;margin-bottom:14px;flex-wrap:wrap'>
          <form method='GET' style='display:flex;gap:6px;align-items:center'>
            <input type='text' name='q' value='" . e($search) . "' class='form-control' style='width:220px' placeholder='Search title…'>
            <button class='btn btn-default'>Search</button>
            " . ($search ? "<a href='/namz-crawler/pending-movies' class='btn btn-default'>Clear</a>" : '') . "
          </form>
          <span style='font-size:13px;color:#7f8c8d'>{$total} pending movies</span>
          <a href='/namz-crawler' class='btn btn-xs btn-default' style='margin-left:auto'>← Back to Dashboard</a>
        </div>
        " . $table->render() . "
        <ul class='pagination' style='margin-top:12px'>{$pagerHtml}</ul>";

        return $content
            ->title('Pending URL Movies')
            ->description('Movies from namzentertainment.com waiting for video URL resolution')
            ->row(function (Row $row) use ($html) {
                $row->column(12, function (Column $col) use ($html) {
                    $col->append(new Box('Pending Movies', $html));
                });
            });
    }

    // ── Session Detail ────────────────────────────────────────────────────────

    public function showSession(int $id, Content $content): Content
    {
        $session = NamzCrawlSession::findOrFail($id);
        $logs    = NamzCrawlLog::where('session_id', $id)->latest()->limit(100)->get();

        $badge = $session->status_badge;
        $pct   = $session->progress_percent;

        $infoHtml = "
        <div style='display:flex;gap:18px;flex-wrap:wrap;font-size:13px;margin-bottom:14px'>
          <div><b>Status:</b> {$badge}</div>
          <div><b>Range:</b> {$session->id_from} → {$session->id_to}</div>
          <div><b>Current:</b> {$session->id_current}</div>
          <div><b>Progress:</b> {$pct}%</div>
          <div><b>Processed:</b> {$session->total_processed}</div>
          <div><b>Success:</b> {$session->total_success}</div>
          <div><b>Skipped:</b> {$session->total_skipped}</div>
          <div><b>Failed:</b> {$session->total_failed}</div>
          <div><b>New Movies:</b> {$session->total_new_movies}</div>
          <div><b>New Series:</b> {$session->total_new_series}</div>
          <div><b>URL Pending:</b> {$session->total_url_pending}</div>
          <div><b>Duration:</b> {$session->duration}</div>
          <div><b>Triggered:</b> {$session->triggered_by}</div>
          <div><b>Started:</b> " . ($session->started_at ? $session->started_at->format('Y-m-d H:i:s') : '—') . "</div>
          <div><b>Ended:</b> " . ($session->ended_at ? $session->ended_at->format('Y-m-d H:i:s') : '—') . "</div>
        </div>
        <div class='kt-prog'><div class='kt-prog-fill' style='width:{$pct}%'></div></div>"
        . ($session->notes ? "<p style='margin-top:8px'><b>Notes:</b> " . e($session->notes) . "</p>" : '');

        $logRows = [];
        foreach ($logs as $l) {
            $movieLink = $l->movie_id ? "<a href='#' onclick=\"showDetail({$l->movie_id});return false\">#{$l->movie_id}</a>" : '—';
            $logRows[] = [
                "<a href='https://namzentertainment.com/prev.php?id={$l->namz_id}' target='_blank'>#{$l->namz_id}</a>",
                e(mb_strimwidth($l->title ?? '—', 0, 45, '…')),
                $l->status_badge, $l->video_status_badge, $l->result ?? '—',
                $movieLink,
                $l->error_message ? '<small style="color:red">' . e(substr($l->error_message, 0, 70)) . '</small>' : '—',
                $l->created_at ? $l->created_at->format('H:i:s') : '—',
            ];
        }
        $logTable = new Table(['Namz ID','Title','Status','Video','Result','Movie','Error','Time'], $logRows);

        $assets = $this->sharedAssets();
        return $content
            ->title("Session #{$id} Detail")
            ->row(function (Row $row) use ($assets, $infoHtml, $logTable) {
                $row->column(12, function (Column $col) use ($assets, $infoHtml, $logTable) {
                    $col->append(new Box("Session Info", $assets . $infoHtml));
                    $col->append(new Box('Log Entries (last 100)', $logTable->render()));
                });
            });
    }

    // ── Probe Page (full page) ────────────────────────────────────────────────

    public function probeFull(Content $content): Content
    {
        $html = $this->sharedAssets() . "
        <p style='font-size:13px;color:#555'>
          The probe tool tests multiple endpoint patterns on namzentertainment.com using your authenticated session.
          It attempts to find the actual video URL API that the site's obfuscated JavaScript calls at runtime.
          Enter any Namz movie ID and click Probe to see what each endpoint returns.
        </p>
        <div style='display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:14px'>
          <input type='number' id='probeIdInput' class='form-control' style='width:200px' placeholder='Namz ID (e.g. 9073)'>
          <button id='probeBtn' onclick='runProbe()' class='btn btn-warning btn-lg'><i class='fa fa-search'></i> Probe ID</button>
          <a href='/namz-crawler' class='btn btn-default'>← Back to Dashboard</a>
        </div>
        <div id='probeOut' class='probe-out' style='display:none;font-size:13px;min-height:200px'></div>
        <style>
          .probe-out{background:#1e1e1e;color:#d4d4d4;border-radius:4px;padding:14px;font-family:monospace;
                     font-size:12px;white-space:pre-wrap;max-height:500px;overflow-y:auto;display:none}
        </style>";

        return $content
            ->title('URL Probe Tool')
            ->description('Test endpoint patterns to discover video URLs for Namz movies')
            ->row(function (Row $row) use ($html) {
                $row->column(12, function (Column $col) use ($html) {
                    $col->append(new Box('Probe Endpoint Patterns', $html));
                });
            });
    }

    // ── Probe AJAX ────────────────────────────────────────────────────────────

    public function probeIdAjax(int $namzId)
    {
        set_time_limit(120);
        $result = ['namz_id' => $namzId, 'found_url' => null, 'endpoints' => [], 'js_analysis' => null, 'movie_id' => null, 'title' => null];

        // Find the movie record
        $movie = MovieModel::where('namz_id', $namzId)->first();
        if ($movie) {
            $result['movie_id'] = $movie->id;
            $result['title']    = $movie->title;
        }

        try {
            [$client, $jar, $drmToken] = $this->makeAuthClient();
            if (!$client) {
                $result['error'] = 'Login failed';
                return response()->json($result);
            }

            // Fetch movie page
            $pageHtml = (string) $client->get(self::NAMZ_SITE . "/prev.php?id={$namzId}", ['cookies' => $jar])->getBody();
            $result['page_title_found'] = str_contains($pageHtml, 'details__title');

            // Try to decode set.js
            try {
                $setJs = (string) $client->get(self::NAMZ_SITE . '/set.js', ['cookies' => $jar])->getBody();
                $decoded = $this->decodeObfuscatedJs($setJs);
                $result['js_analysis'] = [
                    'raw_preview'    => substr($setJs, 0, 200),
                    'decoded_length' => strlen($decoded),
                    'decoded_preview'=> substr($decoded, 0, 400),
                    'endpoints_found'=> $this->extractEndpointsFromJs($decoded),
                    'mp4_urls'       => $this->extractVideoUrls($decoded),
                ];
            } catch (\Throwable $e) {
                $result['js_analysis'] = ['error' => $e->getMessage()];
            }

            // Try endpoint patterns
            $endpoints = [
                'GET' => [
                    "twoline.php?id={$namzId}",
                    "twoline.php?movieid={$namzId}",
                    "twoline.php?id={$namzId}&token={$drmToken}",
                    "play.php?id={$namzId}",
                    "get.php?id={$namzId}",
                    "video.php?id={$namzId}",
                    "stream.php?id={$namzId}",
                    "getlink.php?id={$namzId}",
                    "player.php?id={$namzId}",
                    "watch.php?id={$namzId}",
                ],
                'POST' => [
                    "twoline.php",
                    "get.php",
                    "video.php",
                    "play.php",
                ],
            ];

            foreach ($endpoints['GET'] as $ep) {
                $url = self::NAMZ_SITE . '/' . $ep;
                try {
                    $resp = $client->get($url, ['cookies' => $jar, 'timeout' => 10, 'http_errors' => false]);
                    $body = (string) $resp->getBody();
                    $short = substr($body, 0, 400);
                    $videoUrls = $this->extractVideoUrls($body);
                    $result['endpoints']["GET {$ep}"] = [
                        'status'      => $resp->getStatusCode(),
                        'body'        => $short,
                        'video_urls'  => $videoUrls,
                    ];
                    if ($videoUrls && !$result['found_url']) {
                        $result['found_url'] = $videoUrls[0];
                    }
                } catch (\Throwable $e) {
                    $result['endpoints']["GET {$ep}"] = ['error' => $e->getMessage()];
                }
            }

            $postBodies = [
                ['id' => $namzId],
                ['id' => $namzId, 'token' => $drmToken],
                ['movieid' => $namzId],
                ['movie_id' => $namzId, 't' => $drmToken],
            ];

            foreach ($endpoints['POST'] as $ep) {
                $url = self::NAMZ_SITE . '/' . $ep;
                foreach ($postBodies as $body) {
                    $key = "POST {$ep} " . json_encode($body);
                    try {
                        $resp = $client->post($url, ['cookies' => $jar, 'form_params' => $body, 'timeout' => 10, 'http_errors' => false]);
                        $b = (string) $resp->getBody();
                        $videoUrls = $this->extractVideoUrls($b);
                        $result['endpoints'][$key] = [
                            'status'     => $resp->getStatusCode(),
                            'body'       => substr($b, 0, 400),
                            'video_urls' => $videoUrls,
                        ];
                        if ($videoUrls && !$result['found_url']) {
                            $result['found_url'] = $videoUrls[0];
                        }
                    } catch (\Throwable $e) {
                        $result['endpoints'][$key] = ['error' => $e->getMessage()];
                    }
                }
            }
        } catch (\Throwable $e) {
            $result['fatal_error'] = $e->getMessage();
        }

        return response()->json($result);
    }

    // ── Movie Info JSON (for modal) ────────────────────────────────────────────

    public function movieInfoJson(int $movieId)
    {
        $m = MovieModel::findOrFail($movieId);
        return response()->json([
            'id'              => $m->id,
            'title'           => $m->title,
            'genre'           => $m->genre,
            'year'            => $m->year,
            'duration'        => $m->duration,
            'status'          => $m->status,
            'type'            => $m->type,
            'platform_type'   => $m->platform_type,
            'namz_id'         => $m->namz_id,
            'namz_url_status' => $m->namz_url_status,
            'thumbnail'       => $m->thumbnail_url,
            'url'             => $m->url,
            'external_url'    => $m->external_url,
            'description'     => $m->description,
            'vj'              => $m->vj,
        ]);
    }

    // ── AJAX Set URL ──────────────────────────────────────────────────────────

    public function setUrlAjax(int $movieId, Request $request)
    {
        $videoUrl = $request->input('video_url');
        if (!$videoUrl) return response()->json(['success' => false, 'message' => 'No URL provided']);

        $movie = MovieModel::findOrFail($movieId);
        $movie->url             = $videoUrl;
        $movie->external_url    = $videoUrl;
        $movie->status          = 'Active';
        $movie->namz_url_status = 'found';
        $movie->fix_status      = null;
        $movie->save();

        NamzCrawlLog::where('movie_id', $movieId)
            ->where('video_status', 'not_found')
            ->update(['video_url' => $videoUrl, 'video_status' => 'found']);

        return response()->json(['success' => true, 'message' => "Movie #{$movieId} activated."]);
    }

    // ── Actions ────────────────────────────────────────────────────────────────

    public function startCrawl(Request $request)
    {
        if (NamzCrawlSession::activeSession()) {
            return redirect('/namz-crawler')->with('error', 'A crawl session is already running. Stop it first or wait for it to finish.');
        }
        $from  = max(1,  (int) $request->input('from', 47));
        $to    = min(99999, (int) $request->input('to', 9300));
        $delay = max(200, (int) $request->input('delay', 800));
        $batch = max(10, (int) $request->input('batch', 50));

        $cmd = "php " . base_path('artisan') . " namz:crawl --from={$from} --to={$to} --delay={$delay} --batch={$batch} >> " . storage_path('logs/namz_crawl.log') . " 2>&1 &";
        exec($cmd);

        return redirect('/namz-crawler')->with('success', "Crawl started for IDs {$from}–{$to} (delay: {$delay}ms, batch: {$batch}).");
    }

    public function resumeCrawl()
    {
        $last = NamzCrawlSession::where('status', '!=', NamzCrawlSession::STATUS_RUNNING)->latest()->first();
        if (!$last) {
            return redirect('/namz-crawler')->with('error', 'No previous session to resume.');
        }
        $cmd = "php " . base_path('artisan') . " namz:crawl --session={$last->id} >> " . storage_path('logs/namz_crawl.log') . " 2>&1 &";
        exec($cmd);
        return redirect('/namz-crawler')->with('success', "Resumed session #{$last->id} from ID {$last->id_current}.");
    }

    public function stopCrawl()
    {
        file_put_contents(storage_path('namz_stop'), '1');
        return redirect('/namz-crawler')->with('success', 'Stop signal sent. Crawl will halt gracefully after current ID.');
    }

    public function testLogin()
    {
        try {
            $email    = config('services.namz.email');
            $password = config('services.namz.password');
            $jar      = new \GuzzleHttp\Cookie\CookieJar();
            $client   = new \GuzzleHttp\Client(['allow_redirects' => true, 'verify' => false, 'timeout' => 15]);

            $html = (string) $client->get(self::NAMZ_SITE . '/signin.php', ['cookies' => $jar])->getBody();
            preg_match('/name="token"\s+value="([^"]+)"/', $html, $m);
            $token = $m[1] ?? '';
            if (!$token) {
                return redirect('/namz-crawler')->with('error', 'Could not extract CSRF token from signin.php.');
            }
            $client->post(self::NAMZ_SITE . '/alter.php', [
                'cookies'     => $jar,
                'form_params' => ['token' => $token, 'usname' => $email, 'pword' => $password, 'remember' => 'on', 'submit' => 'Sign in'],
            ]);
            // Fix: lock.php returns token prefix before JSON
            $lockBody = (string) $client->post(self::NAMZ_SITE . '/lock.php', ['cookies' => $jar])->getBody();
            $jsonStart = strpos($lockBody, '{');
            $lock = $jsonStart !== false ? json_decode(substr($lockBody, $jsonStart), true) : null;

            if (($lock['success'] ?? '') == '1' || ($lock['success'] ?? '') == 1) {
                return redirect('/namz-crawler')->with('success', "Login successful! Credentials valid. DRM token: " . substr($lockBody, 0, min(strpos($lockBody, '{') ?: 30, 30)));
            }
            return redirect('/namz-crawler')->with('error', 'Login appeared to work but lock.php verification failed. Raw: ' . substr($lockBody, 0, 150));
        } catch (\Throwable $e) {
            return redirect('/namz-crawler')->with('error', 'Login test failed: ' . $e->getMessage());
        }
    }

    public function backfillOldRecords()
    {
        DB::statement("
            UPDATE movie_models
            SET namz_id = imdb_id,
                platform_type = 'namz',
                namz_url_status = CASE WHEN url IS NOT NULL AND url <> '' THEN 'found' ELSE 'pending' END
            WHERE imdb_url LIKE '%namzentertainment%'
              AND (namz_id IS NULL OR namz_id = 0)
              AND imdb_id IS NOT NULL AND imdb_id > 0
        ");
        $count = DB::table('movie_models')->where('platform_type', 'namz')->count();
        return redirect('/namz-crawler')->with('success', "Backfill done. {$count} namz movies have platform_type=namz.");
    }

    public function activateMoviesWithUrl()
    {
        $count = DB::table('movie_models')
            ->where('platform_type', 'namz')
            ->where('namz_url_status', 'pending')
            ->whereNotNull('url')->where('url', '<>', '')
            ->where('url', 'not like', '%namzentertainment%')
            ->update(['status' => 'Active', 'namz_url_status' => 'found', 'fix_status' => null]);

        return redirect('/namz-crawler')->with('success', "Activated {$count} namz movies that had a URL set.");
    }

    public function bulkRetryFailed()
    {
        $failed = NamzCrawlLog::where('status', 'failed')->pluck('namz_id')->unique()->filter();
        $count = 0;
        foreach ($failed as $namzId) {
            MyCounter::where('type', 'namz_crawler_v2')->where('count_value', $namzId)->delete();
            $count++;
        }
        if ($count > 0) {
            $from = $failed->min();
            $to   = $failed->max();
            $cmd  = "php " . base_path('artisan') . " namz:crawl --from={$from} --to={$to} --force >> " . storage_path('logs/namz_crawl.log') . " 2>&1 &";
            exec($cmd);
        }
        return redirect('/namz-crawler')->with('success', "Re-queued {$count} failed IDs (range {$failed->min()}–{$failed->max()}) with --force.");
    }

    public function retryId(int $namzId)
    {
        MyCounter::where('type', 'namz_crawler_v2')->where('count_value', $namzId)->delete();
        $cmd = "php " . base_path('artisan') . " namz:crawl --single={$namzId} >> " . storage_path('logs/namz_crawl.log') . " 2>&1 &";
        exec($cmd);
        return redirect()->back()->with('success', "Re-queued namz ID {$namzId}.");
    }

    public function setUrlForm(int $movieId, Content $content): Content
    {
        $movie = MovieModel::findOrFail($movieId);
        $form  = "
        <form method='POST' action='/namz-crawler/set-url/{$movieId}/save'>
          <input type='hidden' name='_token' value='" . csrf_token() . "'>
          <div class='form-group'>
            <label>Movie</label>
            <p><b>" . e($movie->title) . "</b> &mdash; namz_id: #{$movie->namz_id}</p>
          </div>
          <div class='form-group'>
            <label>Namzentertainment Page</label><br>
            <a href='https://namzentertainment.com/prev.php?id={$movie->namz_id}' target='_blank' class='btn btn-xs btn-default'>
              Open Movie on Namzentertainment ↗
            </a>
          </div>
          <div class='form-group'>
            <label>Current URL</label>
            <input type='text' class='form-control' value='" . e($movie->url ?? '') . "' readonly>
          </div>
          <div class='form-group'>
            <label>New Direct Video URL <span class='text-danger'>*</span></label>
            <input type='url' name='video_url' class='form-control' required placeholder='https://...mp4 or https://...m3u8'>
            <small class='text-muted'>Play the movie on Namz, open DevTools → Network → filter by mp4/m3u8, copy the URL.</small>
          </div>
          <button type='submit' class='btn btn-success'><i class='fa fa-save'></i> Save &amp; Activate Movie</button>
          <a href='/namz-crawl-logs' class='btn btn-default'>Cancel</a>
        </form>";

        return $content
            ->title("Set Video URL — " . e($movie->title))
            ->row(function (Row $row) use ($form) {
                $row->column(8, function (Column $col) use ($form) {
                    $col->append(new Box('Set Video URL', $form));
                });
            });
    }

    public function saveUrl(int $movieId, Request $request)
    {
        $videoUrl = $request->input('video_url');
        $movie    = MovieModel::findOrFail($movieId);
        $movie->url             = $videoUrl;
        $movie->external_url    = $videoUrl;
        $movie->status          = 'Active';
        $movie->namz_url_status = 'found';
        $movie->fix_status      = null;
        $movie->save();

        NamzCrawlLog::where('movie_id', $movieId)->where('video_status', 'not_found')
            ->update(['video_url' => $videoUrl, 'video_status' => 'found']);

        return redirect('/namz-crawl-logs')->with('success', "URL saved and movie #{$movieId} activated.");
    }

    public function liveSessions()
    {
        $session = NamzCrawlSession::activeSession();
        if (!$session) {
            return response()->json(['running' => false]);
        }
        $session->refresh();
        return response()->json([
            'running'        => true,
            'session_id'     => $session->id,
            'id_current'     => $session->id_current,
            'id_to'          => $session->id_to,
            'progress'       => $session->progress_percent,
            'total_processed'=> $session->total_processed,
            'total_success'  => $session->total_success,
            'total_failed'   => $session->total_failed,
            'total_new'      => $session->total_new_movies,
            'url_pending'    => $session->total_url_pending,
            'duration'       => $session->duration,
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function makeAuthClient(): array
    {
        $email    = config('services.namz.email');
        $password = config('services.namz.password');
        $jar      = new \GuzzleHttp\Cookie\CookieJar();
        $client   = new \GuzzleHttp\Client([
            'allow_redirects' => true,
            'verify'          => false,
            'timeout'         => 20,
            'headers'         => ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0'],
        ]);

        try {
            $html = (string) $client->get(self::NAMZ_SITE . '/signin.php', ['cookies' => $jar])->getBody();
            preg_match('/name="token"\s+value="([^"]+)"/', $html, $m);
            $token = $m[1] ?? '';
            if (!$token) return [null, null, null];

            $client->post(self::NAMZ_SITE . '/alter.php', [
                'cookies'     => $jar,
                'form_params' => ['token' => $token, 'usname' => $email, 'pword' => $password, 'remember' => 'on', 'submit' => 'Sign in'],
            ]);

            $lockBody  = (string) $client->post(self::NAMZ_SITE . '/lock.php', ['cookies' => $jar])->getBody();
            $jsonStart = strpos($lockBody, '{');
            $drmToken  = $jsonStart !== false ? substr($lockBody, 0, $jsonStart) : '';
            $lock      = $jsonStart !== false ? json_decode(substr($lockBody, $jsonStart), true) : null;

            if (!$lock || ($lock['success'] ?? '') != 1 && ($lock['success'] ?? '') != '1') {
                return [null, null, null];
            }
            return [$client, $jar, trim($drmToken)];
        } catch (\Throwable $e) {
            Log::error('[NamzProbe] Auth error: ' . $e->getMessage());
            return [null, null, null];
        }
    }

    private function decodeObfuscatedJs(string $js): string
    {
        $decoded = $js;
        // Try up to 5 layers of atob() decoding
        for ($i = 0; $i < 5; $i++) {
            if (preg_match('/atob\([\'"]([A-Za-z0-9+\/=]+)[\'"]\)/', $decoded, $m)) {
                $decoded = base64_decode($m[1]);
            } elseif (preg_match('/["\'"]([A-Za-z0-9+\/]{100,})["\'"]\s*\)/', $decoded, $m)) {
                $try = base64_decode($m[1]);
                if ($try && mb_detect_encoding($try, 'UTF-8', true)) $decoded = $try;
                else break;
            } else {
                break;
            }
        }
        return $decoded;
    }

    private function extractVideoUrls(string $content): array
    {
        preg_match_all('/https?:\/\/[^\s"\'<>]+\.(mp4|m3u8|mkv|avi|webm|ts)/i', $content, $m);
        return array_unique($m[0] ?? []);
    }

    private function extractEndpointsFromJs(string $js): array
    {
        $found = [];
        // Look for .php URLs
        preg_match_all('/["\']([^"\']*\.php[^"\']*)["\']/', $js, $m);
        foreach ($m[1] as $ep) {
            if (strlen($ep) < 100) $found[] = $ep;
        }
        // Look for fetch/ajax calls
        preg_match_all('/fetch\(["\']([^"\']+)["\']/', $js, $m2);
        $found = array_merge($found, $m2[1]);
        preg_match_all('/url\s*:\s*["\']([^"\']+)["\']/', $js, $m3);
        $found = array_merge($found, $m3[1]);
        return array_unique(array_filter($found));
    }
}
