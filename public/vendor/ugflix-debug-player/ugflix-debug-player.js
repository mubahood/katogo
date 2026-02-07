/**
 * UGFlix Debug Video Player v3.0
 *
 * Admin debug player for testing movie URL playback.
 * Square-cornered, icon-based professional UI. Zero emojis.
 *
 * Flow:
 *  1. Read `url` from movie record
 *  2. Sanitize (trim, HTTPS upgrade for CDN, encode specials)
 *  3. Dead munoserverXX -> fallback to munotek.b-cdn.net
 *  4. Auto-cascade: CDN fallback first, then original
 *  5. Referrer-Policy: no-referrer (HTTP header middleware)
 *
 * @version 3.0.0
 * @requires jQuery, Bootstrap 3 (from laravel-admin)
 */
var UgflixDebugPlayer = (function () {
    'use strict';

    /* ================================================================
     *  SVG ICONS (inline, no external deps)
     * ================================================================ */
    var ICO = {
        play:      '<svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>',
        server:    '<svg viewBox="0 0 24 24"><path d="M20 13H4c-.55 0-1 .45-1 1v6c0 .55.45 1 1 1h16c.55 0 1-.45 1-1v-6c0-.55-.45-1-1-1zM7 19c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zM20 3H4c-.55 0-1 .45-1 1v6c0 .55.45 1 1 1h16c.55 0 1-.45 1-1V4c0-.55-.45-1-1-1zM7 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/></svg>',
        link:      '<svg viewBox="0 0 24 24"><path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/></svg>',
        close:     '<svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>',
        info:      '<svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>',
        url:       '<svg viewBox="0 0 24 24"><path d="M17 7h-4v2h4c1.65 0 3 1.35 3 3s-1.35 3-3 3h-4v2h4c2.76 0 5-2.24 5-5s-2.24-5-5-5zm-6 8H7c-1.65 0-3-1.35-3-3s1.35-3 3-3h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-2zm-3-4h8v2H8z"/></svg>',
        log:       '<svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12z"/></svg>',
        check:     '<svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>',
        error:     '<svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>',
        warn:      '<svg viewBox="0 0 24 24"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>',
        refresh:   '<svg viewBox="0 0 24 24"><path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg>',
        globe:     '<svg viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zm6.93 6h-2.95c-.32-1.25-.78-2.45-1.38-3.56 1.84.63 3.37 1.91 4.33 3.56zM12 4.04c.83 1.2 1.48 2.53 1.91 3.96h-3.82c.43-1.43 1.08-2.76 1.91-3.96zM4.26 14C4.1 13.36 4 12.69 4 12s.1-1.36.26-2h3.38c-.08.66-.14 1.32-.14 2 0 .68.06 1.34.14 2H4.26zm.82 2h2.95c.32 1.25.78 2.45 1.38 3.56-1.84-.63-3.37-1.9-4.33-3.56zm2.95-8H5.08c.96-1.66 2.49-2.93 4.33-3.56C8.81 5.55 8.35 6.75 8.03 8zM12 19.96c-.83-1.2-1.48-2.53-1.91-3.96h3.82c-.43 1.43-1.08 2.76-1.91 3.96zM14.34 14H9.66c-.09-.66-.16-1.32-.16-2 0-.68.07-1.35.16-2h4.68c.09.65.16 1.32.16 2 0 .68-.07 1.34-.16 2zm.25 5.56c.6-1.11 1.06-2.31 1.38-3.56h2.95c-.96 1.65-2.49 2.93-4.33 3.56zM16.36 14c.08-.66.14-1.32.14-2 0-.68-.06-1.34-.14-2h3.38c.16.64.26 1.31.26 2s-.1 1.36-.26 2h-3.38z"/></svg>',
        file:      '<svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zM6 20V4h7v5h5v11H6z"/></svg>',
        timer:     '<svg viewBox="0 0 24 24"><path d="M15 1H9v2h6V1zm-4 13h2V8h-2v6zm8.03-6.61l1.42-1.42c-.43-.51-.9-.99-1.41-1.41l-1.42 1.42C16.07 4.74 14.12 4 12 4c-4.97 0-9 4.03-9 9s4.02 9 9 9 9-4.03 9-9c0-2.12-.74-4.07-1.97-5.61zM12 20c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/></svg>',
        movie:     '<svg viewBox="0 0 24 24"><path d="M18 4l2 4h-3l-2-4h-2l2 4h-3l-2-4H8l2 4H7L5 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V4h-4z"/></svg>',
        trash:     '<svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>',
        dot_green: '<svg viewBox="0 0 8 8"><circle cx="4" cy="4" r="4" fill="#4caf50"/></svg>',
        dot_yellow:'<svg viewBox="0 0 8 8"><circle cx="4" cy="4" r="4" fill="#ff9800"/></svg>',
        dot_gray:  '<svg viewBox="0 0 8 8"><circle cx="4" cy="4" r="4" fill="#555"/></svg>',
        dot_red:   '<svg viewBox="0 0 8 8"><circle cx="4" cy="4" r="4" fill="#ef5350"/></svg>',
        open_ext:  '<svg viewBox="0 0 24 24"><path d="M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/></svg>',
        fix:       '<svg viewBox="0 0 24 24"><path d="M22.7 19l-9.1-9.1c.9-2.3.4-5-1.5-6.9-2-2-5-2.4-7.4-1.3L9 6 6 9 1.6 4.7C.4 7.1.9 10.1 2.9 12.1c1.9 1.9 4.6 2.4 6.9 1.5l9.1 9.1c.4.4 1 .4 1.4 0l2.3-2.3c.5-.4.5-1.1.1-1.4z"/></svg>',
        spinner:   '<svg viewBox="0 0 24 24" class="dp-spin"><path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46C19.54 15.03 20 13.57 20 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74C4.46 8.97 4 10.43 4 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/></svg>'
    };

    /* ================================================================
     *  CONFIG
     * ================================================================ */
    var CONFIG = {
        HTTPS_REQUIRED_DOMAINS: ['b-cdn.net', 'bunnycdn.com', 'cloudfront.net', 'akamaihd.net'],
        MAX_LOG_ENTRIES: 300,
        ATTEMPT_TIMEOUT: 12000,
        _appBase: null
    };

    /* ================================================================
     *  STATE
     * ================================================================ */
    var _state = {
        movieData: null,
        videoElement: null,
        modalElement: null,
        currentAttempt: 0,
        logs: [],
        startTime: null,
        urlHistory: [],
        urlQueue: [],
        currentUrlIndex: -1,
        attemptTimer: null,
        _removeHandlers: null
    };

    /* ================================================================
     *  CSS — all border-radius: 0
     * ================================================================ */
    function _injectStyles() {
        if (document.getElementById('ugflix-dp-styles')) return;
        var css = [
            /* -- reset all radii globally -- */
            '#ugflix-debug-player-modal *{border-radius:0!important}',

            /* -- modal shell -- */
            '#ugflix-debug-player-modal .modal-dialog{width:96vw;max-width:1440px;margin:2vh auto}',
            '#ugflix-debug-player-modal .modal-content{background:#0d0d1a;color:#e2e8f0;border:1px solid #23233a;box-shadow:0 30px 80px rgba(0,0,0,.7);overflow:hidden}',

            /* -- header -- */
            '.dp-header{background:linear-gradient(135deg,#15152a 0%,#1a2440 100%);padding:12px 20px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #23233a}',
            '.dp-header-title{color:#fff;font-size:15px;font-weight:600;margin:0;display:flex;align-items:center;gap:8px}',
            '.dp-header-title svg{width:18px;height:18px;fill:#4fc3f7}',
            '.dp-header-id{background:#4fc3f7;color:#0d0d1a;font-size:11px;padding:2px 8px;font-weight:700}',
            '.dp-close-btn{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);color:#888;width:28px;height:28px;font-size:0;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:.2s}',
            '.dp-close-btn svg{width:16px;height:16px;fill:currentColor}',
            '.dp-close-btn:hover{background:rgba(255,60,60,.35);color:#fff;border-color:rgba(255,60,60,.4)}',

            /* -- body flex -- */
            '.dp-body{display:flex;flex-direction:row;min-height:0}',
            '.dp-video-col{flex:1;min-width:0;display:flex;flex-direction:column}',

            /* -- video player -- */
            '.dp-player-wrap{position:relative;width:100%;padding-top:56.25%;background:#000}',
            '.dp-player-wrap video{position:absolute;top:0;left:0;width:100%;height:100%;outline:none}',
            '.dp-overlay{position:absolute;top:0;left:0;width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.8);z-index:2;transition:opacity .3s}',
            '.dp-overlay-inner{text-align:center}',
            '.dp-overlay-icon{width:40px;height:40px;margin:0 auto 8px}',
            '.dp-overlay-icon svg{width:40px;height:40px}',
            '.dp-overlay-text{color:#888;font-size:13px;max-width:340px;line-height:1.4}',

            /* -- controls bar -- */
            '.dp-controls{padding:10px 16px;background:#111126;border-top:1px solid #23233a;display:flex;align-items:center;gap:8px;flex-wrap:wrap}',
            '.dp-btn{display:inline-flex;align-items:center;gap:5px;padding:7px 14px;border:1px solid transparent;font-size:12px;font-weight:600;cursor:pointer;transition:all .15s;white-space:nowrap;line-height:1;font-family:inherit}',
            '.dp-btn svg{width:14px;height:14px;fill:currentColor;flex-shrink:0}',
            '.dp-btn-primary{background:#4fc3f7;color:#0a0a18;border-color:#4fc3f7}',
            '.dp-btn-primary:hover{background:#29b6f6;border-color:#29b6f6}',
            '.dp-btn-orange{background:#ff9800;color:#0a0a18;border-color:#ff9800}',
            '.dp-btn-orange:hover{background:#fb8c00;border-color:#fb8c00}',
            '.dp-btn-ghost{background:rgba(255,255,255,.04);color:#999;border-color:rgba(255,255,255,.12)}',
            '.dp-btn-ghost:hover{background:rgba(255,255,255,.1);color:#fff;border-color:rgba(255,255,255,.2)}',
            '.dp-btn-fix{background:#00897b;color:#fff;border-color:#00897b}',
            '.dp-btn-fix:hover{background:#00796b;border-color:#00796b}',
            '.dp-btn-fix:disabled{background:#333;color:#666;border-color:#444;cursor:not-allowed}',
            '@keyframes dp-spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}',
            '.dp-spin{animation:dp-spin 1s linear infinite}',
            '.dp-fix-result{margin-top:0;padding:10px 16px;font-size:11px;line-height:1.55;border-left:3px solid #333;background:#111126}',
            '.dp-fix-result.success{border-left-color:#4caf50;background:#0e1e0e;color:#a5d6a7}',
            '.dp-fix-result.failed{border-left-color:#ef5350;background:#1e0e0e;color:#ef9a9a}',
            '.dp-fix-result strong{display:flex;align-items:center;gap:6px;margin-bottom:4px}',
            '.dp-fix-result strong svg{width:14px;height:14px;fill:currentColor}',
            '.dp-fix-changes{margin-top:6px;font-size:10px;color:#888}',
            '.dp-fix-changes dt{color:#4fc3f7;display:inline}',
            '.dp-fix-changes dd{display:inline;margin:0 0 0 4px;color:#ccc}',
            '.dp-fix-changes dd::after{content:"";display:block}',

            /* -- status badge -- */
            '.dp-status{margin-left:auto;font-size:11px;padding:4px 12px;font-weight:600;letter-spacing:.3px;text-transform:uppercase}',
            '.dp-status-ready{background:#222;color:#666}',
            '.dp-status-loading{background:#1565c0;color:#fff;animation:dp-pulse 1.5s infinite}',
            '.dp-status-playing{background:#2e7d32;color:#fff}',
            '.dp-status-failed{background:#c62828;color:#fff}',
            '.dp-status-warning{background:#e65100;color:#fff}',
            '.dp-status-success{background:#2e7d32;color:#fff}',
            '@keyframes dp-pulse{0%,100%{opacity:1}50%{opacity:.55}}',

            /* -- sidebar -- */
            '.dp-sidebar{width:400px;min-width:340px;border-left:1px solid #23233a;display:flex;flex-direction:column;max-height:calc(96vh - 100px);overflow-y:auto}',
            '.dp-panel{padding:12px 16px;border-bottom:1px solid #1a1a2e}',
            '.dp-panel-title{margin:0 0 10px;color:#4fc3f7;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;display:flex;align-items:center;gap:6px}',
            '.dp-panel-title svg{width:14px;height:14px;fill:#4fc3f7}',

            /* -- info grid -- */
            '.dp-info-grid{display:grid;grid-template-columns:90px 1fr;gap:2px 10px;font-size:12px}',
            '.dp-info-label{color:#555;font-weight:500}',
            '.dp-info-value{color:#ddd;word-break:break-word}',

            /* -- url blocks -- */
            '.dp-url-block{background:#10102a;padding:10px 12px;margin-bottom:8px;border-left:3px solid #333;transition:all .25s}',
            '.dp-url-block.active{border-left-color:#4caf50;background:#0e1e0e}',
            '.dp-url-block.failed{border-left-color:#ef5350;background:#1e0e0e}',
            '.dp-url-label{font-size:11px;font-weight:600;color:#777;margin-bottom:5px;display:flex;align-items:center;gap:6px}',
            '.dp-url-label svg{width:8px;height:8px}',
            '.dp-url-text{font-size:11px;color:#9e9e9e;word-break:break-all;line-height:1.55;font-family:"SF Mono","Fira Code",Consolas,monospace}',
            '.dp-url-meta{margin-top:7px;display:flex;flex-wrap:wrap;gap:10px;font-size:10px;color:#555}',
            '.dp-url-meta span{display:flex;align-items:center;gap:4px}',
            '.dp-url-meta svg{width:12px;height:12px;fill:#555}',
            '.dp-playing-tag{color:#4caf50;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px}',

            /* -- log panel -- */
            '.dp-log-panel{flex:1;display:flex;flex-direction:column;overflow:hidden;padding:12px 16px}',
            '.dp-log-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px}',
            '.dp-log-container{flex:1;overflow-y:auto;background:#060612;padding:10px;font-family:"SF Mono","Fira Code",Consolas,monospace;font-size:11px;line-height:1.65;min-height:100px;max-height:260px}',
            '.dp-log-container::-webkit-scrollbar{width:4px}',
            '.dp-log-container::-webkit-scrollbar-track{background:transparent}',
            '.dp-log-container::-webkit-scrollbar-thumb{background:#333}',
            '.dp-log-entry{padding:1px 0}',
            '.dp-log-time{color:#3a3a4a;margin-right:6px;font-size:10px}',
            '.dp-log-error{color:#ef5350}',
            '.dp-log-warn{color:#ffa726}',
            '.dp-log-success{color:#66bb6a}',
            '.dp-log-info{color:#607d8b}',

            /* -- footer -- */
            '.dp-footer{background:#0a0a18;border-top:1px solid #23233a;padding:10px 20px;display:flex;align-items:center;justify-content:space-between}',
            '.dp-footer-info{font-size:11px;color:#3a3a4a;line-height:1.4}',

            /* -- responsive -- */
            '@media(max-width:1024px){#ugflix-debug-player-modal .dp-body{flex-direction:column}#ugflix-debug-player-modal .dp-sidebar{width:100%;border-left:none;border-top:1px solid #23233a;max-height:400px}#ugflix-debug-player-modal .modal-dialog{width:98vw;margin:1vh auto}}',
            '@media(max-width:768px){.dp-controls{padding:8px 10px}.dp-btn{padding:6px 10px;font-size:11px}.dp-info-grid{grid-template-columns:80px 1fr}.dp-sidebar{min-width:0}}'
        ].join('\n');
        var el = document.createElement('style');
        el.id = 'ugflix-dp-styles';
        el.textContent = css;
        document.head.appendChild(el);
    }

    /* ================================================================
     *  BASE URL
     * ================================================================ */
    function _getAppBaseUrl() {
        if (CONFIG._appBase) return CONFIG._appBase;
        if (window.__KATOGO_BASE_URL) {
            CONFIG._appBase = window.__KATOGO_BASE_URL;
            return CONFIG._appBase;
        }
        var scripts = document.querySelectorAll('script[src*="/vendor/laravel-admin/"]');
        for (var i = 0; i < scripts.length; i++) {
            var src = scripts[i].getAttribute('src');
            var idx = src.indexOf('/vendor/laravel-admin');
            if (idx > 0) {
                CONFIG._appBase = window.location.origin + src.substring(0, idx);
                return CONFIG._appBase;
            }
        }
        var parts = window.location.pathname.split('/').filter(Boolean);
        CONFIG._appBase = parts.length > 1
            ? window.location.origin + '/' + parts[0]
            : window.location.origin;
        return CONFIG._appBase;
    }

    /* ================================================================
     *  URL HELPERS
     * ================================================================ */
    function _isKnownCdn(url) {
        if (!url) return false;
        var l = url.toLowerCase();
        for (var i = 0; i < CONFIG.HTTPS_REQUIRED_DOMAINS.length; i++) {
            if (l.indexOf(CONFIG.HTTPS_REQUIRED_DOMAINS[i]) !== -1) return true;
        }
        return false;
    }

    function sanitizeUrl(url) {
        if (!url || !url.length) return url;
        var r = url.trim();
        if (r.indexOf('http://') === 0 && _isKnownCdn(r)) {
            r = r.replace('http://', 'https://');
        }
        if (!/%[0-9A-Fa-f]{2}/.test(r)) {
            try {
                var u = new URL(r);
                var segs = u.pathname.split('/');
                var enc = segs.map(function (s) {
                    return s ? s.replace(/ /g, '%20').replace(/\[/g, '%5B').replace(/]/g, '%5D')
                        .replace(/\{/g, '%7B').replace(/}/g, '%7D')
                        .replace(/\|/g, '%7C').replace(/\^/g, '%5E') : s;
                });
                var p = enc.join('/');
                if (p !== u.pathname) r = u.origin + p + u.search + u.hash;
            } catch (e) { r = r.replace(/ /g, '%20'); }
        }
        return r;
    }

    var DEAD_HOSTNAME_RE = /^(munoserver\d+\.(club|org|xyz)|munowatch\.co|muno\d*\.club|gumite\.club)$/i;
    var CDN_FALLBACK_HOST = 'munotek.b-cdn.net';

    function _getCdnFallbackUrl(sanitizedUrl) {
        try {
            var u = new URL(sanitizedUrl);
            if (DEAD_HOSTNAME_RE.test(u.hostname)) {
                return u.protocol + '//' + CDN_FALLBACK_HOST + u.pathname + u.search + u.hash;
            }
        } catch (e) { /* */ }
        return null;
    }

    function _buildUrlQueue(data) {
        var urls = [], seen = {};
        function add(url, source) {
            if (!url || url.length < 5 || seen[url]) return;
            seen[url] = true;
            urls.push({ url: url, source: source });
        }
        var raw = (data.url || '').trim();
        if (!raw || raw.length < 5) return urls;
        var s = sanitizeUrl(raw);
        var fb = _getCdnFallbackUrl(s);
        if (fb) add(fb, 'url > ' + CDN_FALLBACK_HOST);
        add(s, 'url (original)');
        return urls;
    }

    function detectCdnProvider(url) {
        if (!url) return 'Unknown';
        var l = url.toLowerCase();
        if (l.indexOf('b-cdn.net') !== -1 || l.indexOf('bunnycdn') !== -1) return 'BunnyCDN';
        if (l.indexOf('cloudfront') !== -1) return 'CloudFront';
        if (l.indexOf('akamaihd') !== -1) return 'Akamai';
        if (l.indexOf('googleapis') !== -1) return 'Google Cloud';
        if (l.indexOf('firebase') !== -1) return 'Firebase';
        if (l.indexOf('munoserver') !== -1) return 'MunoServer (Dead)';
        return 'Custom';
    }

    function extractFilename(url) {
        try {
            var segs = new URL(url).pathname.split('/').filter(Boolean);
            return decodeURIComponent(segs[segs.length - 1]) || 'N/A';
        } catch (e) { return 'N/A'; }
    }

    function isValidVideoUrl(url) {
        if (!url || url.length < 10) return { valid: false, reason: 'URL empty / too short' };
        try {
            var u = new URL(url);
            if (u.protocol !== 'http:' && u.protocol !== 'https:') return { valid: false, reason: 'Bad protocol' };
            if (!u.hostname || u.hostname.length < 3) return { valid: false, reason: 'Bad hostname' };
            return { valid: true, reason: 'OK' };
        } catch (e) { return { valid: false, reason: 'URL parse error' }; }
    }

    /* ================================================================
     *  LOGGING
     * ================================================================ */
    function _log(level, message, data) {
        var entry = {
            time: new Date().toISOString().substr(11, 12),
            level: level,
            message: message,
            data: data || null
        };
        _state.logs.push(entry);
        if (_state.logs.length > CONFIG.MAX_LOG_ENTRIES) _state.logs.shift();
        _renderLog();
        var tag = '[DebugPlayer]';
        if (level === 'error') console.error(tag, message, data || '');
        else if (level === 'warn') console.warn(tag, message, data || '');
        else console.log(tag, message, data || '');
    }

    function _renderLog() {
        var el = document.getElementById('ugflix-dp-log');
        if (!el) return;
        var html = '';
        for (var i = _state.logs.length - 1; i >= 0; i--) {
            var e = _state.logs[i];
            var cls = { error: 'dp-log-error', warn: 'dp-log-warn', success: 'dp-log-success' }[e.level] || 'dp-log-info';
            html += '<div class="dp-log-entry ' + cls + '">' +
                '<span class="dp-log-time">' + e.time + '</span>' +
                _escHtml(e.message);
            if (e.data) {
                html += ' <span style="color:#333">' +
                    (typeof e.data === 'string' ? _escHtml(e.data) : JSON.stringify(e.data)) +
                    '</span>';
            }
            html += '</div>';
        }
        el.innerHTML = html;
    }

    /* ================================================================
     *  MODAL BUILD
     * ================================================================ */
    function _createModal() {
        _injectStyles();
        var old = document.getElementById('ugflix-debug-player-modal');
        if (old) old.remove();

        var html =
            '<div class="modal fade" id="ugflix-debug-player-modal" tabindex="-1" role="dialog" style="z-index:10500">' +
            '<div class="modal-dialog" role="document">' +
            '<div class="modal-content">' +

            /* Header */
            '<div class="dp-header">' +
            '  <div class="dp-header-title">' +
                 ICO.movie +
            '    <span id="ugflix-dp-title">Debug Player</span>' +
            '    <span class="dp-header-id" id="ugflix-dp-id"></span>' +
            '  </div>' +
            '  <button class="dp-close-btn" onclick="UgflixDebugPlayer.close()" title="Close">' + ICO.close + '</button>' +
            '</div>' +

            /* Body */
            '<div class="dp-body">' +

            /* Video column */
            '<div class="dp-video-col">' +
            '  <div class="dp-player-wrap">' +
            '    <video id="ugflix-dp-video" controls playsinline preload="metadata"></video>' +
            '    <div class="dp-overlay" id="ugflix-dp-overlay">' +
            '      <div class="dp-overlay-inner">' +
            '        <div class="dp-overlay-icon" id="ugflix-dp-overlay-icon">' + ICO.timer + '</div>' +
            '        <div class="dp-overlay-text" id="ugflix-dp-overlay-text">Preparing video...</div>' +
            '      </div>' +
            '    </div>' +
            '  </div>' +
            '  <div class="dp-controls">' +
            '    <button class="dp-btn dp-btn-primary" onclick="UgflixDebugPlayer._playAttempt(1)" title="Auto-cascade through all candidate URLs">' +
                   ICO.play + ' Auto-Play' +
            '    </button>' +
            '    <button class="dp-btn dp-btn-orange" onclick="UgflixDebugPlayer._playAttempt(2)" title="Test URLs server-side via cURL">' +
                   ICO.server + ' Server Test' +
            '    </button>' +
            '    <button class="dp-btn dp-btn-ghost" onclick="UgflixDebugPlayer._openInNewTab()" title="Open URL in new tab">' +
                   ICO.open_ext + ' Open URL' +
            '    </button>' +
            '    <button class="dp-btn dp-btn-fix" id="ugflix-dp-fix-btn" onclick="UgflixDebugPlayer._fixMovie()" title="Re-fetch movie data from original server and repair">' +
                   ICO.fix + ' Fix Movie' +
            '    </button>' +
            '    <span class="dp-status dp-status-ready" id="ugflix-dp-status">READY</span>' +
            '  </div>' +
            '</div>' +

            /* Sidebar */
            '<div class="dp-sidebar">' +

            '<div class="dp-panel">' +
            '  <div class="dp-panel-title">' + ICO.info + ' Movie Info</div>' +
            '  <div class="dp-info-grid" id="ugflix-dp-info"></div>' +
            '</div>' +

            '<div class="dp-panel">' +
            '  <div class="dp-panel-title">' + ICO.url + ' URL Analysis</div>' +
            '  <div id="ugflix-dp-urls"></div>' +
            '</div>' +

            '<div class="dp-log-panel">' +
            '  <div class="dp-log-header">' +
            '    <div class="dp-panel-title" style="margin:0">' + ICO.log + ' Debug Log</div>' +
            '    <button class="dp-btn dp-btn-ghost" style="padding:3px 8px;font-size:10px" onclick="UgflixDebugPlayer._clearLogs()">' + ICO.trash + ' Clear</button>' +
            '  </div>' +
            '  <div class="dp-log-container" id="ugflix-dp-log"></div>' +
            '</div>' +

            '</div>' + /* /sidebar */
            '</div>' + /* /body */

            /* Footer */
            '<div class="dp-footer">' +
            '  <div class="dp-footer-info">' +
            '    Dead munoserverXX domains auto-fallback to <strong style="color:#4fc3f7">munotek.b-cdn.net</strong>' +
            '    &nbsp;|&nbsp; Referrer-Policy: no-referrer (HTTP header middleware)' +
            '  </div>' +
            '  <button class="dp-btn dp-btn-ghost" onclick="UgflixDebugPlayer.close()">' + ICO.close + ' Close</button>' +
            '</div>' +

            '</div></div></div>';

        document.body.insertAdjacentHTML('beforeend', html);
        _state.modalElement = document.getElementById('ugflix-debug-player-modal');
    }

    function _showOverlay(iconSvg, text) {
        var ov = document.getElementById('ugflix-dp-overlay');
        if (!ov) return;
        ov.style.display = 'flex';
        document.getElementById('ugflix-dp-overlay-icon').innerHTML = iconSvg;
        document.getElementById('ugflix-dp-overlay-text').textContent = text;
    }

    function _hideOverlay() {
        var ov = document.getElementById('ugflix-dp-overlay');
        if (ov) ov.style.display = 'none';
    }

    function _setStatus(text, cls) {
        var el = document.getElementById('ugflix-dp-status');
        if (!el) return;
        el.className = 'dp-status dp-status-' + cls;
        el.textContent = text;
    }

    /* ================================================================
     *  INFO RENDERING
     * ================================================================ */
    function _renderMovieInfo(data) {
        var el = document.getElementById('ugflix-dp-info');
        if (!el) return;
        var isSeries = data.type === 'Series';
        var fields = [
            ['ID', data.id], ['Title', data.title],
            ['Type', isSeries ? '\uD83D\uDCFA Series Episode' : (data.type || 'Movie')],
            ['Status', data.status], ['Genre', data.genre], ['VJ', data.vj]
        ];
        // Series-specific fields
        if (isSeries) {
            fields.push(['Series', data.series_title || data.category || '']);
            fields.push(['Season', data.season_number || 1]);
            fields.push(['Episode #', data.episode_number || '']);
            fields.push(['Ep. Title', data.episode_title || '']);
            if (data.series_total_episodes) fields.push(['Total Eps', data.series_total_episodes]);
            if (data.series_total_seasons) fields.push(['Seasons', data.series_total_seasons]);
            if (data.series_code) fields.push(['Series Code', data.series_code]);
        } else {
            fields.push(['Category', data.category]);
        }
        fields.push(['Duration', data.duration || '']);
        fields.push(['Year', data.year || '']);
        fields.push(['Language', data.language || '']);
        fields.push(['Platform', data.platform_type || 'All']);
        fields.push(['Views', data.views_count || 0]);
        var html = '';
        for (var i = 0; i < fields.length; i++) {
            var val = fields[i][1];
            if (val !== null && val !== undefined && val !== '') {
                html += '<div class="dp-info-label">' + fields[i][0] + '</div>' +
                    '<div class="dp-info-value">' + _escHtml(String(val)) + '</div>';
            }
        }
        el.innerHTML = html;

        // Update panel title
        var panelTitle = el.closest('.dp-panel');
        if (panelTitle) {
            var titleEl = panelTitle.querySelector('.dp-panel-title');
            if (titleEl) titleEl.textContent = isSeries ? 'Episode Info' : 'Movie Info';
        }
    }

    function _renderUrlAnalysis(data) {
        var el = document.getElementById('ugflix-dp-urls');
        if (!el) return;
        var raw = (data.url || '').trim();
        var s = sanitizeUrl(raw);
        var cdn = detectCdnProvider(s);
        var fname = extractFilename(s);
        var valid = isValidVideoUrl(s);
        var fb = _getCdnFallbackUrl(s);

        var html = '';

        html += '<div class="dp-url-block" id="ugflix-dp-url-orig">';
        html += '<div class="dp-url-label">' + ICO.dot_yellow + ' url (primary / database)</div>';
        html += '<div class="dp-url-text">' + _escHtml(raw || '(empty)') + '</div>';
        html += '<div class="dp-url-meta">';
        html += '<span>' + ICO.globe + ' ' + _escHtml(cdn) + '</span>';
        html += '<span>' + ICO.file + ' ' + _escHtml(fname) + '</span>';
        html += '<span>' + (valid.valid ? ICO.check + ' Valid' : ICO.error + ' ' + valid.reason) + '</span>';
        html += '</div></div>';

        if (fb) {
            html += '<div class="dp-url-block" id="ugflix-dp-url-cdn">';
            html += '<div class="dp-url-label">' + ICO.dot_yellow + ' CDN Fallback (tried first)</div>';
            html += '<div class="dp-url-text">' + _escHtml(fb) + '</div>';
            html += '<div class="dp-url-meta">';
            html += '<span>' + ICO.globe + ' BunnyCDN</span>';
            html += '<span>' + ICO.refresh + ' Dead hostname auto-swapped</span>';
            html += '</div></div>';
        }

        el.innerHTML = html;
    }

    function _updateUrlBlocks(activeUrl) {
        var data = _state.movieData;
        if (!data) return;
        var raw = (data.url || '').trim();
        var s = sanitizeUrl(raw);
        var fb = _getCdnFallbackUrl(s);

        var origEl = document.getElementById('ugflix-dp-url-orig');
        var cdnEl = document.getElementById('ugflix-dp-url-cdn');

        if (origEl) {
            origEl.className = 'dp-url-block' + (activeUrl === s ? ' active' : '');
            var origLabel = origEl.querySelector('.dp-url-label');
            if (origLabel) origLabel.innerHTML = (activeUrl === s ? ICO.dot_green : ICO.dot_gray) +
                ' url (primary)' + (activeUrl === s ? ' <span class="dp-playing-tag">PLAYING</span>' : '');
        }
        if (cdnEl && fb) {
            cdnEl.className = 'dp-url-block' + (activeUrl === fb ? ' active' : '');
            var cdnLabel = cdnEl.querySelector('.dp-url-label');
            if (cdnLabel) cdnLabel.innerHTML = (activeUrl === fb ? ICO.dot_green : ICO.dot_gray) +
                ' CDN Fallback' + (activeUrl === fb ? ' <span class="dp-playing-tag">PLAYING</span>' : '');
        }
    }

    function _markUrlBlockFailed(failedUrl) {
        var data = _state.movieData;
        if (!data) return;
        var raw = (data.url || '').trim();
        var s = sanitizeUrl(raw);
        var fb = _getCdnFallbackUrl(s);

        if (failedUrl === s) {
            var origEl = document.getElementById('ugflix-dp-url-orig');
            if (origEl) {
                origEl.className = 'dp-url-block failed';
                var lbl = origEl.querySelector('.dp-url-label');
                if (lbl) lbl.innerHTML = ICO.dot_red + ' url (primary) <span style="color:#ef5350;font-size:10px">FAILED</span>';
            }
        }
        if (fb && failedUrl === fb) {
            var cdnEl = document.getElementById('ugflix-dp-url-cdn');
            if (cdnEl) {
                cdnEl.className = 'dp-url-block failed';
                var lbl2 = cdnEl.querySelector('.dp-url-label');
                if (lbl2) lbl2.innerHTML = ICO.dot_red + ' CDN Fallback <span style="color:#ef5350;font-size:10px">FAILED</span>';
            }
        }
    }

    /* ================================================================
     *  PLAYBACK ENGINE
     * ================================================================ */
    function _startAutoPlay() {
        var data = _state.movieData;
        if (!data) { _log('error', 'No movie data loaded'); return; }

        _state.urlQueue = _buildUrlQueue(data);
        _state.currentUrlIndex = -1;

        if (!_state.urlQueue.length) {
            _log('error', '[FAIL] No playable URL found');
            _showOverlay(ICO.error, 'No video URL available');
            _setStatus('NO URL', 'failed');
            return;
        }

        _log('info', '----------------------------------------');
        _log('info', '[AUTO-PLAY] ' + _state.urlQueue.length + ' candidate URL(s):');
        for (var i = 0; i < _state.urlQueue.length; i++) {
            _log('info', '  ' + (i + 1) + '. [' + _state.urlQueue[i].source + '] ' + _state.urlQueue[i].url);
        }
        _tryNextUrl();
    }

    function _tryNextUrl() {
        _state.currentUrlIndex++;
        if (_state.currentUrlIndex >= _state.urlQueue.length) {
            _allFailed();
            return;
        }
        var entry = _state.urlQueue[_state.currentUrlIndex];
        _playAttemptUrl(entry.url, entry.source);
    }

    function _allFailed() {
        _log('error', '========================================');
        _log('error', '[FAIL] ALL ' + _state.urlQueue.length + ' URLs FAILED');
        _log('info', '[TIP] Use "Server Test" to cURL-test from the server');
        _showOverlay(ICO.error, 'All URLs failed. Try Server Test for diagnostics.');
        _setStatus('FAILED', 'failed');
    }

    function _playAttemptUrl(url, source) {
        _state.currentAttempt = _state.currentUrlIndex + 1;
        var label = _state.currentAttempt + '/' + _state.urlQueue.length;

        _log('info', '----------------------------------------');
        _log('info', '[ATTEMPT ' + label + ']');
        _log('info', '  Source: ' + source);
        _log('info', '  URL: ' + url);
        _log('info', '  CDN: ' + detectCdnProvider(url));
        _log('info', '  Mode: Direct <video> + Referrer-Policy header');

        _showOverlay(ICO.timer, 'Loading (' + label + ': ' + source + ')...');
        _setStatus('LOADING ' + label, 'loading');

        var video = document.getElementById('ugflix-dp-video');
        if (!video) return;

        if (_state._removeHandlers) _state._removeHandlers();
        video.pause();
        video.removeAttribute('src');
        video.load();

        _state.videoElement = video;
        _state.startTime = performance.now();

        if (_state.attemptTimer) clearTimeout(_state.attemptTimer);
        _state.attemptTimer = setTimeout(function () {
            _log('warn', '[TIMEOUT] Attempt ' + label + ' (' + (CONFIG.ATTEMPT_TIMEOUT / 1000) + 's)');
            removeHandlers();
            _markUrlBlockFailed(url);
            _tryNextUrl();
        }, CONFIG.ATTEMPT_TIMEOUT);

        var handlers = {};

        handlers.loadedmetadata = function () {
            if (_state.attemptTimer) { clearTimeout(_state.attemptTimer); _state.attemptTimer = null; }
            var ms = (performance.now() - _state.startTime).toFixed(0);
            _log('success', '[OK] Video loaded in ' + ms + 'ms');
            _log('info', '  Resolution: ' + video.videoWidth + 'x' + video.videoHeight);
            _log('info', '  Duration: ' + _formatDuration(video.duration));
            _log('info', '  Source: ' + source);
            _hideOverlay();
            _setStatus('PLAYING', 'playing');
            _updateUrlBlocks(url);
            video.play().catch(function (e) {
                _log('warn', '[BLOCKED] Autoplay: ' + e.message + ' (click play)');
                _hideOverlay();
                _setStatus('READY', 'success');
            });
        };

        handlers.error = function () {
            if (_state.attemptTimer) { clearTimeout(_state.attemptTimer); _state.attemptTimer = null; }
            var ms = (performance.now() - _state.startTime).toFixed(0);
            var err = video.error;
            var msg = err ? _mediaErrorToString(err.code) : 'Unknown';
            _log('error', '[FAIL] Attempt ' + label + ' (' + ms + 'ms)');
            _log('error', '  Error: ' + msg);
            if (err && err.message) _log('error', '  Detail: ' + err.message);
            removeHandlers();
            _markUrlBlockFailed(url);
            _cascadeOrFail(label, msg);
        };

        handlers.waiting = function () { _setStatus('BUFFERING', 'warning'); };
        handlers.playing = function () { _setStatus('PLAYING', 'playing'); _hideOverlay(); };
        handlers.stalled = function () {
            _log('warn', '[STALL] Network stalled');
            _setStatus('STALLED', 'warning');
        };

        function removeHandlers() {
            for (var k in handlers) video.removeEventListener(k, handlers[k]);
        }
        _state._removeHandlers = removeHandlers;

        for (var k in handlers) {
            video.addEventListener(k, handlers[k], { once: k === 'loadedmetadata' || k === 'error' });
        }

        video.src = url;
        _state.urlHistory.push({ attempt: _state.currentAttempt, url: url, source: source, time: new Date() });
        video.load();
    }

    function _cascadeOrFail(label, errMsg) {
        if (_state.currentUrlIndex < _state.urlQueue.length - 1) {
            _log('info', '[CASCADE] Trying next URL...');
            _tryNextUrl();
        } else {
            _showOverlay(ICO.error, errMsg);
            _setStatus('FAILED', 'failed');
            _allFailed();
        }
    }

    function _playAttempt(mode) {
        var data = _state.movieData;
        if (!data) { _log('error', 'No movie data'); return; }
        if (mode === 1) _startAutoPlay();
        else if (mode === 2) {
            _log('info', '========================================');
            _log('info', '[SERVER TEST] Starting cURL test...');
            _testUrlFromBackend();
        }
    }

    /* ================================================================
     *  SERVER-SIDE TEST
     * ================================================================ */
    function _testUrlFromBackend() {
        var data = _state.movieData;
        if (!data) { _log('error', 'No movie data'); return; }

        _setStatus('TESTING', 'loading');

        var csrf = document.querySelector('meta[name="csrf-token"]');
        var token = csrf ? csrf.getAttribute('content') : '';

        $.ajax({
            url: _getAppBaseUrl() + '/debug-player/proxy',
            method: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': token },
            data: {
                movie_id: data.id,
                url: sanitizeUrl(data.url || ''),
                test_all: data.id ? 1 : 0,
                _token: token
            },
            timeout: 60000,
            success: function (resp) {
                if (resp.mode === 'test_all' && resp.results) {
                    _log('info', '[SERVER] Tested ' + resp.results.length + ' URL(s):');
                    var ok = false;
                    for (var i = 0; i < resp.results.length; i++) {
                        var r = resp.results[i];
                        var pre = r.success ? '[OK]' : '[FAIL]';
                        _log(r.success ? 'success' : 'error',
                            '  ' + pre + ' [' + r.source + '] HTTP ' + r.http_code +
                            ' -- ' + (r.content_type || 'N/A') +
                            ' -- ' + _formatBytes(r.content_length) +
                            (r.is_video ? ' (VIDEO)' : '') +
                            ' -- ' + r.total_time_ms + 'ms');
                        _log('info', '    URL: ' + (r.original_url || 'N/A'));
                        if (r.effective_url && r.effective_url !== r.original_url) {
                            _log('info', '    Redirect: ' + r.effective_url);
                        }
                        if (r.success) ok = true;
                    }
                    _setStatus(ok ? 'URL OK' : 'FAILED', ok ? 'success' : 'failed');
                } else if (resp.success) {
                    _log('success', '[OK] URL accessible from server');
                    _log('info', '  HTTP: ' + resp.http_code);
                    _log('info', '  Type: ' + (resp.content_type || 'N/A'));
                    _log('info', '  Size: ' + _formatBytes(resp.content_length));
                    _log('info', '  Video: ' + (resp.is_video ? 'YES' : 'NO'));
                    if (resp.effective_url) _log('info', '  Redirect: ' + resp.effective_url);
                    if (resp.headers) {
                        _log('info', '  Server: ' + (resp.headers.server || 'N/A'));
                        _log('info', '  Accept-Ranges: ' + (resp.headers['accept-ranges'] || 'N/A'));
                    }
                    _setStatus('URL OK', 'success');
                } else {
                    _log('error', '[FAIL] URL failed server-side');
                    _log('error', '  HTTP: ' + (resp.http_code || 'N/A'));
                    _log('error', '  Error: ' + (resp.error || 'Unknown'));
                    _setStatus('FAILED', 'failed');
                }
            },
            error: function (xhr) {
                _log('error', '[FAIL] Proxy request failed: HTTP ' + xhr.status + ' ' + xhr.statusText);
                if (xhr.responseText) _log('error', '  Response: ' + xhr.responseText.substring(0, 200));
                _log('warn', '[TIP] Check route: ' + _getAppBaseUrl() + '/debug-player/proxy');
                _setStatus('PROXY ERROR', 'failed');
            }
        });
    }

    /* ================================================================
     *  FIX MOVIE
     * ================================================================ */
    function _fixMovie() {
        var data = _state.movieData;
        if (!data || !data.id) { _log('error', 'No movie data to fix'); return; }

        var btn = document.getElementById('ugflix-dp-fix-btn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = ICO.spinner + ' Fixing...';
        }

        // Remove previous fix result
        var oldResult = document.getElementById('ugflix-dp-fix-result');
        if (oldResult) oldResult.remove();

        _log('info', '========================================');
        _log('info', '[FIX] Starting fix for movie #' + data.id + ': ' + data.title);
        _log('info', '[FIX] Re-fetching data from original server...');
        _setStatus('FIXING', 'loading');

        var csrf = document.querySelector('meta[name="csrf-token"]');
        var token = csrf ? csrf.getAttribute('content') : '';

        $.ajax({
            url: _getAppBaseUrl() + '/debug-player/fix-movie',
            method: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': token },
            data: {
                movie_id: data.id,
                _token: token
            },
            timeout: 120000,
            success: function (resp) {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = ICO.fix + ' Fix Movie';
                }

                if (resp.success) {
                    _log('success', '[FIX] Movie fixed successfully!');
                    _log('info', '  ' + (resp.message || ''));

                    if (resp.old_url && resp.new_url && resp.old_url !== resp.new_url) {
                        _log('success', '  URL changed:');
                        _log('info', '    Old: ' + resp.old_url);
                        _log('info', '    New: ' + resp.new_url);
                    } else {
                        _log('info', '  URL unchanged.');
                    }

                    if (resp.changes && Object.keys(resp.changes).length > 0) {
                        _log('info', '  Fields updated:');
                        for (var field in resp.changes) {
                            if (resp.changes.hasOwnProperty(field)) {
                                _log('info', '    ' + field + ': ' + (resp.changes[field].old || 'null') + ' -> ' + (resp.changes[field]['new'] || 'null'));
                            }
                        }
                    }

                    if (resp.url_accessible === false) {
                        _log('warn', '[FIX] Warning: New URL not directly accessible from server. CDN fallback may work in the app.');
                    }

                    // Update movie data in state with fresh data
                    if (resp.movie) {
                        _state.movieData = resp.movie;
                        _renderMovieInfo(resp.movie);
                        _renderUrlAnalysis(resp.movie);

                        var titleEl = document.getElementById('ugflix-dp-title');
                        if (titleEl) {
                            var displayTitle = resp.movie.title || 'Unknown Movie';
                            if (resp.movie.type === 'Series') {
                                var epNum = resp.movie.episode_number ? ' EP ' + resp.movie.episode_number : '';
                                var sNum = resp.movie.season_number ? 'S' + resp.movie.season_number : '';
                                displayTitle = (resp.movie.series_title || displayTitle) + (sNum || epNum ? ' (' + sNum + epNum + ')' : '');
                            }
                            titleEl.textContent = displayTitle;
                        }
                    }

                    _setStatus('FIXED', 'success');
                    _showFixResult(true, resp.message || 'Movie fixed successfully.', resp.changes);

                    // Auto-play with new data after brief delay
                    _log('info', '[FIX] Auto-playing with updated data...');
                    setTimeout(function () {
                        _startAutoPlay();
                    }, 1500);

                } else {
                    _log('error', '[FIX] Fix FAILED');
                    _log('error', '  ' + (resp.message || 'Unknown error'));
                    _setStatus('FIX FAILED', 'failed');
                    _showFixResult(false, resp.message || 'Fix failed. See log for details.', null);
                }
            },
            error: function (xhr) {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = ICO.fix + ' Fix Movie';
                }
                _log('error', '[FIX] Request failed: HTTP ' + xhr.status + ' ' + xhr.statusText);
                if (xhr.responseText) _log('error', '  Response: ' + xhr.responseText.substring(0, 300));
                _setStatus('FIX ERROR', 'failed');
                _showFixResult(false, 'Request failed: HTTP ' + xhr.status + '. Check server logs.', null);
            }
        });
    }

    function _showFixResult(success, message, changes) {
        var old = document.getElementById('ugflix-dp-fix-result');
        if (old) old.remove();

        var cls = success ? 'success' : 'failed';
        var icon = success ? ICO.check : ICO.error;
        var html = '<div class="dp-fix-result ' + cls + '" id="ugflix-dp-fix-result">';
        html += '<strong>' + icon + ' ' + (success ? 'FIX SUCCESSFUL' : 'FIX FAILED') + '</strong>';
        html += _escHtml(message);
        if (changes && Object.keys(changes).length > 0) {
            html += '<dl class="dp-fix-changes">';
            for (var f in changes) {
                if (changes.hasOwnProperty(f)) {
                    html += '<dt>' + _escHtml(f) + ':</dt>';
                    html += '<dd>' + _escHtml(String(changes[f].old || 'null')) + ' &rarr; ' + _escHtml(String(changes[f]['new'] || 'null')) + '</dd>';
                }
            }
            html += '</dl>';
        }
        html += '</div>';

        var controls = document.querySelector('.dp-controls');
        if (controls) {
            controls.insertAdjacentHTML('afterend', html);
        }
    }

    /* ================================================================
     *  UTILITY
     * ================================================================ */
    function _mediaErrorToString(code) {
        var map = {
            1: 'MEDIA_ERR_ABORTED -- Playback aborted',
            2: 'MEDIA_ERR_NETWORK -- Network error',
            3: 'MEDIA_ERR_DECODE -- Decode error',
            4: 'MEDIA_ERR_SRC_NOT_SUPPORTED -- Format or URL not supported'
        };
        return map[code] || 'Unknown (code ' + code + ')';
    }

    function _formatDuration(secs) {
        if (!secs || isNaN(secs) || !isFinite(secs)) return 'N/A';
        var h = Math.floor(secs / 3600);
        var m = Math.floor((secs % 3600) / 60);
        var s = Math.floor(secs % 60);
        return h > 0 ? h + 'h ' + m + 'm ' + s + 's' : m + 'm ' + s + 's';
    }

    function _formatBytes(bytes) {
        if (!bytes) return 'N/A';
        var b = parseInt(bytes, 10);
        if (isNaN(b) || b === 0) return 'N/A';
        if (b < 1024) return b + ' B';
        if (b < 1048576) return (b / 1024).toFixed(1) + ' KB';
        if (b < 1073741824) return (b / 1048576).toFixed(1) + ' MB';
        return (b / 1073741824).toFixed(2) + ' GB';
    }

    function _escHtml(str) {
        if (!str) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str)));
        return d.innerHTML;
    }

    function _openInNewTab() {
        var data = _state.movieData;
        if (!data) return;
        var url = sanitizeUrl((data.url || '').trim());
        if (url) window.open(url, '_blank');
    }

    function _clearLogs() {
        _state.logs = [];
        _renderLog();
    }

    /* ================================================================
     *  PUBLIC API
     * ================================================================ */
    function play(movieData) {
        _state.movieData = movieData;
        _state.logs = [];
        _state.urlHistory = [];
        _state.currentAttempt = 0;
        _state.urlQueue = [];
        _state.currentUrlIndex = -1;
        if (_state.attemptTimer) { clearTimeout(_state.attemptTimer); _state.attemptTimer = null; }

        _log('info', '[OPEN] Debug Player v3.0');
        _log('info', '  Movie: ' + (movieData.title || 'Unknown'));
        _log('info', '  ID: ' + movieData.id);
        if (movieData.type === 'Series') {
            _log('info', '  Type: Series Episode');
            _log('info', '  Series: ' + (movieData.series_title || movieData.category || 'Unknown'));
            _log('info', '  Season: ' + (movieData.season_number || 1) + ', Episode: ' + (movieData.episode_number || '?'));
            if (movieData.episode_title) _log('info', '  Episode Title: ' + movieData.episode_title);
        }
        _log('info', '  Policy: Referrer-Policy: no-referrer');

        _createModal();

        var titleEl = document.getElementById('ugflix-dp-title');
        if (titleEl) {
            var displayTitle = movieData.title || 'Unknown Movie';
            if (movieData.type === 'Series') {
                var epNum = movieData.episode_number ? ' EP ' + movieData.episode_number : '';
                var sNum = movieData.season_number ? 'S' + movieData.season_number : '';
                displayTitle = (movieData.series_title || displayTitle) + (sNum || epNum ? ' (' + sNum + epNum + ')' : '');
            }
            titleEl.textContent = displayTitle;
        }
        var idEl = document.getElementById('ugflix-dp-id');
        if (idEl) idEl.textContent = '#' + movieData.id;

        _renderMovieInfo(movieData);
        _renderUrlAnalysis(movieData);

        $('#ugflix-debug-player-modal').modal({ show: true, backdrop: 'static' });

        setTimeout(function () { _startAutoPlay(); }, 400);
    }

    function close() {
        if (_state.attemptTimer) { clearTimeout(_state.attemptTimer); _state.attemptTimer = null; }
        if (_state._removeHandlers) _state._removeHandlers();
        var video = document.getElementById('ugflix-dp-video');
        if (video) {
            video.pause();
            video.removeAttribute('src');
            video.load();
        }
        $('#ugflix-debug-player-modal').modal('hide');
        _log('info', '[CLOSED] Player closed');
    }

    function testUrl(url) {
        _state.movieData = { url: url, id: 0, title: 'Quick URL Test' };
        _state.logs = [];
        _log('info', '[TEST] Quick URL test: ' + url);
        _testUrlFromBackend();
    }

    return {
        play: play,
        close: close,
        testUrl: testUrl,
        sanitizeUrl: sanitizeUrl,
        detectCdnProvider: detectCdnProvider,
        _playAttempt: _playAttempt,
        _startAutoPlay: _startAutoPlay,
        _tryNextUrl: _tryNextUrl,
        _testUrlFromBackend: _testUrlFromBackend,
        _openInNewTab: _openInNewTab,
        _clearLogs: _clearLogs,
        _fixMovie: _fixMovie
    };

})();

/* ── DELEGATED CLICK HANDLER ──────────────── */
$(document).on('click', '.ugflix-debug-play-btn', function (e) {
    e.preventDefault();
    e.stopPropagation();
    var movieData = $(this).data('movie');
    if (typeof movieData === 'string') {
        try { movieData = JSON.parse(movieData); }
        catch (err) { console.error('[DebugPlayer] Bad data-movie JSON', err); return; }
    }
    UgflixDebugPlayer.play(movieData);
});
