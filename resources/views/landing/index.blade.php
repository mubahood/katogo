@extends('layouts.landing')

@section('title', $siteName . ' - Stream Luganda Translated Movies & Series Online')
@section('description', 'Watch the latest Hollywood movies and TV series with professional Luganda translation. Download ' . $siteName . ' app for unlimited streaming of Luganda dubbed movies in HD quality.')
@section('keywords', 'Luganda movies, translated movies, Uganda movies, Luganda dubbed movies, stream movies Uganda, African cinema, Luganda series, movie app Uganda, watch movies in Luganda')

@push('meta')
<meta property="og:type" content="website">
<meta property="og:title" content="{{ $siteName }} - Luganda Translated Movies">
<meta property="og:description" content="Stream unlimited Luganda translated movies and series. Professional dubbing, HD quality, new releases weekly.">
<meta property="og:image" content="{{ asset('assets/images/logo.png') }}">
<meta property="og:url" content="{{ url('/') }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $siteName }} - Luganda Translated Movies">
<meta name="twitter:image" content="{{ asset('assets/images/logo.png') }}">
<link rel="canonical" href="{{ url('/') }}">
@endpush

@section('content')
<!-- Modern Hero Section -->
<section class="hero-section position-relative overflow-hidden" style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); padding: 2rem 0;">
    <!-- Animated Background -->
    <div class="position-absolute w-100 h-100 top-0 start-0" style="opacity: 0.1;">
        <div style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;60&quot; height=&quot;60&quot; viewBox=&quot;0 0 60 60&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cg fill=&quot;none&quot; fill-rule=&quot;evenodd&quot;%3E%3Cg fill=&quot;%23ffffff&quot; fill-opacity=&quot;0.4&quot;%3E%3Cpath d=&quot;M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z&quot;/%3E%3C/g%3E%3C/g%3E%3C/svg%3E'); width: 100%; height: 100%;"></div>
    </div>
    
    <div class="container position-relative" style="z-index: 2;">
        <div class="row align-items-center g-3 g-md-4">
            <!-- Hero Content -->
            <div class="col-lg-6">
                <div class="hero-content">
                    <span class="badge bg-primary mb-2" style="font-size: 0.7rem; padding: 0.3rem 0.7rem;">
                        <i class="bi bi-film me-1"></i>Luganda Translated Movies
                    </span>
                    <h1 class="fw-bold text-white mb-2 mb-md-3" style="font-size: clamp(1.75rem, 5vw, 3rem); line-height: 1.2;">
                        Watch Movies in <br><span class="text-primary">Your Language</span>
                    </h1>
                    <p class="text-white-50 mb-3" style="font-size: clamp(0.85rem, 2vw, 1.1rem); line-height: 1.5;">
                        Stream unlimited Hollywood movies and series professionally translated into Luganda. HD quality entertainment on any device.
                    </p>
                    <div class="d-flex flex-column flex-sm-row gap-2 mb-3">
                        <a href="{{ $playStoreUrl }}" target="_blank" rel="noopener" class="btn btn-primary btn-sm px-3 py-2" style="border-radius: 8px; font-size: 0.85rem;">
                            <i class="bi bi-google-play me-1"></i>Download for Android
                        </a>
                        <a href="#featured-content" class="btn btn-outline-light btn-sm px-3 py-2" style="border-radius: 8px; font-size: 0.85rem;">
                            <i class="bi bi-collection-play me-1"></i>Browse Movies
                        </a>
                    </div>
                    <div class="d-flex flex-wrap gap-3 text-white-50" style="font-size: 0.75rem;">
                        <div><i class="bi bi-check-circle-fill text-primary me-1"></i>1000+ Movies</div>
                        <div><i class="bi bi-check-circle-fill text-primary me-1"></i>HD Quality</div>
                        <div><i class="bi bi-check-circle-fill text-primary me-1"></i>New Weekly</div>
                    </div>
                </div>
            </div>
            
            <!-- Hero Image/Mockup -->
            <div class="col-lg-6">
                <div class="position-relative text-center">
                    <div class="phone-mockup mx-auto" style="max-width: min(350px, 90vw);">
                        <img src="{{ asset('assets/images/logo.png') }}" 
                             alt="{{ $siteName }} App" 
                             class="img-fluid rounded-3" 
                             style="filter: drop-shadow(0 10px 40px rgba(0,0,0,0.5));">
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Featured Content Section - Mobile Optimized -->
<section id="featured-content" class="py-3 py-md-4 bg-dark">
    <div class="container">
        <!-- Featured Movies -->
        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-2 mb-md-3">
                <h2 class="text-white mb-0" style="font-size: clamp(1rem, 3vw, 1.5rem);">
                    <i class="bi bi-film text-primary me-2"></i>Featured Movies
                </h2>
                <a href="{{ route('landing.movies') }}" class="btn btn-outline-primary btn-sm" style="font-size: 0.75rem;">
                    View All <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="row g-2">
                @foreach($featuredMovies->take(12) as $movie)
                <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                    <a href="{{ route('landing.movie.detail', $movie->id) }}" class="text-decoration-none">
                        <div class="movie-card" style="position: relative; overflow: hidden; border-radius: 6px; transition: transform 0.3s;">
                            @if($movie->thumbnail_url)
                            <img src="{{ $movie->thumbnail_url }}" 
                                 alt="{{ $movie->title }} - Luganda" 
                                 class="img-fluid w-100" 
                                 style="aspect-ratio: 2/3; object-fit: cover;"
                                 loading="lazy">
                            @else
                            <div class="bg-secondary d-flex align-items-center justify-content-center" style="aspect-ratio: 2/3;">
                                <i class="bi bi-film text-white" style="font-size: 2rem;"></i>
                            </div>
                            @endif
                            <div class="movie-overlay" style="position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(0,0,0,0.9)); padding: 0.4rem;">
                                <h6 class="text-white mb-0" style="font-size: 0.7rem; line-height: 1.2;">{{ Str::limit($movie->title, 25) }}</h6>
                                @if($movie->year)
                                <small class="text-white-50" style="font-size: 0.65rem;">{{ $movie->year }}</small>
                                @endif
                            </div>
                            <div style="position: absolute; top: 0.3rem; right: 0.3rem;">
                                <span class="badge bg-primary" style="font-size: 0.55rem; padding: 0.2rem 0.4rem;">Luganda</span>
                            </div>
                        </div>
                    </a>
                </div>
                @endforeach
            </div>
        </div>

        <!-- Featured Series -->
        <div>
            <div class="d-flex justify-content-between align-items-center mb-2 mb-md-3">
                <h2 class="text-white mb-0" style="font-size: clamp(1rem, 3vw, 1.5rem);">
                    <i class="bi bi-tv text-primary me-2"></i>Featured Series
                </h2>
                <a href="{{ route('landing.series') }}" class="btn btn-outline-primary btn-sm" style="font-size: 0.75rem;">
                    View All <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="row g-2">
                @foreach($featuredSeries->take(12) as $series)
                <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                    <a href="{{ route('landing.movie.detail', $series->id) }}" class="text-decoration-none">
                        <div class="movie-card" style="position: relative; overflow: hidden; border-radius: 6px; transition: transform 0.3s;">
                            @if($series->thumbnail_url)
                            <img src="{{ $series->thumbnail_url }}" 
                                 alt="{{ $series->title }} - Luganda Series" 
                                 class="img-fluid w-100" 
                                 style="aspect-ratio: 2/3; object-fit: cover;"
                                 loading="lazy">
                            @else
                            <div class="bg-secondary d-flex align-items-center justify-content-center" style="aspect-ratio: 2/3;">
                                <i class="bi bi-tv text-white" style="font-size: 2rem;"></i>
                            </div>
                            @endif
                            <div class="movie-overlay" style="position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(0,0,0,0.9)); padding: 0.4rem;">
                                <h6 class="text-white mb-0" style="font-size: 0.7rem; line-height: 1.2;">{{ Str::limit($series->title, 25) }}</h6>
                                <small class="text-white-50" style="font-size: 0.65rem;">
                                    <i class="bi bi-collection-play-fill"></i> Series
                                    @if($series->season_number && $series->episode_number)
                                        • S{{ $series->season_number }}E{{ $series->episode_number }}
                                    @endif
                                </small>
                            </div>
                            <div style="position: absolute; top: 0.3rem; right: 0.3rem;">
                                <span class="badge bg-primary" style="font-size: 0.55rem; padding: 0.2rem 0.4rem;">Luganda</span>
                            </div>
                        </div>
                    </a>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

