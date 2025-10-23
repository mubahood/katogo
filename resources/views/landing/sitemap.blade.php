@php echo '<?xml version="1.0" encoding="UTF-8"?>'; @endphp
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"
    xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">

    <!-- Homepage -->
    <url>
        <loc>{{ url('/') }}</loc>
        <lastmod>{{ now()->toAtomString() }}</lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>

    <!-- Movies Listing -->
    <url>
        <loc>{{ route('landing.movies') }}</loc>
        <lastmod>{{ now()->toAtomString() }}</lastmod>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>

    <!-- Series Listing -->
    <url>
        <loc>{{ route('landing.series') }}</loc>
        <lastmod>{{ now()->toAtomString() }}</lastmod>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>

    <!-- Individual Movies and Series -->
    @foreach ($movies as $movie)
        <url>
            <loc>{{ route('landing.movie.detail', $movie->id) }}</loc>
            <lastmod>{{ $movie->updated_at->toAtomString() }}</lastmod>
            <changefreq>weekly</changefreq>
            <priority>0.8</priority>
            @if ($movie->thumbnail_url)
                <image:image>
                    <image:loc>{{ e($movie->thumbnail_url) }}</image:loc>
                    <image:title>{{ e($movie->title) }} - Luganda Translated
                        {{ $movie->type === 'Series' ? 'Series' : 'Movie' }}</image:title>
                    <image:caption>Watch {{ e($movie->title) }} with professional Luganda translation on LugaFlix
                    </image:caption>
                </image:image>
            @endif
            @if ($movie->type !== 'Series')
                <video:video>
                    <video:title>{{ e($movie->title) }} - Luganda Translation</video:title>
                    <video:description>{{ e($movie->description ? strip_tags(Str::limit($movie->description, 200)) : 'Watch ' . $movie->title . ' with professional Luganda translation. Stream unlimited movies and series with HD quality on LugaFlix.') }}</video:description>
                    @if ($movie->thumbnail_url)
                        <video:thumbnail_loc>{{ e($movie->thumbnail_url) }}</video:thumbnail_loc>
                    @endif
                    @php
                        $publicationYear = $movie->year && is_numeric($movie->year) && $movie->year >= 1900 && $movie->year <= date('Y') ? intval($movie->year) : intval(date('Y'));
                    @endphp
                    <video:publication_date>{{ sprintf('%04d-01-01T00:00:00+00:00', $publicationYear) }}</video:publication_date>
                    <video:duration>{{ ($movie->duration && is_numeric($movie->duration) && $movie->duration > 0) ? intval($movie->duration * 60) : 7200 }}</video:duration>
                    <video:rating>{{ ($movie->rating && is_numeric($movie->rating) && $movie->rating >= 0 && $movie->rating <= 10) ? number_format(min(5.0, floatval($movie->rating) / 2), 1) : '4.5' }}</video:rating>
                    <video:family_friendly>yes</video:family_friendly>
                    <video:requires_subscription>yes</video:requires_subscription>
                    <video:platform relationship="allow">web mobile tv</video:platform>
                </video:video>
            @endif
        </url>
    @endforeach

    <!-- Static Pages -->
    @if (Route::has('landing.about'))
        <url>
            <loc>{{ route('landing.about') }}</loc>
            <changefreq>monthly</changefreq>
            <priority>0.5</priority>
        </url>
    @endif

    @if (Route::has('landing.contact'))
        <url>
            <loc>{{ route('landing.contact') }}</loc>
            <changefreq>monthly</changefreq>
            <priority>0.5</priority>
        </url>
    @endif

    @if (Route::has('landing.privacy-policy'))
        <url>
            <loc>{{ route('landing.privacy-policy') }}</loc>
            <changefreq>monthly</changefreq>
            <priority>0.3</priority>
        </url>
    @endif

    @if (Route::has('landing.terms-of-service'))
        <url>
            <loc>{{ route('landing.terms-of-service') }}</loc>
            <changefreq>monthly</changefreq>
            <priority>0.3</priority>
        </url>
    @endif

</urlset>
