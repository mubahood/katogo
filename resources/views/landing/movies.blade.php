@extends('layouts.landing')

@section('title', 'Luganda Translated Movies - Stream Movies in Luganda | ' . $siteName)
@section('description', 'Browse our collection of movies professionally translated into Luganda. Stream HD quality Luganda movies on ' . $siteName . '. Page ' . $currentPage . ' of ' . ceil($totalMovies / 24))
@section('keywords', 'Luganda movies, Luganda translated movies, watch movies Uganda, stream Luganda movies, Luganda action movies, Luganda comedy movies, Luganda drama movies')

@push('meta')
<meta property="og:type" content="website">
<meta property="og:title" content="Luganda Translated Movies - {{ $siteName }}">
<meta property="og:description" content="Browse {{ $totalMovies }}+ movies translated into Luganda. Professional translation, HD quality.">
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
<!-- Compact Header -->
<section class="py-3 py-md-4" style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);">
    <div class="container">
        <div class="text-center">
            <h1 class="h4 h3-md fw-bold text-white mb-2">
                <i class="bi bi-film text-primary"></i> Luganda Movies
            </h1>
            <p class="text-white-50 mb-2" style="font-size: 0.85rem;">
                {{ number_format($totalMovies) }} movies translated to Luganda
            </p>
            <a href="{{ $playStoreUrl }}" target="_blank" rel="noopener" class="btn btn-primary btn-sm px-3 py-1" style="font-size: 0.8rem;">
                <i class="bi bi-google-play me-1"></i>Download App
            </a>
        </div>
    </div>
</section>

<!-- Movies Grid -->
<section class="py-3 py-md-4 bg-dark">
    <div class="container">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-2">
            <ol class="breadcrumb mb-0" style="font-size: 0.75rem;">
                <li class="breadcrumb-item"><a href="{{ route('landing.index') }}" class="text-primary">Home</a></li>
                <li class="breadcrumb-item active text-white" aria-current="page">Movies</li>
            </ol>
        </nav>

        <!-- Results Info -->
        <div class="d-flex justify-content-between align-items-center mb-2 mb-md-3">
            <h2 class="text-white mb-0" style="font-size: 0.8rem;">
                {{ $movies->firstItem() }}-{{ $movies->lastItem() }} of {{ number_format($totalMovies) }}
            </h2>
            <div class="text-white-50" style="font-size: 0.75rem;">
                Page {{ $currentPage }}/{{ $movies->lastPage() }}
            </div>
        </div>

        <!-- Compact Movies Grid -->
        <div class="row g-2 mb-3 mb-md-4">
            @forelse($movies as $movie)
            <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                <a href="{{ route('landing.movie.detail', $movie->id) }}" class="text-decoration-none movie-link">
                    <article class="movie-card h-100" itemscope itemtype="https://schema.org/Movie">
                        <meta itemprop="name" content="{{ $movie->title }} - Luganda">
                        @if($movie->description)
                        <meta itemprop="description" content="{{ Str::limit(strip_tags($movie->description), 150) }}">
                        @endif
                        @if($movie->year)
                        <meta itemprop="datePublished" content="{{ $movie->year }}">
                        @endif
                        
                        <div style="position: relative; overflow: hidden; border-radius: 6px; transition: all 0.3s;">
                            @if($movie->thumbnail_url)
                            <img src="{{ $movie->thumbnail_url }}" 
                                 alt="{{ $movie->title }} - Luganda Translated Movie" 
                                 itemprop="image"
                                 class="img-fluid w-100" 
                                 style="aspect-ratio: 2/3; object-fit: cover;"
                                 loading="lazy">
                            @else
                            <div class="bg-secondary d-flex align-items-center justify-content-center" style="aspect-ratio: 2/3;">
                                <i class="bi bi-film text-white" style="font-size: 2rem;"></i>
                            </div>
                            @endif
                            
                            <div class="movie-overlay" style="position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(0,0,0,0.9)); padding: 0.4rem;">
                                <h3 class="text-white mb-0" style="font-size: 0.7rem; font-weight: 600; line-height: 1.2;">
                                    {{ Str::limit($movie->title, 25) }}
                                </h3>
                                <div class="d-flex justify-content-between align-items-center mt-1" style="font-size: 0.65rem;">
                                    @if($movie->year)
                                    <small class="text-white-50">{{ $movie->year }}</small>
                                    @endif
                                   
                                </div>
                            </div>
                            
                            <!-- Luganda Badge -->
                            <div style="position: absolute; top: 0.3rem; right: 0.3rem;">
                                <span class="badge bg-primary" style="font-size: 0.55rem; padding: 0.2rem 0.4rem;">Luganda</span>
                            </div>
                        </div>
                    </article>
                </a>
            </div>
            @empty
            <div class="col-12">
                <div class="text-center py-4">
                    <i class="bi bi-film text-white-50" style="font-size: 3rem;"></i>
                    <p class="text-white-50 mt-2 mb-0" style="font-size: 0.85rem;">No movies found.</p>
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

<!-- Compact SEO Content -->
<section class="py-3 py-md-4" style="background: #16213e;">
    <div class="container">
        <article class="text-white-50" style="font-size: 0.8rem;">
            <h2 class="h6 h5-md text-white mb-2">Luganda Translated Movies Online</h2>
            <p class="mb-2" style="line-height: 1.5;">
                Welcome to Uganda's largest collection of Luganda translated movies. Professional translation, HD quality, and thousands of films translated to Luganda. Action, comedy, drama, thriller, and more available on {{ $siteName }}.
            </p>
            <ul class="list-unstyled mb-2" style="font-size: 0.75rem;">
                <li class="mb-1"><i class="bi bi-check-circle-fill text-primary me-1"></i>Professional Luganda translation</li>
                <li class="mb-1"><i class="bi bi-check-circle-fill text-primary me-1"></i>HD streaming optimized for Uganda</li>
                <li class="mb-1"><i class="bi bi-check-circle-fill text-primary me-1"></i>Offline downloads available</li>
                <li class="mb-1"><i class="bi bi-check-circle-fill text-primary me-1"></i>New movies added weekly</li>
            </ul>
        </article>
    </div>
</section>

<!-- Compact CTA -->
<section class="py-3 py-md-4 bg-primary">
    <div class="container">
        <div class="row align-items-center g-2">
            <div class="col-12 col-md-8 text-center text-md-start">
                <h2 class="h6 h5-md text-white mb-1">Watch {{ number_format($totalMovies) }} Luganda Movies</h2>
                <p class="text-white mb-2 mb-md-0" style="font-size: 0.8rem;">Download free app and start streaming</p>
            </div>
            <div class="col-12 col-md-4 text-center text-md-end">
                <a href="{{ $playStoreUrl }}" target="_blank" rel="noopener" class="btn btn-dark btn-sm px-3 py-2">
                    <i class="bi bi-download me-1"></i>Download App
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
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.5);
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
