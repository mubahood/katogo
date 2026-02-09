<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Total series: " . DB::selectOne("SELECT COUNT(*) as cnt FROM series_movies")->cnt . "\n";

// Check if category_id has an index
$indexes = DB::select("SHOW INDEX FROM movie_models WHERE Column_name = 'category_id'");
echo "Indexes on movie_models.category_id: " . count($indexes) . "\n";
if (empty($indexes)) {
    echo "Adding index...\n";
    DB::statement("ALTER TABLE movie_models ADD INDEX idx_category_id (category_id)");
    echo "Index added.\n";
}

// Now the LEFT JOIN should be fast
$zeroEps = DB::selectOne("
    SELECT COUNT(*) as cnt FROM series_movies s 
    LEFT JOIN movie_models m ON m.category_id = s.id
    WHERE m.id IS NULL
")->cnt;
echo "Series with 0 episodes: {$zeroEps}\n";

echo "\nSample 0-episode series:\n";
$samples = DB::select("
    SELECT s.id, s.title, s.is_muno, s.munowatch_id, s.series_code, 
           SUBSTRING(s.external_url, 1, 60) as ext_url, s.is_active
    FROM series_movies s 
    LEFT JOIN movie_models m ON m.category_id = s.id
    WHERE m.id IS NULL
    LIMIT 10
");
foreach ($samples as $s) {
    echo "  #{$s->id} | " . mb_substr($s->title, 0, 40) . " | muno={$s->is_muno} | mw_id={$s->munowatch_id} | code={$s->series_code} | active={$s->is_active}\n";
}
// Check munowatch-connected zero-episode series
$zeroMuno = DB::selectOne("
    SELECT COUNT(*) as cnt FROM series_movies s 
    LEFT JOIN movie_models m ON m.category_id = s.id
    WHERE m.id IS NULL AND s.is_muno = 'Yes'
")->cnt;
echo "\n  - Munowatch (is_muno=Yes): {$zeroMuno}\n";

$zeroWithInfo = DB::selectOne("
    SELECT COUNT(*) as cnt FROM series_movies s 
    LEFT JOIN movie_models m ON m.category_id = s.id
    WHERE m.id IS NULL
    AND (
        (s.munowatch_id IS NOT NULL AND s.munowatch_id != '')
        OR (s.external_url IS NOT NULL AND s.external_url != '')
        OR (s.series_code IS NOT NULL AND s.series_code != '')
    )
")->cnt;
echo "  - With any munowatch info: {$zeroWithInfo}\n";

$zeroNoInfo = $zeroEps - $zeroWithInfo;
echo "  - No munowatch info at all: {$zeroNoInfo}\n";

echo "\nSample munowatch-connected 0-episode series:\n";
$samples2 = DB::select("
    SELECT s.id, s.title, s.is_muno, s.munowatch_id, s.series_code, 
           SUBSTRING(s.external_url, 1, 80) as ext_url, s.is_active
    FROM series_movies s 
    LEFT JOIN movie_models m ON m.category_id = s.id
    WHERE m.id IS NULL
    AND (
        (s.munowatch_id IS NOT NULL AND s.munowatch_id != '')
        OR (s.external_url IS NOT NULL AND s.external_url != '')
        OR (s.series_code IS NOT NULL AND s.series_code != '')
    )
    LIMIT 10
");
foreach ($samples2 as $s) {
    echo "  #{$s->id} | " . mb_substr($s->title, 0, 40) . " | muno={$s->is_muno} | mw_id={$s->munowatch_id} | code={$s->series_code} | active={$s->is_active}\n";
    echo "    url=" . ($s->ext_url ?: '-') . "\n";
}
echo "Done.\n";
