<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\MovieCrawlerWebsite;
use Carbon\Carbon;

/**
 * Uganda Hot Girls Crawler Integration Seeder
 * 
 * This seeder sets up the ugandahotgirls.com crawler integration for the dating site.
 * It creates the necessary website registration and configuration required for 
 * the user profile crawler to function.
 * 
 * Features:
 * - Creates ugandahotgirls website record for user profile crawling
 * - Sets up URL templates for homepage and city pages
 * - Configures crawler settings and status
 * - Handles existing records gracefully
 * - Provides comprehensive logging
 * 
 * Usage:
 * php artisan db:seed --class=UgandaHotGirlsCrawlerSeeder
 * 
 * @author Katogo Development Team
 * @date 2026-01-13
 * @version 1.0
 */
class UgandaHotGirlsCrawlerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('🚀 Starting Uganda Hot Girls Crawler Integration Setup...');
        
        try {
            // Check if ugandahotgirls record already exists
            $existingWebsite = MovieCrawlerWebsite::where('slug', 'ugandahotgirls')->first();
            
            if ($existingWebsite) {
                $this->handleExistingRecord($existingWebsite);
            } else {
                $this->createNewRecord();
            }
            
            $this->validateSetup();
            $this->displaySummary();
            
        } catch (\Exception $e) {
            $this->command->error('❌ Seeder failed: ' . $e->getMessage());
            Log::error('UgandaHotGirlsCrawlerSeeder failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    /**
     * Handle existing ugandahotgirls record
     */
    private function handleExistingRecord($existingWebsite)
    {
        $this->command->warn("⚠️  Uganda Hot Girls website record already exists (ID: {$existingWebsite->id})");
        
        if ($this->command->confirm('Do you want to update the existing record with latest configuration?')) {
            $this->updateExistingRecord($existingWebsite);
        } else {
            $this->command->info('✓ Skipping - existing record preserved');
        }
    }
    
    /**
     * Update existing ugandahotgirls record with latest configuration
     */
    private function updateExistingRecord($website)
    {
        $this->command->info('🔄 Updating existing ugandahotgirls record...');
        
        // Update with latest configuration
        $website->update($this->getUgandaHotGirlsConfiguration());
        
        $this->command->info('✅ Uganda Hot Girls record updated successfully');
        $this->displayRecordDetails($website);
    }
    
    /**
     * Create new ugandahotgirls website record
     */
    private function createNewRecord()
    {
        $this->command->info('📝 Creating new ugandahotgirls website record...');
        
        // Begin transaction for safety
        DB::beginTransaction();
        
        try {
            $website = MovieCrawlerWebsite::create($this->getUgandaHotGirlsConfiguration());
            
            DB::commit();
            
            $this->command->info("✅ Uganda Hot Girls website record created successfully (ID: {$website->id})");
            $this->displayRecordDetails($website);
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Failed to create ugandahotgirls record: ' . $e->getMessage());
        }
    }
    
    /**
     * Get ugandahotgirls configuration array
     */
    private function getUgandaHotGirlsConfiguration()
    {
        return [
            'name' => 'Uganda Hot Girls',
            'url' => 'https://www.ugandahotgirls.com',
            'slug' => 'ugandahotgirls',
            'token' => null,
            'email' => null,
            'status' => 'Active',
            'page_number' => 0,
            'max_page' => 100,
            'total_movies_found' => 0,
            'new_movies_found' => 0,
            'fetch_status' => 'pending',
            'error_message' => null,
            'last_fetched_at' => null,
            'response_data' => null,
            'last_page_url' => 'https://www.ugandahotgirls.com',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ];
    }
    
    /**
     * Validate the setup after seeding
     */
    private function validateSetup()
    {
        $this->command->info('🔍 Validating ugandahotgirls setup...');
        
        // Check if record exists and is properly configured
        $website = MovieCrawlerWebsite::where('slug', 'ugandahotgirls')->first();
        
        if (!$website) {
            throw new \Exception('Validation failed: Uganda Hot Girls record not found after seeding');
        }
        
        // Validate required fields
        $requiredFields = ['name', 'url', 'slug', 'status'];
        $missingFields = [];
        
        foreach ($requiredFields as $field) {
            if (empty($website->$field)) {
                $missingFields[] = $field;
            }
        }
        
        if (!empty($missingFields)) {
            throw new \Exception('Validation failed: Missing required fields: ' . implode(', ', $missingFields));
        }
        
        // Validate URL format
        if (!filter_var($website->url, FILTER_VALIDATE_URL)) {
            throw new \Exception('Validation failed: Invalid URL format');
        }
        
        $this->command->info('✅ Validation passed - all checks successful');
    }
    
    /**
     * Display setup summary
     */
    private function displaySummary()
    {
        $website = MovieCrawlerWebsite::where('slug', 'ugandahotgirls')->first();
        
        $this->command->newLine();
        $this->command->info('📊 SETUP SUMMARY');
        $this->command->info('═══════════════════════════════════════════');
        $this->command->table(
            ['Property', 'Value'],
            [
                ['Database ID', $website->id],
                ['Website Name', $website->name],
                ['Base URL', $website->url],
                ['Slug', $website->slug],
                ['Status', $website->status],
                ['Max Pages', $website->max_page],
                ['Description', $website->description],
                ['Created At', $website->created_at->format('Y-m-d H:i:s')],
            ]
        );
        $this->command->info('═══════════════════════════════════════════');
        $this->command->newLine();
        
        $this->command->info('🎯 NEXT STEPS:');
        $this->command->line('   1. Visit /crawl-dating-pages to discover user profile URLs');
        $this->command->line('   2. Visit /extract-dating-users to extract user details');
        $this->command->line('   3. Monitor crawler status in admin panel');
        $this->command->newLine();
        
        $this->command->info('✅ Uganda Hot Girls Crawler Setup Complete!');
    }
    
    /**
     * Display record details
     */
    private function displayRecordDetails($website)
    {
        $this->command->newLine();
        $this->command->table(
            ['Field', 'Value'],
            [
                ['ID', $website->id],
                ['Name', $website->name],
                ['URL', $website->url],
                ['Slug', $website->slug],
                ['Status', $website->status],
                ['Page Number', $website->page_number],
                ['Max Page', $website->max_page],
            ]
        );
        $this->command->newLine();
    }
}
