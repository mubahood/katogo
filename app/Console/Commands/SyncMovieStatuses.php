<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncMovieStatuses extends Command
{
    protected $signature   = 'movies:sync-status {--dry-run : Show counts without applying changes}';
    protected $description = 'Sync movie status field based on fix_status: fixed→Active, error→Inactive';

    public function handle(): int
    {
        $dry = $this->option('dry-run');

        // ── 1. Activate: fix_status='fixed' movies that are still Inactive ──
        $toActivate = DB::table('movie_models')
            ->where('fix_status', 'fixed')
            ->where('status', 'Inactive')
            ->count();

        $this->info("fix_status=fixed  → status=Active  : {$toActivate} movies");

        if (!$dry && $toActivate > 0) {
            DB::table('movie_models')
                ->where('fix_status', 'fixed')
                ->where('status', 'Inactive')
                ->update(['status' => 'Active', 'updated_at' => now()]);
        }

        // ── 2. Deactivate: fix_status='error' movies that are still Active ──
        $toDeactivate = DB::table('movie_models')
            ->where('fix_status', 'error')
            ->where('status', 'Active')
            ->count();

        $this->info("fix_status=error  → status=Inactive: {$toDeactivate} movies");

        if (!$dry && $toDeactivate > 0) {
            DB::table('movie_models')
                ->where('fix_status', 'error')
                ->where('status', 'Active')
                ->update(['status' => 'Inactive', 'updated_at' => now()]);
        }

        if ($dry) {
            $this->line('[dry-run] No changes applied.');
            return self::SUCCESS;
        }

        $this->info('Sync complete.');

        Log::info('movies:sync-status completed', [
            'activated'   => $toActivate,
            'deactivated' => $toDeactivate,
        ]);

        return self::SUCCESS;
    }
}
