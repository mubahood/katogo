<?php

namespace App\Observers;

use App\Models\MovieFileTransfer;
use App\Models\MovieModel;
use Illuminate\Support\Facades\Log;

/**
 * MovieFileTransferObserver
 *
 * Watches MovieModel for created and updated events.
 * When a movie is saved with a non-Hetzner video URL it automatically
 * creates a queued MovieFileTransfer record — no human intervention needed.
 *
 * Guards:
 *  - Skips inactive movies
 *  - Skips movies with no video URL
 *  - Skips if the URL is already on Hetzner Storage
 *  - Skips if a pending/active/completed transfer already exists for this movie
 */
class MovieFileTransferObserver
{
    public function created(MovieModel $movie): void
    {
        $this->maybeQueue($movie, 'observer:created');
    }

    public function updated(MovieModel $movie): void
    {
        // Only react if url actually changed
        if (!$movie->wasChanged('url')) {
            return;
        }

        $newUrl = (string)($movie->attributes['url'] ?? $movie->url ?? '');

        // Skip if the new URL is already on Hetzner
        if (MovieFileTransfer::isAlreadyOnHetzner($newUrl)) {
            return;
        }

        $this->maybeQueue($movie, 'observer:updated');
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    private function maybeQueue(MovieModel $movie, string $initiatedBy): void
    {
        try {
            $url = (string)($movie->attributes['url'] ?? $movie->url ?? '');

            // Skip: no URL
            if (empty($url) || strlen($url) < 10) {
                return;
            }

            // Skip: already on Hetzner
            if (MovieFileTransfer::isAlreadyOnHetzner($url)) {
                return;
            }

            // Skip: movie is not active
            if (($movie->status ?? '') !== 'Active') {
                return;
            }

            // Skip: duplicate — already queued or done for this movie
            if ($movie->id && MovieFileTransfer::hasPendingOrCompleted($movie->id)) {
                return;
            }

            MovieFileTransfer::queueForMovie($movie, $initiatedBy);

            Log::info("[MovieFileTransferObserver] Queued transfer for movie #{$movie->id} — {$movie->title}", [
                'source_url'   => substr($url, 0, 100),
                'initiated_by' => $initiatedBy,
            ]);

        } catch (\Throwable $e) {
            // Never let observer errors break the movie save
            Log::error("[MovieFileTransferObserver] Failed to queue transfer for movie #{$movie->id}: " . $e->getMessage());
        }
    }
}
