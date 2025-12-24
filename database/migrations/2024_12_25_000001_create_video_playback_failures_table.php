<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('video_playback_failures', function (Blueprint $table) {
            $table->id();
            
            // User information
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->string('user_email')->nullable();
            $table->string('user_phone')->nullable();
            
            // Movie information
            $table->unsignedBigInteger('movie_id')->nullable();
            $table->string('movie_title')->nullable();
            $table->text('original_url')->nullable();
            $table->text('transformed_url')->nullable();
            
            // Failure details
            $table->text('error_message')->nullable();
            $table->string('error_code')->nullable();
            $table->string('error_type')->nullable(); // network, playback, timeout, etc.
            $table->integer('retry_count')->default(0);
            
            // Device & App information
            $table->string('device_model')->nullable();
            $table->string('device_os')->nullable();
            $table->string('device_os_version')->nullable();
            $table->string('app_version')->nullable();
            $table->string('player_type')->nullable(); // full_screen, preview, mini
            
            // Network information
            $table->string('network_type')->nullable(); // wifi, mobile, unknown
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            
            // Subscription status
            $table->boolean('has_subscription')->default(false);
            $table->string('subscription_type')->nullable();
            $table->timestamp('subscription_expires_at')->nullable();
            
            // Context
            $table->string('screen_name')->nullable();
            $table->json('additional_data')->nullable(); // Any extra context
            
            // Resolution status
            $table->enum('status', ['pending', 'investigating', 'resolved', 'ignored'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            
            $table->timestamps();
            
            // Indexes for better query performance
            $table->index('user_id');
            $table->index('movie_id');
            $table->index('error_type');
            $table->index('status');
            $table->index('created_at');
            $table->index(['user_id', 'created_at']);
            $table->index(['movie_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('video_playback_failures');
    }
};
