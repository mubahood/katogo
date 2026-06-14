<?php

namespace App\Http\Controllers\Api;

use App\Models\MovieModel;
use App\Models\MovieRating;
use App\Models\User;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MovieRatingController extends BaseController
{
    use ApiResponser;

    private function authUser(Request $request): ?User
    {
        return $request->user() ?? null;
    }

    /**
     * POST /api/movies/{id}/rate
     * Body: { rating: 1-5, review?: string }
     */
    public function rate(Request $request, int $id)
    {
        $user = $this->authUser($request);
        if (!$user) {
            return $this->error('Unauthenticated', 401);
        }

        try {
            $validated = $request->validate([
                'rating' => 'required|integer|min:1|max:5',
                'review' => 'nullable|string|max:1000',
            ]);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422);
        }

        $movie = MovieModel::find($id);
        if (!$movie) {
            return $this->error('Movie not found', 404);
        }

        $rating = MovieRating::updateOrCreate(
            ['user_id' => $user->id, 'movie_id' => $id],
            ['rating' => $validated['rating'], 'review' => $validated['review'] ?? null]
        );

        $this->updateAggregates($id);

        return $this->success([
            'rating'       => $rating->rating,
            'review'       => $rating->review,
            'avg_rating'   => (float) $movie->fresh()->avg_rating,
            'rating_count' => (int) $movie->fresh()->rating_count,
        ], 'Rating saved');
    }

    /**
     * DELETE /api/movies/{id}/rate
     */
    public function unrate(Request $request, int $id)
    {
        $user = $this->authUser($request);
        if (!$user) {
            return $this->error('Unauthenticated', 401);
        }

        MovieRating::where('user_id', $user->id)->where('movie_id', $id)->delete();
        $this->updateAggregates($id);

        return $this->success([], 'Rating removed');
    }

    /**
     * GET /api/movies/{id}/ratings
     * Returns paginated ratings + current user's rating if authenticated
     */
    public function index(Request $request, int $id)
    {
        $movie = MovieModel::find($id);
        if (!$movie) {
            return $this->error('Movie not found', 404);
        }

        $ratings = MovieRating::where('movie_id', $id)
            ->with(['user:id,name,avatar'])
            ->orderByDesc('updated_at')
            ->paginate(20);

        $myRating = null;
        $user = $this->authUser($request);
        if ($user) {
            $myRating = MovieRating::where('user_id', $user->id)
                ->where('movie_id', $id)
                ->first(['rating', 'review', 'updated_at']);
        }

        return $this->success([
            'avg_rating'   => (float) $movie->avg_rating,
            'rating_count' => (int) $movie->rating_count,
            'my_rating'    => $myRating,
            'ratings'      => $ratings,
        ]);
    }

    private function updateAggregates(int $movieId): void
    {
        $agg = DB::table('movie_ratings')
            ->where('movie_id', $movieId)
            ->selectRaw('AVG(rating) as avg_r, COUNT(*) as cnt')
            ->first();

        MovieModel::where('id', $movieId)->update([
            'avg_rating'   => $agg ? round((float) $agg->avg_r, 2) : 0,
            'rating_count' => $agg ? (int) $agg->cnt : 0,
        ]);
    }
}