<!-- Compact Features Section -->
<section class="py-3 py-md-4" style="background: #16213e;">
    <div class="container">
        <div class="text-center mb-3 mb-md-4">
            <h2 class="fw-bold text-white mb-2" style="font-size: clamp(1.25rem, 4vw, 2rem);">Why Choose {{ $siteName }}?</h2>
            <p class="text-white-50 mb-0" style="font-size: clamp(0.8rem, 2vw, 1rem);">The best platform for Luganda entertainment</p>
        </div>
        <div class="row g-3">
            <div class="col-6 col-md-3">
                <div class="text-center p-2 p-md-3">
                    <div class="mb-2">
                        <i class="bi bi-translate text-primary" style="font-size: clamp(2rem, 5vw, 3rem);"></i>
                    </div>
                    <h5 class="text-white mb-1" style="font-size: clamp(0.85rem, 2vw, 1.1rem);">Professional Translation</h5>
                    <p class="text-white-50 mb-0" style="font-size: clamp(0.7rem, 1.5vw, 0.85rem); line-height: 1.4;">Expert Luganda voice actors</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-center p-2 p-md-3">
                    <div class="mb-2">
                        <i class="bi bi-lightning-charge-fill text-primary" style="font-size: clamp(2rem, 5vw, 3rem);"></i>
                    </div>
                    <h5 class="text-white mb-1" style="font-size: clamp(0.85rem, 2vw, 1.1rem);">Fast Streaming</h5>
                    <p class="text-white-50 mb-0" style="font-size: clamp(0.7rem, 1.5vw, 0.85rem); line-height: 1.4;">HD quality optimized</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-center p-2 p-md-3">
                    <div class="mb-2">
                        <i class="bi bi-arrow-clockwise text-primary" style="font-size: clamp(2rem, 5vw, 3rem);"></i>
                    </div>
                    <h5 class="text-white mb-1" style="font-size: clamp(0.85rem, 2vw, 1.1rem);">Weekly Updates</h5>
                    <p class="text-white-50 mb-0" style="font-size: clamp(0.7rem, 1.5vw, 0.85rem); line-height: 1.4;">New content weekly</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-center p-2 p-md-3">
                    <div class="mb-2">
                        <i class="bi bi-download text-primary" style="font-size: clamp(2rem, 5vw, 3rem);"></i>
                    </div>
                    <h5 class="text-white mb-1" style="font-size: clamp(0.85rem, 2vw, 1.1rem);">Offline Viewing</h5>
                    <p class="text-white-50 mb-0" style="font-size: clamp(0.7rem, 1.5vw, 0.85rem); line-height: 1.4;">Download and watch</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Compact CTA Section -->
