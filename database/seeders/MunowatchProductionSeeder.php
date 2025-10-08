<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Simple Munowatch Production Seeder
 * 
 * Lightweight seeder for quick production deployment of munowatch crawler.
 * Creates the essential database record without complex validation.
 * 
 * Usage: php artisan db:seed --class=MunowatchProductionSeeder
 */
class MunowatchProductionSeeder extends Seeder
{
    public function run()
    {
        echo "🚀 Setting up Munowatch Crawler for Production...\n";
        
        // Check if record already exists
        $existing = DB::table('movie_crawler_websites')->where('slug', 'munowatch')->first();
        
        if ($existing) {
            echo "⚠️  Munowatch record already exists (ID: {$existing->id})\n";
            echo "✓ Setup already completed.\n";
            return;
        }
        
        // Insert munowatch configuration
        $id = DB::table('movie_crawler_websites')->insertGetId([
            'name' => 'Munowatch API',
            'url' => 'https://munowatch.com/api/list/p/{category_id}/3/{page}',
            'slug' => 'munowatch',
            'token' => 'munowatch123',
            'email' => 'Api-munowatch-2024',
            'status' => 'Active',
            'page_number' => 0,
            'max_page' => 50,
            'total_movies_found' => 0,
            'new_movies_found' => 0,
            'fetch_status' => 'pending',
            'error_message' => null,
            'last_fetched_at' => null,
            'response_data' => null,
            'last_page_url' => null,
            'description' => 'Munowatch movie crawler integration',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);
        
        echo "✅ Munowatch crawler record created (ID: {$id})\n";
        echo "🎯 Configuration:\n";
        echo "   - Name: Munowatch API\n";
        echo "   - Slug: munowatch\n";
        echo "   - Token: munowatch123\n";
        echo "   - API Key: Api-munowatch-2024\n";
        echo "   - Status: Active\n";
        echo "🚀 Ready for crawler operations!\n";
    }
}