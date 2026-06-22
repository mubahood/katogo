@extends('layouts.landing')

@section('title', $siteName . ' — Uganda\'s Largest Luganda Streaming Platform')
@section('description', 'Stream ' . number_format($totalMovies + $totalSeries) . '+ Luganda-translated movies and series on ' . $siteName . '. Professionally dubbed by Uganda\'s top VJs. Available on Android & iOS.')
@section('keywords', 'Luganda movies, Luganda translated movies, Uganda streaming, LugaFlix, Luganda dubbed movies, watch movies in Luganda, Ugandan VJ movies, African cinema Luganda, stream Uganda movies online')

@push('meta')
<meta property="og:type" content="website">
<meta property="og:title" content="{{ $siteName }} — Uganda's Largest Luganda Streaming Platform">
<meta property="og:description" content="{{ number_format($totalMovies + $totalSeries) }}+ titles dubbed in Luganda by {{ $totalVJs }}+ professional VJs. Download the app and start watching.">
<meta property="og:image" content="{{ url('assets/images/logo.png') }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:url" content="{{ url('/') }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $siteName }} — Uganda's Largest Luganda Streaming Platform">
<meta name="twitter:description" content="{{ number_format($totalMovies + $totalSeries) }}+ Luganda-dubbed titles. Download free on Android & iOS.">
<meta name="twitter:image" content="{{ url('assets/images/logo.png') }}">
<link rel="canonical" href="{{ url('/') }}">
@endpush

@section('content')

{{-- ── HERO ── --}}
<section class="hero-section position-relative overflow-hidden" style="background: linear-gradient(135deg, #0D0D1A 0%, #161630 60%, #1a1a2e 100%); padding: 3rem 0 2.5rem;">
    <div class="position-absolute w-100 h-100 top-0 start-0" style="opacity: 0.06; background-image: url('data:image/svg+xml,%3Csvg width=&quot;60&quot; height=&quot;60&quot; viewBox=&quot;0 0 60 60&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cg fill=&quot;none&quot; fill-rule=&quot;evenodd&quot;%3E%3Cg fill=&quot;%23FF7B00&quot; fill-opacity=&quot;0.5&quot;%3E%3Cpath d=&quot;M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z&quot;/%3E%3C/g%3E%3C/g%3E%3C/svg%3E'); background-size: 60px 60px;"></div>

    <div class="container position-relative" style="z-index: 2;">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <span class="badge mb-3 px-3 py-2" style="background: rgba(255,123,0,0.15); border: 1px solid rgba(255,123,0,0.4); color: #FF7B00; font-size: 0.75rem; border-radius: 20px;">
                    <i class="bi bi-patch-check-fill me-1"></i>Uganda's #1 Luganda Streaming Platform — Live Since 2023
                </span>
                <h1 class="fw-bold text-white mb-3" style="font-size: clamp(2rem, 5vw, 3.2rem); line-height: 1.15;">
                    Watch Movies in <br><span style="color: #FF7B00;">Your Language</span>
                </h1>
                <p class="mb-4" style="color: rgba(255,255,255,0.65); font-size: clamp(0.95rem, 2vw, 1.15rem); line-height: 1.7; max-width: 540px;">
                    {{ $siteName }} is Uganda's largest Luganda-dubbed streaming service —
                    <strong style="color: #fff;">{{ number_format($totalMovies + $totalSeries) }}+ titles</strong>,
                    <strong style="color: #fff;">{{ number_format($totalUsers) }}+ registered users</strong>,
                    and <strong style="color: #fff;">{{ $totalVJs }}+ professional VJs</strong> delivering
                    world-class entertainment in Luganda every day.
                </p>

                <div class="d-flex flex-wrap gap-2 mb-4">
                    <a href="{{ $playStoreUrl }}" target="_blank" rel="noopener"
                       class="btn px-4 py-2 fw-bold"
                       style="background: #FF7B00; color: #000; border-radius: 10px; font-size: 0.9rem;">
                        <i class="bi bi-google-play me-2"></i>Android — Free Download
                    </a>
                    <a href="{{ $appStoreUrl }}" target="_blank" rel="noopener"
                       class="btn btn-outline-light px-4 py-2 fw-bold"
                       style="border-radius: 10px; font-size: 0.9rem;">
                        <i class="bi bi-apple me-2"></i>iOS — App Store
                    </a>
                </div>

                {{-- Live stats bar --}}
                <div class="d-flex flex-wrap gap-4" style="font-size: 0.8rem; color: rgba(255,255,255,0.5);">
                    <div>
                        <span class="fw-bold" style="color: #FF7B00; font-size: 1.1rem;">{{ number_format($totalMovies) }}+</span><br>Movies
                    </div>
                    <div>
                        <span class="fw-bold" style="color: #FF7B00; font-size: 1.1rem;">{{ number_format($totalSeries) }}+</span><br>Series Episodes
                    </div>
                    <div>
                        <span class="fw-bold" style="color: #FF7B00; font-size: 1.1rem;">{{ number_format($totalUsers) }}+</span><br>Users
                    </div>
                    <div>
                        <span class="fw-bold" style="color: #FF7B00; font-size: 1.1rem;">{{ $totalVJs }}+</span><br>Pro VJs
                    </div>
                </div>
            </div>

            <div class="col-lg-5 text-center">
                <div style="max-width: 280px; margin: 0 auto;">
                    <img src="{{ asset('assets/images/logo.png') }}"
                         alt="{{ $siteName }} — Luganda Streaming App"
                         class="img-fluid"
                         style="border-radius: 24px; filter: drop-shadow(0 16px 48px rgba(255,123,0,0.3));">
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ── TRUST BAR ── --}}
<section style="background: #FF7B00; padding: 0.75rem 0;">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-center align-items-center gap-3 gap-md-5 text-black" style="font-size: 0.8rem; font-weight: 700;">
            <span><i class="bi bi-shield-fill-check me-1"></i>100% Original Dubbed Content</span>
            <span><i class="bi bi-cloud-check-fill me-1"></i>Cloud-Hosted on Dedicated Servers</span>
            <span><i class="bi bi-phone-fill me-1"></i>Android & iOS Apps</span>
            <span><i class="bi bi-arrow-repeat me-1"></i>New Titles Added Daily</span>
            <span><i class="bi bi-people-fill me-1"></i>{{ number_format($totalUsers) }}+ Active Users</span>
        </div>
    </div>
