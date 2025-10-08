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
        Schema::create('munowatch_movie_categories', function (Blueprint $table) {
            $table->id();
            
            // Core category data from munowatch dashboard API
            $table->integer('munowatch_category_id')->unique()->comment('Category ID from munowatch dashboard API');
            $table->string('category_name')->comment('Category name from dashboard (e.g., "Latest on Munowatch", "Horror Movies")');
            $table->text('category_description')->nullable()->comment('Category description if available');
            
            // API endpoint information
            $table->string('api_endpoint_type')->default('dashboard')->comment('Type: dashboard, browse, shows, list');
            $table->json('api_parameters')->nullable()->comment('Additional API parameters for fetching this category');
            
            // Category status and management
            $table->enum('status', ['active', 'inactive', 'fetching', 'failed'])->default('active');
            $table->boolean('is_dynamic')->default(true)->comment('Whether category is fetched dynamically from dashboard');
            $table->integer('sort_order')->default(0)->comment('Display order for category');
            
            // Movies and content tracking
            $table->integer('total_movies_in_category')->default(0)->comment('Total movies found in this category');
            $table->integer('movies_fetched')->default(0)->comment('Number of movies already fetched from this category');
            $table->timestamp('last_fetched_from_dashboard_at')->nullable()->comment('When category was last seen in dashboard API');
            $table->timestamp('last_movies_fetched_at')->nullable()->comment('When movies were last fetched for this category');
            
            // Category metadata from dashboard
            $table->json('sample_movies')->nullable()->comment('Sample movies from dashboard to understand category content');
            $table->json('category_metadata')->nullable()->comment('Additional metadata from dashboard API');
            
            // Performance and crawling
            $table->boolean('has_pagination')->default(false)->comment('Whether this category supports pagination for more movies');
            $table->string('pagination_endpoint')->nullable()->comment('Specific endpoint for paginated content');
            $table->integer('current_page')->default(1)->comment('Current page for pagination');
            $table->integer('max_pages')->nullable()->comment('Maximum pages available for this category');
            
            // Error handling and status
            $table->text('last_error_message')->nullable()->comment('Last error when fetching this category');
            $table->timestamp('next_fetch_at')->nullable()->comment('When to fetch this category next');
            $table->integer('fetch_frequency_hours')->default(6)->comment('How often to check dashboard for this category');
            
            $table->timestamps();
            
                        // Indexes for performance
            $table->index(['status', 'is_dynamic'], 'muno_status_dynamic_idx');
            $table->index(['munowatch_category_id'], 'muno_category_id_idx'); 
            $table->index(['last_fetched_from_dashboard_at', 'status'], 'muno_dashboard_status_idx');
            $table->index(['next_fetch_at', 'status'], 'muno_next_fetch_idx');
            $table->index(['sort_order'], 'muno_sort_order_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('munowatch_movie_categories');
    }
};
