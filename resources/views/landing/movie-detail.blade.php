@extends('layouts.landing')

@section('title', $movie->title . ' - Watch in Luganda | ' . $siteName)
@section('description', strip_tags(Str::limit($movie->description ?? 'Watch ' . $movie->title . ' with professional Luganda translation. Stream this ' . $movie->type . ' in HD quality on ' . $siteName, 155)))
@section('keywords', $movie->title . ', ' . $movie->title . ' Luganda, watch ' . $movie->title . ' in Luganda, ' . $movie->title . ' translated, Luganda movies, Uganda streaming')

@push('meta')
<meta property="og:type" content="{{ $movie->type === 'Series' ? 'video.tv_show' : 'video.movie' }}">
<meta property="og:title" content="{{ $movie->title }} - Luganda Translated">
<meta property="og:description" content="{{ strip_tags(Str::limit($movie->description ?? 'Watch ' . $movie->title . ' in Luganda', 200)) }}">
@if($movie->thumbnail_url)
<meta property="og:image" content="{{ $movie->thumbnail_url }}">
<meta itemprop="image" content="{{ $movie->thumbnail_url }}">
@endif
<meta property="og:url" content="{{ url('/movie/' . ($movie->id)) }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $movie->title }} - Luganda">
@if($movie->thumbnail_url)
<meta name="twitter:image" content="{{ $movie->thumbnail_url }}">
@endif
<link rel="canonical" href="{{ url('/movie/' . ($movie->id)) }}">

<!-- Rich Snippets / Schema.org -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "{{ $movie->type === 'Series' ? 'TVSeries' : 'Movie' }}",
  "name": "{{ $movie->title }}",
  "description": "{{ strip_tags(Str::limit($movie->description ?? 'Watch ' . $movie->title . ' in Luganda', 250)) }}",
  @if($movie->thumbnail_url)
  "image": "{{ $movie->thumbnail_url }}",
  @endif
  @if($movie->year)
  "datePublished": "{{ $movie->year }}-01-01",
  @endif
  @if($movie->rating)
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": "{{ $movie->rating }}",
    "bestRating": "10"
  },
  @endif
  "inLanguage": "lg",
  "potentialAction": {
    "@type": "WatchAction",
    "target": {
      "@type": "EntryPoint",
      "urlTemplate": "{{ $playStoreUrl }}",
      "actionPlatform": [
        "http://schema.org/DesktopWebPlatform",
        "http://schema.org/MobileWebPlatform",
        "http://schema.org/AndroidPlatform"
      ]
    }
  }
}
</script>
@endpush

