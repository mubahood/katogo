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
<meta property="og:image:secure_url" content="{{ $movie->thumbnail_url }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="{{ $movie->title }} - Luganda Translated">
<meta itemprop="image" content="{{ $movie->thumbnail_url }}">
@else
<meta property="og:image" content="{{ asset('assets/images/logo.png') }}">
<meta property="og:image:secure_url" content="{{ asset('assets/images/logo.png') }}">
@endif
<meta property="og:url" content="{{ url('/movie/' . $movie->id) }}">
<meta property="og:site_name" content="{{ $siteName }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $movie->title }} - Luganda">
<meta name="twitter:description" content="{{ strip_tags(Str::limit($movie->description ?? 'Watch ' . $movie->title . ' in Luganda', 200)) }}">
@if($movie->thumbnail_url)
<meta name="twitter:image" content="{{ $movie->thumbnail_url }}">
@else
<meta name="twitter:image" content="{{ asset('assets/images/logo.png') }}">
@endif
<link rel="canonical" href="{{ url('/movie/' . $movie->id) }}">

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
<!-- Compact Mobile-First Hero Section -->
<section class="movie-hero py-3 py-md-4" style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);">
    <div class="container">
        <!-- Compact Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-2">
            <ol class="breadcrumb mb-0" style="font-size: 0.75rem;">
                <li class="breadcrumb-item"><a href="{{ route('landing.index') }}" class="text-primary">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route($movie->type === 'Series' ? 'landing.series' : 'landing.movies') }}" class="text-primary">{{ $movie->type === 'Series' ? 'Series' : 'Movies' }}</a></li>
                <li class="breadcrumb-item active text-white" aria-current="page">{{ Str::limit($movie->title, 30) }}</li>
            </ol>
        </nav>

        <div class="row g-2 g-md-3">
            <!-- Poster Column -->
            <div class="col-4 col-md-3">
                <div class="movie-poster" style="border-radius: 6px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.4);">
                    @if($movie->thumbnail_url)
                    <img src="{{ $movie->thumbnail_url }}" 
                         alt="{{ $movie->title }} - Luganda" 
                         class="img-fluid w-100"
                         style="aspect-ratio: 2/3; object-fit: cover;">
                    @else
                    <div class="bg-secondary d-flex align-items-center justify-content-center" style="aspect-ratio: 2/3;">
                        <i class="bi bi-{{ $movie->type === 'Series' ? 'tv' : 'film' }} text-white" style="font-size: 2rem;"></i>
                    </div>
                    @endif
                </div>
            </div>
            
            <!-- Info Column -->
            <div class="col-8 col-md-9">
                <div class="movie-info">
                    <!-- Badges -->
                    <div class="mb-2">
                        <span class="badge bg-primary me-1" style="font-size: 0.65rem;">Luganda</span>
                        @if($movie->type)
                        <span class="badge bg-secondary" style="font-size: 0.65rem;">{{ $movie->type }}</span>
                        @endif
                    </div>
                    
                    <!-- Title -->
                    <h1 class="h4 h5-md fw-bold text-white mb-2">{{ $movie->title }}</h1>
                    
                    <!-- Meta Info -->
                    <div class="d-flex flex-wrap gap-2 mb-2 text-white-50" style="font-size: 0.7rem;">
                        @if($movie->year)
                        <div><i class="bi bi-calendar3 me-1"></i>{{ $movie->year }}</div>
                        @endif
                        @if($movie->rating)
                        <div><i class="bi bi-star-fill text-warning me-1"></i>{{ number_format($movie->rating, 1) }}</div>
                        @endif
                        @if($movie->duration)
                        <div><i class="bi bi-clock me-1"></i>{{ $movie->duration }}m</div>
                        @endif
                    </div>

                    <!-- Action Buttons - Mobile Optimized -->
                    <div class="d-flex flex-column flex-sm-row gap-1 gap-sm-2 mt-2">
                        <a href="{{ $playStoreUrl }}" target="_blank" rel="noopener" class="btn btn-primary btn-sm px-2 py-1" style="font-size: 0.75rem;">
                            <i class="bi bi-play-fill me-1"></i>Watch Now
                        </a>
                        <button onclick="shareMovie()" class="btn btn-outline-light btn-sm px-2 py-1" style="font-size: 0.75rem;">
                            <i class="bi bi-share me-1"></i>Share
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Description - Collapsible on mobile -->
        @if($movie->description)
        <div class="mt-3 mt-md-4">
            <div class="collapse" id="movieDescription">
                <p class="text-white-50 mb-0" style="font-size: 0.8rem; line-height: 1.5;">
                    {{ Str::limit(strip_tags($movie->description), 300) }}
                </p>
            </div>
            <button class="btn btn-link text-primary p-0 mt-1" type="button" data-bs-toggle="collapse" data-bs-target="#movieDescription" style="font-size: 0.75rem;">
                <span class="when-collapsed">Read more <i class="bi bi-chevron-down"></i></span>
                <span class="when-expanded">Read less <i class="bi bi-chevron-up"></i></span>
            </button>
        </div>
        @endif

        <!-- Additional Info - Compact -->
        @if($movie->genre || $movie->director || $movie->stars)
        <div class="mt-3 mt-md-4" style="font-size: 0.75rem;">
            @if($movie->genre)
            <div class="mb-1"><strong class="text-white">Genre:</strong> <span class="text-white-50">{{ $movie->genre }}</span></div>
            @endif
            @if($movie->director)
            <div class="mb-1"><strong class="text-white">Director:</strong> <span class="text-white-50">{{ $movie->director }}</span></div>
            @endif
            @if($movie->stars)
            <div class="mb-1"><strong class="text-white">Cast:</strong> <span class="text-white-50">{{ Str::limit($movie->stars, 100) }}</span></div>
            @endif
        </div>
        @endif
    </div>