</section>

{{-- ── FEATURED MOVIES ── --}}
<section id="featured-content" class="py-4 bg-dark">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="text-white mb-0" style="font-size: clamp(1.1rem, 3vw, 1.5rem);">
                <i class="bi bi-film me-2" style="color: #FF7B00;"></i>Latest Movies
                <span class="ms-2 badge" style="background: rgba(255,123,0,0.15); color: #FF7B00; font-size: 0.65rem; border-radius: 20px; font-weight: 600;">{{ number_format($totalMovies) }} total</span>
            </h2>
            <a href="{{ route('landing.movies') }}" class="btn btn-sm px-3" style="background: rgba(255,123,0,0.15); color: #FF7B00; border: 1px solid rgba(255,123,0,0.3); font-size: 0.75rem; border-radius: 20px;">
                View All <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
        <div class="row g-2">
            @foreach($featuredMovies->take(12) as $movie)
            <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                <a href="{{ route('landing.movie.detail', $movie->id) }}" class="text-decoration-none">
                    <div class="movie-card" style="position: relative; overflow: hidden; border-radius: 8px;">
                        @if($movie->thumbnail_url)
                        <img src="{{ $movie->thumbnail_url }}"
                             alt="{{ $movie->title }} — Luganda Dubbed"
                             class="img-fluid w-100"
                             style="aspect-ratio: 2/3; object-fit: cover;"
                             loading="lazy">
                        @else
                        <div class="d-flex align-items-center justify-content-center" style="aspect-ratio: 2/3; background: #161630;">
                            <i class="bi bi-film text-white" style="font-size: 2rem; opacity: 0.3;"></i>
                        </div>
                        @endif
                        <div style="position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(0,0,0,0.92)); padding: 0.5rem 0.4rem 0.4rem;">
                            <p class="text-white mb-0" style="font-size: 0.68rem; font-weight: 600; line-height: 1.2;">{{ Str::limit($movie->title, 28) }}</p>
                            @if($movie->vj)
                            <p class="mb-0" style="font-size: 0.6rem; color: #FF7B00;">VJ {{ $movie->vj }}</p>
                            @endif
                        </div>
                        <div style="position: absolute; top: 0.4rem; left: 0.4rem;">
                            <span style="background: #FF7B00; color: #000; font-size: 0.55rem; font-weight: 800; padding: 0.15rem 0.4rem; border-radius: 4px;">LUGANDA</span>
                        </div>
                    </div>
                </a>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ── FEATURED SERIES ── --}}
