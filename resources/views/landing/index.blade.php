@extends('layouts.landing')

@section('title', $siteName . ' - Stream Luganda Translated Movies & Series Online')
@section('description', 'Watch the latest Hollywood movies and TV series with professional Luganda translation. Download ' . $siteName . ' app for unlimited streaming of Luganda dubbed movies in HD quality.')
@section('keywords', 'Luganda movies, translated movies, Uganda movies, Luganda dubbed movies, stream movies Uganda, African cinema, Luganda series, movie app Uganda, watch movies in Luganda')

@push('meta')
<meta property="og:type" content="website">
<meta property="og:title" content="{{ $siteName }} - Luganda Translated Movies">
<meta property="og:description" content="Stream unlimited Luganda translated movies and series. Professional dubbing, HD quality, new releases weekly.">
<meta property="og:url" content="{{ url('/') }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $siteName }} - Luganda Translated Movies">
<link rel="canonical" href="{{ url('/') }}">
@endpush

@section('content')
<!-- Hero Section -->
<section class="hero-section py-5" style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); min-height: 85vh; display: flex; align-items: center;">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <div class="hero-content">
                    <span class="badge bg-primary mb-3" style="font-size: 0.9rem; padding: 0.5rem 1rem;">🎬 Luganda Translated Movies</span>
                    <h1 class="display-3 fw-bold text-white mb-4" style="line-height: 1.2;">
                        Watch Movies in <span class="text-primary">Your Language</span>
                    </h1>
                    <p class="lead text-white-50 mb-4" style="font-size: 1.25rem;">
                        Stream unlimited Hollywood movies and series professionally translated into Luganda. 
                        Download now and enjoy HD quality entertainment on any device.
                    </p>
                    <div class="d-flex flex-wrap gap-3 mb-4">
                        <a href="{{ $playStoreUrl }}" target="_blank" rel="noopener" class="btn btn-primary btn-lg px-4 py-3" style="border-radius: 12px;">
                            <i class="bi bi-google-play me-2"></i>Download for Android
                        </a>
                        <a href="#browse-movies" class="btn btn-outline-light btn-lg px-4 py-3" style="border-radius: 12px;">
                            <i class="bi bi-collection-play me-2"></i>Browse Movies
                        </a>
                    </div>
                    <div class="d-flex gap-4 text-white-50">
                        <div>
                            <i class="bi bi-check-circle-fill text-primary me-2"></i>1000+ Movies
                        </div>
                        <div>
                            <i class="bi bi-check-circle-fill text-primary me-2"></i>HD Quality
                        </div>
                        <div>
                            <i class="bi bi-check-circle-fill text-primary me-2"></i>New Weekly
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="position-relative text-center">
                    <div class="phone-mockup" style="max-width: 400px; margin: 0 auto;">
                        <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 300 600'%3E%3Crect width='300' height='600' rx='30' fill='%23000'/%3E%3Crect x='10' y='10' width='280' height='580' rx='20' fill='%231a1a2e'/%3E%3Crect x='20' y='70' width='260' height='460' fill='%23e50914'/%3E%3Ctext x='150' y='320' font-size='60' fill='white' text-anchor='middle'%3E▶%3C/text%3E%3C/svg%3E" alt="{{ $siteName }} App" class="img-fluid" style="filter: drop-shadow(0 20px 60px rgba(0,0,0,0.5));">
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Featured Content Section -->
<section id="browse-movies" class="py-5 bg-dark">
    <div class="container">
        <!-- Featured Movies -->
        <div class="mb-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="h3 text-white mb-0">
                    <i class="bi bi-film text-primary me-2"></i>Featured Luganda Translated Movies
                </h2>
                <a href="{{ route('landing.movies') }}" class="btn btn-outline-primary">
                    View All Movies <i class="bi bi-arrow-right ms-2"></i>
                </a>
            </div>
            <div class="row g-3">
                @foreach($featuredMovies->take(6) as $movie)
                <div class="col-lg-2 col-md-3 col-sm-4 col-6">
                    <a href="{{ route('landing.movie.detail', $movie->id) }}" class="text-decoration-none">
                        <div class="movie-card" style="position: relative; overflow: hidden; border-radius: 8px; transition: transform 0.3s;">
                            @if($movie->thumbnail_url)
                            <img src="{{ $movie->thumbnail_url }}" alt="{{ $movie->title }} - Luganda Translated" class="img-fluid w-100" style="aspect-ratio: 2/3; object-fit: cover;">
                            @else
                            <div class="bg-secondary d-flex align-items-center justify-content-center" style="aspect-ratio: 2/3;">
                                <i class="bi bi-film text-white" style="font-size: 3rem;"></i>
                            </div>
                            @endif
                            <div class="movie-overlay" style="position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(0,0,0,0.9)); padding: 1rem 0.5rem;">
                                <h6 class="text-white mb-0 small">{{ Str::limit($movie->title, 30) }}</h6>
                                @if($movie->year)
                                <small class="text-white-50">{{ $movie->year }}</small>
                                @endif
                            </div>
                        </div>
                    </a>
                </div>
                @endforeach
            </div>
        </div>

        <!-- Featured Series -->
        <div>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="h3 text-white mb-0">
                    <i class="bi bi-tv text-primary me-2"></i>Luganda Translated Series
                </h2>
                <a href="{{ route('landing.series') }}" class="btn btn-outline-primary">
                    View All Series <i class="bi bi-arrow-right ms-2"></i>
                </a>
            </div>
            <div class="row g-3">
                @foreach($featuredSeries->take(6) as $series)
                <div class="col-lg-2 col-md-3 col-sm-4 col-6">
                    <a href="{{ route('landing.movie.detail', $series->id) }}" class="text-decoration-none">
                        <div class="movie-card" style="position: relative; overflow: hidden; border-radius: 8px; transition: transform 0.3s;">
                            @if($series->thumbnail)
                            <img src="{{ $series->thumbnail }}" alt="{{ $series->name }} - Luganda Translated Series" class="img-fluid w-100" style="aspect-ratio: 2/3; object-fit: cover;">
                            @else
                            <div class="bg-secondary d-flex align-items-center justify-content-center" style="aspect-ratio: 2/3;">
                                <i class="bi bi-tv text-white" style="font-size: 3rem;"></i>
                            </div>
                            @endif
                            <div class="movie-overlay" style="position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(0,0,0,0.9)); padding: 1rem 0.5rem;">
                                <h6 class="text-white mb-0 small">{{ Str::limit($series->name, 30) }}</h6>
                                <small class="text-white-50"><i class="bi bi-collection-play-fill"></i> Series</small>
                            </div>
                        </div>
                    </a>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="py-5" style="background: #16213e;">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="display-5 fw-bold text-white mb-3">Why Choose {{ $siteName }}?</h2>
            <p class="lead text-white-50">The best platform for Luganda translated entertainment</p>
        </div>
        <div class="row g-4">
            <div class="col-lg-3 col-md-6">
                <div class="text-center p-4">
                    <div class="mb-3">
                        <i class="bi bi-translate text-primary" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="text-white">Professional Translation</h5>
                    <p class="text-white-50 small">Expert Luganda voice actors and translators ensure authentic cultural adaptation</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="text-center p-4">
                    <div class="mb-3">
                        <i class="bi bi-lightning-charge-fill text-primary" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="text-white">Fast Streaming</h5>
                    <p class="text-white-50 small">HD quality streaming optimized for Uganda's internet speeds</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="text-center p-4">
                    <div class="mb-3">
                        <i class="bi bi-arrow-clockwise text-primary" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="text-white">Weekly Updates</h5>
                    <p class="text-white-50 small">New Luganda translated movies and series added every week</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="text-center p-4">
                    <div class="mb-3">
                        <i class="bi bi-device-hdd-fill text-primary" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="text-white">Offline Viewing</h5>
                    <p class="text-white-50 small">Download movies to watch offline anytime, anywhere</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="py-5 bg-primary">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h2 class="h3 text-white mb-2">Start Watching Luganda Movies Today</h2>
                <p class="text-white mb-3 mb-lg-0">Download {{ $siteName }} app and enjoy unlimited Luganda translated entertainment</p>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a href="{{ $playStoreUrl }}" target="_blank" rel="noopener" class="btn btn-dark btn-lg px-4">
                    <i class="bi bi-google-play me-2"></i>Download Now
                </a>
            </div>
        </div>
    </div>