<section class="py-3 py-md-4 bg-primary">
    <div class="container">
        <div class="row align-items-center g-2">
            <div class="col-12 col-md-8 text-center text-md-start">
                <h2 class="text-white mb-1" style="font-size: clamp(1.1rem, 3vw, 1.5rem);">Start Watching Luganda Movies Today</h2>
                <p class="text-white mb-2 mb-md-0" style="font-size: clamp(0.8rem, 2vw, 1rem);">Download {{ $siteName }} app and enjoy unlimited entertainment</p>
            </div>
            <div class="col-12 col-md-4 text-center text-md-end">
                <a href="{{ $playStoreUrl }}" target="_blank" rel="noopener" class="btn btn-dark btn-sm px-3 py-2">
                    <i class="bi bi-google-play me-1"></i>Download Now
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Compact SEO Content -->
<section class="py-3 py-md-4 bg-dark">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <article class="text-white-50" style="font-size: 0.8rem;">
                    <h2 class="text-white mb-2" style="font-size: clamp(1rem, 3vw, 1.25rem);">About {{ $siteName }} - Luganda Movie Streaming</h2>
                    <p style="line-height: 1.5;">
                        {{ $siteName }} is Uganda's premier streaming platform for Hollywood movies and international series professionally translated into Luganda. We bring you world-class entertainment in your mother tongue with HD quality, expert dubbing, and new content added weekly. Download the app and start streaming thousands of Luganda translated movies today.
                    </p>
                    <h3 class="h5 text-white mt-4 mb-3">Watch Luganda Translated Movies Online</h3>
                    <p style="line-height: 1.5;">
                        {{ $siteName }} is Uganda's premier streaming platform for Hollywood movies and international series professionally translated into Luganda. We bring you world-class entertainment in your mother tongue with HD quality, expert dubbing, and new content added weekly. Download the app and start streaming thousands of Luganda translated movies today.
                    </p>
                    <ul class="list-unstyled mb-0" style="font-size: 0.75rem;">
                        <li class="mb-1"><i class="bi bi-check-circle-fill text-primary me-1"></i>Professional Luganda dubbing</li>
                        <li class="mb-1"><i class="bi bi-check-circle-fill text-primary me-1"></i>HD streaming quality</li>
                        <li class="mb-1"><i class="bi bi-check-circle-fill text-primary me-1"></i>Offline downloads</li>
                        <li class="mb-1"><i class="bi bi-check-circle-fill text-primary me-1"></i>Weekly new releases</li>
                        <li class="mb-1"><i class="bi bi-check-circle-fill text-primary me-1"></i>All Android devices</li>
                    </ul>
                </article>
            </div>
        </div>
    </div>
</section>

<style>
.movie-card {
    transition: transform 0.3s;
}
.movie-card:hover {
    transform: translateY(-3px);
}
</style>
@endsection