<section class="py-4" style="background: #0D0D1A;">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="text-white mb-0" style="font-size: clamp(1.1rem, 3vw, 1.5rem);">
                <i class="bi bi-tv me-2" style="color: #FF7B00;"></i>Series & Shows
                <span class="ms-2 badge" style="background: rgba(255,123,0,0.15); color: #FF7B00; font-size: 0.65rem; border-radius: 20px; font-weight: 600;">{{ number_format($totalSeries) }} episodes</span>
            </h2>
            <a href="{{ route('landing.series') }}" class="btn btn-sm px-3" style="background: rgba(255,123,0,0.15); color: #FF7B00; border: 1px solid rgba(255,123,0,0.3); font-size: 0.75rem; border-radius: 20px;">
                View All <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
        <div class="row g-2">
            @foreach($featuredSeries->take(12) as $series)
            <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                <a href="{{ route('landing.movie.detail', $series->id) }}" class="text-decoration-none">
                    <div class="movie-card" style="position: relative; overflow: hidden; border-radius: 8px;">
                        @if($series->thumbnail_url)
                        <img src="{{ $series->thumbnail_url }}"
                             alt="{{ $series->title }} — Luganda Series"
                             class="img-fluid w-100"
                             style="aspect-ratio: 2/3; object-fit: cover;"
                             loading="lazy">
                        @else
                        <div class="d-flex align-items-center justify-content-center" style="aspect-ratio: 2/3; background: #161630;">
                            <i class="bi bi-tv text-white" style="font-size: 2rem; opacity: 0.3;"></i>
                        </div>
                        @endif
                        <div style="position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(0,0,0,0.92)); padding: 0.5rem 0.4rem 0.4rem;">
                            <p class="text-white mb-0" style="font-size: 0.68rem; font-weight: 600; line-height: 1.2;">{{ Str::limit($series->title, 28) }}</p>
                            <p class="mb-0" style="font-size: 0.6rem; color: rgba(255,255,255,0.45);">
                                <i class="bi bi-collection-play-fill me-1"></i>Series
                                @if($series->season_number && $series->episode_number)· S{{ $series->season_number }}E{{ $series->episode_number }}@endif
                            </p>
                        </div>
                        <div style="position: absolute; top: 0.4rem; left: 0.4rem;">
                            <span style="background: rgba(255,123,0,0.85); color: #000; font-size: 0.55rem; font-weight: 800; padding: 0.15rem 0.4rem; border-radius: 4px;">SERIES</span>
                        </div>
                    </div>
                </a>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ── WHY LUGAFLIX (real facts) ── --}}
