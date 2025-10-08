<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\MovieCrawlerWebsite;
use Carbon\Carbon;

/**
 * Munowatch Crawler Integration Seeder
 * 
 * This seeder sets up the munowatch crawler integration in the database
 * for production deployment. It creates the necessary website registration
 * and configuration required for the munowatch movie crawler to function.
 * 
 * Features:
 * - Creates munowatch website record with proper authentication
 * - Sets up URL templates for API/scraping endpoints
 * - Configures crawler settings and status
 * - Handles existing records gracefully
 * - Provides comprehensive logging
 * 
 * Usage:
 * php artisan db:seed --class=MunowatchCrawlerSeeder
 * 
 * @author Katogo Development Team
 * @date 2025-10-08
 * @version 1.0
 */
class MunowatchCrawlerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('🚀 Starting Munowatch Crawler Integration Setup...');
        
        try {
            // Check if munowatch record already exists
            $existingWebsite = MovieCrawlerWebsite::where('slug', 'munowatch')->first();
            
            if ($existingWebsite) {
                $this->handleExistingRecord($existingWebsite);
            } else {
                $this->createNewRecord();
            }
            
            $this->validateSetup();
            $this->displaySummary();
            
        } catch (\Exception $e) {
            $this->command->error('❌ Seeder failed: ' . $e->getMessage());
            Log::error('MunowatchCrawlerSeeder failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    /**
     * Handle existing munowatch record
     */
    private function handleExistingRecord($existingWebsite)
    {
        $this->command->warn("⚠️  Munowatch website record already exists (ID: {$existingWebsite->id})");
        
        if ($this->command->confirm('Do you want to update the existing record with latest configuration?')) {
            $this->updateExistingRecord($existingWebsite);
        } else {
            $this->command->info('✓ Skipping - existing record preserved');
        }
    }
    
    /**
     * Update existing munowatch record with latest configuration
     */
    private function updateExistingRecord($website)
    {
        $this->command->info('🔄 Updating existing munowatch record...');
        
        $originalData = $website->toArray();
        
        // Update with latest configuration
        $website->update($this->getMunowatchConfiguration());
        
        $this->command->info('✅ Munowatch record updated successfully');
        
        // Log the changes
        Log::info('Munowatch crawler record updated via seeder', [
            'id' => $website->id,
            'original' => $originalData,
            'updated' => $website->fresh()->toArray()
        ]);
        
        $this->displayRecordDetails($website);
    }
    
    /**
     * Create new munowatch website record
     */
    private function createNewRecord()
    {
        $this->command->info('📝 Creating new munowatch website record...');
        
        // Begin transaction for safety
        DB::beginTransaction();
        
        try {
            $website = MovieCrawlerWebsite::create($this->getMunowatchConfiguration());
            
            DB::commit();
            
            $this->command->info("✅ Munowatch website record created successfully (ID: {$website->id})");
            
            // Log the creation
            Log::info('Munowatch crawler record created via seeder', [
                'id' => $website->id,
                'data' => $website->toArray()
            ]);
            
            $this->displayRecordDetails($website);
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Failed to create munowatch record: ' . $e->getMessage());
        }
    }
    
    /**
     * Get munowatch configuration array
     */
    private function getMunowatchConfiguration()
    {
        return [
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
            'description' => 'Munowatch movie and series crawler integration for translated content',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ];
    }
    
    /**
     * Validate the setup after seeding
     */
    private function validateSetup()
    {
        $this->command->info('🔍 Validating munowatch setup...');
        
        // Check if record exists and is properly configured
        $website = MovieCrawlerWebsite::where('slug', 'munowatch')->first();
        
        if (!$website) {
            throw new \Exception('Validation failed: Munowatch record not found after seeding');
        }
        
        // Validate required fields
        $requiredFields = ['name', 'url', 'slug', 'token', 'email', 'status'];
        $missingFields = [];
        
        foreach ($requiredFields as $field) {
            if (empty($website->$field)) {
                $missingFields[] = $field;
            }
        }
        
        if (!empty($missingFields)) {
            throw new \Exception('Validation failed: Missing required fields: ' . implode(', ', $missingFields));
        }
        
        // Validate URL template format
        if (!str_contains($website->url, '{category_id}') || !str_contains($website->url, '{page}')) {
            throw new \Exception('Validation failed: URL template does not contain required placeholders');
        }
        
        // Validate authentication configuration
        if ($website->token !== 'munowatch123' || $website->email !== 'Api-munowatch-2024') {
            throw new \Exception('Validation failed: Authentication configuration incorrect');
        }
        
        // Test MUNOWATCH constant if class is available
        try {
            if (defined('App\Models\MovieCrawlerWebsite::MUNOWATCH')) {
                $constant = MovieCrawlerWebsite::MUNOWATCH;
                if ($constant !== 'munowatch') {
                    $this->command->warn("⚠️  MUNOWATCH constant value mismatch: expected 'munowatch', got '{$constant}'");
                }
            }
        } catch (\Exception $e) {
            $this->command->warn('⚠️  Could not validate MUNOWATCH constant: ' . $e->getMessage());
        }
        
        $this->command->info('✅ Validation completed successfully');
    }
    
    /**
     * Display comprehensive setup summary
     */
    private function displaySummary()
    {
        $website = MovieCrawlerWebsite::where('slug', 'munowatch')->first();
        
        $this->command->info('');
        $this->command->info('🎯 MUNOWATCH CRAWLER SETUP SUMMARY');
        $this->command->info('=====================================');
        $this->command->info("Database Record ID: {$website->id}");
        $this->command->info("Website Name: {$website->name}");
        $this->command->info("Slug: {$website->slug}");
        $this->command->info("Status: {$website->status}");
        $this->command->info("URL Template: {$website->url}");
        $this->command->info("Authentication Token: {$website->token}");
        $this->command->info("API Key: {$website->email}");
        $this->command->info("Max Pages: {$website->max_page}");
        $this->command->info("Current Page: {$website->page_number}");
        $this->command->info("Fetch Status: {$website->fetch_status}");
        $this->command->info('');
        
        // Display crawler endpoints that will be used
        $this->command->info('🔗 CRAWLER ENDPOINTS CONFIGURATION:');
        $this->command->info('Category 1 (Movies): https://munowatch.com/api/list/p/1/3/{page}');
        $this->command->info('Category 2 (Series): https://munowatch.com/api/list/p/2/3/{page}');
        $this->command->info('Category 3 (Korean): https://munowatch.com/api/list/p/3/3/{page}');
        $this->command->info('Category 4 (Animation): https://munowatch.com/api/list/p/4/3/{page}');
        $this->command->info('');
        
        // Display next steps
        $this->command->info('📋 NEXT STEPS:');
        $this->command->info('1. Verify munowatch.com API endpoints are accessible');
        $this->command->info('2. Test crawler functionality: php artisan crawler:test munowatch');
        $this->command->info('3. Run full crawler: Access /crawler route in browser');
        $this->command->info('4. Monitor logs: storage/logs/laravel.log');
        $this->command->info('5. Check created pages: movie_crawler_pages table');
        $this->command->info('');
        
        $this->command->info('✅ MUNOWATCH CRAWLER SETUP COMPLETED SUCCESSFULLY!');
        $this->command->info('🚀 Ready for production deployment.');
    }
    
    /**
     * Display detailed record information
     */
    private function displayRecordDetails($website)
    {
        $this->command->info('');
        $this->command->info('📊 Record Details:');
        $this->command->table(
            ['Field', 'Value'],
            [
                ['ID', $website->id],
                ['Name', $website->name],
                ['Slug', $website->slug],
                ['URL Template', $website->url],
                ['Token', $website->token],
                ['API Key', $website->email],
                ['Status', $website->status],
                ['Max Pages', $website->max_page],
                ['Page Number', $website->page_number],
                ['Fetch Status', $website->fetch_status],
                ['Created At', $website->created_at],
                ['Updated At', $website->updated_at]
            ]
        );
    }
    
    /**
     * Check database requirements
     */
    private function checkDatabaseRequirements()
    {
        // Check if movie_crawler_websites table exists
        if (!DB::getSchemaBuilder()->hasTable('movie_crawler_websites')) {
            throw new \Exception('Required table movie_crawler_websites does not exist. Run migrations first.');
        }
        
        // Check required columns
        $requiredColumns = [
            'id', 'name', 'url', 'slug', 'token', 'email', 'status', 
            'page_number', 'max_page', 'fetch_status', 'created_at', 'updated_at'
        ];
        
        $missingColumns = [];
        foreach ($requiredColumns as $column) {
            if (!DB::getSchemaBuilder()->hasColumn('movie_crawler_websites', $column)) {
                $missingColumns[] = $column;
            }
        }
        
        if (!empty($missingColumns)) {
            throw new \Exception('Missing required columns in movie_crawler_websites: ' . implode(', ', $missingColumns));
        }
        
        $this->command->info('✅ Database requirements validated');
    }
    
    /**
     * Additional setup verification
     */
    private function performAdditionalChecks()
    {
        // Check if MovieCrawlerWebsite model exists and is accessible
        try {
            $testModel = new MovieCrawlerWebsite();
            $fillable = $testModel->getFillable();
            
            if (empty($fillable)) {
                $this->command->warn('⚠️  MovieCrawlerWebsite model has no fillable fields defined');
            }
            
        } catch (\Exception $e) {
            throw new \Exception('MovieCrawlerWebsite model is not accessible: ' . $e->getMessage());
        }
        
        // Check if Utils class has required methods
        try {
            $utilsClass = new \ReflectionClass('App\Models\Utils');
            $requiredMethods = ['get_munowatch_headers', 'get_url_with_auth', 'call_munowatch_api'];
            
            foreach ($requiredMethods as $method) {
                if (!$utilsClass->hasMethod($method)) {
                    $this->command->warn("⚠️  Utils class missing method: {$method}");
                }
            }
            
        } catch (\Exception $e) {
            $this->command->warn('⚠️  Could not verify Utils class methods: ' . $e->getMessage());
        }
    }
}