</section>

<!-- Episodes Section (if series) - Compact -->
@if($movie->type === 'Series' && count($episodes) > 0)
<section class="py-3 py-md-4 bg-dark">
    <div class="container">
        <h2 class="h6 h5-md text-white mb-2 mb-md-3">
            <i class="bi bi-collection-play text-primary me-1"></i>Episodes
        </h2>
        
        @php
            $episodesBySeason = $episodes->groupBy('season_number');
        @endphp
        
        @foreach($episodesBySeason as $season => $seasonEpisodes)
        <div class="season-section mb-3">
            <h3 class="text-white mb-2" style="font-size: 0.85rem;">Season {{ $season }}</h3>
            <div class="row g-2">
                @foreach($seasonEpisodes as $episode)
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="episode-card p-2" style="background: rgba(255,255,255,0.05); border-radius: 6px; border: 1px solid rgba(255,255,255,0.1);">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <h4 class="text-white mb-0" style="font-size: 0.75rem;">S{{ $episode->season_number }}E{{ $episode->episode_number }}</h4>
                            <span class="badge bg-primary" style="font-size: 0.6rem;">Luganda</span>
                        </div>
                        <p class="text-white-50 small mb-0" style="font-size: 0.7rem;">
                            {{ Str::limit($episode->title ?? 'Episode ' . $episode->episode_number, 35) }}
                        </p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach

        <div class="alert alert-info mt-3 py-2 px-3" style="font-size: 0.75rem;">
            <i class="bi bi-info-circle me-1"></i>
            Download {{ $siteName }} app to watch all episodes
        </div>
    </div>
</section>
@endif

<!-- Related Content - Compact -->
@if(count($relatedMovies) > 0)
<section class="py-3 py-md-4" style="background: #16213e;">
    <div class="container">
        <h2 class="h6 h5-md text-white mb-2 mb-md-3">
            <i class="bi bi-{{ $movie->type === 'Series' ? 'tv' : 'film' }} text-primary me-1"></i>
            More Like This
        </h2>
        <div class="row g-2">
            @foreach($relatedMovies as $related)
            <div class="col-6 col-md-4 col-lg-2">
                <a href="{{ route('landing.movie.detail', $related->id) }}" class="text-decoration-none">
                    <div class="movie-card" style="position: relative; overflow: hidden; border-radius: 6px; transition: transform 0.3s;">
                        @if($related->thumbnail_url)
                        <img src="{{ $related->thumbnail_url }}" 
                             alt="{{ $related->title }} - Luganda" 
                             class="img-fluid w-100" 
                             style="aspect-ratio: 2/3; object-fit: cover;"
                             loading="lazy">
                        @else
                        <div class="bg-secondary d-flex align-items-center justify-content-center" style="aspect-ratio: 2/3;">
                            <i class="bi bi-film text-white" style="font-size: 2rem;"></i>
                        </div>
                        @endif
                        <div style="position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(0,0,0,0.9)); padding: 0.4rem;">
                            <h3 class="text-white mb-0" style="font-size: 0.7rem;">{{ Str::limit($related->title, 20) }}</h3>
                        </div>
                    </div>
                </a>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

