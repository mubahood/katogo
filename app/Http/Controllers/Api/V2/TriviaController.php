<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\TriviaQuestion;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ═══════════════════════════════════════════════════════════════
 *  V2 Trivia API Controller
 * ═══════════════════════════════════════════════════════════════
 *
 *  Endpoints:
 *    GET  /api/v2/trivia/version    – Check current question bank version
 *    GET  /api/v2/trivia/questions  – Fetch all questions (or since version)
 *    GET  /api/v2/trivia/stats      – Category/difficulty stats
 * ═══════════════════════════════════════════════════════════════
 */
class TriviaController extends Controller
{
    use ApiResponser;

    /**
     * GET /api/v2/trivia/version
     * Returns current version + total count so client can decide whether to sync.
     */
    public function version()
    {
        $maxVersion = TriviaQuestion::active()->max('version') ?? 1;
        $totalCount = TriviaQuestion::active()->count();

        return $this->success([
            'version'     => (int) $maxVersion,
            'total_count' => (int) $totalCount,
        ], 'Trivia version info.');
    }

    /**
     * GET /api/v2/trivia/questions?since_version=0&page=1&per_page=500
     * Returns questions newer than `since_version` (0 = all).
     * Paginated to avoid huge payloads.
     */
    public function questions(Request $request)
    {
        $sinceVersion = (int) $request->get('since_version', 0);
        $perPage      = min((int) $request->get('per_page', 500), 1000);

        $query = TriviaQuestion::active()
            ->select([
                'id', 'question', 'difficulty', 'category', 'format',
                'correct_answer', 'wrong_answers', 'hint', 'image_url',
                'points', 'timer_seconds', 'version',
            ])
            ->orderBy('id');

        if ($sinceVersion > 0) {
            $query->newerThan($sinceVersion);
        }

        $paginated = $query->paginate($perPage);

        Log::info("[V2:trivia] since_version={$sinceVersion} page={$paginated->currentPage()} total={$paginated->total()}");

        return $this->success([
            'items'      => $paginated->items(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ], 'Trivia questions retrieved.');
    }

    /**
     * GET /api/v2/trivia/stats
     * Returns breakdown by difficulty and category.
     */
    public function stats()
    {
        $byDifficulty = TriviaQuestion::active()
            ->select('difficulty', DB::raw('COUNT(*) as count'))
            ->groupBy('difficulty')
            ->pluck('count', 'difficulty');

        $byCategory = TriviaQuestion::active()
            ->select('category', DB::raw('COUNT(*) as count'))
            ->groupBy('category')
            ->pluck('count', 'category');

        return $this->success([
            'by_difficulty' => $byDifficulty,
            'by_category'   => $byCategory,
        ], 'Trivia stats.');
    }
}
