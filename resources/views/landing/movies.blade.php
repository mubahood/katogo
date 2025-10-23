@extends('layouts.landing')

@section('title', 'Luganda Translated Movies - Watch Hollywood Movies in Luganda | ' . $siteName)
@section('description', 'Browse our extensive collection of Hollywood movies professionally translated into Luganda. Stream HD quality Luganda dubbed movies on ' . $siteName . '. Page ' . $currentPage . ' of ' . ceil($totalMovies / 24))
@section('keywords', 'Luganda movies, Hollywood movies in Luganda, Luganda dubbed movies, watch movies Uganda, stream Luganda movies, Luganda action movies, Luganda comedy movies, Luganda drama movies')

@push('meta')
<meta property="og:type" content="website">
<meta property="og:title" content="Luganda Translated Movies - {{ $siteName }}">
<meta property="og:description" content="Browse {{ $totalMovies }}+ Hollywood movies translated into Luganda. Professional dubbing, HD quality.">
<meta property="og:url" content="{{ url('/movies') }}">
<meta name="twitter:card" content="summary_large_image">
<link rel="canonical" href="{{ url('/movies') }}">
@if($currentPage > 1)
<link rel="prev" href="{{ $movies->previousPageUrl() }}">
@endif
@if($movies->hasMorePages())
<link rel="next" href="{{ $movies->nextPageUrl() }}">
@endif
@endpush

@section('content')
<!-- Header Section -->
<section class="py-5" style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 mx-auto text-center">
                <h1 class="display-4 fw-bold text-white mb-3">
                    <i class="bi bi-film text-primary"></i> Luganda Translated Movies
                </h1>
                <p class="lead text-white-50 mb-4">
                    Watch {{ number_format($totalMovies) }} Hollywood movies professionally translated into Luganda. 
                    Stream in HD quality on any device.
                </p>
                <div class="d-inline-block">
                    <a href="{{ $playStoreUrl }}" target="_blank" rel="noopener" class="btn btn-primary btn-lg">
                        <i class="bi bi-google-play me-2"></i>Download App to Watch
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Movies Grid -->
<section class="py-5 bg-dark">
    <div class="container">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('landing.index') }}" class="text-primary">Home</a></li>
                <li class="breadcrumb-item active text-white" aria-current="page">Luganda Movies</li>
            </ol>
        </nav>

        <!-- Results Info -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h5 text-white mb-0">
                Showing {{ $movies->firstItem() }} - {{ $movies->lastItem() }} of {{ number_format($totalMovies) }} movies
            </h2>
            <div class="text-white-50 small">
                Page {{ $currentPage }} of {{ $movies->lastPage() }}
            </div>
        </div>

        <!-- Movies Grid -->
        <div class="row g-3 mb-5">
            @forelse($movies as $movie)
            <div class="col-lg-2 col-md-3 col-sm-4 col-6">
                <a href="{{ route('landing.movie.detail', $movie->id) }}" class="text-decoration-none movie-link">
                    <article class="movie-card h-100" itemscope itemtype="https://schema.org/Movie">
                        <meta itemprop="name" content="{{ $movie->title }} - Luganda">
                        @if($movie->description)
                        <meta itemprop="description" content="{{ Str::limit(strip_tags($movie->description), 150) }}">
                        @endif
                        @if($movie->year)
                        <meta itemprop="datePublished" content="{{ $movie->year }}">
                        @endif
                        
                        <div style="position: relative; overflow: hidden; border-radius: 8px; transition: all 0.3s;">
                            @if($movie->thumbnail_url)
                            <img src="{{ $movie->thumbnail_url }}" 
                                 alt="{{ $movie->title }} - Luganda Translated Movie" 
                                 itemprop="image"
                                 class="img-fluid w-100" 
                                 style="aspect-ratio: 2/3; object-fit: cover;"
                                 loading="lazy">
                            @else
                            <div class="bg-secondary d-flex align-items-center justify-content-center" style="aspect-ratio: 2/3;">
                                <i class="bi bi-film text-white" style="font-size: 3rem;"></i>
                            </div>
                            @endif
                            
                            <div class="movie-overlay" style="position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(0,0,0,0.9)); padding: 1rem 0.5rem;">
                                <h3 class="text-white mb-0 small" style="font-size: 0.875rem; font-weight: 600;">
                                    {{ Str::limit($movie->title, 35) }}
                                </h3>
                                <div class="d-flex justify-content-between align-items-center mt-1">
                                    @if($movie->year)
                                    <small class="text-white-50">{{ $movie->year }}</small>
                                    @endif
                                    @if($movie->rating)
                                    <small class="text-warning">
                                        <i class="bi bi-star-fill"></i> {{ number_format($movie->rating, 1) }}
                                    </small>
                                    @endif
                                </div>
                            </div>
                            
                            <!-- Luganda Badge -->
                            <div style="position: absolute; top: 0.5rem; right: 0.5rem;">
                                <span class="badge bg-primary" style="font-size: 0.7rem;">Luganda</span>
                            </div>
                        </div>
                    </article>
                </a>
            </div>
            @empty
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="bi bi-film text-white-50" style="font-size: 4rem;"></i>
                    <p class="text-white-50 mt-3">No movies found.</p>
                </div>
            </div>
            @endforelse
        </div>

        <!-- Pagination -->
        @if($movies->hasPages())
        <div class="d-flex justify-content-center">
            <nav aria-label="Page navigation">
                {{ $movies->links('pagination::bootstrap-5') }}
            </nav>
        </div>
        @endif
    </div>
