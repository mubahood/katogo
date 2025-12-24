<?php
// Analyze all muno movie domains in the database

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    echo "✅ Connected to database\n\n";
    
    // Get all movie URLs
    $movies = DB::table('movie_models')
        ->whereNotNull('url')
        ->where('url', '!=', '')
        ->where(function($query) {
            $query->where('url', 'LIKE', '%muno%')
                  ->orWhere('url', 'LIKE', '%.club%')
                  ->orWhere('url', 'LIKE', '%cdn%')
                  ->orWhere('url', 'LIKE', '%server%');
        })
        ->limit(2000)
        ->pluck('url');
    
    $domains = [];
    $protocols = [];
    $patterns = [];
    
    foreach ($movies as $url) {
        // Extract domain
        if (preg_match('#^(https?://)([^/]+)#i', $url, $matches)) {
            $protocol = $matches[1];
            $domain = $matches[2];
            
            // Track protocols
            if (!isset($protocols[$protocol])) {
                $protocols[$protocol] = 0;
            }
            $protocols[$protocol]++;
            
            // Track domains
            if (!isset($domains[$domain])) {
                $domains[$domain] = 0;
            }
            $domains[$domain]++;
            
            // Track URL patterns
            $pattern = preg_replace('#[0-9]+#', 'N', $domain);
            if (!isset($patterns[$pattern])) {
                $patterns[$pattern] = 0;
            }
            $patterns[$pattern]++;
        }
    }
    
    echo "📊 ANALYSIS RESULTS\n";
    echo "==================\n\n";
    
    echo "🌐 DOMAINS (sorted by frequency):\n";
    echo "---------------------------------\n";
    arsort($domains);
    foreach ($domains as $domain => $count) {
        echo sprintf("  %5d × %s\n", $count, $domain);
    }
    
    echo "\n🔒 PROTOCOLS:\n";
    echo "-------------\n";
    foreach ($protocols as $protocol => $count) {
        echo sprintf("  %5d × %s\n", $count, $protocol);
    }
    
    echo "\n📋 DOMAIN PATTERNS:\n";
    echo "------------------\n";
    arsort($patterns);
    foreach ($patterns as $pattern => $count) {
        echo sprintf("  %5d × %s\n", $count, $pattern);
    }
    
    // Get sample URLs from each major domain
    echo "\n📝 SAMPLE URLs:\n";
    echo "--------------\n";
    $topDomains = array_slice(array_keys($domains), 0, 10);
    foreach ($topDomains as $domain) {
        $samples = DB::table('movie_models')
            ->where('url', 'LIKE', "%{$domain}%")
            ->limit(2)
            ->pluck('url');
        
        echo "\n🔗 {$domain}:\n";
        foreach ($samples as $url) {
            echo "   - {$url}\n";
        }
    }
    
    // Generate transformation rules
    echo "\n\n🔧 RECOMMENDED URL TRANSFORMATION RULES:\n";
    echo "========================================\n";
    echo "Based on the analysis, transform these protected servers to CDN:\n\n";
    
    foreach ($domains as $domain => $count) {
        if (preg_match('/munoserver|\.club/', $domain)) {
            echo "  {$domain} → munotek-vault.b-cdn.net\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
