<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Download LugaFlix</title>
<style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{background:#0d0d0f;color:#fff;font-family:system-ui,-apple-system,'Segoe UI',sans-serif;
         min-height:100vh;display:flex;flex-direction:column;align-items:center;
         padding:40px 18px 50px;text-align:center}
    .logo{font-size:26px;font-weight:900;letter-spacing:1px;margin-bottom:30px}
    .logo span{color:#E8602C}
    .big-btn{display:block;width:100%;max-width:400px;background:#E8602C;color:#fff;text-decoration:none;
             font-size:18px;font-weight:800;padding:18px;border-radius:12px;margin-bottom:12px;
             animation:pulse 1.6s ease-in-out infinite}
    .big-btn:active{opacity:.85;animation:none;transform:scale(.97)}
    @keyframes pulse{
        0%,100%{transform:scale(1);box-shadow:0 0 0 0 rgba(232,96,44,.55)}
        50%{transform:scale(1.03);box-shadow:0 0 0 14px rgba(232,96,44,0)}
    }
    @media (prefers-reduced-motion: reduce){.big-btn{animation:none}}
    .alt{color:#8a8a8a;font-size:12.5px;margin-bottom:34px}
    .alt a{color:#ccc}
    .video-link{display:block;width:100%;max-width:320px;position:relative;border-radius:14px;overflow:hidden}
    .video-link img{width:100%;display:block}
    .video-link .play{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
                      background:rgba(0,0,0,.25)}
    .video-link .play span{background:#E8602C;width:64px;height:64px;border-radius:50%;
                           display:flex;align-items:center;justify-content:center}
    .video-link .play svg{width:26px;height:26px;fill:#fff;margin-left:4px}
    .video-caption{font-size:14px;color:#bbb;margin-bottom:12px}
</style>
</head>
<body>

<div class="logo">LUGA<span>FLIX</span></div>

<a class="big-btn" id="dl" href="{{ url('/app/download/arm64') }}@if(request('src'))?src={{ urlencode(request('src')) }}@endif">
    Download LugaFlix
</a>
<div class="alt">
    Won't install? <a id="dl32" href="{{ url('/app/download/arm32') }}@if(request('src'))?src={{ urlencode(request('src')) }}@endif">Try this version</a>
</div>

<div class="video-caption">Watch how to install</div>
<a class="video-link" id="vid" href="https://www.youtube.com/shorts/M2sAr8IAago" target="_blank" rel="noopener">
    <img src="https://i.ytimg.com/vi/M2sAr8IAago/oar2.jpg"
         onerror="this.src='https://i.ytimg.com/vi/M2sAr8IAago/hqdefault.jpg'"
         alt="How to install LugaFlix" loading="lazy">
    <span class="play"><span><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></span></span>
</a>

<script>
(function () {
    var sid = 'pv_' + Math.random().toString(36).substr(2, 12) + Date.now().toString(36);
    var params = new URLSearchParams(window.location.search);

    // Page view beacon
    fetch('/api/track-visit', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            session_id: sid,
            page_url: '/app/android',
            referrer_url: document.referrer || null,
            utm_source: params.get('src') || params.get('utm_source'),
            utm_medium: params.get('utm_medium'),
            utm_campaign: params.get('utm_campaign')
        })
    }).catch(function(){});

    // Attach the session to download links so downloads join to this visit
    ['dl', 'dl32'].forEach(function (id) {
        var a = document.getElementById(id);
        if (a) a.href += (a.href.indexOf('?') > -1 ? '&' : '?') + 'sid=' + sid;
    });

    // Video-click beacon
    var v = document.getElementById('vid');
    if (v) v.addEventListener('click', function () {
        fetch('/api/track-event', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({session_id: sid, button_clicked: 'video'})
        }).catch(function(){});
    });
})();
</script>
</body>
</html>
