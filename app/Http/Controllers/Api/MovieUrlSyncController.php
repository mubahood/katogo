<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MovieModel;
use App\Models\MovieVideoURLChange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives URL-change pushes from Hetzner and applies them to this server's movie_models.
 * Endpoint: POST /api/movie-url-sync
 * Auth:     X-Url-Sync-Secret header (shared secret)
 */
class MovieUrlSyncController extends Controller
{
    public function receive(Request $request): JsonResponse
    {
        // ── Auth ──────────────────────────────────────────────────────────────
        $secret = config('services.url_sync.secret');
        if (!$secret || $request->header('X-Url-Sync-Secret') !== $secret) {
            Log::warning('[MovieUrlSync] Unauthorized push attempt from ' . $request->ip());
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // ── Parse payload ─────────────────────────────────────────────────────
        $data = $request->json()->all();

        // Accept both single change and batched array
        $changes = isset($data['changes']) ? $data['changes'] : [$data];

        if (empty($changes)) {
            return response()->json(['error' => 'No changes provided'], 422);
        }

        $results  = [];
        $applied  = 0;
        $skipped  = 0;
        $errored  = 0;

        foreach ($changes as $item) {
            $result = $this->applyChange($item);
            $results[] = $result;
            match ($result['status']) {
                'applied' => $applied++,
                'skipped' => $skipped++,
                default   => $errored++,
            };
        }

        $overallStatus = $errored > 0 ? 'partial' : 'ok';

        Log::info("[MovieUrlSync] Batch processed: {$applied} applied, {$skipped} skipped, {$errored} errored.");

        return response()->json([
            'status'  => $overallStatus,
            'applied' => $applied,
            'skipped' => $skipped,
            'errored' => $errored,
            'results' => $results,
        ]);
    }

    private function applyChange(array $item): array
    {
        $movieId  = (int) ($item['movie_model_id'] ?? 0);
        $newUrl   = trim((string) ($item['new_url'] ?? ''));
        $source   = $item['source'] ?? MovieVideoURLChange::SOURCE_API_PUSH;
        $remoteId = $item['change_id'] ?? null; // ID on the Hetzner side

        if (!$movieId || !$newUrl) {
            return [
                'movie_model_id' => $movieId,
                'status'  => 'error',
                'message' => 'Missing movie_model_id or new_url',
            ];
        }

        try {
            $movie = MovieModel::find($movieId);

            if (!$movie) {
                return [
                    'movie_model_id' => $movieId,
                    'status'  => 'skipped',
                    'message' => "Movie #{$movieId} not found on this server",
                ];
            }

            // Idempotency: if the URL is already set to the new value, skip
            if ($movie->url === $newUrl) {
                return [
                    'movie_model_id' => $movieId,
                    'status'  => 'skipped',
                    'message' => 'URL already up to date',
                ];
            }

            $oldUrl = $movie->url;

            // Apply the URL change
            $movie->url = $newUrl;
            $movie->save();

            // Record the change on this server (origin side)
            MovieVideoURLChange::create([
                'movie_model_id'    => $movieId,
                'movie_title'       => $movie->title ?? $movie->name ?? null,
                'old_url'           => $oldUrl,
                'new_url'           => $newUrl,
                'source'            => MovieVideoURLChange::SOURCE_API_PUSH,
                'status'            => MovieVideoURLChange::STATUS_APPLIED,
                'local_status'      => 'applied',
                'remote_status'     => MovieVideoURLChange::REMOTE_SKIPPED, // origin doesn't push further
                'local_applied_at'  => now(),
                'remote_applied_at' => now(),
                'transfer_id'       => $item['transfer_id'] ?? null,
                'error_message'     => null,
            ]);

            Log::info("[MovieUrlSync] Movie #{$movieId} URL updated.", [
                'old' => substr($oldUrl ?? '', 0, 80),
                'new' => substr($newUrl, 0, 80),
            ]);

            return [
                'movie_model_id' => $movieId,
                'remote_change_id' => $remoteId,
                'status'  => 'applied',
                'message' => 'URL updated successfully',
            ];

        } catch (\Throwable $e) {
            Log::error("[MovieUrlSync] Failed to apply change for movie #{$movieId}: " . $e->getMessage());
            return [
                'movie_model_id' => $movieId,
                'status'  => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Health-check endpoint — allows Hetzner to verify the sync connection.
     */
    public function ping(Request $request): JsonResponse
    {
        $secret = config('services.url_sync.secret');
        if (!$secret || $request->header('X-Url-Sync-Secret') !== $secret) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return response()->json([
            'status'  => 'ok',
            'server'  => config('app.url'),
            'time'    => now()->toIso8601String(),
        ]);
    }
}
