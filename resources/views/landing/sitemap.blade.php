{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
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
                    @if ($movie->description)
                        <video:description>{{ e(strip_tags(Str::limit($movie->description, 200))) }}
                        </video:description>
                    @endif
                    @if ($movie->thumbnail_url)
                        <video:thumbnail_loc>{{ e($movie->thumbnail_url) }}</video:thumbnail_loc>
                    @endif
                    @if ($movie->year)
                        <video:publication_date>{{ $movie->year }}-01-01T00:00:00+00:00</video:publication_date>
                    @endif
                    @if ($movie->duration)
                        <video:duration>{{ $movie->duration * 60 }}</video:duration>
                    @endif
                    @if ($movie->rating)
                        <video:rating>{{ $movie->rating }}</video:rating>
                    @endif
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
