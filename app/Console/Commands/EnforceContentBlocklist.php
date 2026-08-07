<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * DMCA compliance sweep. Deactivates + flags every movie whose title matches
 * a content_blocklist pattern. Runs every 30 minutes as the backstop for
 * update paths that bypass Eloquent (raw SQL in movies:sync-status, bulk
 * crawler upserts, replica sync) — the MovieModel::saving hook handles
 * everything that goes through the model.
 *
 *   php artisan content:enforce-blocklist
 *   php artisan content:enforce-blocklist --dry-run
 */
class EnforceContentBlocklist extends Command
{
    protected $signature   = 'content:enforce-blocklist {--dry-run : Report matches without deactivating}';
    protected $description = 'Deactivate every Active movie whose title matches content_blocklist (DMCA compliance)';

    public function handle(): int
    {
        $patterns = DB::table('content_blocklist')->get();
        if ($patterns->isEmpty()) {
            $this->info('Blocklist is empty — nothing to enforce.');
            return 0;
        }

        $total = 0;
        foreach ($patterns as $b) {
            $q = DB::table('movie_models')
                ->where(fn ($w) => $w->where('status', 'Active')->orWhere('is_blocklisted', 0));
            switch ($b->match_type) {
                case 'exact':  $q->where('title', $b->pattern); break;
                case 'like':   $q->where('title', 'like', $b->pattern); break;
                case 'regexp': $q->where('title', 'regexp', $b->pattern); break;
                default: continue 2;
            }

            $ids = $q->pluck('id');
            if ($ids->isEmpty()) {
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("  [{$b->match_type}] '{$b->pattern}' would deactivate/flag {$ids->count()} record(s)");
                continue;
            }

            DB::table('movie_models')->whereIn('id', $ids)->update([
                'status'         => 'Inactive',
                'is_blocklisted' => 1,
                'muno_message'   => 'content:enforce-blocklist ' . now()->toDateString(),
                'updated_at'     => now(),
            ]);
            $total += $ids->count();
            $this->line("  [{$b->match_type}] '{$b->pattern}' → {$ids->count()} enforced");
        }

        // ── Series catalog (series_movies) — the series TITLE itself must
        //    also disappear from search/browse, not just its episodes. ──
        $seriesTotal = 0;
        foreach ($patterns as $b) {
            $q = DB::table('series_movies')->where('is_active', 'Yes');
            switch ($b->match_type) {
                case 'exact':  $q->where('title', $b->pattern); break;
                case 'like':   $q->where('title', 'like', $b->pattern); break;
                case 'regexp': $q->where('title', 'regexp', $b->pattern); break;
                default: continue 2;
            }
            $ids = $q->pluck('id');
            if ($ids->isEmpty()) {
                continue;
            }
            if ($this->option('dry-run')) {
                $this->line("  [series] '{$b->pattern}' would deactivate {$ids->count()} series");
                continue;
            }
            DB::table('series_movies')->whereIn('id', $ids)
                ->update(['is_active' => 'No', 'updated_at' => now()]);
            $seriesTotal += $ids->count();
            $this->line("  [series] '{$b->pattern}' → {$ids->count()} series hidden");
        }

        if ($total > 0 || $seriesTotal > 0) {
            // Blocked titles may sit inside cached manifest sections — flush.
            Cache::flush();
            Log::warning("[Blocklist] Enforcement: {$total} movie record(s), {$seriesTotal} series hidden.");
        }

        $this->info("Done — {$total} movie record(s), {$seriesTotal} series enforced this run.");
        return 0;
    }
}
