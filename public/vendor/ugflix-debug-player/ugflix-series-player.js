/**
 * UGFlix Series Debug Player v2.0
 *
 * A robust series/TV-show debug player for the admin panel.
 * Features:
 *  - Video player with episode sidebar (episodes LEFT, player RIGHT)
 *  - Season tabs, episode list with status indicators
 *  - Pagination-aware range fetching from munowatch API
 *  - "Fetch Range" buttons per pagination range with progress
 *  - Fix Series / Fix Episode / Sync All buttons
 *  - Auto-play cascading with CDN fallback
 *  - Series metadata display
 *  - Remote episode comparison with range breakdown
 *
 * Loaded globally on all admin pages via bootstrap.php.
 * Activated when a .ugflix-series-play-btn is clicked.
 */
;(function($) {
    'use strict';

    // ─── BASE URL HELPER ───
    function _getBase() {
        if (window.__KATOGO_BASE_URL) return window.__KATOGO_BASE_URL;
        var m = window.location.pathname.match(/^(\/[^\/]*)\//);
        return m ? window.location.origin + m[1] : '';
    }

    function _getStreamToken() {
        return window.__KATOGO_STREAM_TOKEN || '';
    }

    // ─── DEAD HOST / CDN FALLBACK ───
    var DEAD_HOSTS = [
        /munoserver\d*\.\w+/i,
        /muno\d+\.club/i,
        /gumite\.club/i,
        /munowatch\.co/i
    ];
    var CDN_HOST = 'munotek.b-cdn.net';

    function _sanitizeUrl(url) {
        if (!url) return '';
        return url.replace(/[\r\n\t]/g, '').trim();
    }

    function _getStreamUrl(rawUrl) {
        var url = _sanitizeUrl(rawUrl);
        if (!url) return '';
        var base = _getBase();
        var token = _getStreamToken();
        return base + '/debug-player/stream?url=' + encodeURIComponent(url) + '&token=' + encodeURIComponent(token);
    }

    function _getCdnFallback(url) {
        if (!url) return null;
        try {
            var u = new URL(url);
            for (var i = 0; i < DEAD_HOSTS.length; i++) {
                if (DEAD_HOSTS[i].test(u.hostname)) {
                    u.hostname = CDN_HOST;
                    if (u.protocol === 'http:') u.protocol = 'https:';
                    return u.toString();
                }
            }
        } catch (e) {}
        return null;
    }

    // ─── STATE ───
    var _state = {
        series: null,       // Series metadata object
        episodes: [],       // All episodes array
        seasons: {},        // Episodes grouped by season {sn: [eps]}
        currentEpisode: null, // Currently playing episode
        currentSeason: '1',
        isPlaying: false,
        isLoading: false,
        isFixing: false,
        isFetchingRange: false,
        logs: [],
        ranges: null,       // Remote range data from fetchRemoteRanges
        rangesVisible: false,
    };

    // ─── LOGGING ───
    function _log(msg, type) {
        type = type || 'info';
        var ts = new Date().toLocaleTimeString();
        var prefix = {info: 'ℹ️', success: '✅', error: '❌', warn: '⚠️', fix: '🔧'}[type] || 'ℹ️';
        _state.logs.push({ts: ts, msg: msg, type: type, prefix: prefix});
        var $log = $('#sfx-log-content');
        if ($log.length) {
            var color = {info:'#8ec6f8', success:'#6ddb8a', error:'#f87171', warn:'#fbbf24', fix:'#c084fc'}[type] || '#ccc';
            $log.append('<div style="color:' + color + ';margin-bottom:2px"><span style="opacity:.5">[' + ts + ']</span> ' + prefix + ' ' + msg + '</div>');
            $log.scrollTop($log[0].scrollHeight);
        }
    }

    // ─── BUILD MODAL HTML ───
    function _buildModal() {
        if ($('#sfx-modal').length) return;

        var html = '\
<div id="sfx-modal" style="display:none;position:fixed;inset:0;z-index:19999;background:rgba(0,0,0,.92);font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif">\
  <div id="sfx-container" style="display:flex;flex-direction:column;height:100%;width:100%">\
    <!-- Title Bar -->\
    <div id="sfx-titlebar" style="display:flex;align-items:center;justify-content:space-between;padding:8px 16px;background:#1a1a2e;min-height:44px;flex-shrink:0">\
      <div style="display:flex;align-items:center;gap:10px">\
        <span style="font-size:18px">📺</span>\
        <span id="sfx-title" style="color:#fff;font-size:14px;font-weight:600">Series Debug Player</span>\
        <span id="sfx-badge" style="background:#7c3aed;color:#fff;font-size:10px;padding:2px 8px;border-radius:10px;font-weight:600;display:none"></span>\
      </div>\
      <div style="display:flex;gap:6px">\
        <button id="sfx-btn-ranges" class="sfx-btn sfx-btn-purple" title="Show episode ranges from remote API for selective fetching">📦 Ranges</button>\
        <button id="sfx-btn-sync" class="sfx-btn sfx-btn-blue" title="Sync ALL episodes from remote API (all ranges)">🔄 Sync All</button>\
        <button id="sfx-btn-fix-series" class="sfx-btn sfx-btn-green" title="Fix entire series">🔧 Fix Series</button>\
        <button id="sfx-btn-close" class="sfx-btn sfx-btn-red" title="Close">✕</button>\
      </div>\
    </div>\
    <!-- Main Content: Episodes LEFT + Player RIGHT -->\
    <div style="display:flex;flex:1;overflow:hidden">\
      <!-- Left: Episode Sidebar -->\
      <div id="sfx-sidebar" style="width:340px;flex-shrink:0;display:flex;flex-direction:column;background:#0f0f23;border-right:1px solid #2a2a4a">\
        <!-- Series Thumbnail + Title -->\
        <div id="sfx-series-header" style="padding:10px 12px;border-bottom:1px solid #2a2a4a">\
          <div style="display:flex;gap:10px;align-items:center">\
            <img id="sfx-series-thumb" src="" style="width:44px;height:44px;border-radius:6px;object-fit:cover;background:#222">\
            <div style="flex:1;min-width:0">\
              <div id="sfx-series-name" style="color:#fff;font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"></div>\
              <div id="sfx-series-meta" style="color:#7c8da6;font-size:11px;margin-top:2px"></div>\
              <div id="sfx-series-status" style="margin-top:4px"></div>\
            </div>\
          </div>\
        </div>\
        <!-- Season Tabs -->\
        <div id="sfx-season-tabs" style="display:flex;gap:0;border-bottom:1px solid #2a2a4a;overflow-x:auto;flex-shrink:0"></div>\
        <!-- Episode List -->\
        <div id="sfx-ep-list" style="flex:1;overflow-y:auto;padding:6px"></div>\
        <!-- Ranges Panel (hidden by default, shown when "Ranges" is clicked) -->\
        <div id="sfx-ranges-panel" style="display:none;flex:1;overflow-y:auto;padding:10px;background:#0d1117">\
          <div id="sfx-ranges-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">\
            <span style="color:#d1d5db;font-size:12px;font-weight:600">📦 Episode Ranges</span>\
            <button id="sfx-ranges-back" class="sfx-btn sfx-btn-sm" style="background:#374151">← Episodes</button>\
          </div>\
          <div id="sfx-ranges-summary" style="color:#7c8da6;font-size:11px;margin-bottom:8px"></div>\
          <div id="sfx-ranges-list"></div>\
          <div id="sfx-ranges-actions" style="margin-top:10px;text-align:center"></div>\
        </div>\
        <!-- Sidebar Footer -->\
        <div id="sfx-sidebar-footer" style="padding:6px 12px;border-top:1px solid #2a2a4a;background:#0a0a1a">\
          <div id="sfx-ep-count" style="color:#7c8da6;font-size:11px;text-align:center"></div>\
        </div>\
      </div>\
      <!-- Right: Compact Player + Info -->\
      <div id="sfx-player-col" style="flex:1;display:flex;flex-direction:column;min-width:0">\
        <!-- Video Player (compact) -->\
        <div id="sfx-video-wrap" style="position:relative;background:#000;flex-shrink:0">\
          <video id="sfx-video" style="width:100%;max-height:40vh;display:block;background:#000" controls preload="metadata"></video>\
          <div id="sfx-video-overlay" style="display:none;position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.7);color:#fff;font-size:14px">\
            <div id="sfx-overlay-text" style="text-align:center">Loading...</div>\
          </div>\
        </div>\
        <!-- Episode Info Bar -->\
        <div id="sfx-ep-info" style="padding:10px 16px;background:#16213e;border-top:1px solid #2a2a4a;overflow-y:auto;flex:1">\
          <div id="sfx-ep-info-content" style="color:#ccc;font-size:12px"></div>\
          <!-- Log Panel -->\
          <div style="margin-top:10px">\
            <div style="color:#7c8da6;font-size:10px;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;cursor:pointer" onclick="$(\'#sfx-log-content\').toggle()">▾ Debug Log</div>\
            <div id="sfx-log-content" style="background:#0d1117;border-radius:4px;padding:8px;max-height:150px;overflow-y:auto;font-family:monospace;font-size:11px;display:none"></div>\
          </div>\
        </div>\
      </div>\
    </div>\
  </div>\
</div>';

        // CSS
        var css = '\
<style id="sfx-styles">\
.sfx-btn{border:0;border-radius:4px;padding:4px 10px;font-size:11px;font-weight:600;cursor:pointer;transition:all .15s;color:#fff}\
.sfx-btn:hover{filter:brightness(1.2)}\
.sfx-btn:disabled{opacity:.5;cursor:not-allowed}\
.sfx-btn-blue{background:#2563eb}\
.sfx-btn-green{background:#16a34a}\
.sfx-btn-purple{background:#7c3aed}\
.sfx-btn-red{background:#dc2626}\
.sfx-btn-orange{background:#ea580c}\
.sfx-btn-sm{padding:2px 6px;font-size:10px}\
.sfx-ep-item{display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:6px;cursor:pointer;transition:all .15s;margin-bottom:2px}\
.sfx-ep-item:hover{background:#1a1a3e}\
.sfx-ep-item.sfx-active{background:#1e3a5f;border-left:3px solid #3b82f6}\
.sfx-ep-item .sfx-ep-num{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0}\
.sfx-ep-item .sfx-ep-num.sfx-ok{background:#16a34a;color:#fff}\
.sfx-ep-item .sfx-ep-num.sfx-err{background:#dc2626;color:#fff}\
.sfx-ep-item .sfx-ep-num.sfx-unk{background:#374151;color:#9ca3af}\
.sfx-ep-item .sfx-ep-title{flex:1;min-width:0;color:#d1d5db;font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}\
.sfx-ep-item .sfx-ep-dur{color:#6b7280;font-size:10px;flex-shrink:0}\
.sfx-ep-item .sfx-ep-fix{flex-shrink:0}\
.sfx-season-tab{padding:6px 14px;color:#7c8da6;font-size:12px;font-weight:600;cursor:pointer;border-bottom:2px solid transparent;white-space:nowrap;transition:all .15s}\
.sfx-season-tab:hover{color:#d1d5db;background:rgba(255,255,255,.03)}\
.sfx-season-tab.sfx-tab-active{color:#3b82f6;border-bottom-color:#3b82f6}\
.sfx-spinner{display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:sfx-spin .6s linear infinite}\
@keyframes sfx-spin{to{transform:rotate(360deg)}}\
.sfx-range-card{transition:all .15s}\
.sfx-range-card:hover{filter:brightness(1.1)}\
</style>';

        $('body').append(css + html);

        // ─── EVENT BINDINGS ───
        $('#sfx-btn-close').on('click', function() { _close(); });
        $('#sfx-btn-sync').on('click', function() { _syncSeries(); });
        $('#sfx-btn-fix-series').on('click', function() { _fixSeries(); });
        $('#sfx-btn-ranges').on('click', function() { _toggleRangesPanel(); });
        $('#sfx-ranges-back').on('click', function() { _hideRangesPanel(); });

        // ESC to close
        $(document).on('keydown.sfx', function(e) {
            if (e.key === 'Escape' && $('#sfx-modal').is(':visible')) _close();
        });
    }

    // ─── OPEN PLAYER ───
    function _open(seriesData) {
        _buildModal();

        // Reset state
        _state = {
            series: seriesData,
            episodes: [],
            seasons: {},
            currentEpisode: null,
            currentSeason: '1',
            isPlaying: false,
            isLoading: true,
            isFixing: false,
            isFetchingRange: false,
            logs: [],
            ranges: null,
            rangesVisible: false,
        };

        // Show modal
        $('#sfx-modal').fadeIn(200);
        $('#sfx-log-content').empty().hide();
        $('#sfx-title').text(seriesData.title || 'Series Debug Player');
        $('#sfx-series-thumb').attr('src', seriesData.thumbnail || '');
        $('#sfx-series-name').text(seriesData.title || 'Unknown Series');
        $('#sfx-series-meta').text(
            (seriesData.total_episodes || '?') + ' episodes' +
            (seriesData.genre ? ' · ' + seriesData.genre : '') +
            (seriesData.vj ? ' · ' + seriesData.vj : '')
        );

        _showOverlay('Loading series data...');
        _log('Opening series: ' + (seriesData.title || 'ID ' + seriesData.id));

        // Fetch full series info from backend
        _fetchSeriesInfo(seriesData.id);
    }

    // ─── CLOSE PLAYER ───
    function _close() {
        var video = document.getElementById('sfx-video');
        if (video) {
            video.pause();
            video.src = '';
            video.load();
        }
        _state.isPlaying = false;
        $('#sfx-modal').fadeOut(200);
    }

    // ─── FETCH SERIES INFO ───
    function _fetchSeriesInfo(seriesId) {
        _log('Fetching series info from backend...');
        $.ajax({
            url: _getBase() + '/debug-player/series-info',
            method: 'POST',
            data: {series_id: seriesId, _token: LA.token},
            dataType: 'json',
            timeout: 30000,
            success: function(resp) {
                if (resp.success) {
                    _state.series = resp.series;
                    _state.episodes = resp.episodes || [];
                    _state.seasons = resp.seasons || {};
                    _log('Loaded ' + _state.episodes.length + ' episodes in ' + Object.keys(_state.seasons).length + ' season(s)', 'success');
                    _renderSidebar();
                    _renderSeriesHeader();

                    // Auto-play first episode
                    if (_state.episodes.length > 0) {
                        _playEpisode(_state.episodes[0]);
                    } else {
                        _showOverlay('No episodes found. Try "Sync" to fetch from remote.');
                        _log('No episodes found for this series', 'warn');
                    }
                } else {
                    _showOverlay('Error: ' + (resp.error || 'Unknown error'));
                    _log('Failed to load series: ' + (resp.error || 'Unknown'), 'error');
                }
            },
            error: function(xhr) {
                _showOverlay('Network error loading series data');
                _log('Network error: ' + xhr.status + ' ' + xhr.statusText, 'error');
            }
        });
    }

    // ─── RENDER SERIES HEADER ───
    function _renderSeriesHeader() {
        var s = _state.series;
        if (!s) return;

        $('#sfx-series-name').text(s.title || 'Unknown');
        if (s.thumbnail) $('#sfx-series-thumb').attr('src', s.thumbnail);

        var meta = [];
        if (s.total_episodes) meta.push(s.total_episodes + ' eps');
        if (s.total_seasons) meta.push(s.total_seasons + ' seasons');
        if (s.genre) meta.push(s.genre);
        if (s.vj) meta.push(s.vj);
        if (s.year) meta.push(s.year);
        if (s.language) meta.push(s.language);
        $('#sfx-series-meta').text(meta.join(' · '));

        var statusHtml = '';
        var isActive = s.is_active || 'No';
        var sColor = isActive === 'Yes' ? '#16a34a' : (isActive === 'Failed' ? '#dc2626' : '#6b7280');
        statusHtml += '<span style="background:' + sColor + ';color:#fff;font-size:9px;padding:1px 6px;border-radius:8px;font-weight:600">' + isActive + '</span>';
        if (s.is_muno === 'Yes') statusHtml += ' <span style="background:#7c3aed;color:#fff;font-size:9px;padding:1px 6px;border-radius:8px;font-weight:600">Munowatch</span>';
        if (s.series_code) statusHtml += ' <span style="background:#1e40af;color:#fff;font-size:9px;padding:1px 6px;border-radius:8px;font-weight:600">Code: ' + s.series_code + '</span>';
        if (s.fix_status) {
            var fxBg = s.fix_status === 'fixed' ? '#16a34a' : (s.fix_status === 'error' ? '#dc2626' : '#d97706');
            statusHtml += ' <span style="background:' + fxBg + ';color:#fff;font-size:9px;padding:1px 6px;border-radius:8px;font-weight:600">Fix: ' + s.fix_status + '</span>';
            if (s.fix_counter > 0) statusHtml += ' <span style="background:#6b7280;color:#fff;font-size:9px;padding:1px 6px;border-radius:8px;font-weight:600">#' + s.fix_counter + '</span>';
        }
        $('#sfx-series-status').html(statusHtml);

        // Title bar
        $('#sfx-title').text(s.title || 'Series Debug Player');
        var totalSeasons = Object.keys(_state.seasons).length;
        if (totalSeasons > 0) {
            $('#sfx-badge').text(totalSeasons + 'S · ' + _state.episodes.length + 'E').show();
        }
    }

    // ─── RENDER SIDEBAR ───
    function _renderSidebar() {
        var seasons = _state.seasons;
        var keys = Object.keys(seasons).sort(function(a, b) { return parseInt(a) - parseInt(b); });

        // Season tabs
        var $tabs = $('#sfx-season-tabs').empty();
        if (keys.length <= 1) {
            // Single season — no tabs needed
            _state.currentSeason = keys[0] || '1';
        } else {
            keys.forEach(function(sn) {
                var cnt = seasons[sn].length;
                var $tab = $('<div class="sfx-season-tab" data-season="' + sn + '">S' + sn + ' <small style="opacity:.5">(' + cnt + ')</small></div>');
                if (sn === _state.currentSeason) $tab.addClass('sfx-tab-active');
                $tab.on('click', function() {
                    _state.currentSeason = sn;
                    $('.sfx-season-tab').removeClass('sfx-tab-active');
                    $(this).addClass('sfx-tab-active');
                    _renderEpisodeList();
                });
                $tabs.append($tab);
            });
        }

        _renderEpisodeList();

        // Footer count
        $('#sfx-ep-count').text(
            _state.episodes.length + ' episodes · ' + keys.length + ' season(s)'
        );
    }

    // ─── RENDER EPISODE LIST ───
    function _renderEpisodeList() {
        var $list = $('#sfx-ep-list').empty();
        var sn = _state.currentSeason;
        var eps = _state.seasons[sn] || [];

        if (eps.length === 0) {
            $list.html('<div style="color:#6b7280;text-align:center;padding:20px;font-size:12px">No episodes in Season ' + sn + '</div>');
            return;
        }

        eps.forEach(function(ep, idx) {
            var isActive = _state.currentEpisode && _state.currentEpisode.id === ep.id;
            var hasUrl = ep.url && ep.url.length > 5;
            var statusClass = hasUrl ? 'sfx-ok' : 'sfx-err';
            if (ep.status === 'Inactive') statusClass = 'sfx-err';

            var epNum = ep.episode_number || (idx + 1);
            var title = ep.title || ('Episode ' + epNum);
            if (title.length > 35) title = title.substring(0, 35) + '…';

            var $item = $('<div class="sfx-ep-item' + (isActive ? ' sfx-active' : '') + '" data-ep-id="' + ep.id + '"></div>');
            $item.html(
                '<div class="sfx-ep-num ' + statusClass + '">' + epNum + '</div>' +
                '<div class="sfx-ep-title" title="' + _escHtml(ep.title || '') + '">' + _escHtml(title) + '</div>' +
                (ep.duration ? '<span class="sfx-ep-dur">' + ep.duration + '</span>' : '') +
                '<button class="sfx-btn sfx-btn-orange sfx-btn-sm sfx-ep-fix" data-ep-id="' + ep.id + '" title="Fix this episode">🔧</button>'
            );

            // Click to play
            $item.on('click', function(e) {
                if ($(e.target).closest('.sfx-ep-fix').length) return; // Don't play when fix btn clicked
                _playEpisode(ep);
            });

            // Fix button
            $item.find('.sfx-ep-fix').on('click', function(e) {
                e.stopPropagation();
                _fixEpisode(ep.id, $(this));
            });

            $list.append($item);
        });
    }

    // ─── PLAY EPISODE ───
    function _playEpisode(ep) {
        _state.currentEpisode = ep;
        _state.isPlaying = false;

        // Highlight active
        $('.sfx-ep-item').removeClass('sfx-active');
        $('.sfx-ep-item[data-ep-id="' + ep.id + '"]').addClass('sfx-active');

        _log('Playing episode #' + ep.id + ': ' + (ep.title || 'Unknown'));
        _renderEpisodeInfo(ep);

        var url = _sanitizeUrl(ep.url || '');
        if (!url) {
            _showOverlay('No video URL for this episode. Try "Fix" to repair.');
            _log('No URL for episode #' + ep.id, 'warn');
            return;
        }

        _showOverlay('Loading video...');

        // Build URL queue: stream proxy first (bypasses hotlink), then CDN fallback, then direct
        var queue = [];
        queue.push({url: _getStreamUrl(url), label: 'Stream Proxy', rawUrl: url});
        var fallback = _getCdnFallback(url);
        if (fallback) {
            queue.push({url: _getStreamUrl(fallback), label: 'CDN Fallback', rawUrl: fallback});
        }
        queue.push({url: url, label: 'Direct', rawUrl: url});

        _tryPlayQueue(queue, 0);
    }

    // ─── TRY PLAY QUEUE (auto-cascade) ───
    function _tryPlayQueue(queue, idx) {
        if (idx >= queue.length) {
            _showOverlay('All playback attempts failed for this episode.');
            _log('All ' + queue.length + ' attempts failed', 'error');
            return;
        }

        var attempt = queue[idx];
        _log('Attempt ' + (idx + 1) + '/' + queue.length + ': ' + attempt.label + ' → ' + attempt.rawUrl);

        var video = document.getElementById('sfx-video');
        if (!video) return;

        // Timeout — if nothing happens in 12s, try next
        var timeout = setTimeout(function() {
            _log('Timeout on attempt ' + (idx + 1), 'warn');
            video.removeAttribute('src');
            video.load();
            _tryPlayQueue(queue, idx + 1);
        }, 12000);

        var cleanup = function() {
            clearTimeout(timeout);
            $(video).off('.sfxplay');
        };

        $(video).on('loadedmetadata.sfxplay', function() {
            cleanup();
            _hideOverlay();
            _state.isPlaying = true;
            video.play().catch(function() {});
            _log('Playing via ' + attempt.label + ' (' + Math.round(video.duration) + 's)', 'success');
        });

        $(video).on('error.sfxplay', function() {
            cleanup();
            var err = video.error;
            _log(attempt.label + ' error: ' + (err ? ('code=' + err.code + ' ' + (err.message || '')) : 'unknown'), 'error');
            _tryPlayQueue(queue, idx + 1);
        });

        video.src = attempt.url;
        video.load();
    }

    // ─── RENDER EPISODE INFO ───
    function _renderEpisodeInfo(ep) {
        var html = '<div style="display:flex;gap:16px;flex-wrap:wrap">';

        // Left: Basic info
        html += '<div style="flex:1;min-width:200px">';
        html += '<div style="color:#fff;font-size:14px;font-weight:600;margin-bottom:6px">' + _escHtml(ep.title || 'Unknown') + '</div>';
        html += '<table style="font-size:11px;color:#9ca3af;line-height:1.8">';
        html += '<tr><td style="padding-right:12px;white-space:nowrap;color:#6b7280">ID</td><td style="color:#d1d5db">' + ep.id + '</td></tr>';
        if (ep.episode_number) html += '<tr><td style="padding-right:12px;color:#6b7280">Episode</td><td style="color:#d1d5db">' + ep.episode_number + '</td></tr>';
        if (ep.season_number) html += '<tr><td style="padding-right:12px;color:#6b7280">Season</td><td style="color:#d1d5db">' + ep.season_number + '</td></tr>';
        if (ep.duration) html += '<tr><td style="padding-right:12px;color:#6b7280">Duration</td><td style="color:#d1d5db">' + ep.duration + '</td></tr>';
        if (ep.vj) html += '<tr><td style="padding-right:12px;color:#6b7280">VJ</td><td style="color:#d1d5db">' + _escHtml(ep.vj) + '</td></tr>';
        if (ep.genre) html += '<tr><td style="padding-right:12px;color:#6b7280">Genre</td><td style="color:#d1d5db">' + _escHtml(ep.genre) + '</td></tr>';
        html += '<tr><td style="padding-right:12px;color:#6b7280">Status</td><td>';
        var stColor = ep.status === 'Active' ? '#16a34a' : '#dc2626';
        html += '<span style="color:' + stColor + ';font-weight:600">' + (ep.status || '?') + '</span>';
        html += '</td></tr>';
        if (ep.fix_status) {
            var fxColor = ep.fix_status === 'fixed' ? '#16a34a' : (ep.fix_status === 'error' ? '#dc2626' : '#d97706');
            html += '<tr><td style="padding-right:12px;color:#6b7280">Fix Status</td><td><span style="color:' + fxColor + ';font-weight:600">' + _escHtml(ep.fix_status) + '</span>';
            if (ep.fix_counter > 0) html += ' <span style="color:#6b7280;font-size:10px">(#' + ep.fix_counter + ')</span>';
            html += '</td></tr>';
        }
        html += '</table></div>';

        // Right: URLs
        html += '<div style="flex:1;min-width:200px">';
        html += '<table style="font-size:11px;color:#9ca3af;line-height:1.8;word-break:break-all">';
        html += '<tr><td style="padding-right:8px;white-space:nowrap;color:#6b7280">Video URL</td><td style="color:#60a5fa">' + _escHtml(ep.url || '—') + '</td></tr>';
        html += '<tr><td style="padding-right:8px;color:#6b7280">Ext URL</td><td style="color:#60a5fa">' + _escHtml(ep.external_url || '—') + '</td></tr>';
        if (ep.munowatch_id) html += '<tr><td style="padding-right:8px;color:#6b7280">Munowatch</td><td style="color:#d1d5db">' + ep.munowatch_id + '</td></tr>';
        html += '</table></div>';

        html += '</div>';
        $('#sfx-ep-info-content').html(html);
    }

    // ─── FIX EPISODE ───
    function _fixEpisode(movieId, $btn) {
        if (_state.isFixing) return;
        _state.isFixing = true;

        var origHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="sfx-spinner"></span>');
        _log('Fixing episode #' + movieId + '...', 'fix');

        $.ajax({
            url: _getBase() + '/debug-player/fix-episode',
            method: 'POST',
            data: {movie_id: movieId, _token: LA.token},
            dataType: 'json',
            timeout: 60000,
            success: function(resp) {
                _state.isFixing = false;
                $btn.prop('disabled', false).html(origHtml);

                if (resp.success) {
                    _log('Episode #' + movieId + ' fixed: ' + (resp.message || 'OK'), 'success');
                    // Update the episode data in state
                    if (resp.movie) {
                        _updateEpisodeInState(movieId, resp.movie);
                    }
                    // If this is the currently playing episode, reload it
                    if (_state.currentEpisode && _state.currentEpisode.id === movieId) {
                        var updatedEp = _findEpisodeById(movieId);
                        if (updatedEp) _playEpisode(updatedEp);
                    }
                    _renderEpisodeList();
                } else {
                    _log('Fix failed for #' + movieId + ': ' + (resp.message || 'Unknown'), 'error');
                }
            },
            error: function(xhr) {
                _state.isFixing = false;
                $btn.prop('disabled', false).html(origHtml);
                _log('Fix request error: ' + xhr.status, 'error');
            }
        });
    }

    // ─── FIX ENTIRE SERIES ───
    function _fixSeries() {
        if (!_state.series || _state.isFixing) return;
        if (!confirm('Fix entire series "' + _state.series.title + '"? This will fix all episodes (may take a while).')) return;

        _state.isFixing = true;
        var $btn = $('#sfx-btn-fix-series');
        $btn.prop('disabled', true).html('<span class="sfx-spinner"></span> Fixing...');
        _log('Fixing entire series #' + _state.series.id + '...', 'fix');

        $.ajax({
            url: _getBase() + '/debug-player/fix-series',
            method: 'POST',
            data: {series_id: _state.series.id, _token: LA.token},
            dataType: 'json',
            timeout: 300000, // 5 minutes
            success: function(resp) {
                _state.isFixing = false;
                $btn.prop('disabled', false).html('🔧 Fix Series');

                if (resp.success) {
                    _log('Series fix complete: ' + (resp.message || ''), 'success');
                    if (resp.fixed) _log('Fixed: ' + resp.fixed + ', Failed: ' + (resp.failed || 0), 'success');
                    // Reload series data
                    _fetchSeriesInfo(_state.series.id);
                } else {
                    _log('Series fix failed: ' + (resp.error || resp.message || 'Unknown'), 'error');
                }
            },
            error: function(xhr) {
                _state.isFixing = false;
                $btn.prop('disabled', false).html('🔧 Fix Series');
                _log('Series fix request error: ' + xhr.status, 'error');
            }
        });
    }

    // ─── SYNC SERIES (fetch all ranges, batched per range) ───
    function _syncSeries() {
        if (!_state.series || _state.isFixing) return;
        if (!confirm('Sync ALL episodes for "' + _state.series.title + '" from remote API?\nThis will fetch every range in batches of 3 episodes per request.')) return;

        // First, fetch ranges if we don't have them
        if (!_state.ranges) {
            _log('Loading ranges first before full sync...', 'info');
            var $btn = $('#sfx-btn-sync');
            $btn.prop('disabled', true).html('<span class="sfx-spinner"></span> Loading...');

            $.ajax({
                url: _getBase() + '/debug-player/series-remote-episodes',
                method: 'POST',
                data: {series_id: _state.series.id, _token: LA.token},
                dataType: 'json',
                timeout: 60000,
                success: function(resp) {
                    if (resp.success) {
                        _state.ranges = resp;
                        _doFullSync();
                    } else {
                        $btn.prop('disabled', false).html('🔄 Sync All');
                        _log('Failed to load ranges: ' + (resp.error || 'Unknown'), 'error');
                    }
                },
                error: function(xhr) {
                    $btn.prop('disabled', false).html('🔄 Sync All');
                    _log('Range load error: ' + xhr.status, 'error');
                }
            });
        } else {
            _doFullSync();
        }
    }

    function _doFullSync() {
        if (!_state.ranges || !_state.ranges.ranges) return;

        _state.isFixing = true;
        var $btn = $('#sfx-btn-sync');
        var allRanges = [];
        for (var i = 0; i < _state.ranges.ranges.length; i++) allRanges.push(i);

        var totalRanges = allRanges.length;
        var current = 0;
        var grandCreated = 0, grandUpdated = 0, grandSkipped = 0;
        var anyChainEnded = false;

        $btn.prop('disabled', true).html('<span class="sfx-spinner"></span> 0/' + totalRanges);
        _log('Full sync: ' + totalRanges + ' ranges to process (batched)...', 'fix');

        function syncNextRange() {
            if (current >= totalRanges) {
                _state.isFixing = false;
                $btn.prop('disabled', false).html('🔄 Sync All');
                _log('Full sync complete! Created: ' + grandCreated + ', Updated: ' + grandUpdated + ', Skipped: ' + grandSkipped, 'success');
                _fetchSeriesInfo(_state.series.id);
                _state.ranges = null;
                if (anyChainEnded) _checkAndActivateSeries();
                $('#sfx-log-content').show();
                return;
            }

            var ridx = allRanges[current];
            var range = _state.ranges.ranges[ridx];
            var label = 'Eps ' + range.start_ep + '–' + range.end_ep;
            $btn.html('<span class="sfx-spinner"></span> ' + (current + 1) + '/' + totalRanges);
            _log('Sync range ' + (current + 1) + '/' + totalRanges + ': ' + label, 'fix');

            var continueFrom = null, continueEp = null, rangeEndEp = null, batchNum = 0;

            // Pre-initialize from range data for FAST PATH
            if (range) {
                continueFrom = String(range.start_video_id);
                continueEp = range.start_ep;
                rangeEndEp = range.end_ep;
            }

            function fetchBatch() {
                batchNum++;
                $btn.html('<span class="sfx-spinner"></span> ' + (current + 1) + '/' + totalRanges + ': ' + label + ' B' + batchNum);
                var postData = {
                    series_id: _state.series.id,
                    range_index: ridx,
                    season: _state.currentSeason,
                    batch_size: 20,
                    _token: LA.token
                };
                if (continueFrom) postData.continue_from = continueFrom;
                if (continueEp !== null) postData.continue_ep = continueEp;
                if (rangeEndEp !== null) postData.range_end_ep = rangeEndEp;

                $.ajax({
                    url: _getBase() + '/debug-player/fetch-range',
                    method: 'POST',
                    data: postData,
                    dataType: 'json',
                    timeout: 120000,
                    success: function(resp) {
                        if (resp.success) {
                            grandCreated += (resp.created || 0);
                            grandUpdated += (resp.updated || 0);
                            grandSkipped += (resp.skipped || 0);
                            if (resp.range_end_ep) rangeEndEp = resp.range_end_ep;
                            if (resp.chain_ended) anyChainEnded = true;

                            // Live-update sidebar
                            if (resp.series_info) {
                                _state.series = resp.series_info.series || _state.series;
                                _state.episodes = resp.series_info.episodes || _state.episodes;
                                _state.seasons = resp.series_info.seasons || _state.seasons;
                                _renderSidebar();
                                _renderSeriesHeader();
                            }

                            if (resp.has_more && resp.next_video_id) {
                                continueFrom = resp.next_video_id;
                                continueEp = resp.next_ep_num;
                                fetchBatch();
                            } else {
                                _log('  ' + label + ' ✓', 'info');
                                current++;
                                syncNextRange();
                            }
                        } else {
                            _log('  ' + label + ' failed: ' + (resp.error || ''), 'error');
                            current++;
                            syncNextRange();
                        }
                    },
                    error: function(xhr) {
                        _log('  ' + label + ' error: ' + xhr.status, 'error');
                        current++;
                        syncNextRange();
                    }
                });
            }

            fetchBatch();
        }

        syncNextRange();
    }

    // ─── RANGES PANEL: Toggle Visibility ───
    function _toggleRangesPanel() {
        if (_state.rangesVisible) {
            _hideRangesPanel();
        } else {
            _showRangesPanel();
        }
    }

    function _showRangesPanel() {
        _state.rangesVisible = true;
        $('#sfx-ep-list').hide();
        $('#sfx-season-tabs').hide();
        $('#sfx-ranges-panel').show();
        $('#sfx-btn-ranges').html('📋 Episodes').attr('title', 'Back to episode list');

        if (!_state.ranges) {
            _fetchRangesData();
        } else {
            _renderRangesList();
        }
    }

    function _hideRangesPanel() {
        _state.rangesVisible = false;
        $('#sfx-ranges-panel').hide();
        $('#sfx-ep-list').show();
        $('#sfx-season-tabs').show();
        $('#sfx-btn-ranges').html('📦 Ranges').attr('title', 'Show episode ranges from remote API');
    }

    // ─── FETCH RANGES DATA from remote ───
    function _fetchRangesData() {
        if (!_state.series) return;

        $('#sfx-ranges-list').html('<div style="text-align:center;padding:20px"><span class="sfx-spinner"></span><div style="color:#7c8da6;font-size:11px;margin-top:8px">Loading ranges from remote API...</div></div>');
        _log('Fetching episode ranges from munowatch API...', 'info');

        $.ajax({
            url: _getBase() + '/debug-player/series-remote-episodes',
            method: 'POST',
            data: {series_id: _state.series.id, _token: LA.token},
            dataType: 'json',
            timeout: 60000,
            success: function(resp) {
                if (resp.success) {
                    _state.ranges = resp;
                    _log('Loaded ' + resp.total_ranges + ' ranges (' + resp.total_remote_episodes + ' remote eps, ' + resp.local_episode_count + ' local)', 'success');
                    _renderRangesList();
                } else {
                    _log('Failed to load ranges: ' + (resp.error || 'Unknown'), 'error');
                    $('#sfx-ranges-list').html('<div style="color:#f87171;text-align:center;padding:20px;font-size:12px">Error: ' + _escHtml(resp.error || 'Unknown error') + '</div>');
                }
                $('#sfx-log-content').show();
            },
            error: function(xhr) {
                _log('Range fetch error: ' + xhr.status, 'error');
                $('#sfx-ranges-list').html('<div style="color:#f87171;text-align:center;padding:20px;font-size:12px">Network error loading ranges</div>');
            }
        });
    }

    // ─── RENDER RANGES LIST ───
    function _renderRangesList() {
        var data = _state.ranges;
        if (!data || !data.ranges) return;

        // Summary
        var summaryHtml = '<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:4px">';
        summaryHtml += '<span>Remote: <b style="color:#f59e0b">' + data.total_remote_episodes + '</b> eps</span>';
        summaryHtml += '<span>Local: <b style="color:#3b82f6">' + data.local_episode_count + '</b> eps</span>';
        if (data.total_from_preview) summaryHtml += '<span>Preview: <b style="color:#8b5cf6">' + data.total_from_preview + '</b></span>';
        summaryHtml += '</div>';
        if (data.series_code) summaryHtml += '<div style="opacity:.6;font-size:10px">Series Code: ' + data.series_code + ' • Show ID: ' + data.show_id + '</div>';
        $('#sfx-ranges-summary').html(summaryHtml);

        // Range cards
        var $list = $('#sfx-ranges-list').empty();
        var allComplete = true;

        data.ranges.forEach(function(range, idx) {
            var pct = range.episode_count > 0 ? Math.round((range.local_count / range.episode_count) * 100) : 0;
            var isComplete = range.is_complete;
            if (!isComplete) allComplete = false;

            // Color based on completion
            var barColor = isComplete ? '#16a34a' : (range.local_count > 0 ? '#f59e0b' : '#dc2626');
            var statusIcon = isComplete ? '✅' : (range.local_count > 0 ? '🟡' : '🔴');
            var bgColor = isComplete ? 'rgba(22,163,106,.08)' : 'rgba(245,158,11,.05)';

            var html = '<div class="sfx-range-card" data-range="' + idx + '" style="background:' + bgColor + ';border:1px solid ' + barColor + '33;border-radius:8px;padding:10px;margin-bottom:6px">';

            // Header row
            html += '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">';
            html += '<div style="display:flex;align-items:center;gap:6px">';
            html += '<span style="font-size:12px">' + statusIcon + '</span>';
            html += '<span style="color:#d1d5db;font-size:12px;font-weight:600">Eps ' + range.start_ep + '–' + range.end_ep + '</span>';
            html += '<span style="color:#6b7280;font-size:10px">(' + range.episode_count + ' eps)</span>';
            html += '</div>';
            html += '<span style="color:' + barColor + ';font-size:10px;font-weight:700">' + range.local_count + '/' + range.episode_count + '</span>';
            html += '</div>';

            // Progress bar
            html += '<div style="height:4px;background:#1f2937;border-radius:2px;overflow:hidden;margin-bottom:6px">';
            html += '<div class="sfx-range-progress-fill" style="height:100%;width:' + pct + '%;background:' + barColor + ';border-radius:2px;transition:width .3s"></div>';
            html += '</div>';

            // Details row
            html += '<div style="display:flex;justify-content:space-between;align-items:center">';
            html += '<span style="color:#6b7280;font-size:9px">VIDs: ' + range.start_video_id + '→' + range.end_video_id;
            if (!range.is_contiguous) html += ' <span style="color:#f59e0b" title="Non-contiguous: video IDs have gaps, requires chain traversal">⚡ chain</span>';
            html += '</span>';

            // Fetch button
            if (!isComplete) {
                html += '<button class="sfx-btn sfx-btn-sm sfx-range-fetch" data-range-idx="' + idx + '" style="background:#2563eb;font-size:10px">⬇ Fetch</button>';
            } else {
                html += '<button class="sfx-btn sfx-btn-sm sfx-range-fetch" data-range-idx="' + idx + '" style="background:#374151;font-size:10px">↻ Re-fetch</button>';
            }
            html += '</div>';

            html += '</div>';
            $list.append(html);
        });

        // Fetch All button at bottom
        var $actions = $('#sfx-ranges-actions').empty();
        if (!allComplete) {
            $actions.append('<button id="sfx-fetch-all-ranges" class="sfx-btn sfx-btn-blue" style="width:100%;padding:8px">⬇ Fetch All Missing Ranges</button>');
            $('#sfx-fetch-all-ranges').on('click', function() { _fetchAllMissingRanges(); });
        } else {
            $actions.append('<div style="color:#16a34a;font-size:11px;padding:8px">✅ All ranges are fully synced!</div>');
        }

        // Bind fetch buttons
        $list.find('.sfx-range-fetch').on('click', function(e) {
            e.stopPropagation();
            var ridx = parseInt($(this).data('range-idx'));
            _fetchSingleRange(ridx, $(this));
        });
    }

    // ─── FETCH SINGLE RANGE (batched — 3 eps per request to avoid MAMP 30s timeout) ───
    function _fetchSingleRange(rangeIdx, $btn) {
        if (_state.isFetchingRange) {
            _log('Already fetching a range, please wait...', 'warn');
            return;
        }
        _state.isFetchingRange = true;

        var range = _state.ranges && _state.ranges.ranges ? _state.ranges.ranges[rangeIdx] : null;
        var label = range ? ('Eps ' + range.start_ep + '–' + range.end_ep) : ('Range #' + rangeIdx);
        var totalExpected = range ? range.episode_count : 0;

        var origHtml = $btn.html();
        $btn.prop('disabled', true);
        _log('Fetching ' + label + ' in batches of 3...', 'fix');
        $('#sfx-log-content').show();

        var totalCreated = 0, totalUpdated = 0, totalSkipped = 0, totalFetched = 0, batchNum = 0;
        var chainEnded = false;
        // Pre-initialize from range data so first batch also uses FAST PATH (skips resolve+ranges API calls)
        var continueFrom = range ? String(range.start_video_id) : null;
        var continueEp = range ? range.start_ep : null;
        var rangeEndEp = range ? range.end_ep : null;

        // Find the progress bar for this range card
        var $card = $btn.closest('.sfx-range-card');
        var $progressBar = $card.find('.sfx-range-progress-fill');
        var $statusText = $card.find('.sfx-range-status');

        function _updateProgress() {
            var pct = totalExpected > 0 ? Math.min(100, Math.round((totalFetched / totalExpected) * 100)) : 0;
            if ($progressBar.length) $progressBar.css('width', pct + '%');
            $btn.html('<span class="sfx-spinner"></span> ' + totalFetched + '/' + totalExpected);
            if ($statusText.length) $statusText.text(totalFetched + '/' + totalExpected);
        }

        function fetchBatch() {
            batchNum++;
            _updateProgress();

            var postData = {
                series_id: _state.series.id,
                range_index: rangeIdx,
                season: _state.currentSeason,
                batch_size: 20,
                _token: LA.token
            };
            if (continueFrom) postData.continue_from = continueFrom;
            if (continueEp !== null) postData.continue_ep = continueEp;
            if (rangeEndEp !== null) postData.range_end_ep = rangeEndEp;

            $.ajax({
                url: _getBase() + '/debug-player/fetch-range',
                method: 'POST',
                data: postData,
                dataType: 'json',
                timeout: 120000,
                success: function(resp) {
                    if (resp.success) {
                        totalCreated += (resp.created || 0);
                        totalUpdated += (resp.updated || 0);
                        totalSkipped += (resp.skipped || 0);
                        totalFetched += (resp.created || 0) + (resp.updated || 0) + (resp.skipped || 0);
                        _log('  Batch ' + batchNum + ': ' + resp.message, 'info');

                        if (resp.errors && resp.errors.length) {
                            resp.errors.forEach(function(e) { _log('  ⚠️ ' + e, 'warn'); });
                        }

                        // Live-update sidebar with latest episode data
                        if (resp.series_info) {
                            _state.series = resp.series_info.series || _state.series;
                            _state.episodes = resp.series_info.episodes || _state.episodes;
                            _state.seasons = resp.series_info.seasons || _state.seasons;
                            _renderSidebar();
                            _renderSeriesHeader();
                        }

                        // Store range_end_ep for fast continuation
                        if (resp.range_end_ep) rangeEndEp = resp.range_end_ep;

                        // Detect end of episode chain
                        if (resp.chain_ended) {
                            chainEnded = true;
                            _log('🏁 Reached end of episode chain!', 'success');
                        }

                        _updateProgress();

                        // Continue fetching if more batches remain
                        if (resp.has_more && resp.next_video_id) {
                            continueFrom = resp.next_video_id;
                            continueEp = resp.next_ep_num;
                            fetchBatch();
                        } else {
                            // Done with this range
                            _finishRangeFetch(label, totalCreated, totalUpdated, totalSkipped, origHtml, $btn, chainEnded);
                        }
                    } else {
                        _log('Fetch failed for ' + label + ': ' + (resp.error || 'Unknown'), 'error');
                        _state.isFetchingRange = false;
                        $btn.prop('disabled', false).html(origHtml);
                        $('#sfx-log-content').show();
                    }
                },
                error: function(xhr) {
                    _log('Fetch request error for ' + label + ' (batch ' + batchNum + '): ' + xhr.status + ' ' + xhr.statusText, 'error');
                    // If we already fetched some batches, still finish gracefully
                    if (batchNum > 1) {
                        _finishRangeFetch(label, totalCreated, totalUpdated, totalSkipped, origHtml, $btn, chainEnded);
                    } else {
                        _state.isFetchingRange = false;
                        $btn.prop('disabled', false).html(origHtml);
                    }
                    $('#sfx-log-content').show();
                }
            });
        }

        fetchBatch();
    }

    function _finishRangeFetch(label, totalCreated, totalUpdated, totalSkipped, origHtml, $btn, chainEnded) {
        _state.isFetchingRange = false;
        $btn.prop('disabled', false).html(origHtml);

        _log(label + ' complete: ' + totalCreated + ' created, ' + totalUpdated + ' updated, ' + totalSkipped + ' skipped', 'success');

        // Live-refresh sidebar and header
        _renderSidebar();
        _renderSeriesHeader();

        // If chain ended (last episode reached), check if we should activate the series
        if (chainEnded) {
            _checkAndActivateSeries();
        }

        // Force re-fetch ranges data to update completion status
        _state.ranges = null;
        _fetchRangesData();
        $('#sfx-log-content').show();
    }

    /**
     * Call backend to check if the series should be activated (all episodes fetched).
     * Also triggers title cleaning.
     */
    function _checkAndActivateSeries() {
        if (!_state.series) return;
        _log('Checking if series should be activated...', 'info');

        $.ajax({
            url: _getBase() + '/debug-player/check-activation',
            method: 'POST',
            data: { series_id: _state.series.id, _token: LA.token },
            dataType: 'json',
            timeout: 15000,
            success: function(resp) {
                if (resp.success) {
                    if (resp.activated) {
                        _log('✨ Series activated! ' + resp.reason, 'success');
                    }
                    if (resp.title_cleaned) {
                        _log('📝 Title cleaned: → ' + resp.new_title, 'success');
                    }
                    if (resp.series_info) {
                        _state.series = resp.series_info.series || _state.series;
                        _state.episodes = resp.series_info.episodes || _state.episodes;
                        _state.seasons = resp.series_info.seasons || _state.seasons;
                        _renderSidebar();
                        _renderSeriesHeader();
                    }
                }
            },
            error: function() { /* silent fail */ }
        });
    }

    // ─── FETCH ALL MISSING RANGES (sequential, batched per range) ───
    function _fetchAllMissingRanges() {
        if (!_state.ranges || !_state.ranges.ranges) return;
        if (_state.isFetchingRange) {
            _log('Already fetching, please wait...', 'warn');
            return;
        }

        var missing = [];
        _state.ranges.ranges.forEach(function(r, idx) {
            if (!r.is_complete) missing.push(idx);
        });

        if (missing.length === 0) {
            _log('All ranges are already complete!', 'success');
            return;
        }

        _log('Fetching ' + missing.length + ' incomplete range(s) sequentially (batched)...', 'fix');

        var $allBtn = $('#sfx-fetch-all-ranges');
        $allBtn.prop('disabled', true).html('<span class="sfx-spinner"></span> Fetching 0/' + missing.length + '...');

        var current = 0;
        var anyChainEnded = false;

        function fetchNextRange() {
            if (current >= missing.length) {
                $allBtn.prop('disabled', false).html('⬇ Fetch All Missing Ranges');
                _log('All ' + missing.length + ' ranges fetched!', 'success');
                // Reload everything
                _fetchSeriesInfo(_state.series.id);
                _state.ranges = null;
                if (anyChainEnded) _checkAndActivateSeries();
                if (_state.rangesVisible) {
                    setTimeout(function() { _fetchRangesData(); }, 1500);
                }
                return;
            }

            var ridx = missing[current];
            var range = _state.ranges.ranges[ridx];
            var label = 'Eps ' + range.start_ep + '–' + range.end_ep;

            $allBtn.html('<span class="sfx-spinner"></span> ' + (current + 1) + '/' + missing.length + ': ' + label);
            _log('Batch fetch ' + (current + 1) + '/' + missing.length + ': ' + label + '...', 'fix');

            var $cardBtn = $('.sfx-range-fetch[data-range-idx="' + ridx + '"]');
            var origCardHtml = $cardBtn.html();
            $cardBtn.prop('disabled', true).html('<span class="sfx-spinner"></span>');

            // Batched fetch for this range
            // Pre-initialize from range data so first batch also uses FAST PATH
            var continueFrom = range ? String(range.start_video_id) : null;
            var continueEp = range ? range.start_ep : null;
            var rangeEndEp = range ? range.end_ep : null;
            var batchNum = 0;

            function fetchRangeBatch() {
                batchNum++;
                $cardBtn.html('<span class="sfx-spinner"></span> B' + batchNum);

                var postData = {
                    series_id: _state.series.id,
                    range_index: ridx,
                    season: _state.currentSeason,
                    batch_size: 20,
                    _token: LA.token
                };
                if (continueFrom) postData.continue_from = continueFrom;
                if (continueEp !== null) postData.continue_ep = continueEp;
                if (rangeEndEp !== null) postData.range_end_ep = rangeEndEp;

                $.ajax({
                    url: _getBase() + '/debug-player/fetch-range',
                    method: 'POST',
                    data: postData,
                    dataType: 'json',
                    timeout: 120000,
                    success: function(resp) {
                        if (resp.success) {
                            _log('  ' + label + ' batch ' + batchNum + ': ' + resp.message, 'info');
                            if (resp.range_end_ep) rangeEndEp = resp.range_end_ep;
                            if (resp.chain_ended) anyChainEnded = true;

                            // Live-update sidebar
                            if (resp.series_info) {
                                _state.series = resp.series_info.series || _state.series;
                                _state.episodes = resp.series_info.episodes || _state.episodes;
                                _state.seasons = resp.series_info.seasons || _state.seasons;
                                _renderSidebar();
                                _renderSeriesHeader();
                            }

                            if (resp.has_more && resp.next_video_id) {
                                continueFrom = resp.next_video_id;
                                continueEp = resp.next_ep_num;
                                fetchRangeBatch();
                            } else {
                                // This range done
                                $cardBtn.prop('disabled', false).html(origCardHtml);
                                var $card = $cardBtn.closest('.sfx-range-card');
                                $card.css({'background': 'rgba(22,163,106,.08)', 'border-color': 'rgba(22,163,106,.3)'});
                                $cardBtn.css('background', '#374151').html('↻ Re-fetch');
                                _log(label + ' complete ✓', 'success');
                                current++;
                                fetchNextRange();
                            }
                        } else {
                            _log(label + ' failed: ' + (resp.error || 'Unknown'), 'error');
                            $cardBtn.prop('disabled', false).html(origCardHtml);
                            current++;
                            fetchNextRange();
                        }
                    },
                    error: function(xhr) {
                        _log(label + ' request error: ' + xhr.status, 'error');
                        $cardBtn.prop('disabled', false).html(origCardHtml);
                        current++;
                        fetchNextRange();
                    }
                });
            }

            fetchRangeBatch();
        }

        fetchNextRange();
    }

    // ─── HELPERS ───
    function _showOverlay(text) {
        $('#sfx-video-overlay').show();
        $('#sfx-overlay-text').html(text);
    }

    function _hideOverlay() {
        $('#sfx-video-overlay').hide();
    }

    function _escHtml(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s));
        return d.innerHTML;
    }

    function _updateEpisodeInState(movieId, movieData) {
        // Update in episodes array
        for (var i = 0; i < _state.episodes.length; i++) {
            if (_state.episodes[i].id === movieId) {
                // Merge updated fields
                if (movieData.url) _state.episodes[i].url = movieData.url;
                if (movieData.title) _state.episodes[i].title = movieData.title;
                if (movieData.status) _state.episodes[i].status = movieData.status;
                if (movieData.thumbnail_url) _state.episodes[i].thumbnail_url = movieData.thumbnail_url;
                if (movieData.episode_number) _state.episodes[i].episode_number = movieData.episode_number;
                if (movieData.duration) _state.episodes[i].duration = movieData.duration;
                break;
            }
        }
        // Update in seasons
        for (var sn in _state.seasons) {
            for (var j = 0; j < _state.seasons[sn].length; j++) {
                if (_state.seasons[sn][j].id === movieId) {
                    if (movieData.url) _state.seasons[sn][j].url = movieData.url;
                    if (movieData.title) _state.seasons[sn][j].title = movieData.title;
                    if (movieData.status) _state.seasons[sn][j].status = movieData.status;
                    if (movieData.thumbnail_url) _state.seasons[sn][j].thumbnail_url = movieData.thumbnail_url;
                    if (movieData.episode_number) _state.seasons[sn][j].episode_number = movieData.episode_number;
                    if (movieData.duration) _state.seasons[sn][j].duration = movieData.duration;
                    break;
                }
            }
        }
    }

    function _findEpisodeById(id) {
        for (var i = 0; i < _state.episodes.length; i++) {
            if (_state.episodes[i].id === id) return _state.episodes[i];
        }
        return null;
    }

    // ─── PUBLIC API ───
    window.UgflixSeriesPlayer = {
        play: function(seriesData) { _open(seriesData); },
        close: function() { _close(); }
    };

    // ─── DELEGATED CLICK HANDLER ───
    $(document).on('click', '.ugflix-series-play-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var raw = $(this).attr('data-series');
        if (!raw) return;
        try {
            var data = JSON.parse(raw);
            UgflixSeriesPlayer.play(data);
        } catch (ex) {
            console.error('Series player: invalid data-series JSON', ex);
        }
    });

})(jQuery);