@section('content')
<!-- Hero Section with Movie Details -->
<section class="movie-hero" style="position: relative; min-height: 70vh; background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);">
    @if($movie->thumbnail_url || $movie->cover_image)
    <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; overflow: hidden;">
        <img src="{{ $movie->cover_image ?? $movie->thumbnail_url }}" 
             alt="{{ $movie->title }} Backdrop" 
             style="width: 100%; height: 100%; object-fit: cover; opacity: 0.2; filter: blur(10px);">
    </div>
    @endif
    
    <div class="container" style="position: relative; z-index: 2; padding-top: 3rem; padding-bottom: 3rem;">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('landing.index') }}" class="text-primary">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route($movie->type === 'Series' ? 'landing.series' : 'landing.movies') }}" class="text-primary">{{ $movie->type === 'Series' ? 'Series' : 'Movies' }}</a></li>
                <li class="breadcrumb-item active text-white" aria-current="page">{{ $movie->title }}</li>
            </ol>
        </nav>

        <div class="row">
            <div class="col-lg-4 col-md-5 mb-4 mb-md-0">
                <div class="movie-poster" style="border-radius: 12px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.5);">
                    @if($movie->thumbnail_url)
                    <img src="{{ $movie->thumbnail_url }}" 
                         alt="{{ $movie->title }} - Luganda Translated {{ $movie->type === 'Series' ? 'Series' : 'Movie' }}" 
                         class="img-fluid w-100"
                         style="aspect-ratio: 2/3; object-fit: cover;">
                    @else
                    <div class="bg-secondary d-flex align-items-center justify-content-center" style="aspect-ratio: 2/3;">
                        <i class="bi bi-{{ $movie->type === 'Series' ? 'tv' : 'film' }} text-white" style="font-size: 5rem;"></i>
                    </div>
                    @endif
                </div>
            </div>
            
            <div class="col-lg-8 col-md-7">
                <div class="movie-info">
                    <div class="mb-3">
                        <span class="badge bg-primary me-2" style="font-size: 0.9rem;">Luganda Translation</span>
                        @if($movie->type)
                        <span class="badge bg-secondary" style="font-size: 0.9rem;">{{ ucfirst($movie->type) }}</span>
                        @endif
                    </div>
                    
                    <h1 class="display-4 fw-bold text-white mb-3">{{ $movie->title }}</h1>
                    
                    <div class="d-flex flex-wrap gap-3 mb-4 text-white-50">
                        @if($movie->year)
                        <div>
                            <i class="bi bi-calendar3 me-1"></i>{{ $movie->year }}
                        </div>
                        @endif
                        @if($movie->rating)
                        <div>
                            <i class="bi bi-star-fill text-warning me-1"></i>{{ number_format($movie->rating, 1) }}/10
                        </div>
                        @endif
                        @if($movie->duration)
                        <div>
                            <i class="bi bi-clock me-1"></i>{{ $movie->duration }} min
                        </div>
                        @endif
                        <div>
                            <i class="bi bi-translate me-1"></i>Luganda Audio
                        </div>
                    </div>

                    @if($movie->description)
                    <div class="movie-description mb-4">
                        <h2 class="h5 text-white mb-3">Synopsis</h2>
                        <p class="text-white-50" style="line-height: 1.8;">
                            {!! nl2br(e(strip_tags($movie->description))) !!}
                        </p>
                    </div>
                    @endif

                    @if($movie->director || $movie->cast || $movie->genre)
                    <div class="movie-metadata mb-4">
                        @if($movie->genre)
                        <div class="mb-2">
                            <strong class="text-white">Genre:</strong>
                            <span class="text-white-50">{{ $movie->genre }}</span>
                        </div>
                        @endif
                        @if($movie->director)
                        <div class="mb-2">
                            <strong class="text-white">Director:</strong>
                            <span class="text-white-50">{{ $movie->director }}</span>
                        </div>
                        @endif
                        @if($movie->cast)
                        <div class="mb-2">
                            <strong class="text-white">Cast:</strong>
                            <span class="text-white-50">{{ $movie->cast }}</span>
                        </div>
                        @endif
                    </div>
                    @endif

                    <!-- Main CTA -->
                    <div class="mt-4">
                        <a href="{{ $playStoreUrl }}" 
                           target="_blank" 
                           rel="noopener"
                           class="btn btn-primary btn-lg px-5 py-3"
                           style="border-radius: 12px; font-size: 1.1rem;">
                            <i class="bi bi-google-play me-2"></i>Download App to Watch
                        </a>
                        <p class="text-white-50 small mt-3 mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            Free to download • HD Quality • Luganda Translation • Offline Download
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Episodes Section (if series) -->
@if($movie->type === 'Series' && count($episodes) > 0)
<section class="py-5 bg-dark">
    <div class="container">
        <h2 class="h3 text-white mb-4">
            <i class="bi bi-collection-play text-primary me-2"></i>Episodes - Luganda Translation
        </h2>
        
        @php
            $episodesBySeason = $episodes->groupBy('season');
        @endphp
        
        @foreach($episodesBySeason as $season => $seasonEpisodes)
        <div class="season-section mb-4">
            <h3 class="h5 text-white mb-3">Season {{ $season }}</h3>
            <div class="row g-3">
                @foreach($seasonEpisodes as $episode)
                <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="episode-card p-3" style="background: rgba(255,255,255,0.05); border-radius: 8px; border: 1px solid rgba(255,255,255,0.1);">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h4 class="h6 text-white mb-0">Episode {{ $episode->episode }}</h4>
                            <span class="badge bg-primary small">Luganda</span>
                        </div>
                        <p class="text-white-50 small mb-0">
                            {{ Str::limit($episode->name ?? $episode->title ?? 'Episode ' . $episode->episode, 50) }}
                        </p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach

        <div class="alert alert-info mt-4">
            <i class="bi bi-info-circle me-2"></i>
            <strong>Watch All Episodes:</strong> Download {{ $siteName }} app to stream all episodes with Luganda translation in HD quality.
        </div>
    </div>