</section>

<!-- SEO Content Section -->
<section class="py-5 bg-dark">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <article class="text-white-50">
                    <h2 class="h4 text-white mb-3">About {{ $siteName }} - Your Premier Luganda Movie Streaming Platform</h2>
                    <p>
                        {{ $siteName }} is Uganda's leading streaming platform dedicated to bringing you the best Hollywood movies and international series professionally translated into Luganda. We understand the importance of enjoying entertainment in your mother tongue, which is why we've made it our mission to make world-class content accessible to Luganda speakers everywhere.
                    </p>
                    <h3 class="h5 text-white mt-4 mb-3">Watch Luganda Translated Movies Online</h3>
                    <p>
                        Our extensive library features over 1,000 movies and series, all carefully translated and dubbed by professional Luganda voice actors. From action-packed blockbusters to heartwarming dramas, thrilling mysteries to hilarious comedies - there's something for everyone. Every title is available in crystal-clear HD quality, optimized for smooth streaming even on slower internet connections.
                    </p>
                    <h3 class="h5 text-white mt-4 mb-3">Why Luganda Translation Matters</h3>
                    <p>
                        Watching movies in Luganda isn't just about language - it's about cultural connection and authentic entertainment. Our translation team goes beyond word-for-word translation to capture idioms, humor, and cultural nuances that make the content relatable and enjoyable for Ugandan audiences. This attention to detail sets {{ $siteName }} apart as the premier platform for Luganda entertainment.
                    </p>
                    <h3 class="h5 text-white mt-4 mb-3">Features That Make Us Stand Out</h3>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i>Professional Luganda dubbing and subtitles</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i>HD and Full HD streaming quality</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i>Download for offline viewing</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i>New content added weekly</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i>Compatible with all Android devices</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i>User-friendly interface in Luganda</li>
                    </ul>
                </article>
            </div>
        </div>
    </div>
</section>

<style>
.movie-card:hover {
    transform: scale(1.05);
}
</style>
@endsection
