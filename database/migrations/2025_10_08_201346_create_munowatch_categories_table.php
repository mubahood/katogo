<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('munowatch_categories', function (Blueprint $table) {
            $table->id();
            
            // Core category information from munowatch API
            $table->integer('munowatch_id')->unique()->comment('Category ID from munowatch API');
            $table->string('name')->comment('Category name (e.g., Movies, Series, Korean, Animation)');
            $table->string('slug')->unique()->comment('URL-friendly version of category name');
            $table->text('description')->nullable()->comment('Category description from API');
            
            // API-specific fields
            $table->string('api_endpoint')->comment('Specific API endpoint for this category');
            $table->json('api_parameters')->nullable()->comment('Additional API parameters for this category');
            
            // Category status and management
            $table->enum('status', ['active', 'inactive', 'deprecated'])->default('active');
            $table->boolean('is_featured')->default(false)->comment('Whether to prioritize this category');
            $table->integer('sort_order')->default(0)->comment('Display order for category rotation');
            
            // Crawling statistics and management
            $table->integer('total_pages')->default(0)->comment('Total pages available in this category');
            $table->integer('current_page')->default(0)->comment('Current page being crawled');
            $table->integer('total_videos_found')->default(0)->comment('Total videos discovered in this category');
            $table->integer('new_videos_last_crawl')->default(0)->comment('New videos found in last crawl');
            
            // Crawling status and timing
            $table->enum('crawl_status', ['pending', 'in_progress', 'completed', 'failed', 'paused'])->default('pending');
            $table->text('error_message')->nullable()->comment('Last error message if crawling failed');
            $table->timestamp('last_crawled_at')->nullable()->comment('When this category was last crawled');
            $table->timestamp('next_crawl_at')->nullable()->comment('When to crawl this category next');
            
            // Performance tracking
            $table->integer('crawl_frequency_hours')->default(24)->comment('How often to crawl this category (hours)');
            $table->integer('videos_per_page')->default(10)->comment('Expected videos per page for this category');
            $table->decimal('success_rate', 5, 2)->default(100.00)->comment('Crawling success rate percentage');
            
            // Metadata and relationships
            $table->json('metadata')->nullable()->comment('Additional category metadata from API');
            $table->integer('parent_category_id')->nullable()->comment('For subcategories');
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['status', 'is_featured']);
            $table->index(['crawl_status', 'next_crawl_at']);
            $table->index(['sort_order', 'status']);
            $table->index('last_crawled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('munowatch_categories');
    }
};
