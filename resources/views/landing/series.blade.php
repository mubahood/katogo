@extends('layouts.landing')

@section('title', 'Luganda Translated TV Series - Watch Series in Luganda | ' . $siteName)
@section('description', 'Stream TV series and shows professionally translated into Luganda. Watch episodes with Luganda dubbing in HD quality on ' . $siteName . '. Page ' . $currentPage . ' of ' . ceil($totalSeries / 24))
@section('keywords', 'Luganda series, TV shows in Luganda, Luganda dubbed series, watch series Uganda, stream Luganda shows, Luganda TV series, episode in Luganda')

@push('meta')
<meta property="og:type" content="website">
<meta property="og:title" content="Luganda Translated TV Series - {{ $siteName }}">
<meta property="og:description" content="Browse {{ $totalSeries }}+ TV series translated into Luganda. Professional dubbing, all episodes available.">
<meta property="og:url" content="{{ url('/series') }}">
<link rel="canonical" href="{{ url('/series') }}">
@if($currentPage > 1)
<link rel="prev" href="{{ $series->previousPageUrl() }}">
@endif
@if($series->hasMorePages())
<link rel="next" href="{{ $series->nextPageUrl() }}">
@endif
@endpush

@section('content')
<!-- Header Section -->
<section class="py-5" style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 mx-auto text-center">
                <h1 class="display-4 fw-bold text-white mb-3">
                    <i class="bi bi-tv text-primary"></i> Luganda Translated TV Series
                </h1>
                <p class="lead text-white-50 mb-4">
                    Stream {{ number_format($totalSeries) }} TV series and shows professionally translated into Luganda. 
                    Watch all episodes in HD quality.
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

<!-- Series Grid -->
<section class="py-5 bg-dark">
    <div class="container">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('landing.index') }}" class="text-primary">Home</a></li>
                <li class="breadcrumb-item active text-white" aria-current="page">Luganda Series</li>
            </ol>
        </nav>

        <!-- Results Info -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h5 text-white mb-0">
                Showing {{ $series->firstItem() }} - {{ $series->lastItem() }} of {{ number_format($totalSeries) }} series
            </h2>
            <div class="text-white-50 small">
                Page {{ $currentPage }} of {{ $series->lastPage() }}
            </div>
        </div>

        <!-- Series Grid -->
        <div class="row g-3 mb-5">
            @forelse($series as $show)
            <div class="col-lg-2 col-md-3 col-sm-4 col-6">
                <a href="{{ route('landing.movie.detail', $show->id) }}" class="text-decoration-none series-link">
                    <article class="series-card h-100" itemscope itemtype="https://schema.org/TVSeries">
                        <meta itemprop="name" content="{{ $show->title }} - Luganda">
                        @if($show->description)
                        <meta itemprop="description" content="{{ Str::limit(strip_tags($show->description), 150) }}">
                        @endif
                        
                        <div style="position: relative; overflow: hidden; border-radius: 8px; transition: all 0.3s;">
                            @if($show->thumbnail_url
                            <img src="{{ $show->thumbnail_url}}" 
                                 alt="{{ $show->title }} - Luganda Translated Series" 
                                 itemprop="image"
                                 class="img-fluid w-100" 
                                 style="aspect-ratio: 2/3; object-fit: cover;"
                                 loading="lazy">
                            @else
                            <div class="bg-secondary d-flex align-items-center justify-content-center" style="aspect-ratio: 2/3;">
                                <i class="bi bi-tv text-white" style="font-size: 3rem;"></i>
                            </div>
                            @endif
                            
                            <div class="movie-overlay" style="position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(0,0,0,0.9)); padding: 1rem 0.5rem;">
                                <h3 class="text-white mb-0 small" style="font-size: 0.875rem; font-weight: 600;">
                                    {{ Str::limit($show->title, 35) }}
                                </h3>
                                <div class="d-flex justify-content-between align-items-center mt-1">
                                    <small class="text-white-50">
                                        <i class="bi bi-collection-play-fill"></i> Series
                                    </small>
                                    @if($show->season_number && $show->episode_number)
                                    <small class="text-primary">
                                        S{{ $show->season_number }}E{{ $show->episode_number }}
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
                    <i class="bi bi-tv text-white-50" style="font-size: 4rem;"></i>
                    <p class="text-white-50 mt-3">No series found.</p>
                </div>
            </div>
            @endforelse
        </div>

        <!-- Pagination -->
        @if($series->hasPages())
        <div class="d-flex justify-content-center">
            <nav aria-label="Page navigation">
                {{ $series->links('pagination::bootstrap-5') }}
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
                    <h2 class="h4 text-white mb-4">Stream Luganda Translated TV Series</h2>
                    <p class="mb-3">
                        Discover Uganda's largest collection of TV series and shows professionally translated into Luganda. From binge-worthy dramas to hilarious comedies, thrilling crime series to captivating documentaries – every episode is available with authentic Luganda dubbing that captures the essence of the original content while making it culturally relevant for Ugandan audiences.
                    </p>
                    <p class="mb-3">
                        Each series in our collection includes all episodes with high-quality Luganda translation by expert voice actors. Whether you're catching up on the latest season of your favorite show or discovering a classic series for the first time, {{ $siteName }} makes it easy to enjoy premium TV content in your mother tongue.
                    </p>
                    <h3 class="h5 text-white mb-3 mt-4">Why Watch Series in Luganda?</h3>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i>Complete seasons with all episodes</li>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i>Professional Luganda voice acting</li>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i>HD streaming quality</li>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i>Download episodes for offline viewing</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i>New episodes added weekly</li>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i>Multiple genres available</li>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i>Resume watching on any device</li>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i>Family-friendly content ratings</li>
                            </ul>
                        </div>
                    </div>
                    <h3 class="h5 text-white mb-3 mt-4">Popular Luganda Series Categories</h3>
                    <p class="mb-3">
                        Explore drama series in Luganda, comedy shows translated to Luganda, action-packed series with Luganda dubbing, reality TV in Luganda, crime and mystery series, sci-fi and fantasy shows, romantic series, and documentary series – all professionally translated for your viewing pleasure.
                    </p>
                    <p class="mb-3">
                        Download the {{ $siteName }} app to start streaming Luganda translated TV series today. With our easy-to-use interface, you can browse by genre, search for specific shows, track your watching progress, and discover new series based on your preferences. Every episode features first-class Luganda translation that respects cultural context while delivering entertainment that resonates with Ugandan viewers.
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
                <h2 class="h4 text-white mb-2">Binge-Watch Luganda Series Today</h2>
                <p class="text-white mb-3 mb-lg-0">Get instant access to {{ number_format($totalSeries) }} TV series translated into Luganda</p>
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
.series-card {
    transition: transform 0.3s;
}
.series-link:hover .series-card {
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
