<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Subscription Plans Table
     * Stores all available subscription plans with pricing, features, and multilingual support
     */
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            // Plan Names (Multilingual)
            $table->string('name', 191)->comment('Plan name in English');
            $table->string('name_luganda', 191)->nullable()->comment('Plan name in Luganda');
            $table->string('name_swahili', 191)->nullable()->comment('Plan name in Swahili');
            $table->string('slug', 191)->unique()->comment('URL-friendly identifier');
            
            // Description
            $table->text('description')->nullable()->comment('Plan description in English');
            $table->text('description_luganda')->nullable()->comment('Plan description in Luganda');
            $table->text('description_swahili')->nullable()->comment('Plan description in Swahili');

            // Pricing
            $table->decimal('price', 15, 2)->comment('Plan price');
            $table->string('currency', 10)->default('UGX')->comment('Currency code (UGX, USD, etc)');
            $table->integer('duration_days')->comment('Subscription duration in days');

            // Features (HTML formatted)
            $table->text('features')->nullable()->comment('Plan features in HTML (English)');
            $table->text('features_luganda')->nullable()->comment('Plan features in HTML (Luganda)');
            $table->text('features_swahili')->nullable()->comment('Plan features in HTML (Swahili)');

            // Status & Display
            $table->enum('status', ['Active', 'Inactive'])->default('Active')->comment('Plan availability status');
            $table->boolean('is_featured')->default(false)->comment('Show as featured/recommended plan');
            $table->integer('sort_order')->default(0)->comment('Display order (ascending)');

            // Discount & Promotions
            $table->decimal('discount_percentage', 5, 2)->default(0.00)->comment('Discount percentage (0-100)');
            $table->boolean('is_trial')->default(false)->comment('Is this a trial plan?');

            // Feature Limits (NULL = unlimited)
            $table->integer('max_downloads')->nullable()->comment('Maximum downloads allowed (NULL = unlimited)');
            $table->integer('max_watchlist')->nullable()->comment('Maximum watchlist items (NULL = unlimited)');
            $table->boolean('ad_free')->default(true)->comment('Is this plan ad-free?');
            $table->boolean('hd_streaming')->default(true)->comment('Allow HD streaming?');

            // Metadata
            $table->unsignedBigInteger('created_by')->nullable()->comment('Admin user who created this plan');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Admin user who last updated this plan');

            // Indexes for performance
            $table->index('status', 'idx_status');
            $table->index(['sort_order', 'status'], 'idx_sort_order');
            $table->index('slug', 'idx_slug');
            $table->index('duration_days', 'idx_duration');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
