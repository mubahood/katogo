<?php

namespace Tests\Feature;

use App\Models\MovieModel;
use App\Models\MovieView;
use App\Models\MovieDownload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicCrudControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: random_movie returns recently viewed movie when available
     */
    public function test_random_movie_returns_recently_viewed()
    {
        // Create a movie
        $movie = MovieModel::create([
            'title' => 'Recently Viewed Movie',
            'status' => 'Active',
            'type' => 'Movie',
            'url' => 'https://example.com/video.mp4',
        ]);

        // Create a view record (>60 seconds of progress)
        MovieView::create([
            'movie_model_id' => $movie->id,
            'status' => 'Active',
            'progress' => 120,  // >60 seconds
            'created_at' => now()->subDays(15),
        ]);

        $response = $this->getJson('api/random_movie');

        $response->assertStatus(200);
        $response->assertJsonMissing(['status' => 'error']);
        $response->assertJsonPath('data.id', $movie->id);
        $response->assertJsonPath('data.video_url', 'https://example.com/video.mp4');
    }

    /**
     * Test: random_movie returns recently downloaded movie
     */
    public function test_random_movie_returns_recently_downloaded()
    {
        // Create a movie
        $movie = MovieModel::create([
            'title' => 'Recently Downloaded Movie',
            'status' => 'Active',
            'type' => 'Movie',
            'url' => 'https://example.com/video.mp4',
        ]);

        // Create a download record
        MovieDownload::create([
            'movie_model_id' => $movie->id,
            'created_at' => now()->subDays(10),
        ]);

        $response = $this->getJson('api/random_movie');

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $movie->id);
    }

    /**
     * Test: random_movie prefers Firebase URL over regular URL
     */
    public function test_random_movie_prefers_firebase_url()
    {
        $movie = MovieModel::create([
            'title' => 'Test Movie',
            'status' => 'Active',
            'type' => 'Movie',
            'url' => 'https://example.com/video.mp4',
            'firebase_video_url' => 'https://firebase.com/video.mp4',
        ]);

        MovieView::create([
            'movie_model_id' => $movie->id,
            'status' => 'Active',
            'progress' => 120,
            'created_at' => now(),
        ]);

        $response = $this->getJson('api/random_movie');

        $response->assertStatus(200);
        $response->assertJsonPath('data.video_url', 'https://firebase.com/video.mp4');
    }

    /**
     * Test: random_movie ignores movies with insufficient progress
     */
    public function test_random_movie_ignores_low_progress_views()
    {
        // Create movie with low progress view
        $lowProgressMovie = MovieModel::create([
            'title' => 'Low Progress Movie',
            'status' => 'Active',
            'type' => 'Movie',
            'url' => 'https://example.com/video1.mp4',
        ]);

        MovieView::create([
            'movie_model_id' => $lowProgressMovie->id,
            'status' => 'Active',
            'progress' => 30,  // <60 seconds (ignored)
            'created_at' => now(),
        ]);

        // Create fallback movie (any active movie)
        $fallbackMovie = MovieModel::create([
            'title' => 'Fallback Movie',
            'status' => 'Active',
            'type' => 'Movie',
            'url' => 'https://example.com/video2.mp4',
        ]);

        $response = $this->getJson('api/random_movie');

        $response->assertStatus(200);
        $response->assertJsonMissing(['title' => 'Low Progress Movie']);
    }

    /**
     * Test: random_movie never returns series
     */
    public function test_random_movie_never_returns_series()
    {
        // Create a series
        $series = MovieModel::create([
            'title' => 'Test Series',
            'status' => 'Active',
            'type' => 'Series',
            'url' => 'https://example.com/series.mp4',
        ]);

        MovieView::create([
            'movie_model_id' => $series->id,
            'status' => 'Active',
            'progress' => 120,
            'created_at' => now(),
        ]);

        // Create a fallback movie
        $movie = MovieModel::create([
            'title' => 'Test Movie',
            'status' => 'Active',
            'type' => 'Movie',
            'url' => 'https://example.com/video.mp4',
        ]);

        $response = $this->getJson('api/random_movie');

        $response->assertStatus(200);
        $response->assertJsonMissing(['type' => 'Series']);
        $response->assertJsonPath('data.id', $movie->id);
    }

    /**
     * Test: random_movie returns 404 when no movies available
     */
    public function test_random_movie_returns_404_when_no_movies()
    {
        $response = $this->getJson('api/random_movie');

        $response->assertStatus(404);
        $response->assertJsonPath('status', 'error');
    }

    /**
     * Test: random_movie requires playable video URL
     */
    public function test_random_movie_requires_video_url()
    {
        // Create movie without URL
        $movieNoUrl = MovieModel::create([
            'title' => 'No URL Movie',
            'status' => 'Active',
            'type' => 'Movie',
            'url' => '',  // Empty URL
        ]);

        // Create movie with URL
        $movieWithUrl = MovieModel::create([
            'title' => 'With URL Movie',
            'status' => 'Active',
            'type' => 'Movie',
            'url' => 'https://example.com/video.mp4',
        ]);

        $response = $this->getJson('api/random_movie');

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $movieWithUrl->id);
    }

    /**
     * Test: random_movie ignores old views (>30 days)
     */
    public function test_random_movie_ignores_old_views()
    {
        // Old view (>30 days)
        $oldMovie = MovieModel::create([
            'title' => 'Old Movie',
            'status' => 'Active',
            'type' => 'Movie',
            'url' => 'https://example.com/old.mp4',
        ]);

        MovieView::create([
            'movie_model_id' => $oldMovie->id,
            'status' => 'Active',
            'progress' => 120,
            'created_at' => now()->subDays(31),  // >30 days
        ]);

        // Recent movie
        $recentMovie = MovieModel::create([
            'title' => 'Recent Movie',
            'status' => 'Active',
            'type' => 'Movie',
            'url' => 'https://example.com/recent.mp4',
        ]);

        $response = $this->getJson('api/random_movie');

        $response->assertStatus(200);
        $response->assertJsonMissing(['title' => 'Old Movie']);
    }

    /**
     * Test: random_movie ignores inactive movies
     */
    public function test_random_movie_ignores_inactive_movies()
    {
        // Inactive movie that was downloaded
        $inactiveMovie = MovieModel::create([
            'title' => 'Inactive Movie',
            'status' => 'Inactive',
            'type' => 'Movie',
            'url' => 'https://example.com/inactive.mp4',
        ]);

        MovieDownload::create([
            'movie_model_id' => $inactiveMovie->id,
            'created_at' => now(),
        ]);

        // Active fallback movie
        $activeMovie = MovieModel::create([
            'title' => 'Active Movie',
            'status' => 'Active',
            'type' => 'Movie',
            'url' => 'https://example.com/active.mp4',
        ]);

        $response = $this->getJson('api/random_movie');

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'Active');
    }

    /**
     * Test: response includes all required fields
     */
    public function test_random_movie_response_structure()
    {
        $movie = MovieModel::create([
            'title' => 'Test Movie',
            'status' => 'Active',
            'type' => 'Movie',
            'url' => 'https://example.com/video.mp4',
            'description' => 'Test description',
            'year' => 2020,
            'rating' => 8.5,
            'genre' => 'Action',
            'thumbnail_url' => 'https://example.com/thumb.jpg',
            'image_url' => 'https://example.com/image.jpg',
            'category' => 'Action',
            'actor' => 'John Doe',
            'vj' => 'VJ Name'
        ]);

        $response = $this->getJson('api/random_movie');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id', 'title', 'description', 'video_url',
                'thumbnail_url', 'image_url', 'year', 'rating',
                'genre', 'type', 'category', 'actor', 'vj'
            ]
        ]);
    }
}