<!-- Compact SEO Content -->
<section class="py-3 py-md-4 bg-dark">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <article class="text-white-50" style="font-size: 0.8rem;">
                    <h2 class="h6 h5-md text-white mb-2">Watch {{ $movie->title }} in Luganda</h2>
                    <p class="mb-2" style="line-height: 1.5;">
                        Experience {{ $movie->title }} with professional Luganda translation. This {{ $movie->type === 'Series' ? 'series' : 'movie' }} features expert dubbing by Luganda voice actors. Download {{ $siteName }} app to stream {{ $movie->title }} and thousands of titles in HD quality.
                    </p>
                    <ul class="list-unstyled mb-0" style="font-size: 0.75rem;">
                        <li class="mb-1"><i class="bi bi-check-circle-fill text-primary me-1"></i>Professional Luganda dubbing</li>
                        <li class="mb-1"><i class="bi bi-check-circle-fill text-primary me-1"></i>HD streaming optimized for Uganda</li>
                        <li class="mb-1"><i class="bi bi-check-circle-fill text-primary me-1"></i>Offline download available</li>
                    </ul>
                </article>
            </div>
        </div>
    </div>
</section>

<!-- Compact Final CTA -->
<section class="py-3 py-md-4 bg-primary">
    <div class="container text-center">
        <h2 class="h6 h5-md text-white mb-2">Watch {{ $movie->title }} Now</h2>
        <p class="text-white mb-2" style="font-size: 0.8rem;">Download free app and start streaming</p>
        <a href="{{ $playStoreUrl }}" target="_blank" rel="noopener" class="btn btn-dark btn-sm px-3 py-2">
            <i class="bi bi-download me-1"></i>Download App
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
.when-collapsed .when-expanded,
.when-expanded .when-collapsed {
    display: none;
}
.collapsed .when-collapsed {
    display: inline;
}
.collapsed .when-expanded {
    display: none;
}
:not(.collapsed) .when-collapsed {
    display: none;
}
:not(.collapsed) .when-expanded {
    display: inline;
}
</style>

<script>
function shareMovie() {
    const title = "{{ $movie->title }}";
    const appName = "{{ $siteName }}";
    const appLink = "{{ $playStoreUrl }}";
    const movieUrl = "{{ url('/movie/' . $movie->id) }}";
    const shareText = `Watch "${title}" on ${appName} now. Download the app: ${appLink}\n\nView details: ${movieUrl}`;
    
    // Check if Web Share API is supported
    if (navigator.share) {
        navigator.share({
            title: `${title} - ${appName}`,
            text: shareText,
            url: movieUrl
        }).then(() => {
            console.log('Shared successfully');
        }).catch((error) => {
            console.log('Error sharing:', error);
            fallbackShare(shareText);
        });
    } else {
        fallbackShare(shareText);
    }
}

function fallbackShare(text) {
    // Fallback: Copy to clipboard
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            alert('Link copied to clipboard! Share it anywhere.');
        }).catch(() => {
            promptShare(text);
        });
    } else {
        promptShare(text);
    }
}

function promptShare(text) {
    // Final fallback: Prompt user
    const dummy = document.createElement('textarea');
    document.body.appendChild(dummy);
    dummy.value = text;
    dummy.select();
    document.execCommand('copy');
    document.body.removeChild(dummy);
    alert('Link copied to clipboard! Share it anywhere.');
}
</script>
@endsection