<section class="py-4 py-md-5" style="background: #161630;">
    <div class="container">
        <div class="text-center mb-4">
            <h2 class="fw-bold text-white mb-2" style="font-size: clamp(1.3rem, 4vw, 2rem);">A Real Platform — Not a Demo</h2>
            <p style="color: rgba(255,255,255,0.5); font-size: 0.95rem; max-width: 560px; margin: 0 auto;">
                {{ $siteName }} has been running live since 2023 with a real paying user base, dedicated cloud infrastructure, and a full production team of professional Luganda voice actors.
            </p>
        </div>
        <div class="row g-3">
            <div class="col-6 col-md-3">
                <div class="text-center p-3" style="background: rgba(255,123,0,0.06); border: 1px solid rgba(255,123,0,0.15); border-radius: 12px; height: 100%;">
                    <i class="bi bi-film" style="font-size: 2.2rem; color: #FF7B00;"></i>
                    <div class="fw-bold text-white mt-2" style="font-size: 1.4rem;">{{ number_format($totalMovies) }}+</div>
                    <div style="color: rgba(255,255,255,0.5); font-size: 0.8rem;">Movies Dubbed</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-center p-3" style="background: rgba(255,123,0,0.06); border: 1px solid rgba(255,123,0,0.15); border-radius: 12px; height: 100%;">
                    <i class="bi bi-tv" style="font-size: 2.2rem; color: #FF7B00;"></i>
                    <div class="fw-bold text-white mt-2" style="font-size: 1.4rem;">{{ number_format($totalSeries) }}+</div>
                    <div style="color: rgba(255,255,255,0.5); font-size: 0.8rem;">Series Episodes</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-center p-3" style="background: rgba(255,123,0,0.06); border: 1px solid rgba(255,123,0,0.15); border-radius: 12px; height: 100%;">
                    <i class="bi bi-people-fill" style="font-size: 2.2rem; color: #FF7B00;"></i>
                    <div class="fw-bold text-white mt-2" style="font-size: 1.4rem;">{{ number_format($totalUsers) }}+</div>
                    <div style="color: rgba(255,255,255,0.5); font-size: 0.8rem;">Registered Users</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-center p-3" style="background: rgba(255,123,0,0.06); border: 1px solid rgba(255,123,0,0.15); border-radius: 12px; height: 100%;">
                    <i class="bi bi-mic-fill" style="font-size: 2.2rem; color: #FF7B00;"></i>
                    <div class="fw-bold text-white mt-2" style="font-size: 1.4rem;">{{ $totalVJs }}+</div>
                    <div style="color: rgba(255,255,255,0.5); font-size: 0.8rem;">Professional VJs</div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-2">
            <div class="col-12 col-md-4">
                <div class="p-3 d-flex gap-3" style="background: rgba(255,255,255,0.03); border-radius: 12px; border: 1px solid rgba(255,255,255,0.07);">
                    <i class="bi bi-cloud-check-fill flex-shrink-0 mt-1" style="color: #FF7B00; font-size: 1.3rem;"></i>
                    <div>
                        <div class="text-white fw-bold" style="font-size: 0.9rem;">Dedicated Cloud Infrastructure</div>
                        <div style="color: rgba(255,255,255,0.45); font-size: 0.78rem; line-height: 1.5;">All videos are stored on a dedicated Hetzner storage cluster and served via a global CDN — not YouTube links or third-party embeds.</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="p-3 d-flex gap-3" style="background: rgba(255,255,255,0.03); border-radius: 12px; border: 1px solid rgba(255,255,255,0.07);">
                    <i class="bi bi-translate flex-shrink-0 mt-1" style="color: #FF7B00; font-size: 1.3rem;"></i>
                    <div>
                        <div class="text-white fw-bold" style="font-size: 0.9rem;">Original Luganda Dubbing</div>
                        <div style="color: rgba(255,255,255,0.45); font-size: 0.78rem; line-height: 1.5;">Every title is dubbed in-house by Uganda's professional VJ voice actors — original creative works owned by LugaFlix.</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="p-3 d-flex gap-3" style="background: rgba(255,255,255,0.03); border-radius: 12px; border: 1px solid rgba(255,255,255,0.07);">
                    <i class="bi bi-phone-fill flex-shrink-0 mt-1" style="color: #FF7B00; font-size: 1.3rem;"></i>
                    <div>
                        <div class="text-white fw-bold" style="font-size: 0.9rem;">Android & iOS Apps</div>
                        <div style="color: rgba(255,255,255,0.45); font-size: 0.78rem; line-height: 1.5;">Native apps on Google Play and the Apple App Store. Offline downloads, HD streaming, and personalised recommendations.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ── CTA ── --}}
