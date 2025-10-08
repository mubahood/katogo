<?php

namespace Database\Seeders;

use App\Models\MunowatchMovieCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class MunowatchMovieCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * This seeder fetches dynamic categories from the munowatch dashboard API
     * and populates the munowatch_movie_categories table.
     */
    public function run(): void
    {
        $this->command->info('Fetching categories from munowatch dashboard API...');
        
        try {
            // Fetch categories from dashboard API
            $categoriesCount = MunowatchMovieCategory::fetchCategoriesFromDashboard();
            
            $this->command->info("Successfully fetched {$categoriesCount} categories from munowatch dashboard.");
            
            // Display the categories that were fetched
            $categories = MunowatchMovieCategory::orderBy('munowatch_category_id')->get();
            
            $this->command->info('Categories fetched:');
            foreach ($categories as $category) {
                $this->command->line("  - ID {$category->munowatch_category_id}: {$category->category_name} ({$category->total_movies_in_category} movies)");
            }
            
        } catch (\Exception $e) {
            $this->command->error('Failed to fetch categories from munowatch dashboard: ' . $e->getMessage());
            Log::error('MunowatchMovieCategorySeeder failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
