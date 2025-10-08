<?php

namespace Database\Seeders;

use App\Models\MunowatchCategory;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

/**
 * Munowatch Categories Seeder
 * 
 * Seeds the default munowatch categories with proper configuration
 * for organized video crawling and category-based content management.
 */
class MunowatchCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚀 Seeding Munowatch Categories...');

        // Check if categories already exist
        $existingCount = MunowatchCategory::count();
        if ($existingCount > 0) {
            $this->command->warn("⚠️  Found {$existingCount} existing categories");
            $this->command->info('Updating existing categories with latest configuration...');
        }

        // Use the model's built-in seeding method
        MunowatchCategory::seedDefaultCategories();

        // Display results
        $categories = MunowatchCategory::orderBy('sort_order')->get();
        
        $this->command->info('✅ Munowatch Categories Configuration:');
        $this->command->line('');
        
        foreach ($categories as $category) {
            $status = $category->status === 'active' ? '🟢' : '🔴';
            $featured = $category->is_featured ? '⭐' : '  ';
            
            $this->command->line("{$status} {$featured} {$category->display_name}");
            $this->command->line("    📂 Slug: {$category->slug}");
            $this->command->line("    🔗 API: {$category->api_endpoint}");
            $this->command->line("    ⏱️  Crawl Frequency: {$category->crawl_frequency_hours} hours");
            $this->command->line("    📊 Status: {$category->crawl_status}");
            
            if ($category->next_crawl_at) {
                $nextCrawl = $category->next_crawl_at->diffForHumans();
                $this->command->line("    📅 Next Crawl: {$nextCrawl}");
            }
            
            $this->command->line('');
        }

        // Generate sample API URLs for verification
        $this->command->info('🔗 Sample API URLs:');
        foreach ($categories->where('status', 'active') as $category) {
            $sampleUrl = $category->getApiUrl(1, '169464');
            $this->command->line("    {$category->name}: {$sampleUrl}");
        }

        $this->command->line('');
        $this->command->info('🎯 Integration Instructions:');
        $this->command->line('1. Categories are now available for crawler rotation');
        $this->command->line('2. Use MunowatchCategory::getNextForCrawling() to get next category');
        $this->command->line('3. Each category tracks its own crawling progress and status');
        $this->command->line('4. Featured categories (Movies, Series) are prioritized');
        $this->command->line('');
        
        $activeCount = $categories->where('status', 'active')->count();
        $featuredCount = $categories->where('is_featured', true)->count();
        
        $this->command->info("📈 Summary: {$activeCount} active categories, {$featuredCount} featured");
        $this->command->info('🚀 Ready for category-based munowatch crawling!');
    }
}
