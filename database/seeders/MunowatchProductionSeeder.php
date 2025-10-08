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
        
        // Insert munowatch configuration with correct API structure
        $id = DB::table('movie_crawler_websites')->insertGetId([
            'name' => 'Munowatch API',
            'url' => 'https://munowatch.org/api/list/{pipe}/{pid}/{uid}/{lid}',
            'slug' => 'munowatch',
            'token' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VybmFtZSI6IkFuZHJvaWQgVFYiLCJhcHBuYW1lIjoiTXVub3dhdGNoIFRWIiwiaG9zdCI6Im11bm93YXRjaC5jbyIsImFwcHNlY3JldCI6IjAyMjc3OGU0MThhZDY4ZmZkYTlhYTRmYWIxODkyZmZmIiwiYWN0aXZhdGVkIjoiMSIsImV4cCI6MTcwNzM2ODQwMH0.unlPnEzptg6VFHs7WWm213bRHHNxYuAN2eZQvjtPKL0',
            'email' => '169464', // User ID from .env
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
        echo "   - Base URL: https://munowatch.org/api/\n";
        echo "   - API Format: list/{pipe}/{pid}/{uid}/{lid}\n";
        echo "   - Slug: munowatch\n";
        echo "   - JWT Token: (Real token configured)\n";
        echo "   - User ID: 169464\n";
        echo "   - Status: Active\n";
        echo "🚀 Ready for crawler operations with CORRECTED API structure!\n";
    }
}