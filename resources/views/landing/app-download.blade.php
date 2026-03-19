<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download LugaFlix — Luganda Translated Movies & Series</title>
    <meta name="description" content="Download LugaFlix app to watch Luganda translated movies and series. Available on Android, iOS, and Web.">
    <meta name="theme-color" content="#0a0a0a">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta property="og:title" content="Download LugaFlix">
    <meta property="og:description" content="Watch Luganda translated movies and series with all your favourite VJs.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url('/app') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Inter',system-ui,sans-serif;background:#0a0a0a;color:#fff;-webkit-font-smoothing:antialiased;overflow-x:hidden}
        a{color:inherit;text-decoration:none}

        /* ====== HERO ====== */
        .hero{
            min-height:100vh;min-height:100dvh;
            display:flex;flex-direction:column;align-items:center;justify-content:center;
            text-align:center;padding:56px 28px 48px;position:relative;
            background:
                radial-gradient(ellipse at 50% 0%,rgba(180,30,30,0.15) 0%,transparent 60%),
                radial-gradient(ellipse at 20% 100%,rgba(180,30,30,0.08) 0%,transparent 50%),
                #0a0a0a;
        }

        .hero-icon{margin-bottom:24px}
        .hero-icon svg{width:48px;height:48px;fill:#C0392B}

        .hero h1{
            font-size:clamp(56px,14vw,88px);font-weight:900;letter-spacing:6px;line-height:1;
        }
        .hero h1 span{color:#C0392B}

        .hero-divider{width:56px;height:3px;background:#C0392B;margin:24px auto}

        .hero-sub{
            font-size:clamp(16px,3.5vw,22px);font-weight:700;line-height:1.4;
            max-width:420px;margin-bottom:6px;
        }
        .hero-note{
            font-size:13px;font-weight:500;color:rgba(255,255,255,0.35);
            letter-spacing:1px;margin-bottom:44px;
        }

        /* 3 separate buttons with gaps */
        .hero-actions{
            display:flex;flex-direction:column;gap:14px;
            width:100%;max-width:340px;
        }
        .dl-btn{
            display:flex;align-items:center;gap:14px;
            padding:16px 24px;
            background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);
            transition:all .25s cubic-bezier(.4,0,.2,1);
            cursor:pointer;
            box-shadow:0 2px 8px rgba(0,0,0,0.3);
        }
        .dl-btn:hover{
            background:rgba(255,255,255,0.1);border-color:rgba(255,255,255,0.3);
            transform:translateY(-3px);
            box-shadow:0 8px 24px rgba(0,0,0,0.4);
        }
        .dl-btn:active{
            transform:translateY(0);
            box-shadow:0 1px 4px rgba(0,0,0,0.3);
        }
        .dl-btn svg{width:24px;height:24px;fill:#fff;flex-shrink:0;transition:transform .25s ease}
        .dl-btn:hover svg{transform:scale(1.15)}
        .dl-btn .dl-info{text-align:left}
        .dl-btn .dl-label{font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,0.4);transition:color .25s ease}
        .dl-btn .dl-name{font-size:16px;font-weight:800;letter-spacing:.5px}
        .dl-btn:hover .dl-label{color:rgba(255,255,255,0.6)}

        .dl-btn.primary{
            background:#C0392B;border-color:#C0392B;
            box-shadow:0 4px 16px rgba(192,57,43,0.4);
        }
        .dl-btn.primary:hover{
            background:#a83225;
            box-shadow:0 10px 32px rgba(192,57,43,0.5);
            transform:translateY(-3px);
        }
        .dl-btn.primary:active{
            transform:translateY(0);
            box-shadow:0 2px 8px rgba(192,57,43,0.3);
        }
        .dl-btn.primary .dl-label{color:rgba(255,255,255,0.7)}

        /* entrance animation */
        @keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
        .dl-btn{animation:fadeUp .5s ease both}
        .dl-btn:nth-child(1){animation-delay:.1s}
        .dl-btn:nth-child(2){animation-delay:.25s}
        .dl-btn:nth-child(3){animation-delay:.4s}

        /* ====== FEATURES STRIP ====== */
        .strip{
            background:#C0392B;padding:20px 24px;
            display:flex;justify-content:center;gap:32px;flex-wrap:wrap;
        }
        .strip-item{
            display:flex;align-items:center;gap:8px;
            font-size:12px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;
        }
        .strip-item svg{width:18px;height:18px;fill:#fff}

        /* ====== SEARCH CTA ====== */
        .search-cta{
            padding:64px 28px;text-align:center;
            background:#0a0a0a;
            border-top:1px solid rgba(255,255,255,0.06);
        }
        .search-cta p{
            font-size:14px;color:rgba(255,255,255,0.4);margin-bottom:16px;line-height:1.5;
        }
        .search-cta .term{
            display:inline-block;
            border:2px solid #C0392B;padding:12px 32px;
            font-size:18px;font-weight:900;letter-spacing:2px;color:#fff;
            margin-bottom:16px;
        }
        .search-cta .small{font-size:12px;color:rgba(255,255,255,0.25)}



        /* ====== FOOTER ====== */
        .footer{
            padding:20px 24px;text-align:center;
            background:#0a0a0a;border-top:1px solid rgba(255,255,255,0.06);
        }
        .footer p{font-size:10px;font-weight:700;letter-spacing:3px;text-transform:uppercase;color:rgba(255,255,255,0.2)}

        /* ====== DESKTOP ====== */
        @media(min-width:600px){
            .hero-actions{flex-direction:row;max-width:600px;gap:16px}
            .dl-btn{flex:1;flex-direction:column;text-align:center;padding:24px 16px;gap:10px}
            .dl-btn .dl-info{text-align:center}
            .qr-inner{gap:32px}
        }
    </style>
</head>
<body>

<!-- HERO -->
<section class="hero">
    <div class="hero-icon">
        <svg viewBox="0 0 24 24"><path d="M18 4l2 4h-3l-2-4h-2l2 4h-3l-2-4H8l2 4H7L5 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V4h-4z"/></svg>
    </div>

    <h1>LUGA<span>FLIX</span></h1>
    <div class="hero-divider"></div>
    <p class="hero-sub">Luganda Translated Movies<br>&amp; Series with All VJs</p>
    <p class="hero-note">Free Download Available Now</p>

    <div class="hero-actions">
        <!-- Android (primary) -->
        <a href="https://play.google.com/store/apps/details?id=lugaflix.movies" class="dl-btn primary" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24"><path d="M3.609 1.814L13.792 12 3.61 22.186a.996.996 0 0 1-.61-.92V2.734a1 1 0 0 1 .609-.92zm10.89 10.893l2.302 2.302-10.937 6.333 8.635-8.635zm3.199-3.199l2.302 2.302a1 1 0 0 1 0 1.38l-2.302 2.302L15.396 12l2.302-2.492zM5.864 2.658L16.8 8.99l-2.302 2.302L5.864 2.658z"/></svg>
            <div class="dl-info">
                <div class="dl-label">Download on</div>
                <div class="dl-name">Google Play</div>
            </div>
        </a>

        <!-- iOS -->
        <a href="https://movies.ugnews24.info" class="dl-btn" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/></svg>
            <div class="dl-info">
                <div class="dl-label">Open for</div>
                <div class="dl-name">iPhone &amp; iPad</div>
            </div>
        </a>

        <!-- Web -->
        <a href="https://movies.ugnews24.info" class="dl-btn" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24"><path d="M21 2H3c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h7l-2 3v1h8v-1l-2-3h7c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H3V4h18v12z"/></svg>
            <div class="dl-info">
                <div class="dl-label">Watch on</div>
                <div class="dl-name">Web / Computer</div>
            </div>
        </a>
    </div>
</section>

<!-- FEATURES STRIP -->
<div class="strip">
    <div class="strip-item">
        <svg viewBox="0 0 24 24"><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H8V4h12v12zm-8-1l5-3.5-5-3.5v7z"/></svg>
        Latest Movies
    </div>
    <div class="strip-item">
        <svg viewBox="0 0 24 24"><path d="M21 3H3c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H3V5h18v14zM10 8.5l5 3.5-5 3.5v-7z"/></svg>
        Full Series
    </div>
    <div class="strip-item">
        <svg viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
        All VJs
    </div>
    <div class="strip-item">
        <svg viewBox="0 0 24 24"><path d="M5 20h14v-2H5v2zm0-10h4v6h6v-6h4l-7-7-7 7z"/></svg>
        Offline Download
    </div>
</div>

<!-- SEARCH CTA -->
<section class="search-cta">
    <p>Or search on Google Play Store</p>
    <div class="term">"Lugaflix App"</div>
    <p class="small">Watch Luganda Translated Movies &amp; Series with All VJs</p>
</section>

<!-- FOOTER -->
<footer class="footer">
    <p>LugaFlix &mdash; Uganda's #1 Luganda Movie App</p>
</footer>

<script>
(function(){
    var sid = 'pv_' + Math.random().toString(36).substr(2,12) + Date.now().toString(36);
    var start = Date.now();
    var params = new URLSearchParams(window.location.search);

    // Record visit
    fetch('/api/track-visit', {
        method: 'POST',
        headers: {'Content-Type':'application/json','Accept':'application/json'},
        body: JSON.stringify({
            session_id: sid,
            page_url: window.location.pathname + window.location.search,
            referrer_url: document.referrer || null,
            utm_source: params.get('utm_source'),
            utm_medium: params.get('utm_medium'),
            utm_campaign: params.get('utm_campaign')
        })
    }).catch(function(){});

    // Track button clicks
    document.querySelectorAll('.dl-btn').forEach(function(btn, i){
        var types = ['android','ios','web'];
        btn.addEventListener('click', function(){
            navigator.sendBeacon('/api/track-event', new Blob([JSON.stringify({
                session_id: sid,
                button_clicked: types[i],
                time_on_page_seconds: Math.round((Date.now()-start)/1000)
            })], {type:'application/json'}));
        });
    });

    // Track time on page when leaving
    function sendLeave(){
        navigator.sendBeacon('/api/track-event', new Blob([JSON.stringify({
            session_id: sid,
            time_on_page_seconds: Math.round((Date.now()-start)/1000)
        })], {type:'application/json'}));
    }
    document.addEventListener('visibilitychange', function(){
        if(document.visibilityState==='hidden') sendLeave();
    });
})();
</script>

</body>
</html>