</section>

<!-- SEO Content -->
<section class="py-5" style="background: #16213e;">
    <div class="container">
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <article class="text-white-50">
                    <h2 class="h4 text-white mb-4">Watch Luganda Translated Movies Online</h2>
                    <p class="mb-3">
                        Welcome to Uganda's most comprehensive collection of Luganda translated movies. Our platform brings you the best of Hollywood entertainment with professional Luganda dubbing and subtitles. Every movie in our catalog has been carefully selected and translated by expert Luganda voice actors who understand the cultural nuances that make entertainment truly enjoyable.
                    </p>
                    <p class="mb-3">
                        Whether you're looking for action-packed blockbusters, heartwarming romantic comedies, edge-of-your-seat thrillers, or family-friendly animations, you'll find thousands of options in our Luganda movies collection. Each title is available in high-definition quality, optimized for streaming on Uganda's internet infrastructure.
                    </p>
                    <h3 class="h5 text-white mb-3 mt-4">Popular Luganda Movie Categories</h3>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i>Action & Adventure Movies in Luganda</li>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i>Comedy Movies Translated to Luganda</li>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i>Drama & Romance in Luganda</li>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i>Thriller & Horror Movies - Luganda</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i>Sci-Fi & Fantasy Luganda Movies</li>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i>Family & Animation in Luganda</li>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i>Crime & Mystery Movies - Luganda</li>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i>Documentary Films in Luganda</li>
                            </ul>
                        </div>
                    </div>
                    <p class="mb-3">
                        Download the {{ $siteName }} app today to start streaming Luganda translated movies anytime, anywhere. With offline download capabilities, you can enjoy your favorite films even without an internet connection. New Luganda movies are added to our collection every week, ensuring you never run out of quality entertainment.
                    </p>
                </article>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="py-5 bg-primary">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h2 class="h4 text-white mb-2">Ready to Watch Luganda Movies?</h2>
                <p class="text-white mb-3 mb-lg-0">Download {{ $siteName }} app and get instant access to {{ number_format($totalMovies) }} Luganda translated movies</p>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a href="{{ $playStoreUrl }}" target="_blank" rel="noopener" class="btn btn-dark btn-lg px-4">
                    <i class="bi bi-download me-2"></i>Download Free App
                </a>
            </div>
        </div>
    </div>
</section>

<style>
.movie-card {
    transition: transform 0.3s;
}
.movie-link:hover .movie-card {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
}
.breadcrumb {
    background: transparent;
    padding: 0;
    margin: 0;
}
.breadcrumb-item + .breadcrumb-item::before {
    color: #6c757d;
}
</style>
@endsection