<section class="py-4" style="background: linear-gradient(135deg, #FF7B00 0%, #e06500 100%);">
    <div class="container">
        <div class="row align-items-center g-3">
            <div class="col-12 col-md-8 text-center text-md-start">
                <h2 class="text-black fw-bold mb-1" style="font-size: clamp(1.1rem, 3vw, 1.6rem);">Start Watching {{ number_format($totalMovies + $totalSeries) }}+ Titles Today</h2>
                <p class="mb-0" style="color: rgba(0,0,0,0.65); font-size: 0.9rem;">Free to download. Available on Android and iPhone.</p>
            </div>
            <div class="col-12 col-md-4 text-center text-md-end d-flex flex-wrap justify-content-center justify-content-md-end gap-2">
                <a href="{{ $playStoreUrl }}" target="_blank" rel="noopener"
                   class="btn btn-dark px-3 py-2 fw-bold" style="border-radius: 8px; font-size: 0.85rem;">
                    <i class="bi bi-google-play me-1"></i>Google Play
                </a>
                <a href="{{ $appStoreUrl }}" target="_blank" rel="noopener"
                   class="btn btn-dark px-3 py-2 fw-bold" style="border-radius: 8px; font-size: 0.85rem;">
                    <i class="bi bi-apple me-1"></i>App Store
                </a>
            </div>
        </div>
    </div>
</section>

{{-- ── SEO CONTENT ── --}}
<section class="py-4 bg-dark">
    <div class="container">
        <article style="color: rgba(255,255,255,0.45); font-size: 0.82rem; max-width: 800px;">
            <h2 class="text-white mb-2" style="font-size: clamp(1rem, 3vw, 1.25rem);">About {{ $siteName }}</h2>
            <p style="line-height: 1.7;">
                {{ $siteName }} is Uganda's largest and most active Luganda-dubbed streaming service, founded and operated from Kampala, Uganda.
                Since launching in 2023, the platform has grown to over <strong style="color: #fff;">{{ number_format($totalMovies + $totalSeries) }} titles</strong> spanning movies, series, documentaries, and lifestyle content —
                all professionally dubbed into Luganda by a team of <strong style="color: #fff;">{{ $totalVJs }}+ voice artists</strong> (locally known as VJs).
                The service is entirely self-hosted on dedicated cloud infrastructure and does not rely on YouTube, Netflix, or any third-party streaming provider.
            </p>
            <p style="line-height: 1.7;">
                With over <strong style="color: #fff;">{{ number_format($totalUsers) }} registered users</strong> on Android and iOS,
                {{ $siteName }} is the go-to destination for Ugandans at home and in the diaspora who want to enjoy international and local films in their mother tongue.
                New content is processed and published daily. Users can stream in HD or download titles for offline viewing.
            </p>
            <ul class="list-unstyled mb-0 mt-3 row g-1" style="font-size: 0.78rem;">
                <li class="col-12 col-sm-6"><i class="bi bi-check-circle-fill me-2" style="color: #FF7B00;"></i>{{ number_format($totalMovies) }}+ Luganda-dubbed movies</li>
                <li class="col-12 col-sm-6"><i class="bi bi-check-circle-fill me-2" style="color: #FF7B00;"></i>{{ number_format($totalSeries) }}+ series episodes</li>
                <li class="col-12 col-sm-6"><i class="bi bi-check-circle-fill me-2" style="color: #FF7B00;"></i>{{ $totalVJs }}+ professional Ugandan VJs</li>
                <li class="col-12 col-sm-6"><i class="bi bi-check-circle-fill me-2" style="color: #FF7B00;"></i>{{ number_format($totalUsers) }}+ registered users</li>
                <li class="col-12 col-sm-6"><i class="bi bi-check-circle-fill me-2" style="color: #FF7B00;"></i>Self-hosted on dedicated cloud servers</li>
                <li class="col-12 col-sm-6"><i class="bi bi-check-circle-fill me-2" style="color: #FF7B00;"></i>Android & iOS native apps</li>
                <li class="col-12 col-sm-6"><i class="bi bi-check-circle-fill me-2" style="color: #FF7B00;"></i>HD streaming + offline downloads</li>
                <li class="col-12 col-sm-6"><i class="bi bi-check-circle-fill me-2" style="color: #FF7B00;"></i>New content published daily</li>
            </ul>
        </article>
    </div>
</section>

<style>
.movie-card { transition: transform 0.25s ease; }
.movie-card:hover { transform: translateY(-4px) scale(1.02); }
</style>
@endsection