</section>
@endif

<!-- Related Content -->
@if(count($relatedMovies) > 0)
<section class="py-5" style="background: #16213e;">
    <div class="container">
        <h2 class="h3 text-white mb-4">
            <i class="bi bi-{{ $movie->type === 'Series' ? 'tv' : 'film' }} text-primary me-2"></i>
            More Luganda {{ $movie->type === 'Series' ? 'Series' : 'Movies' }} You May Like
        </h2>
        <div class="row g-3">
            @foreach($relatedMovies as $related)
            <div class="col-lg-2 col-md-3 col-sm-4 col-6">
                <a href="{{ route('landing.movie.detail', $related->id) }}" class="text-decoration-none">
                    <div class="movie-card" style="position: relative; overflow: hidden; border-radius: 8px; transition: transform 0.3s;">
                        @if($related->thumbnail)
                        <img src="{{ $related->thumbnail }}" 
                             alt="{{ $related->name }} - Luganda" 
                             class="img-fluid w-100" 
                             style="aspect-ratio: 2/3; object-fit: cover;"
                             loading="lazy">
                        @else
                        <div class="bg-secondary d-flex align-items-center justify-content-center" style="aspect-ratio: 2/3;">
                            <i class="bi bi-film text-white" style="font-size: 3rem;"></i>
                        </div>
                        @endif
                        <div style="position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(0,0,0,0.9)); padding: 1rem 0.5rem;">
                            <h3 class="text-white mb-0 small">{{ Str::limit($related->name, 25) }}</h3>
                        </div>
                    </div>
                </a>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

<!-- SEO Content Section -->
<section class="py-5 bg-dark">
    <div class="container">
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <article class="text-white-50">
                    <h2 class="h4 text-white mb-3">Watch {{ $movie->title }} with Luganda Translation</h2>
                    <p class="mb-3">
                        Experience {{ $movie->title }} like never before with professional Luganda translation. This {{ $movie->type === 'Series' ? 'series' : 'movie' }} has been expertly translated and dubbed by experienced Luganda voice actors who bring authentic cultural context to every scene. Whether you're a fan of the original or discovering it for the first time, watching {{ $movie->title }} in Luganda offers a unique and enjoyable viewing experience.
                    </p>
                    <p class="mb-3">
                        {{ $siteName }} is proud to offer {{ $movie->title }} in high-definition quality with crystal-clear Luganda audio. Our professional translation team has ensured that every dialogue, joke, and emotional moment is accurately conveyed in Luganda while maintaining the essence of the original content. Download our app today to stream {{ $movie->title }} and thousands of other titles translated into Luganda.
                    </p>
                    @if($movie->type === 'Series')
                    <p class="mb-3">
                        All episodes of {{ $movie->title }} are available with complete Luganda translation. Binge-watch the entire series, pick up where you left off, or rewatch your favorite episodes – all in your preferred language. With offline download capabilities, you can enjoy {{ $movie->title }} even without an internet connection.
                    </p>
                    @endif
                    <h3 class="h5 text-white mt-4 mb-3">Why Watch {{ $movie->title }} in Luganda?</h3>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i>Professional Luganda dubbing by expert voice actors</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i>HD streaming quality optimized for Uganda</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i>Cultural adaptation for Ugandan audiences</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i>Download for offline viewing anytime</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i>Watch on any Android device</li>
                    </ul>
                </article>
            </div>
        </div>
    </div>
</section>

<!-- Final CTA -->
<section class="py-5 bg-primary">
    <div class="container text-center">
        <h2 class="h3 text-white mb-3">Ready to Watch {{ $movie->title }} in Luganda?</h2>
        <p class="text-white mb-4">Download {{ $siteName }} app now and start streaming instantly</p>
        <a href="{{ $playStoreUrl }}" target="_blank" rel="noopener" class="btn btn-dark btn-lg px-5 py-3">
            <i class="bi bi-download me-2"></i>Download Free App
        </a>
    </div>
</section>

<style>
.movie-card:hover {
    transform: scale(1.05);
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
