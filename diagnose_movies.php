<?php
/**
 * Quick diagnostic script — hit this from a browser on the remote server:
 *   https://katogo.schooldynamics.ug/diagnose_movies.php
 *
 * This checks the movie_models table to understand why only 33 movies show up.
 * DELETE THIS FILE after diagnosis.
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

header('Content-Type: application/json');

$counts = [
    'total_rows'                      => DB::table('movie_models')->count(),
    'is_muno_yes'                     => DB::table('movie_models')->where('is_muno', 'Yes')->count(),
    'is_muno_no'                      => DB::table('movie_models')->where('is_muno', 'No')->count(),
    'is_muno_null'                    => DB::table('movie_models')->whereNull('is_muno')->count(),
    'type_Movie'                      => DB::table('movie_models')->where('type', 'Movie')->count(),
    'type_Series'                     => DB::table('movie_models')->where('type', 'Series')->count(),
    'type_null_or_empty'              => DB::table('movie_models')->where(function ($q) {
                                            $q->whereNull('type')->orWhere('type', '');
                                        })->count(),
    'type_other'                      => DB::table('movie_models')->whereNotIn('type', ['Movie', 'Series', ''])->whereNotNull('type')->count(),
    'movie_active_muno'               => DB::table('movie_models')->where(['type' => 'Movie', 'status' => 'Active', 'is_muno' => 'Yes'])->count(),
    'movie_active_no_muno_filter'     => DB::table('movie_models')->where(['type' => 'Movie', 'status' => 'Active'])->count(),
    'movie_inactive'                  => DB::table('movie_models')->where('type', 'Movie')->where('status', '!=', 'Active')->count(),
    'movie_muno_no'                   => DB::table('movie_models')->where(['type' => 'Movie', 'is_muno' => 'No'])->count(),
    'movie_muno_null'                 => DB::table('movie_models')->where('type', 'Movie')->whereNull('is_muno')->count(),
];

// Also get distinct type values
$distinctTypes = DB::table('movie_models')
    ->select('type', DB::raw('COUNT(*) as cnt'))
    ->groupBy('type')
    ->orderByDesc('cnt')
    ->get()
    ->toArray();

// Sample: 5 recent type=Movie items
$sampleMovies = DB::table('movie_models')
    ->where('type', 'Movie')
    ->where('is_muno', 'Yes')
    ->where('status', 'Active')
    ->orderByDesc('id')
    ->limit(5)
    ->get(['id', 'title', 'type', 'status', 'is_muno'])
    ->toArray();

echo json_encode([
    'counts'         => $counts,
    'distinct_types' => $distinctTypes,
    'sample_movies'  => $sampleMovies,
    'diagnosis'      => $counts['movie_active_muno'] < 100
        ? "PROBLEM: Only {$counts['movie_active_muno']} movies match type=Movie+Active+is_muno=Yes. Check if movies were imported with wrong type or is_muno values."
        : "OK: {$counts['movie_active_muno']} movies match the V2 filter.",
], JSON_PRETTY_PRINT);
